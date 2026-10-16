<?php

namespace Infernalmedia\ResponsiveImageCraft\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Infernalmedia\ResponsiveImageCraft\Events\ResponsiveImageGenerationFailed;
use Infernalmedia\ResponsiveImageCraft\ImageInfoFromString;
use Infernalmedia\ResponsiveImageCraft\Jobs\GenerateResponsiveImageJob;
use Infernalmedia\ResponsiveImageCraft\Support\GenerationOptions;
use Infernalmedia\ResponsiveImageCraft\Support\ImageProcessor;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Throwable;

final class GenerateResponsiveImages extends Command
{
    protected $signature = 'responsive-image-craft:generate
        {--source-disk= : Disk to scan for source images}
        {--relative-source-path= : Directory to scan on the source disk}
        {--path= : Process a single file instead of scanning a directory}
        {--force : Regenerate variants that already exist on the target disk}
        {--sizes= : Comma-separated widths overriding the configured sizes}
        {--extensions= : Comma-separated formats overriding the configured output formats}
        {--queue= : Dispatch each image to the given queue instead of processing inline}
        {--dry-run : List the files that would be generated without writing anything}';

    protected $description = 'Generate responsive images';

    protected $aliases = ['images:craft'];

    private int $generatedImages = 0;

    private int $treatedImages = 0;

    private int $skippedImages = 0;

    private int $imagesInError = 0;

    /**
     * @var array{generated_on: string|null, generated: array<string, array<string, string[]>>, errors: array<string, string[]>}
     */
    private array $logArray = [
        'generated_on' => null,
        'generated' => [],
        'errors' => [],
    ];

    public function handle(): int
    {
        $options = $this->buildOptions();
        $images = $this->collectImages($options);

        if ($images->isEmpty()) {
            $this->warn('No supported image found.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            return $this->dryRun($images, $options);
        }

        if ($queue = $this->stringOption('queue')) {
            return $this->dispatchJobs($images, $options, $queue);
        }

        return $this->process($images, $options);
    }

    /**
     * @param  Collection<int, ImageInfoFromString>  $images
     */
    private function process(Collection $images, GenerationOptions $options): int
    {
        $processor = new ImageProcessor($options);
        $existingTargets = $this->existingTargetFiles($options);
        $temporaryDirectory = (new TemporaryDirectory)->create();

        try {
            $this->withProgressBar($images, function (ImageInfoFromString $image) use ($processor, $options, $existingTargets, $temporaryDirectory) {
                if (! $options->force && $this->isFullyGenerated($processor, $image, $existingTargets)) {
                    $this->skippedImages++;

                    return;
                }

                try {
                    $generated = $processor->process($image, $temporaryDirectory);
                    $temporaryDirectory->empty();
                } catch (Throwable $exception) {
                    $this->logError($image, $exception->getMessage());
                    ResponsiveImageGenerationFailed::dispatch($image->getRelativePathname(), $exception->getMessage());
                    $this->error($exception->getMessage());

                    return;
                }

                $this->logGenerated($image, $generated);
                $this->treatedImages++;
            });
        } finally {
            $temporaryDirectory->delete();
        }

        $this->storeLogToJsonFile($options);

        $this->newLine(3);
        $this->info("{$this->generatedImages} responsive images generated successfully from {$this->treatedImages} images!");

        if ($this->skippedImages > 0) {
            $this->info("{$this->skippedImages} images skipped (already generated — use --force to regenerate).");
        }

        if ($this->imagesInError > 0) {
            $this->warn("{$this->imagesInError} images have errors! See images-log.json on the target disk.");
        }

        return $this->imagesInError > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  Collection<int, ImageInfoFromString>  $images
     */
    private function dryRun(Collection $images, GenerationOptions $options): int
    {
        $processor = new ImageProcessor($options);

        foreach ($images as $image) {
            $width = $this->knownWidth($image, $options);
            $paths = $width !== null
                ? $processor->expectedVariantPaths($image, $width)
                : $processor->expectedFullSizePaths($image);

            $this->line($image->getRelativePathname());
            foreach ($paths as $path) {
                $this->line("  - {$path}");
            }
        }

        $this->info("{$images->count()} images would be processed.");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, ImageInfoFromString>  $images
     */
    private function dispatchJobs(Collection $images, GenerationOptions $options, string $queue): int
    {
        foreach ($images as $image) {
            GenerateResponsiveImageJob::dispatch($image->getRelativePathname(), $options->toArray())
                ->onQueue($queue);
        }

        $this->info("{$images->count()} image generation jobs dispatched to the [{$queue}] queue.");

        return self::SUCCESS;
    }

    private function buildOptions(): GenerationOptions
    {
        $options = GenerationOptions::fromConfig();

        return new GenerationOptions(
            sourceDisk: $this->stringOption('source-disk') ?? $options->sourceDisk,
            sourceDirectory: $this->stringOption('relative-source-path') ?? $options->sourceDirectory,
            targetDisk: $options->targetDisk,
            targetDirectory: $options->targetDirectory,
            sizes: $this->sizesOverride() ?? $options->sizes,
            extensions: $this->extensionsOverride() ?? $options->extensions,
            quality: $options->quality,
            optimize: $options->optimize,
            force: (bool) $this->option('force'),
            filenameSpacer: $options->filenameSpacer,
        );
    }

    /**
     * @return int[]|null
     */
    private function sizesOverride(): ?array
    {
        $sizes = $this->stringOption('sizes');

        if ($sizes === null) {
            return null;
        }

        return array_map('intval', explode(',', $sizes));
    }

    /**
     * @return string[]|null
     */
    private function extensionsOverride(): ?array
    {
        $extensions = $this->stringOption('extensions');

        if ($extensions === null) {
            return null;
        }

        return array_map('strtolower', array_map('trim', explode(',', $extensions)));
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return Collection<int, ImageInfoFromString>
     */
    private function collectImages(GenerationOptions $options): Collection
    {
        $path = $this->stringOption('path');

        /** @var string[] $files */
        $files = $path === null
            ? Storage::disk($options->sourceDisk)->allFiles($options->sourceDirectory)
            : [$path];

        return collect($files)
            ->map(fn (string $file) => new ImageInfoFromString($file, $options->sourceDisk))
            ->filter(fn (ImageInfoFromString $image) => $image->isSupportedFile())
            ->values();
    }

    /**
     * @return array<string, true>
     */
    private function existingTargetFiles(GenerationOptions $options): array
    {
        try {
            return array_fill_keys(
                Storage::disk($options->targetDisk)->allFiles($this->targetListingRoot($options)),
                true
            );
        } catch (Throwable) {
            return [];
        }
    }

    private function targetListingRoot(GenerationOptions $options): string
    {
        $path = $this->stringOption('path');

        if ($path === null) {
            return $options->sourceDirectory;
        }

        $directory = pathinfo($path, PATHINFO_DIRNAME);

        return $directory === '.' ? '' : $directory;
    }

    /**
     * @param  array<string, true>  $existingTargets
     */
    private function isFullyGenerated(
        ImageProcessor $processor,
        ImageInfoFromString $image,
        array $existingTargets
    ): bool {
        foreach ($processor->expectedFullSizePaths($image) as $path) {
            if (! isset($existingTargets[$path])) {
                return false;
            }
        }

        return true;
    }

    private function knownWidth(ImageInfoFromString $image, GenerationOptions $options): ?int
    {
        if (config("filesystems.disks.{$options->sourceDisk}.driver") !== 'local') {
            return null;
        }

        $size = @getimagesize(Storage::disk($options->sourceDisk)->path($image->getRelativePathname()));

        return $size === false ? null : $size[0];
    }

    /**
     * @param  array<string, string[]>  $generated
     */
    private function logGenerated(ImageInfoFromString $image, array $generated): void
    {
        foreach ($generated as $extension => $files) {
            foreach ($files as $file) {
                $this->logArray['generated'][$image->getRelativePathname()][$extension][] = $file;
                $this->generatedImages++;
            }
        }
    }

    private function logError(ImageInfoFromString $image, string $message): void
    {
        if (! Arr::exists($this->logArray['errors'], $image->getRelativePathname())) {
            $this->logArray['errors'][$image->getRelativePathname()] = [];
        }

        $this->logArray['errors'][$image->getRelativePathname()][] = $message;
        $this->imagesInError++;
    }

    private function storeLogToJsonFile(GenerationOptions $options): void
    {
        $this->logArray['generated_on'] = Carbon::now()->format('Y-m-d H:i:s');

        Storage::disk($options->targetDisk)->put(
            "{$options->targetDirectory}/images-log.json",
            json_encode($this->logArray, JSON_THROW_ON_ERROR)
        );
    }
}
