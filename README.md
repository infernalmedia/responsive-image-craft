# Laravel ResponsiveImageCraft 

[![Latest Version on Packagist](https://img.shields.io/packagist/v/infernalmedia/responsive-image-craft.svg?style=flat-square)](https://packagist.org/packages/infernalmedia/responsive-image-craft)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/infernalmedia/responsive-image-craft/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/infernalmedia/responsive-image-craft/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/infernalmedia/responsive-image-craft/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/infernalmedia/responsive-image-craft/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/infernalmedia/responsive-image-craft.svg?style=flat-square)](https://packagist.org/packages/infernalmedia/responsive-image-craft)

Introducing **ResponsiveImageCraft**, a powerful Laravel package that empowers you to effortlessly generate responsive images in various desired static formats through a simple command line interface. This package equips you with a versatile component to effortlessly display these responsive images on your web pages. Additionally, you'll find a collection of SCSS mixins to effortlessly generate the necessary code when using the images as background-images, ensuring a seamless integration into your projects.

With **ResponsiveImageCraft**, you can now efficiently manage and serve responsive images, enhancing your website's performance and user experience across various devices. Embrace the ease and flexibility of **ResponsiveImageCraft** as it takes care of the heavy lifting for you, delivering stunning visuals without the hassle. So why wait? Elevate your image handling capabilities today with **ResponsiveImageCraft**.

## Installation

You can install the package via composer:

```bash
composer require infernalmedia/responsive-image-craft
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="responsive-image-craft-config"
```

This is the contents of the published config file:

```php
return [
    'use_responsive_images' => env('USE_RESPONSIVE_IMAGES', true),
    'source_disk' => env('RESPONSIVE_IMAGES_SOURCE_DISK', 'local'),
    'target_disk' => env('RESPONSIVE_IMAGES_TARGET_DISK', 's3'),
    'source_directory' => env('RESPONSIVE_IMAGES_SOURCE_DIRECTORY', 'images'),
    'target_directory' => env('RESPONSIVE_IMAGES_TARGET_DIRECTORY', 'images'),
    'sizes' => [
        320,
        640,
        880,
        1024,
        1200,
        1760,
        2100,
    ],
    'extensions' => [
        Manipulations::FORMAT_JPG,
        Manipulations::FORMAT_PNG,
        Manipulations::FORMAT_AVIF,
        Manipulations::FORMAT_WEBP,
    ],
    'extensions_filters_rules' => [
        Manipulations::FORMAT_JPG => [Manipulations::FORMAT_PNG],
        Manipulations::FORMAT_PNG => [Manipulations::FORMAT_JPG],
        Manipulations::FORMAT_WEBP => [],
        Manipulations::FORMAT_AVIF => [],
    ],
    'extensions_to_ignore' => [
        'svg'
    ],
    'filename_to_ignore' => [
        'favicon'
    ],
    'supported_file_extensions' => [
        Manipulations::FORMAT_JPG,
        Manipulations::FORMAT_WEBP,
        Manipulations::FORMAT_PNG,
        Manipulations::FORMAT_AVIF,
        Manipulations::FORMAT_GIF,
        Manipulations::FORMAT_TIFF,
        Manipulations::FORMAT_PJPG
    ],
    'filename_spacer' => '@',
    'container_css_class_name' => 'img-container'
];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="responsive-image-craft-views"
```

## Usage

```php
$responsiveImageCraft = new Infernalmedia\ResponsiveImageCraft();
echo $responsiveImageCraft->echoPhrase('Hello, Infernalmedia!');
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Guillaume Ernst](https://github.com/infernalmedia)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
