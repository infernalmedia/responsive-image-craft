<?php

namespace Infernalmedia\ResponsiveImageCraft\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Infernalmedia\ResponsiveImageCraft\ImageInfoFromString;
use Infernalmedia\ResponsiveImageCraft\Support\PackageConfig;

/**
 * Deletes target variants whose source image no longer exists.
 * A target file is considered stale when no supported source image shares
 * its base path (`{dir}/{name}` without extension or `@{width}` suffix).
 */
final class CleanResponsiveImages extends Command
{
    protected $signature = 'responsive-image-craft:clean
        {--source-disk= : Disk to scan for source images}
        {--target-disk= : Disk where variants live}
        {--relative-source-path= : Directory scanned on the source disk}
        {--dry-run : List stale files without deleting them}';

    protected $description = 'Delete responsive image variants whose source image no longer exists';

    public function handle(): int
    {
        $sourceDisk = $this->stringOption('source-disk') ?? PackageConfig::sourceDisk();
        $targetDisk = $this->stringOption('target-disk') ?? PackageConfig::targetDisk();
        $sourceDirectory = $this->stringOption('relative-source-path') ?? PackageConfig::sourceDirectory();

        $sourceKeys = collect(Storage::disk($sourceDisk)->allFiles($sourceDirectory))
            ->map(fn (string $file) => new ImageInfoFromString($file, $sourceDisk))
            ->filter(fn (ImageInfoFromString $image) => $image->isSupportedFile())
            ->map(fn (ImageInfoFromString $image) => $image->getRelativePathnameWithoutExtension())
            ->flip()
            ->all();

        $imageFormats = array_unique(array_merge(
            PackageConfig::inputFormats(),
            PackageConfig::outputFormats()
        ));

        $stale = collect(Storage::disk($targetDisk)->allFiles($sourceDirectory))
            ->filter(fn (string $file) => $this->isStale($file, $sourceKeys, $imageFormats))
            ->values();

        if ($stale->isEmpty()) {
            $this->info('No stale variant found.');

            return self::SUCCESS;
        }

        foreach ($stale as $file) {
            $this->line($file);

            if (! $this->option('dry-run')) {
                Storage::disk($targetDisk)->delete($file);
            }
        }

        $verb = $this->option('dry-run') ? 'would be deleted' : 'deleted';
        $this->info("{$stale->count()} stale variants {$verb}.");

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $sourceKeys
     * @param  string[]  $imageFormats
     */
    private function isStale(string $file, array $sourceKeys, array $imageFormats): bool
    {
        if (! in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $imageFormats)) {
            return false;
        }

        $directory = pathinfo($file, PATHINFO_DIRNAME);
        $name = pathinfo($file, PATHINFO_FILENAME);
        $name = preg_replace(
            '/'.preg_quote(PackageConfig::filenameSpacer(), '/').'\d+$/',
            '',
            $name
        );

        $baseKey = ($directory === '.' ? '' : $directory.'/').$name;

        return ! array_key_exists($baseKey, $sourceKeys);
    }
}
