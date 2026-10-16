<?php

use Infernalmedia\ResponsiveImageCraft\Support\GenerationOptions;

it('builds options from config', function () {
    $options = GenerationOptions::fromConfig();

    expect($options->sourceDisk)->toBe('public')
        ->and($options->targetDisk)->toBe('target')
        ->and($options->sourceDirectory)->toBe('images')
        ->and($options->sizes)->toBe([50, 100, 200])
        ->and($options->extensions)->toContain('webp')
        ->and($options->optimize)->toBeFalse()
        ->and($options->force)->toBeFalse()
        ->and($options->filenameSpacer)->toBe('@');
});

it('serializes to and from an array', function () {
    $options = new GenerationOptions(
        sourceDisk: 'src',
        sourceDirectory: 'pics',
        targetDisk: 'dst',
        targetDirectory: 'out',
        sizes: [100],
        extensions: ['webp'],
        quality: ['webp' => 80],
        optimize: false,
        force: true,
        filenameSpacer: '-',
    );

    $restored = GenerationOptions::fromArray($options->toArray());

    expect($restored)->toEqual($options);
});

it('restores defaults for missing array keys', function () {
    $options = GenerationOptions::fromArray([]);

    expect($options->sourceDisk)->toBe('public')
        ->and($options->sizes)->toBe([])
        ->and($options->optimize)->toBeTrue()
        ->and($options->filenameSpacer)->toBe('@');
});
