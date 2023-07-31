<?php

use Spatie\Image\Manipulations;

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
        'svg',
    ],
    'filename_to_ignore' => [
        'favicon',
    ],
    'supported_file_extensions' => [
        Manipulations::FORMAT_JPG,
        Manipulations::FORMAT_WEBP,
        Manipulations::FORMAT_PNG,
        Manipulations::FORMAT_AVIF,
        Manipulations::FORMAT_GIF,
        Manipulations::FORMAT_TIFF,
        Manipulations::FORMAT_PJPG,
    ],
    'filename_spacer' => '@',
    'container_css_class_name' => 'img-container',
];
