<?php

namespace Infernalmedia\ResponsiveImageCraft;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The `ImageInfoFromString` class is responsible for extracting information from the file path string.
 * It provides methods to retrieve various details about the file, such as the absolute path, relative
 * path, file name, file extension, and more. It also includes methods to check if the file is a
 * supported image file and if it meets certain criteria, such as accepted extensions and file names.
 *
 * @param  string  $file The `file` parameter is the string path to the file
 */
class ImageInfoFromString
{
    public function __construct(
        private string $file
    ) {
    }

    /**
     * The function returns the absolute path name of the file using the Laravel Storage facade.
     *
     * @return string the absolute path name of the file.
     */
    public function getAbsolutePathname(): string
    {
        return Storage::disk($this->getSourceDisk())->path($this->getRelativePathname());
    }

    /**
     * The function returns the relative path of the file by removing the filename and the last directory
     * from the given file path.
     *
     * @return string the relative path of the file,  this path does not contain the file name.
     */
    public function getRelativePath(): string
    {
        $path = Str::beforeLast($this->file, $this->getFilename());

        return Str::beforeLast($path, '/');
    }

    /**
     * The function "getRelativePathname" returns the file path as a string.
     *
     * @return string the value of the variable `file`, which is a string.
     */
    public function getRelativePathname(): string
    {
        return $this->file;
    }

    /**
     * The function returns the relative pathname of the file without its extension.
     *
     * @return string a string that represents the relative pathname of the file without its extension.
     */
    public function getRelativePathnameWithoutExtension(): string
    {
        return Str::beforeLast(Str::beforeLast($this->file, $this->getFileExtension()), '.');
    }

    /**
     * The function `getFilenameWithoutExtension` returns the filename without the file extension.
     *
     * @return string the filename without the file extension.
     */
    public function getFilenameWithoutExtension(): string
    {
        $filename = $this->getFilename();

        return pathinfo($filename, PATHINFO_FILENAME);
    }

    /**
     * The getFileExtension function returns the lowercase file extension of a given file path.
     *
     * @return string the file extension in lowercase.
     */
    public function getFileExtension(): string
    {
        $extension = pathinfo($this->file, PATHINFO_EXTENSION);

        return strtolower($extension);
    }

    /**
     * The function returns the base name of the file path.
     *
     * @return string the base name of the file.
     */
    public function getFilename(): string
    {
        return pathinfo($this->file, PATHINFO_BASENAME);
    }

    /**
     * The function checks if the file is an image, has an accepted extension, and has an accepted file name.
     *
     * @return bool a boolean value.
     */
    public function isSupportedFile(): bool
    {
        return $this->isImage() && $this->isAcceptedExtension() && $this->isAcceptedFileName();
    }

    /**
     * The function returns an array of filtered extensions by excluding the ones specified in the excluded
     * extensions list.
     *
     * @return array an array of filtered extensions.
     */
    public function getFilteredExtensions(): array
    {
        return array_diff(
            config('responsive-image-craft.extensions'),
            $this->getExcludedExtensions()
        );
    }

    /**
     * The function `getFilteredSizes` filters an array of sizes based on a maximum image width.
     *
     * @param  int  $imagMaxWidth The `imagMaxWidth` parameter is an integer representing the maximum width of
     * an image.
     * @return array An array of sizes that are less than or equal to the given ``.
     */
    public function getFilteredSizes(int $imagMaxWidth): array
    {
        return array_filter(config('responsive-image-craft.sizes'), function ($width) use ($imagMaxWidth) {
            return $width <= $imagMaxWidth;
        });
    }

    /**
     * The function checks if the file extension is not in the list of extensions to ignore.
     *
     * @return bool a boolean value.
     */
    private function isAcceptedExtension(): bool
    {
        return ! in_array($this->getFileExtension(), $this->getExtensionsToIgnore());
    }

    /**
     * The function checks if the filename without extension is not in the list of filenames to ignore.
     *
     * @return bool a boolean value.
     */
    private function isAcceptedFileName(): bool
    {
        return ! in_array($this->getFilenameWithoutExtension(), $this->getFileNamesToIgnore());
    }

    /**
     * The function checks if the file extension is in the list of supported image extensions.
     *
     * @return bool a boolean value.
     */
    private function isImage(): bool
    {
        return in_array(
            $this->getFileExtension(),
            $this->getSupportedImageExtensions()
        );
    }

    /**
     * The function `getExcludedExtensions` returns an array of excluded extensions based on the file
     * extension of the current file.
     *
     * @return array an array.
     */
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
