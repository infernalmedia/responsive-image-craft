<?php

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

it('can get the image type', function () {
    $component = new ResponsiveImg('image.jpg');

    $this->assertSame('image/jpeg', $component->getImageType('jpg'));
    $this->assertSame('', $component->getImageType('invalid-extension'));
});

it('jpg returns an array of filtered extensions without png', function () {
    $component = new ResponsiveImg(
        src: 'image.jpg',
    );

    $filteredExtensions = $component->getFilteredExtensions();

    expect($filteredExtensions)->toContain('webp');
    expect($filteredExtensions)->not->toContain('png');
});

it('png returns an array of filtered extensions without jpg', function () {
    $component = new ResponsiveImg(
        src: 'image.png',
    );

    $filteredExtensions = $component->getFilteredExtensions();

    expect($filteredExtensions)->toContain('webp');
    expect($filteredExtensions)->not->toContain('jpg');
});
