<?php

namespace Infernalmedia\ResponsiveImageCraft\Support;

final class PackageConfig
{
    private const string PREFIX = 'responsive-image-craft.';

    public static function enabled(): bool
    {
        return (bool) config(self::PREFIX.'enabled', true);
    }

    public static function sourceDisk(): string
    {
        return (string) config(self::PREFIX.'source_disk', 'public');
    }

    public static function targetDisk(): string
    {
        return (string) config(self::PREFIX.'target_disk', 's3');
    }

    public static function sourceDirectory(): string
    {
        return (string) config(self::PREFIX.'source_directory', 'images');
    }

    public static function targetDirectory(): string
    {
        return (string) config(self::PREFIX.'target_directory', 'images');
    }

    /**
     * @return int[]
     */
    public static function sizes(): array
    {
        return array_map('intval', (array) config(self::PREFIX.'sizes', []));
    }

    /**
     * @return string[]
     */
    public static function outputFormats(): array
    {
        return (array) config(self::PREFIX.'output_formats', []);
    }

    /**
     * @return array<string, string[]>
     */
    public static function skipFormats(): array
    {
        return (array) config(self::PREFIX.'skip_formats', []);
    }

    /**
     * @return string[]
     */
    public static function ignoredExtensions(): array
    {
        return (array) config(self::PREFIX.'ignored_extensions', []);
    }

    /**
     * @return string[]
     */
    public static function ignoredFilenames(): array
    {
        return (array) config(self::PREFIX.'ignored_filenames', []);
    }

    /**
     * @return string[]
     */
    public static function inputFormats(): array
    {
        return (array) config(self::PREFIX.'input_formats', []);
    }

    public static function filenameSpacer(): string
    {
        return (string) config(self::PREFIX.'filename_spacer', '@');
    }

    public static function containerCssClassName(): string
    {
        return (string) config(self::PREFIX.'container_css_class_name', 'img-container');
    }

    public static function optimize(): bool
    {
        return (bool) config(self::PREFIX.'optimize', true);
    }

    public static function quality(string $extension): ?int
    {
        $quality = config(self::PREFIX.'quality.'.$extension);

        return $quality === null ? null : (int) $quality;
    }
}
