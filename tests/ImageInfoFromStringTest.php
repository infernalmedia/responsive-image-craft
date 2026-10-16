<?php

use Illuminate\Support\Facades\Storage;
use Infernalmedia\ResponsiveImageCraft\ImageInfoFromString;

it('returns the configured source disk by default', function () {
    $image = new ImageInfoFromString('images/photo.jpg');

    expect($image->getSourceDisk())->toBe('public');
});

it('uses the injected disk over the configured one', function () {
    $image = new ImageInfoFromString('images/photo.jpg', 'custom-disk');

    expect($image->getSourceDisk())->toBe('custom-disk');
});

it('resolves the absolute pathname on a local disk', function () {
    Storage::fake('public');
    $this->seedFixtureImage();

    $image = new ImageInfoFromString('images/photo.jpg');

    expect($image->getAbsolutePathname())
        ->toBe(Storage::disk('public')->path('images/photo.jpg'));
});

it('parses nested paths', function () {
    $image = new ImageInfoFromString('images/sub/photo.jpg');

    expect($image->getRelativePath())->toBe('images/sub')
        ->and($image->getRelativePathname())->toBe('images/sub/photo.jpg')
        ->and($image->getRelativePathnameWithoutExtension())->toBe('images/sub/photo')
        ->and($image->getFilename())->toBe('photo.jpg')
        ->and($image->getFilenameWithoutExtension())->toBe('photo')
        ->and($image->getFileExtension())->toBe('jpg');
});

it('parses root level paths', function () {
    $image = new ImageInfoFromString('photo.jpg');

    expect($image->getRelativePath())->toBe('')
        ->and($image->getRelativePathnameWithoutExtension())->toBe('photo');
});

it('lowercases the extension', function () {
    expect((new ImageInfoFromString('images/PHOTO.JPG'))->getFileExtension())->toBe('jpg');
});

it('accepts supported image files', function () {
    expect((new ImageInfoFromString('images/photo.jpg'))->isSupportedFile())->toBeTrue()
        ->and((new ImageInfoFromString('images/photo.gif'))->isSupportedFile())->toBeTrue();
});

it('rejects unsupported extensions', function () {
    expect((new ImageInfoFromString('images/doc.txt'))->isSupportedFile())->toBeFalse();
});

it('rejects ignored extensions', function () {
    expect((new ImageInfoFromString('images/icon.svg'))->isSupportedFile())->toBeFalse();
});

it('rejects ignored filenames', function () {
    expect((new ImageInfoFromString('images/favicon.png'))->isSupportedFile())->toBeFalse();
});

it('filters output formats using skip rules', function () {
    $jpg = new ImageInfoFromString('images/photo.jpg');
    $png = new ImageInfoFromString('images/photo.png');

    expect($jpg->getFilteredExtensions())->not->toContain('png')
        ->and($jpg->getFilteredExtensions())->toContain('webp')
        ->and($png->getFilteredExtensions())->not->toContain('jpg');
});

it('filters a custom extension list', function () {
    $image = new ImageInfoFromString('images/photo.jpg');

    expect($image->getFilteredExtensions(['png', 'tiff']))->toBe(['tiff']);
});

it('filters sizes below the max width as ints', function () {
    $image = new ImageInfoFromString('images/photo.jpg');

    expect($image->getFilteredSizes(100))->toBe([50, 100])
        ->and($image->getFilteredSizes(10))->toBe([]);
});
