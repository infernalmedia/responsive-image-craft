<?php

namespace Infernalmedia\ResponsiveImageCraft\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Infernalmedia\ResponsiveImageCraft\ImageInfoFromString;
use Spatie\Image\Exceptions\CouldNotConvert;
use Spatie\Image\Exceptions\InvalidImageDriver;
use Spatie\Image\Exceptions\InvalidManipulation;
use Spatie\Image\Exceptions\InvalidTemporaryDirectory;
use Spatie\Image\Image;
use Spatie\ImageOptimizer\OptimizerChainFactory;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Throwable;

class GenerateResponsiveImages extends Command
{
    protected $signature = 'responsive-image-craft:generate {--source-disk=} {--relative-source-path=}';

    protected $description = 'Generate responsive images';

    private int $generatedImages = 0;

    private int $treatedImages = 0;

    private int $imagesInError = 0;

    private ?TemporaryDirectory $temporaryDirectory = null;

    private array $logArray = [
        'generated_on' => null,
        'generated' => [],
        'errors' => [],
    ];

    public function handle()
    {
        try {
            $this->generateResponsiveImages();
            $this->storeLogToJsonFile();
            $this->newLine(3);
            $this->info("$this->generatedImages responsive images generated successfully from $this->treatedImages!");

            self::SUCCESS;
        } catch (Throwable $exception) {
            $this->newLine();
            $this->warn("$this->generatedImages responsive images generated successfully from $this->treatedImages!");
            $this->warn("$this->imagesInError images has error!");
            $this->error($exception->getMessage());
            self::FAILURE;
        }
    }

    private function generateResponsiveImages(): void
    {
        $this->withProgressBar($this->getFilteredImages(), function (ImageInfoFromString $imageString) {
            $this->generateAndSaveResponsiveImages($imageString);
        });
    }

    /**
     * The function generates and saves responsive images in different sizes and formats.
     *
     * @param ImageInfoFromString imageString An instance of the class `ImageInfoFromString`, which
     * contains information about the image file, such as its absolute pathname and filtered extensions.
     */
    private function generateAndSaveResponsiveImages(ImageInfoFromString $imageString): void
    {
        $this->temporaryDirectory = (new TemporaryDirectory())->create();

        $image = Image::load($imageString->getAbsolutePathname());
        $this->optimizeOriginalImage($imageString, $image->getWidth());

        foreach ($imageString->getFilteredExtensions() as $extension) {
            $this->optimizeOriginalImageToSpecificExtension(
                $image,
                $imageString,
                $extension
            );

            $this->saveImageToSpecifiedSizes($image, $imageString, $extension);
        }

        $this->temporaryDirectory->empty();
        $this->temporaryDirectory->delete();

        $this->treatedImages++;
    }

    /**
     * The function saves an image to specified sizes by iterating through the filtered sizes, formatting
     * and optimizing the image, and then saving it to a temporary file before storing it to the target
     * location.
     *
     * @param Image image An instance of the Image class, representing the image to be saved.
     * @param ImageInfoFromString imageString An instance of the `ImageInfoFromString` class, which
     * contains information about the image file such as its filename and width.
     * @param string extension The `extension` parameter is a string that represents the file extension of
     * the image file. It is used to determine the format in which the image should be saved.
     */
    private function saveImageToSpecifiedSizes(Image $image, ImageInfoFromString $imageString, string $extension): void
    {
        foreach ($imageString->getFilteredSizes($image->getWidth()) as $responsiveWidth) {
            try {
                //phpcs:ignore
                $fileName = "{$imageString->getFilenameWithoutExtension()}{$this->getFilenameSpacer()}$responsiveWidth.$extension";
                $tempFileName = "{$this->temporaryDirectory->path()}/{$fileName}";

                $image
                    ->format($extension)
                    ->optimize()
                    ->width($responsiveWidth)
                    ->save($tempFileName);

                $this->storeFileToTarget($imageString, $tempFileName, $fileName);
                $this->logGeneratedImages($imageString, $extension, $responsiveWidth);
            } catch (CouldNotConvert | InvalidImageDriver | InvalidManipulation | InvalidTemporaryDirectory $e) {
                $this->error($e->getMessage());
            } catch (Throwable $exception) {
                $this->error($exception->getMessage());
            }
        }
    }

    /**
     * The `optimizeOriginalImage` function optimizes an original image by applying an optimizer chain and
     * storing the optimized image to a target location.
     *
     * @param ImageInfoFromString imageString An instance of the `ImageInfoFromString` class that contains
     * information about the image, such as the filename, absolute pathname, and file extension.
     * @param int width The `width` parameter is an integer that represents the desired width of the
     * optimized image.
     */
    private function optimizeOriginalImage(ImageInfoFromString $imageString, int $width): void
    {
        try {
            $newFileName = "{$this->getTargetPath($imageString)}/{$imageString->getFilename()}";
            $tempFileName = "{$this->temporaryDirectory->path()}/{$imageString->getFilename()}";

            $optimizerChain = OptimizerChainFactory::create();
            $optimizerChain->optimize(
                $imageString->getAbsolutePathname(),
                $tempFileName
            );

            $this->storeFileToTarget($imageString, $tempFileName);
            $this->logGeneratedImages($imageString, $imageString->getFileExtension(), $width, $newFileName);
        } catch (Throwable $exception) {
            $this->logError($imageString, $exception->getMessage());
            $this->error($exception->getMessage());
        }
    }

    /**
     * The function optimizes an original image to a specific file extension and saves it to a temporary
     * directory.
     *
     * @param Image image An instance of the `Image` class, which represents the original image to be
     * optimized and converted to a specific extension.
     * @param ImageInfoFromString imageString An instance of the `ImageInfoFromString` class that contains
     * information about the original image, such as the filename and other metadata.
     * @param string extension The `extension` parameter is a string that represents the desired file
     * extension for the optimized image. It specifies the format in which the image should be saved after
     * optimization.
     */
    private function optimizeOriginalImageToSpecificExtension(
        Image $image,
        ImageInfoFromString $imageString,
        string $extension
    ): void {
        try {
            $newFileName = "{$imageString->getFilenameWithoutExtension()}.$extension";
            $tempFileName = "{$this->temporaryDirectory->path()}/{$newFileName}";
            $image
                ->format($extension)
                ->optimize()
                ->save($tempFileName);
            $this->storeFileToTarget($imageString, $tempFileName, $newFileName);
            $this->logGeneratedImages($imageString, $extension, $image->getWidth(), $newFileName);
        } catch (CouldNotConvert | InvalidImageDriver | InvalidManipulation | InvalidTemporaryDirectory $e) {
            $this->logError($imageString, $e->getMessage());
            $this->error($e->getMessage());
        } catch (Throwable $exception) {
            $this->logError($imageString, $exception->getMessage());
            $this->error($exception->getMessage());
        }
    }

    /**
     * The function stores a file to a target location using the Laravel Storage facade.
     *
     * @param ImageInfoFromString imageString An instance of the ImageInfoFromString class, which contains
     * information about the image file being stored.
     * @param string tempFileName The temporary file name of the image that needs to be stored. This is the
     * file that is currently being processed or uploaded.
     * @param string newFileName The `newFileName` parameter is an optional parameter that specifies the
     * name of the file to be stored in the target location. If this parameter is not provided or is empty,
     * the filename from the `ImageInfoFromString` object (`->getFilename()`) will be used as
     * the name
     */
    private function storeFileToTarget(ImageInfoFromString $imageString, string $tempFileName, string $newFileName = ''): void
    {
        Storage::disk($this->getTargetDisk())
            ->putFileAs(
                $this->getTargetPath($imageString),
                file: $tempFileName,
                name: empty($newFileName) ? $imageString->getFilename() : $newFileName,
                options: 'public'
            );
    }

    /**
     * The function returns the relative path of an image given an ImageInfoFromString object.
     *
     * @param ImageInfoFromString imageString An instance of the class `ImageInfoFromString`
     * @return string the relative path of the image as a string.
     */
    private function getTargetPath(ImageInfoFromString $imageString): string
    {
        return "{$imageString->getRelativePath()}";
    }

    /**
     * The function returns the target file path by concatenating the target path and the filename without
     * extension from the given ImageInfoFromString object.
     *
     * @param ImageInfoFromString imageString The parameter `` is an instance of the
     * `ImageInfoFromString` class.
     * @return string a string that represents the target file path for the given ImageInfoFromString
     * object.
     */
    private function getTargetFilePath(ImageInfoFromString $imageString): string
    {
        return "{$this->getTargetPath($imageString)}/{$imageString->getFilenameWithoutExtension()}";
    }

    /**
     * The logError function logs an error message for a given image string and increments the count of
     * images in error.
     *
     * @param ImageInfoFromString imageString The parameter `` is an instance of the
     * `ImageInfoFromString` class. It represents an image and contains information about the image, such
     * as its relative pathname.
     * @param string message The `` parameter is a string that represents the error message to be
     * logged.
     */
    private function logError(ImageInfoFromString $imageString, string $message): void
    {
        if (!Arr::exists($this->logArray['errors'], $imageString->getRelativePathname())) {
            $this->logArray['errors'][$imageString->getRelativePathname()] = [];
        }

        array_push($this->logArray['errors'][$imageString->getRelativePathname()], $message);
        $this->imagesInError++;
    }

    /**
     * The function logs information about generated images, including the image string, extension, width,
     * and optional new file name.
     *
     * @param ImageInfoFromString imageString The `` parameter is an instance of the
     * `ImageInfoFromString` class, which contains information about the image such as its relative
     * pathname.
     * @param string extension The "extension" parameter is a string that represents the file extension of
     * the generated image. It is used to determine the file format of the image file, such as "jpg",
     * "png", etc.
     * @param int width The "width" parameter in the code snippet represents the width of the generated
     * image. It is an integer value that specifies the desired width of the image.
     * @param string newFileName The `newFileName` parameter is an optional parameter that allows you to
     * specify a custom name for the generated image file. If you provide a value for `newFileName`, it
     * will be used as the file name for the generated image. If you don't provide a value for
     * `newFileName`, a
     */
    private function logGeneratedImages(
        ImageInfoFromString $imageString,
        string $extension,
        int $width,
        string $newFileName = ''
    ): void {
        if (!Arr::exists($this->logArray['generated'], $imageString->getRelativePathname())) {
            $this->logArray['generated'][$imageString->getRelativePathname()] = [];
        }
        if (!Arr::exists($this->logArray['generated'][$imageString->getRelativePathname()], $extension)) {
            $this->logArray['generated'][$imageString->getRelativePathname()][$extension] = [];
        }

        $filName = !empty($newFileName) ?
            $newFileName
            : "{$this->getTargetFilePath($imageString)}{$this->getFilenameSpacer()}$width.$extension";

        $this->logArray['generated'][$imageString->getRelativePathname()][$extension][$width] = $filName;

        $this->generatedImages++;
    }

    /**
     * The function `getFilteredImages()` retrieves a collection of image files from a specified disk and
     * directory, filters out unsupported file types, and returns a collection of `ImageInfoFromString`
     * objects.
     *
     * @return Collection a Collection of ImageInfoFromString objects.
     */
    private function getFilteredImages(): Collection
    {
        $imageFiles = Storage::disk($this->getSourceDisk())->allFiles($this->getSourceDirectory());

        return collect($imageFiles)
            ->mapInto(ImageInfoFromString::class)
            ->filter(function (ImageInfoFromString $image) {
                return $image->isSupportedFile();
            });
    }

    /**
     * The function `storeLogToJsonFile` stores the log array as a JSON file with a timestamp in the
     * specified target directory and disk.
     */
    private function storeLogToJsonFile(): void
    {
        $this->logArray['generated_on'] = Carbon::now()->format('Y-m-d H:i:s');

        $fileName = "{$this->getTargetDirectory()}/images-log.json";
        Storage::disk($this->getTargetDisk())->put($fileName, json_encode($this->logArray));
    }

    private function getTargetDisk(): string
    {
        return config('responsive-image-craft.target_disk');
    }

    private function getTargetDirectory(): string
    {
        return config('responsive-image-craft.target_directory');
    }

    private function getSourceDisk(): string
    {
        if ($this->option('source-disk')) {
            return $this->option('source-disk');
        }

        return config('responsive-image-craft.source_disk');
    }

    private function getSourceDirectory(): string
    {
        if ($this->option('relative-source-path')) {
            return $this->option('relative-source-path');
        }

        return config('responsive-image-craft.source_directory');
    }

    private function getFilenameSpacer(): string
    {
        return config('responsive-image-craft.filename_spacer');
    }
}
