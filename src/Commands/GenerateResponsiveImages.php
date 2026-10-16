<?php

namespace Infernalmedia\ResponsiveImageCraft\Commands;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Infernalmedia\ResponsiveImageCraft\Events\ResponsiveImageGenerationFailed;
use Infernalmedia\ResponsiveImageCraft\ImageInfoFromString;
use Infernalmedia\ResponsiveImageCraft\Jobs\GenerateResponsiveImageJob;
use Infernalmedia\ResponsiveImageCraft\Support\Disks;
use Infernalmedia\ResponsiveImageCraft\Support\GenerationOptions;
use Infernalmedia\ResponsiveImageCraft\Support\ImageProcessor;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

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

    private const string MODE_PROCESS = 'process';

    private const string MODE_DRY_RUN = 'dry-run';

    private const string MODE_QUEUE = 'queue';

    private string $mode = self::MODE_PROCESS;

    private ?string $queueName = null;

    private ?string $singlePath = null;

    private int $generatedImages = 0;

    private int $treatedImages = 0;

    private int $skippedImages = 0;

    private int $imagesInError = 0;

    /**
     * @var array{
     *     generated_on: string|null,
     *     generated: array<string, array<string, string[]>>,
     *     errors: array<string, string[]>
     * }
     */
    private array $logArray = [
        'generated_on' => null,
        'generated' => [],
        'errors' => [],
    ];

    public function handle(): int
    {
        $this->resolveMode();
        $options = $this->resolveOptions();
        $images = $this->collectImages($options);

        if ($images->isEmpty()) {
            $this->warn('No supported image found.');

            return self::SUCCESS;
        }

        return match ($this->mode) {
            self::MODE_DRY_RUN => $this->dryRun($images, $options),
            self::MODE_QUEUE => $this->dispatchJobs($images, $options, $this->queueName ?? 'default'),
            default => $this->process($images, $options),
        };
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
            $this->withProgressBar(
                $images,
                fn (ImageInfoFromString $image) => $this->processImage(
                    $image,
                    $processor,
                    $options,
                    $existingTargets,
                    $temporaryDirectory
                )
            );
        } finally {
            $temporaryDirectory->delete();
        }

        $this->storeLogToJsonFile($options);

        $this->newLine(3);
        $this->info(
            "{$this->generatedImages} responsive images generated successfully ".
                "from {$this->treatedImages} images!"
        );

        if ($this->skippedImages > 0) {
            $this->info("{$this->skippedImages} images skipped (already generated — use --force to regenerate).");
        }

        if ($this->imagesInError > 0) {
            $this->warn("{$this->imagesInError} images have errors! See images-log.json on the target disk.");
        }

        return $this->imagesInError > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<string, true>  $existingTargets
     */
    private function processImage(
        ImageInfoFromString $image,
        ImageProcessor $processor,
        GenerationOptions $options,
        array $existingTargets,
        TemporaryDirectory $temporaryDirectory
    ): void {
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

    /**
     * Resolve the run mode from the options, asking interactively when
     * neither --dry-run nor --queue was given.
     */
    private function resolveMode(): void
    {
        if ((bool) $this->option('dry-run')) {
            $this->mode = self::MODE_DRY_RUN;

            return;
        }

        if ($queue = $this->stringOption('queue')) {
            $this->mode = self::MODE_QUEUE;
            $this->queueName = $queue;

            return;
        }

        $mode = $this->prompted(fn () => select(
            label: 'How should the images be processed?',
            options: [
                self::MODE_PROCESS => 'Process them now',
                self::MODE_DRY_RUN => 'Dry run — list the files that would be generated',
                self::MODE_QUEUE => 'Dispatch one job per image to a queue',
            ],
            default: self::MODE_PROCESS,
        ));

        if ($mode === self::MODE_DRY_RUN || $mode === self::MODE_QUEUE) {
            $this->mode = $mode;
        }

        if ($this->mode === self::MODE_QUEUE) {
            $queueName = $this->prompted(fn () => text(
                label: 'Which queue should the jobs run on?',
                default: 'default',
                required: true,
            ));

            if (is_string($queueName) && $queueName !== '') {
                $this->queueName = $queueName;
            }
        }
    }

    private function resolveOptions(): GenerationOptions
    {
        $options = GenerationOptions::fromConfig();

        $sourceDisk = $this->resolveSourceDisk($options);
        $this->singlePath = $this->resolvePath();
        $sourceDirectory = $this->resolveSourceDirectory($options);

        return new GenerationOptions(
            sourceDisk: $sourceDisk,
            sourceDirectory: $sourceDirectory,
            targetDisk: $options->targetDisk,
            targetDirectory: $options->targetDirectory,
            sizes: $this->resolveSizes($options),
            extensions: $this->resolveExtensions($options),
            quality: $options->quality,
            optimize: $options->optimize,
            force: $this->resolveForce(),
            filenameSpacer: $options->filenameSpacer,
        );
    }

    private function resolveSourceDisk(GenerationOptions $options): string
    {
        $disk = $this->stringOption('source-disk') ?? $this->prompted(fn () => select(
            label: 'Which disk contains the source images?',
            options: Disks::all($options->sourceDisk),
            default: $options->sourceDisk,
        ));

        return is_string($disk) && $disk !== '' ? $disk : $options->sourceDisk;
    }

    private function resolvePath(): ?string
    {
        if ($path = $this->stringOption('path')) {
            return $path;
        }

        if ($this->stringOption('relative-source-path') !== null) {
            return null;
        }

        return $this->prompted(function () {
            $scope = select(
                label: 'What should be generated?',
                options: [
                    'directory' => 'Every supported image in a directory',
                    'file' => 'A single file',
                ],
                default: 'directory',
            );

            if ($scope !== 'file') {
                return null;
            }

            $path = text(
                label: 'Path to the image on the source disk',
                placeholder: 'images/photo.jpg',
                required: true,
            );

            return $path === '' ? null : $path;
        });
    }

    private function resolveSourceDirectory(GenerationOptions $options): string
    {
        $directory = $this->stringOption('relative-source-path');

        if ($directory === null && $this->singlePath === null) {
            $directory = $this->prompted(fn () => text(
                label: 'Which directory on the source disk should be scanned?',
                default: $options->sourceDirectory,
            ));
        }

        return is_string($directory) && $directory !== '' ? $directory : $options->sourceDirectory;
    }

    /**
     * @return int[]
     */
    private function resolveSizes(GenerationOptions $options): array
    {
        $sizes = $this->stringOption('sizes') ?? $this->prompted(fn () => text(
            label: 'Widths to generate, comma-separated',
            default: implode(',', $options->sizes),
        ));

        return is_string($sizes) && $sizes !== ''
            ? array_map('intval', explode(',', $sizes))
            : $options->sizes;
    }

    /**
     * @return string[]
     */
    private function resolveExtensions(GenerationOptions $options): array
    {
        $extensions = $this->stringOption('extensions') ?? $this->prompted(fn () => text(
            label: 'Output formats to generate, comma-separated',
            default: implode(',', $options->extensions),
        ));

        return is_string($extensions) && $extensions !== ''
            ? array_map('strtolower', array_map('trim', explode(',', $extensions)))
            : $options->extensions;
    }

    private function resolveForce(): bool
    {
        if ((bool) $this->option('force') || $this->mode === self::MODE_DRY_RUN) {
            return (bool) $this->option('force');
        }

        return $this->prompted(fn () => confirm(
            label: 'Regenerate variants that already exist on the target disk?',
            default: false,
        )) === true;
    }

    /**
     * Run the given prompt only when the console is interactive.
     *
     * @template TValue
     *
     * @param  Closure(): TValue  $prompt
     * @return TValue|null
     */
    private function prompted(Closure $prompt): mixed
    {
        return $this->input->isInteractive() ? $prompt() : null;
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
        /** @var string[] $files */
        $files = $this->singlePath === null
            ? Storage::disk($options->sourceDisk)->allFiles($options->sourceDirectory)
            : [$this->singlePath];

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
        if ($this->singlePath === null) {
            return $options->sourceDirectory;
        }

        $directory = pathinfo($this->singlePath, PATHINFO_DIRNAME);

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
