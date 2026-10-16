<?php

use Infernalmedia\ResponsiveImageCraft\Support\PackageConfig;

it('reads the renamed v1 config keys', function () {
    expect(PackageConfig::enabled())->toBeTrue()
        ->and(PackageConfig::sourceDisk())->toBe('public')
        ->and(PackageConfig::targetDisk())->toBe('target')
        ->and(PackageConfig::sourceDirectory())->toBe('images')
        ->and(PackageConfig::targetDirectory())->toBe('images')
        ->and(PackageConfig::outputFormats())->toContain('webp')
        ->and(PackageConfig::inputFormats())->toContain('gif')
        ->and(PackageConfig::ignoredExtensions())->toContain('svg')
        ->and(PackageConfig::ignoredFilenames())->toContain('favicon')
        ->and(PackageConfig::filenameSpacer())->toBe('@')
        ->and(PackageConfig::containerCssClassName())->toBe('img-container')
        ->and(PackageConfig::optimize())->toBeFalse();
});

it('casts sizes to integers', function () {
    config()->set('responsive-image-craft.sizes', ['320', '640']);

    expect(PackageConfig::sizes())->toBe([320, 640]);
});

it('reads skip formats rules', function () {
    expect(PackageConfig::skipFormats()['jpg'])->toBe(['png']);
});

it('returns null quality when not configured and int when set', function () {
    expect(PackageConfig::quality('jpg'))->toBeNull();

    config()->set('responsive-image-craft.quality', ['jpg' => 70]);

    expect(PackageConfig::quality('jpg'))->toBe(70)
        ->and(PackageConfig::quality('webp'))->toBeNull();
});
