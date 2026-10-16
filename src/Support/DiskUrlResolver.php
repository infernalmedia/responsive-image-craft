<?php

namespace Infernalmedia\ResponsiveImageCraft\Support;

use Infernalmedia\ResponsiveImageCraft\Exceptions\InvalidDiskException;

final class DiskUrlResolver
{
    /**
     * Returns the `url` configured for the given filesystem disk.
     *
     * @throws InvalidDiskException when the disk has no url key or an empty one
     */
    public static function baseUrl(string $disk): string
    {
        $diskConfig = config("filesystems.disks.{$disk}");

        if (! is_array($diskConfig) || ! array_key_exists('url', $diskConfig)) {
            throw InvalidDiskException::urlIsMissing();
        }

        if (empty($diskConfig['url'])) {
            throw InvalidDiskException::urlIsNotSet();
        }

        return rtrim((string) $diskConfig['url'], '/');
    }

    /**
     * Whether the given disk is backed by a local driver, meaning files can be
     * read directly through `Storage::disk()->path()`.
     */
    public static function isLocal(string $disk): bool
    {
        return config("filesystems.disks.{$disk}.driver") === 'local';
    }
}
