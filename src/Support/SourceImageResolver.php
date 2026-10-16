<?php

namespace Infernalmedia\ResponsiveImageCraft\Support;

use Illuminate\Support\Facades\Storage;
use Infernalmedia\ResponsiveImageCraft\ImageInfoFromString;
use Spatie\TemporaryDirectory\TemporaryDirectory;

/**
 * Resolves an image on any filesystem disk to a local path usable by
 * spatie/image. Local disks are passed through; remote disks are streamed
 * into the run's temporary directory.
 */
final class SourceImageResolver
{
    public function __construct(
        private readonly TemporaryDirectory $temporaryDirectory
    ) {}

    public function resolve(ImageInfoFromString $image): string
    {
        $disk = $image->getSourceDisk();

        if (DiskUrlResolver::isLocal($disk)) {
            return Storage::disk($disk)->path($image->getRelativePathname());
        }

        $localPath = $this->temporaryDirectory->path('source-'.$image->getFilename());

        file_put_contents(
            $localPath,
            Storage::disk($disk)->get($image->getRelativePathname())
        );

        return $localPath;
    }
}
