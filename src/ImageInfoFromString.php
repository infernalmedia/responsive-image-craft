<?php

namespace Infernalmedia\ResponsiveImageCraft;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Infernalmedia\ResponsiveImageCraft\Support\PackageConfig;

/**
 * Extracts information from an image file path: directory, filename,
 * extension, and the filtering rules deciding whether and how the file
 * should be processed.
 */
final class ImageInfoFromString
{
    public function __construct(
        private readonly string $file,
        private readonly ?string $disk = null
    ) {}

    /**
     * The disk the file lives on. Falls back to the configured source disk.
     */
    public function getSourceDisk(): string
    {
        return $this->disk ?? PackageConfig::sourceDisk();
    }

    /**
     * Absolute path of the file. Only usable on local disks — remote files
     * should go through SourceImageResolver.
     */
    public function getAbsolutePathname(): string
    {
        return Storage::disk($this->getSourceDisk())->path($this->getRelativePathname());
    }

    /**
     * Relative directory of the file, without the filename.
     * Empty string for files stored at the disk root.
     */
    public function getRelativePath(): string
    {
        $directory = pathinfo($this->file, PATHINFO_DIRNAME);

        return $directory === '.' ? '' : $directory;
    }

    /**
     * The file path as given.
     */
    public function getRelativePathname(): string
    {
        return $this->file;
    }

    /**
     * The file path without its extension.
     */
    public function getRelativePathnameWithoutExtension(): string
    {
        $directory = $this->getRelativePath();
        $filename = $this->getFilenameWithoutExtension();

        return $directory === '' ? $filename : "{$directory}/{$filename}";
    }

    /**
     * The filename without its extension.
     */
    public function getFilenameWithoutExtension(): string
    {
        return pathinfo($this->file, PATHINFO_FILENAME);
    }

    /**
     * Lowercase file extension.
     */
    public function getFileExtension(): string
    {
        return strtolower(pathinfo($this->file, PATHINFO_EXTENSION));
    }

    /**
     * The basename of the file.
     */
    public function getFilename(): string
    {
        return pathinfo($this->file, PATHINFO_BASENAME);
    }

    /**
     * Whether the file is a supported, non-ignored image.
     */
    public function isSupportedFile(): bool
    {
        return $this->isImage() && $this->isAcceptedExtension() && $this->isAcceptedFileName();
    }

    /**
     * Output formats for this file: the given formats (or the configured ones)
     * minus the ones skipped for this file's own extension.
     *
     * @param  string[]|null  $extensions
     * @return string[]
     */
    public function getFilteredExtensions(?array $extensions = null): array
    {
        return array_values(array_diff(
            $extensions ?? PackageConfig::outputFormats(),
            $this->getExcludedExtensions()
        ));
    }

    /**
     * Configured sizes smaller than or equal to the given maximum width.
     *
     * @return int[]
     */
    public function getFilteredSizes(int $maxWidth): array
    {
        return array_values(array_filter(
            PackageConfig::sizes(),
            fn (int $width) => $width <= $maxWidth
        ));
    }

    private function isAcceptedExtension(): bool
    {
        return ! in_array($this->getFileExtension(), PackageConfig::ignoredExtensions());
    }

    private function isAcceptedFileName(): bool
    {
        return ! in_array($this->getFilenameWithoutExtension(), PackageConfig::ignoredFilenames());
    }

    private function isImage(): bool
    {
        return in_array($this->getFileExtension(), PackageConfig::inputFormats());
    }

    /**
     * @return string[]
     */
    private function getExcludedExtensions(): array
    {
        return Arr::get(PackageConfig::skipFormats(), $this->getFileExtension(), []);
    }
}
