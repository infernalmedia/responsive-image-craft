<?php

namespace Infernalmedia\ResponsiveImageCraft\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Infernalmedia\ResponsiveImageCraft\Events\ResponsiveImageGenerated;
use Infernalmedia\ResponsiveImageCraft\ImageInfoFromString;
use Spatie\Image\Image;
use Spatie\ImageOptimizer\OptimizerChain;
use Spatie\ImageOptimizer\OptimizerChainFactory;
use Spatie\TemporaryDirectory\TemporaryDirectory;

/**
 * Generates the responsive variants for a single source image:
 * the optimized original, one full-size conversion per output format,
 * then every configured size descending — decoding the source only once
 * per format.
 */
final class ImageProcessor
{
    private ?OptimizerChain $optimizerChain = null;

    public function __construct(
        private readonly GenerationOptions $options
    ) {}

    /**
     * @return array<string, string[]> Generated target paths, keyed by extension
     */
    public function process(ImageInfoFromString $image, TemporaryDirectory $temporaryDirectory): array
    {
        $localPath = (new SourceImageResolver($temporaryDirectory))->resolve($image);

        $generated = [];

        $this->optimizeOriginal($image, $localPath, $temporaryDirectory);
        $generated[$image->getFileExtension()][] = $this->targetPath($image, $image->getFilename());

        $imageWidth = $this->imageWidth($localPath);

        foreach ($this->outputExtensionsFor($image) as $extension) {
            foreach ($this->generateFormat($image, $localPath, $imageWidth, $extension, $temporaryDirectory) as $file) {
                $generated[$extension][] = $file;
            }
        }

        ResponsiveImageGenerated::dispatch($image->getRelativePathname(), $generated);

        return $generated;
    }

    /**
     * Output formats that will be generated for this image, given the
     * configured skip rules for its source extension.
     *
     * @return string[]
     */
    public function outputExtensionsFor(ImageInfoFromString $image): array
    {
        $skipped = Arr::get(PackageConfig::skipFormats(), $image->getFileExtension(), []);

        return array_values(array_diff($this->options->extensions, $skipped));
    }

    /**
     * Full-size target paths (optimized original + one per output format).
     * Deterministic without reading the image — used for skip and clean checks.
     *
     * @return string[]
     */
    public function expectedFullSizePaths(ImageInfoFromString $image): array
    {
        $paths = [$this->targetPath($image, $image->getFilename())];

        foreach ($this->outputExtensionsFor($image) as $extension) {
            $paths[] = $this->targetPath($image, "{$image->getFilenameWithoutExtension()}.{$extension}");
        }

        return array_values(array_unique($paths));
    }

    /**
     * Every variant path the image would produce with the given max width.
     *
     * @return string[]
     */
    public function expectedVariantPaths(ImageInfoFromString $image, int $imageWidth): array
    {
        $paths = $this->expectedFullSizePaths($image);

        foreach ($this->outputExtensionsFor($image) as $extension) {
            foreach ($this->sizesFor($imageWidth) as $width) {
                $paths[] = $this->targetPath(
                    $image,
                    "{$image->getFilenameWithoutExtension()}{$this->options->filenameSpacer}{$width}.{$extension}"
                );
            }
        }

        return array_values(array_unique($paths));
    }

    private function optimizeOriginal(
        ImageInfoFromString $image,
        string $localPath,
        TemporaryDirectory $temporaryDirectory
    ): void {
        $tempPath = $temporaryDirectory->path($image->getFilename());

        if ($this->options->optimize) {
            $this->optimizerChain()->optimize($localPath, $tempPath);
        } else {
            copy($localPath, $tempPath);
        }

        $this->storeToTarget($image, $tempPath, $image->getFilename());
    }

    /**
     * @return string[] Generated target paths for this format
     */
    private function generateFormat(
        ImageInfoFromString $image,
        string $localPath,
        int $imageWidth,
        string $extension,
        TemporaryDirectory $temporaryDirectory
    ): array {
        $generated = [];
        $name = $image->getFilenameWithoutExtension();

        $driver = Image::load($localPath)->format($extension);

        if ($this->options->optimize) {
            $driver->optimize($this->optimizerChain());
        }

        $quality = $this->options->quality[$extension] ?? null;
        if ($quality !== null) {
            $driver->quality((int) $quality);
        }

        $fileName = "{$name}.{$extension}";

        // A format identical to the source's is already covered by the
        // optimized original — rewriting it would clobber that file.
        if ($fileName !== $image->getFilename()) {
            $driver->save($temporaryDirectory->path($fileName));
            $this->storeToTarget($image, $temporaryDirectory->path($fileName), $fileName);
            $generated[] = $this->targetPath($image, $fileName);
        }

        // Descending order: every resize is a downscale of the previous one,
        // so the source is only decoded once per format.
        foreach ($this->sizesFor($imageWidth) as $width) {
            $fileName = "{$name}{$this->options->filenameSpacer}{$width}.{$extension}";
            $driver->width($width)->save($temporaryDirectory->path($fileName));
            $this->storeToTarget($image, $temporaryDirectory->path($fileName), $fileName);
            $generated[] = $this->targetPath($image, $fileName);
        }

        return $generated;
    }

    /**
     * Configured sizes not exceeding the image width, sorted descending.
     *
     * @return int[]
     */
    private function sizesFor(int $imageWidth): array
    {
        $sizes = array_values(array_filter(
            $this->options->sizes,
            fn (int $size) => $size <= $imageWidth
        ));

        rsort($sizes);

        return $sizes;
    }

    private function imageWidth(string $localPath): int
    {
        $size = @getimagesize($localPath);

        if ($size !== false) {
            return $size[0];
        }

        return Image::load($localPath)->getWidth();
    }

    private function storeToTarget(ImageInfoFromString $image, string $tempFile, string $fileName): void
    {
        Storage::disk($this->options->targetDisk)->putFileAs(
            $image->getRelativePath(),
            file: $tempFile,
            name: $fileName,
            options: 'public'
        );
    }

    private function targetPath(ImageInfoFromString $image, string $fileName): string
    {
        $directory = $image->getRelativePath();

        return $directory === '' ? $fileName : "{$directory}/{$fileName}";
    }

    private function optimizerChain(): OptimizerChain
    {
        return $this->optimizerChain ??= OptimizerChainFactory::create();
    }
}
