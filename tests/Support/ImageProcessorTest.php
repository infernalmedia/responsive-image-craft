<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Infernalmedia\ResponsiveImageCraft\Events\ResponsiveImageGenerated;
use Infernalmedia\ResponsiveImageCraft\ImageInfoFromString;
use Infernalmedia\ResponsiveImageCraft\Support\GenerationOptions;
use Infernalmedia\ResponsiveImageCraft\Support\ImageProcessor;
use Spatie\TemporaryDirectory\TemporaryDirectory;

function makeProcessor(array $overrides = []): ImageProcessor
{
    $defaults = [
        'sourceDisk' => 'public',
        'sourceDirectory' => 'images',
        'targetDisk' => 'target',
        'targetDirectory' => 'images',
        'sizes' => [50, 100, 200],
        'extensions' => ['webp', 'jpg'],
        'optimize' => false,
    ];

    return new ImageProcessor(new GenerationOptions(...[...$defaults, ...$overrides]));
}

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('target');
    $this->temporaryDirectory = (new TemporaryDirectory)->create();
});

afterEach(function () {
    $this->temporaryDirectory->delete();
});

it('generates the original, full-size formats and every size', function () {
    $this->seedFixtureImage();
    $image = new ImageInfoFromString('images/photo.jpg', 'public');

    $generated = makeProcessor()->process($image, $this->temporaryDirectory);

    foreach ([50, 100, 200] as $width) {
        Storage::disk('target')->assertExists("images/photo@{$width}.webp");
        Storage::disk('target')->assertExists("images/photo@{$width}.jpg");
    }

    Storage::disk('target')->assertExists('images/photo.jpg');
    Storage::disk('target')->assertExists('images/photo.webp');

    // 'jpg' holds the optimized original plus its 3 size variants; its
    // full-size entry is the original itself, not a re-encoded copy.
    expect($generated['webp'])->toHaveCount(4)
        ->and($generated['jpg'])->toHaveCount(4);
});

it('resizes variants below the original width', function () {
    $this->seedFixtureImage();
    $image = new ImageInfoFromString('images/photo.jpg', 'public');

    makeProcessor()->process($image, $this->temporaryDirectory);

    $variant = getimagesize(Storage::disk('target')->path('images/photo@100.jpg'));
    expect($variant[0])->toBe(100);
});

it('skips sizes larger than the image width', function () {
    $this->seedFixtureImage();
    $image = new ImageInfoFromString('images/photo.jpg', 'public');

    makeProcessor(['sizes' => [100, 5000]])->process($image, $this->temporaryDirectory);

    Storage::disk('target')->assertExists('images/photo@100.jpg');
    Storage::disk('target')->assertMissing('images/photo@5000.jpg');
});

it('applies skip format rules of the source extension', function () {
    $image = new ImageInfoFromString('images/photo.jpg', 'public');

    expect(makeProcessor(['extensions' => ['webp', 'jpg', 'png']])->outputExtensionsFor($image))
        ->toBe(['webp', 'jpg']);
});

it('computes expected full size paths', function () {
    $image = new ImageInfoFromString('images/photo.jpg', 'public');

    expect(makeProcessor(['extensions' => ['webp', 'jpg']])->expectedFullSizePaths($image))
        ->toBe(['images/photo.jpg', 'images/photo.webp']);
});

it('computes expected variant paths', function () {
    $image = new ImageInfoFromString('images/photo.jpg', 'public');

    $paths = makeProcessor(['sizes' => [100], 'extensions' => ['webp']])
        ->expectedVariantPaths($image, 320);

    expect($paths)->toContain('images/photo.jpg')
        ->and($paths)->toContain('images/photo.webp')
        ->and($paths)->toContain('images/photo@100.webp');
});

it('dispatches the generated event', function () {
    Event::fake();
    $this->seedFixtureImage();
    $image = new ImageInfoFromString('images/photo.jpg', 'public');

    makeProcessor()->process($image, $this->temporaryDirectory);

    Event::assertDispatched(
        ResponsiveImageGenerated::class,
        fn (ResponsiveImageGenerated $event) => $event->file === 'images/photo.jpg'
            && array_key_exists('webp', $event->generatedFiles)
    );
});

it('copies the original untouched when optimization is disabled', function () {
    $this->seedFixtureImage();
    $image = new ImageInfoFromString('images/photo.jpg', 'public');

    makeProcessor()->process($image, $this->temporaryDirectory);

    expect(Storage::disk('target')->get('images/photo.jpg'))
        ->toBe(Storage::disk('public')->get('images/photo.jpg'));
});

it('runs the optimizer chain when enabled', function () {
    $this->seedFixtureImage();
    $image = new ImageInfoFromString('images/photo.jpg', 'public');

    makeProcessor(['optimize' => true])->process($image, $this->temporaryDirectory);

    Storage::disk('target')->assertExists('images/photo.jpg');
    Storage::disk('target')->assertExists('images/photo@50.webp');
});

it('accepts a quality setting per format', function () {
    $this->seedFixtureImage();
    $image = new ImageInfoFromString('images/photo.jpg', 'public');

    makeProcessor(['quality' => ['jpg' => 60]])->process($image, $this->temporaryDirectory);

    Storage::disk('target')->assertExists('images/photo@100.jpg');
});
