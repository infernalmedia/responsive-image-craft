<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Infernalmedia\ResponsiveImageCraft\Exceptions\InvalidDiskException;
use Infernalmedia\ResponsiveImageCraft\View\Components\ResponsiveImg;

it('can be created with minimum required attributes', function () {
    $component = new ResponsiveImg('image.jpg');
    expect($component)->toBeInstanceOf(ResponsiveImg::class);
});

it('can get the container CSS class', function () {
    $component = new ResponsiveImg(src: 'image.jpg');
    $this->assertSame('img-container', $component->getContainerCssClass());

    $component = new ResponsiveImg(src: 'image.jpg', containerClass: 'custom-class');
    $this->assertSame('img-container custom-class', $component->getContainerCssClass());
});

it('returns image mime types', function () {
    $component = new ResponsiveImg('image.jpg');

    $this->assertSame('image/jpeg', $component->getImageType('jpg'));
    $this->assertSame('image/jpeg', $component->getImageType('pjpg'));
    $this->assertSame('image/webp', $component->getImageType('webp'));
    $this->assertSame('', $component->getImageType('invalid-extension'));
});

it('filters extensions by source format rules', function () {
    $component = new ResponsiveImg('image.jpg');
    expect($component->getFilteredExtensions())->toContain('webp')
        ->not->toContain('png')
        ->not->toContain('jpg');

    $component = new ResponsiveImg('image.png');
    expect($component->getFilteredExtensions())->toContain('webp')
        ->not->toContain('jpg')
        ->not->toContain('png');
});

it('builds a srcset for an extension', function () {
    $component = new ResponsiveImg(src: 'images/photo.jpg', width: 120);

    $srcset = $component->getSrcset('webp');

    expect($srcset)->toContain('https://target.test/images/photo@50.webp 50w')
        ->and($srcset)->toContain('https://target.test/images/photo@100.webp 100w')
        ->and($srcset)->toEndWith('photo.webp 120w');
});

it('returns lazy/eager loading and async/auto decoding', function () {
    expect((new ResponsiveImg('image.jpg'))->getLoading())->toBe('lazy')
        ->and((new ResponsiveImg('image.jpg', lazy: false))->getLoading())->toBe('eager')
        ->and((new ResponsiveImg('image.jpg'))->getDecoding())->toBe('async')
        ->and((new ResponsiveImg('image.jpg', asyncDecoding: false))->getDecoding())->toBe('auto');
});

it('validates fetch priority', function () {
    expect((new ResponsiveImg('image.jpg'))->getFetchPriority())->toBeNull()
        ->and((new ResponsiveImg('image.jpg', fetchPriority: 'high'))->getFetchPriority())->toBe('high')
        ->and((new ResponsiveImg('image.jpg', fetchPriority: 'bogus'))->getFetchPriority())->toBeNull();
});

it('falls back to the app name for alt', function () {
    config()->set('app.name', 'TestApp');

    expect((new ResponsiveImg('image.jpg'))->getAltAttribute())->toBe('TestApp')
        ->and((new ResponsiveImg('image.jpg', alt: 'A picture'))->getAltAttribute())->toBe('A picture');
});

it('reads the enabled flag', function () {
    expect((new ResponsiveImg('image.jpg'))->enabled())->toBeTrue();

    config()->set('responsive-image-craft.enabled', false);
    expect((new ResponsiveImg('image.jpg'))->enabled())->toBeFalse();
});

it('resolves the base url from the target disk', function () {
    expect((new ResponsiveImg('image.jpg'))->getUrlBasePath())->toBe('https://target.test');
});

it('resolves the base url from the source disk when disabled', function () {
    config()->set('responsive-image-craft.enabled', false);

    expect((new ResponsiveImg('image.jpg'))->getUrlBasePath())->toBe('https://source.test');
});

it('throws when the resolved disk has no url', function () {
    config()->set('filesystems.disks.target', ['driver' => 'local']);

    (new ResponsiveImg('image.jpg'))->getUrlBasePath();
})->throws(InvalidDiskException::class);

it('detects dimensions from local source images', function () {
    Storage::fake('public');
    $this->seedFixtureImage();

    $component = new ResponsiveImg('images/photo.jpg');

    expect($component->width)->toBe(320)
        ->and($component->height)->toBe(240);
});

it('keeps explicit dimensions over detected ones', function () {
    Storage::fake('public');
    $this->seedFixtureImage();

    $component = new ResponsiveImg('images/photo.jpg', width: 800, height: 600);

    expect($component->width)->toBe(800)
        ->and($component->height)->toBe(600);
});

it('leaves dimensions null on remote source disks', function () {
    config()->set('responsive-image-craft.source_disk', 'remote');
    config()->set('filesystems.disks.remote', ['driver' => 's3', 'url' => 'https://s3.test']);

    $component = new ResponsiveImg('images/photo.jpg');

    expect($component->width)->toBeNull()
        ->and($component->height)->toBeNull();
});

it('leaves dimensions null when the source file cannot be read', function () {
    Storage::fake('public');

    $component = new ResponsiveImg('images/missing.jpg');

    expect($component->width)->toBeNull()
        ->and($component->height)->toBeNull();
});

it('leaves dimensions null when the local source disk is misconfigured', function () {
    config()->set('responsive-image-craft.source_disk', 'broken');
    config()->set('filesystems.disks.broken', ['driver' => 'local']);

    $component = new ResponsiveImg('images/photo.jpg');

    expect($component->width)->toBeNull()
        ->and($component->height)->toBeNull();
});

it('renders the full picture markup', function () {
    Storage::fake('public');
    $this->seedFixtureImage();

    $html = Blade::render('<x-infernal-responsive-img src="images/photo.jpg" alt="A picture" />');

    expect($html)
        ->toContain('class="img-container"')
        ->toContain('<picture')
        ->toContain('type="image/webp"')
        ->toContain('type="image/avif"')
        ->toContain('https://target.test/images/photo@50.webp 50w')
        ->toContain('src="https://target.test/images/photo.jpg"')
        ->toContain('width="320"')
        ->toContain('height="240"')
        ->toContain('alt="A picture"')
        ->toContain('decoding="async"')
        ->toContain('loading="lazy"');
});

it('renders without the picture tag when skipped', function () {
    Storage::fake('public');
    $this->seedFixtureImage();

    $html = Blade::render('<x-infernal-responsive-img src="images/photo.jpg" :skip-picture-tag="true" class="extra" />');

    expect($html)
        ->not->toContain('<picture')
        ->toContain('<img')
        ->toContain('class="extra"');
});

it('renders raw img attributes unescaped', function () {
    Storage::fake('public');
    $this->seedFixtureImage();

    $html = Blade::render(
        '<x-infernal-responsive-img src="images/photo.jpg" img-attributes=\'data-track="hero"\' />'
    );

    expect($html)->toContain('data-track="hero"')
        ->not->toContain('&quot;');
});

it('renders fetchpriority and sizes attributes', function () {
    Storage::fake('public');
    $this->seedFixtureImage();

    $html = Blade::render(
        '<x-infernal-responsive-img src="images/photo.jpg" fetch-priority="high" sizes="100vw" />'
    );

    expect($html)->toContain('fetchpriority="high"')
        ->toContain('sizes="100vw"');
});

it('renders the plain img without srcset when disabled', function () {
    config()->set('responsive-image-craft.enabled', false);
    Storage::fake('public');
    $this->seedFixtureImage();

    $html = Blade::render('<x-infernal-responsive-img src="images/photo.jpg" />');

    expect($html)
        ->not->toContain('<source')
        ->not->toContain('srcset')
        ->toContain('src="https://source.test/images/photo.jpg"');
});
