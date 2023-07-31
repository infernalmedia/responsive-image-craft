<?php

namespace Infernalmedia\ResponsiveImageCraft;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageInfoFromString
{
    public function __construct(
        private string $file
    ) {
    }

    public function getAbsolutePathname(): string
    {
        return Storage::disk($this->getSourceDisk())->path($this->getRelativePathname());
    }

    /**
     * Returns the relative path.
     *
     * This path does not contain the file name.
     */
    public function getRelativePath(): string
    {
        $path = Str::beforeLast($this->file, $this->getFilename());

        return Str::beforeLast($path, '/');
    }

    /**
     * Returns the relative path name.
     *
     * This path contains the file name.
     */
    public function getRelativePathname(): string
    {
        return $this->file;
    }

    /**
     * Returns the relative path name.
     *
     * This path contains the file name without extension.
     */
    public function getRelativePathnameWithoutExtension(): string
    {
        return Str::beforeLast(Str::beforeLast($this->file, $this->getFileExtension()), '.');
    }

    public function getFilenameWithoutExtension(): string
    {
        $filename = $this->getFilename();

        return pathinfo($filename, PATHINFO_FILENAME);
    }

    public function getFileExtension(): string
    {
        $extension = pathinfo($this->file, PATHINFO_EXTENSION);

        return strtolower($extension);
    }

    public function getFilename(): string
    {
        return pathinfo($this->file, PATHINFO_BASENAME);
    }

    public function isSupportedFile(): bool
    {
        return $this->isImage() && $this->isAcceptedExtension() && $this->isAcceptedFileName();
    }

    public function getFilteredExtensions(): array
    {
        return array_diff(
            config('responsive-image-craft.extensions'),
            $this->getExcludedExtensions()
        );
    }

    public function getFilteredSizes(int $imagMaxWidth): array
    {
        return array_filter(config('responsive-image-craft.sizes'), function ($width) use ($imagMaxWidth) {
            return $width <= $imagMaxWidth;
        });
    }

    private function isAcceptedExtension(): bool
    {
        return ! in_array($this->getFileExtension(), $this->getExtensionsToIgnore());
    }

    private function isAcceptedFileName(): bool
    {
        return ! in_array($this->getFilenameWithoutExtension(), $this->getFileNamesToIgnore());
    }

    private function isImage(): bool
    {
        return in_array(
            $this->getFileExtension(),
            $this->getSupportedImageExtensions()
        );
    }

    private function getExcludedExtensions(): array
    {
        if (Arr::exists(config('responsive-image-craft.extensions_filters_rules', []), $this->getFileExtension())) {
            return Arr::get(config('responsive-image-craft.extensions_filters_rules', []), $this->getFileExtension());
        }

        return [];
    }

    private function getSupportedImageExtensions(): array
    {
        return config('responsive-image-craft.supported_file_extensions');
    }

    private function getExtensionsToIgnore(): array
    {
        return config('responsive-image-craft.extensions_to_ignore');
    }

    private function getFileNamesToIgnore(): array
    {
        return config('responsive-image-craft.filename_to_ignore');
    }

    private function getSourceDisk(): string
    {
        return config('responsive-image-craft.source_disk');
    }
}
