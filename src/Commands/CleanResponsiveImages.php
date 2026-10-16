<?php

namespace Infernalmedia\ResponsiveImageCraft\Commands;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Infernalmedia\ResponsiveImageCraft\ImageInfoFromString;
use Infernalmedia\ResponsiveImageCraft\Support\Disks;
use Infernalmedia\ResponsiveImageCraft\Support\PackageConfig;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

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
        $sourceDisk = $this->resolveDisk(
            'source-disk',
            'Which disk contains the source images?',
            PackageConfig::sourceDisk()
        );
        $targetDisk = $this->resolveDisk(
            'target-disk',
            'Which disk contains the generated variants?',
            PackageConfig::targetDisk()
        );
        $sourceDirectory = $this->resolveDirectory();

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
        }

        if ($this->option('dry-run')) {
            $this->info("{$stale->count()} stale variants would be deleted.");

            return self::SUCCESS;
        }

        if (! $this->confirmDeletion($stale)) {
            $this->info('Aborted — nothing was deleted.');

            return self::SUCCESS;
        }

        foreach ($stale as $file) {
            Storage::disk($targetDisk)->delete($file);
        }

        $this->info("{$stale->count()} stale variants deleted.");

        return self::SUCCESS;
    }

    private function resolveDisk(string $option, string $label, string $default): string
    {
        $disk = $this->stringOption($option) ?? $this->prompted(fn () => select(
            label: $label,
            options: Disks::all($default),
            default: $default,
        ));

        return is_string($disk) && $disk !== '' ? $disk : $default;
    }

    private function resolveDirectory(): string
    {
        $default = PackageConfig::sourceDirectory();

        $directory = $this->stringOption('relative-source-path') ?? $this->prompted(fn () => text(
            label: 'Which directory on the source disk should be scanned?',
            default: $default,
        ));

        return is_string($directory) && $directory !== '' ? $directory : $default;
    }

    /**
     * Ask before deleting when interactive; proceed otherwise.
     *
     * @param  Collection<int, string>  $stale
     */
    private function confirmDeletion(Collection $stale): bool
    {
        return $this->prompted(fn () => confirm(
            label: "Delete these {$stale->count()} stale variants?",
            default: false,
        )) ?? true;
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
