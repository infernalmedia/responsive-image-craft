<?php

use Illuminate\Support\Facades\Config;
use Infernalmedia\ResponsiveImageCraft\Exceptions\InvalidDiskException;
use Infernalmedia\ResponsiveImageCraft\ResponsiveImageCraft;

test('it throws an exception if URL is missing', function () {
    Config::set('responsive-image-craft.use_responsive_images', true);
    Config::set('responsive-image-craft.source_disk', 'local');
    Config::set('filesystems.disks.local.url', null);

    $responsiveImageCraft = new ResponsiveImageCraft();

    $maxWidth = 800;
    $extensions = ['jpg', 'webp'];

    $this->expectException(InvalidDiskException::class);

    $responsiveImageCraft->getCssVariables('image.jpg', $maxWidth, $extensions);
});
