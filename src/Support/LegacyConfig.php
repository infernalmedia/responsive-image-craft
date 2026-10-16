<?php

namespace Infernalmedia\ResponsiveImageCraft\Support;

/**
 * Maps 0.x config keys onto their v1 replacement.
 *
 * The v1 config file only ships the new keys, so an old key present in the
 * merged config can only come from a user-published 0.x file: its value then
 * takes precedence over the new key's default.
 */
final class LegacyConfig
{
    /**
     * @var array<string, string>
     */
    private const array RENAMED_KEYS = [
        'use_responsive_images' => 'enabled',
        'extensions' => 'output_formats',
        'extensions_filters_rules' => 'skip_formats',
        'extensions_to_ignore' => 'ignored_extensions',
        'filename_to_ignore' => 'ignored_filenames',
        'supported_file_extensions' => 'input_formats',
    ];

    public static function apply(string $configKey = 'responsive-image-craft'): void
    {
        foreach (self::RENAMED_KEYS as $legacyKey => $newKey) {
            $legacyValue = config("{$configKey}.{$legacyKey}");

            if ($legacyValue !== null) {
                config()->set("{$configKey}.{$newKey}", $legacyValue);
            }
        }
    }
}
