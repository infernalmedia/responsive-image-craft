<?php

namespace Infernalmedia\ResponsiveImageCraft\Commands;

use Infernalmedia\ResponsiveImageCraft\ImageInfoFromString;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
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
    protected $signature = 'image-craft:responsive-generate {--source-disk=} {--relative-source-path=}';

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


    private function generateAndSaveResponsiveImages(ImageInfoFromString $imageString): void
    {

        $this->temporaryDirectory = (new TemporaryDirectory())->create();

        $image = Image::load($imageString->getAbsolutePathname());
        $this->optimizeOriginalImage($imageString, $image->getWidth());

        foreach ($image->getFilteredExtensions() as $extension) {
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


    private function storeFileToTarget(ImageInfoFromString $imageString, string $tempFileName, string $newFileName = ''): void
    {
        Storage::disk($this->getTargetDisk())
            ->putFileAs(
                $this->getTargetPath($imageString),
                file: $tempFileName,
                name: empty($newFileName) ? $imageString->getFilename() : $newFileName
            );
    }

    private function getTargetPath(ImageInfoFromString $imageString): string
    {
        return "{$imageString->getRelativePath()}";
    }

    private function getTargetFilePath(ImageInfoFromString $imageString): string
    {
        return "{$this->getTargetPath($imageString)}/{$imageString->getFilenameWithoutExtension()}";
    }

    private function logError(ImageInfoFromString $imageString, string $message): void
    {
        if (!Arr::exists($this->logArray['errors'], $imageString->getRelativePathname())) {
            $this->logArray['errors'][$imageString->getRelativePathname()] = [];
        }

        array_push($this->logArray['errors'][$imageString->getRelativePathname()], $message);
        $this->imagesInError++;
    }

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

    private function getFilteredImages(): Collection
    {
        $imageFiles = Storage::disk($this->getSourceDisk())->allFiles($this->getSourceDirectory());

        return collect($imageFiles)
            ->mapInto(ImageInfoFromString::class)
            ->filter(function (ImageInfoFromString $image) {
                return $image->isSupportedFile();
            });
    }

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
