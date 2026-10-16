<?php

use Infernalmedia\ResponsiveImageCraft\ImageInfoFromString;
use Infernalmedia\ResponsiveImageCraft\Support\SrcsetGenerator;

it('builds a srcset with filtered sizes and the full-size entry', function () {
    $generator = new SrcsetGenerator('https://cdn.test', [50, 100, 200]);
    $image = new ImageInfoFromString('images/photo.jpg');

    $srcset = $generator->generate($image, 'webp', 150);

    expect($srcset)->toBe(
        'https://cdn.test/images/photo@50.webp 50w, '
        .'https://cdn.test/images/photo@100.webp 100w, '
        .'https://cdn.test/images/photo.webp 150w'
    );
});

it('uses every size and the largest one for the original entry', function () {
    $generator = new SrcsetGenerator('https://cdn.test', [50, 100, 200]);
    $image = new ImageInfoFromString('images/photo.jpg');

    $srcset = $generator->generate($image, 'jpg');

    expect($srcset)->toContain('photo@200.jpg 200w')
        ->and($srcset)->toEndWith('photo.jpg 200w');
});

it('handles root level files without double slashes', function () {
    $generator = new SrcsetGenerator('https://cdn.test', [50]);
    $image = new ImageInfoFromString('photo.jpg');

    expect($generator->generate($image, 'jpg'))
        ->toBe('https://cdn.test/photo@50.jpg 50w, https://cdn.test/photo.jpg 50w');
});

it('uses a custom filename spacer', function () {
    $generator = new SrcsetGenerator('https://cdn.test', [50], '-');
    $image = new ImageInfoFromString('images/photo.jpg');

    expect($generator->generate($image, 'jpg'))->toContain('photo-50.jpg 50w');
});

it('exposes variant urls', function () {
    $generator = new SrcsetGenerator('https://cdn.test', [50]);
    $image = new ImageInfoFromString('images/photo.jpg');

    expect($generator->variantUrl($image, 'webp', 50))->toBe('https://cdn.test/images/photo@50.webp')
        ->and($generator->variantUrl($image, 'webp'))->toBe('https://cdn.test/images/photo.webp');
});

it('handles an empty size list', function () {
    $generator = new SrcsetGenerator('https://cdn.test', []);
    $image = new ImageInfoFromString('images/photo.jpg');

    expect($generator->generate($image, 'jpg'))->toBe('https://cdn.test/images/photo.jpg 0w');
});
