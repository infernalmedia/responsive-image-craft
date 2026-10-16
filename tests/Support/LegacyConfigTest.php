<?php

use Infernalmedia\ResponsiveImageCraft\Support\LegacyConfig;

it('maps every 0.x key onto its v1 replacement', function () {
    config()->set('responsive-image-craft.use_responsive_images', false);
    config()->set('responsive-image-craft.extensions', ['tiff']);
    config()->set('responsive-image-craft.extensions_filters_rules', ['gif' => ['jpg']]);
    config()->set('responsive-image-craft.extensions_to_ignore', ['bmp']);
    config()->set('responsive-image-craft.filename_to_ignore', ['logo']);
    config()->set('responsive-image-craft.supported_file_extensions', ['bmp']);

    LegacyConfig::apply();

    expect(config('responsive-image-craft.enabled'))->toBeFalse()
        ->and(config('responsive-image-craft.output_formats'))->toBe(['tiff'])
        ->and(config('responsive-image-craft.skip_formats'))->toBe(['gif' => ['jpg']])
        ->and(config('responsive-image-craft.ignored_extensions'))->toBe(['bmp'])
        ->and(config('responsive-image-craft.ignored_filenames'))->toBe(['logo'])
        ->and(config('responsive-image-craft.input_formats'))->toBe(['bmp']);
});

it('leaves v1 keys untouched when no legacy key is present', function () {
    $before = config('responsive-image-craft.output_formats');

    LegacyConfig::apply();

    expect(config('responsive-image-craft.output_formats'))->toBe($before);
});
