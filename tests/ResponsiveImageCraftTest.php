<?php

use Illuminate\Support\Facades\Config;
use Infernalmedia\ResponsiveImageCraft\Exceptions\InvalidDiskException;
use Infernalmedia\ResponsiveImageCraft\Facades\ResponsiveImageCraft as ResponsiveImageCraftFacade;
use Infernalmedia\ResponsiveImageCraft\ResponsiveImageCraft;

it('throws an exception if the target disk url is missing', function () {
    Config::set('responsive-image-craft.enabled', true);
    Config::set('filesystems.disks.target', ['driver' => 'local']);

    (new ResponsiveImageCraft)->getCssVariables('images/photo.jpg', 800, ['jpg', 'webp']);
})->throws(InvalidDiskException::class);

it('generates css variables prefixed with the target disk url', function () {
    $variables = (new ResponsiveImageCraft)->getCssVariables('images/photo.jpg', 100, ['jpg', 'webp']);

    expect($variables)->toBe(
        '--jpg-full:url(https://target.test/images/photo.jpg);'
            .'--webp-full:url(https://target.test/images/photo.webp);'
            .'--jpg-50:url(https://target.test/images/photo@50.jpg);'
            .'--webp-50:url(https://target.test/images/photo@50.webp);'
            .'--jpg-100:url(https://target.test/images/photo@100.jpg);'
            .'--webp-100:url(https://target.test/images/photo@100.webp);'
    );
});

it('uses the source disk url when responsive images are disabled', function () {
    Config::set('responsive-image-craft.enabled', false);

    $variables = (new ResponsiveImageCraft)->getCssVariables('images/photo.jpg', 50, ['jpg']);

    expect($variables)->toContain('https://source.test/images/photo.jpg')
        ->and($variables)->toContain('--jpg-50:url(https://source.test/images/photo@50.jpg);');
});

it('leaves absolute urls untouched', function () {
    $variables = (new ResponsiveImageCraft)->getCssVariables('https://cdn.example.com/photo.jpg', 50, ['jpg']);

    expect($variables)->toBe(
        '--jpg-full:url(https://cdn.example.com/photo.jpg);'
            .'--jpg-50:url(https://cdn.example.com/photo@50.jpg);'
    );
});

it('strips leading dots and slashes from relative paths', function () {
    $variables = (new ResponsiveImageCraft)->getCssVariables('./images/photo.jpg', 50, ['jpg']);

    expect($variables)->toContain('https://target.test/images/photo.jpg');
});

it('is callable through the facade', function () {
    $variables = ResponsiveImageCraftFacade::getCssVariables('images/photo.jpg', 50, ['jpg']);

    expect($variables)->toContain('--jpg-50:url(');
});
