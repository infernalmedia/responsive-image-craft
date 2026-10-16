<?php

namespace Infernalmedia\ResponsiveImageCraft\Support;

/**
 * Lists the filesystem disks configured in the app.
 */
final class Disks
{
    /**
     * Every configured disk name, with extra names appended when missing.
     *
     * @return string[]
     */
    public static function all(string ...$include): array
    {
        /** @var array<array-key, mixed> $disks */
        $disks = config('filesystems.disks') ?? [];

        return array_values(array_unique(array_map(
            'strval',
            [...array_keys($disks), ...$include]
        )));
    }
}
