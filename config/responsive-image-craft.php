<?php

return [
    /*
    | Display responsive images in srcset and css variables or display the original one.
    | Legacy key: use_responsive_images
    */
    'enabled' => env('USE_RESPONSIVE_IMAGES', true),

    /*
    | Source and target disks and directories for the responsive images.
    */
    'source_disk' => env('RESPONSIVE_IMAGES_SOURCE_DISK', 'public'),
    'target_disk' => env('RESPONSIVE_IMAGES_TARGET_DISK', 's3'),
    'source_directory' => env('RESPONSIVE_IMAGES_SOURCE_DIRECTORY', 'images'),
    'target_directory' => env('RESPONSIVE_IMAGES_TARGET_DIRECTORY', 'images'),

    /*
    | Widths, in pixels, used to generate responsive image variants.
    */
    'sizes' => array_map('intval', explode(',', (string) env('RESPONSIVE_IMAGES_SIZES', '320,640,880,1024,1200,1760,2100'))),

    /*
    | Formats generated for each source image.
    | Legacy key: extensions
    | @see Spatie\Image\Drivers\ImageDriver::format() for available formats
    */
    'output_formats' => [
        'jpg',
        'png',
        'avif',
        'webp',
    ],

    /*
    | Output formats to skip depending on the source format.
    | e.g. a `jpg` source does not get a `png` output.
    | Legacy key: extensions_filters_rules
    */
    'skip_formats' => [
        'jpg' => ['png'],
        'png' => ['jpg'],
        'webp' => [],
        'avif' => [],
    ],

    /*
    | Source file extensions never processed.
    | Legacy key: extensions_to_ignore
    */
    'ignored_extensions' => [
        'svg',
    ],

    /*
    | Source filenames never processed.
    | Legacy key: filename_to_ignore
    */
    'ignored_filenames' => [
        'favicon',
    ],

    /*
    | Accepted source formats.
    | Legacy key: supported_file_extensions
    */
    'input_formats' => [
        'jpg',
        'webp',
        'png',
        'avif',
        'gif',
    ],

    /*
    | Separator between the filename and the width in generated files.
    | e.g.: `my-image-filename.jpg` => `my-image-filename@1200.jpg`
    */
    'filename_spacer' => '@',

    /*
    | CSS class applied to the wrapping container of the responsive image component.
    */
    'container_css_class_name' => 'img-container',

    /*
    | Run generated files through spatie/image-optimizer. Disable when the
    | optimizer binaries (jpegoptim, optipng, ...) are not installed.
    */
    'optimize' => env('RESPONSIVE_IMAGES_OPTIMIZE', true),

    /*
    | Compression quality per output format (0-100). Empty keeps driver defaults.
    */
    'quality' => [
        // 'jpg' => 80,
        // 'webp' => 80,
        // 'avif' => 65,
    ],

    /*
    | Publish target for the SCSS files.
    */
    'scss_path' => resource_path('/scss/utilities'),

    /*
    | Publish target for the JS files.
    */
    'js_path' => resource_path('/js'),
];
