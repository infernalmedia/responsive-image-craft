<?php

use Spatie\Image\Manipulations;

return [
    /*
    | Display responsive images in srcset et css variables or display the original one
    */
    'use_responsive_images' => env('USE_RESPONSIVE_IMAGES', true),

    /*
    | These lines are setting the values of configuration options related to the source and target disks
    | and directories for the responsive images.
    */
    'source_disk' => env('RESPONSIVE_IMAGES_SOURCE_DISK', 'local'),
    'target_disk' => env('RESPONSIVE_IMAGES_TARGET_DISK', 's3'),
    'source_directory' => env('RESPONSIVE_IMAGES_SOURCE_DIRECTORY', 'images'),
    'target_directory' => env('RESPONSIVE_IMAGES_TARGET_DIRECTORY', 'images'),

    /*
    | The `sizes` array contains a list of image sizes in pixels. These sizes are used to generate
    | responsive images with different dimensions.
    */
    'sizes' => explode(',', env('RESPONSIVE_IMAGES_SIZES', "320,640,880,1024,1200,1760,2100")),

    /*
    | The `extensions` array contains a list of image formats that are supported by the code. These
    | formats include JPEG (JPG), PNG, AVIF, and WEBP.
    | @see Spatie\Image\Manipulation for available formats
    */
    'extensions' => [
        Manipulations::FORMAT_JPG,
        Manipulations::FORMAT_PNG,
        Manipulations::FORMAT_AVIF,
        Manipulations::FORMAT_WEBP,
    ],

    /*
    | The `extensions_filters_rules` array is used to define the image format ignoring rules. Each
    | key-value pair in the array represents a format and its corresponding ignoring conversions.
    */
    'extensions_filters_rules' => [
        Manipulations::FORMAT_JPG => [Manipulations::FORMAT_PNG],
        Manipulations::FORMAT_PNG => [Manipulations::FORMAT_JPG],
        Manipulations::FORMAT_WEBP => [],
        Manipulations::FORMAT_AVIF => [],
    ],

    /*
    | The `extensions_to_ignore` array is used to specify image file extensions that should be ignored by
    | the code. In this case, the code is ignoring SVG files. This means that
    | any image file with the extension `.svg` will not be processed or included in the responsive images
    | generation.
    */
    'extensions_to_ignore' => [
        'svg',
    ],

    /*
    | The `filename_to_ignore` array is used to specify image file names that should be ignored by the
    | code. In this case, the code is ignoring any image file with the name "favicon". This means that any
    | image file with the name "favicon" will not be processed or included in the responsive images
    | generation.
    */
    'filename_to_ignore' => [
        'favicon',
    ],

    /*
    | The `supported_file_extensions` array is defining the list of image formats that are supported by
    | the code. It includes the formats JPEG (JPG), PNG, AVIF, WEBP, and GIF. This means that the code
    | will only process and generate responsive images for files with these extensions. Any other image
    | formats will be ignored.
    */
    'supported_file_extensions' => [
        Manipulations::FORMAT_JPG,
        Manipulations::FORMAT_WEBP,
        Manipulations::FORMAT_PNG,
        Manipulations::FORMAT_AVIF,
        Manipulations::FORMAT_GIF,
    ],

    /*
    | The value of `filename_spacer` is used to split filename from image size.
    | e.g.: `my-image-filename.jpg` => `my-image-filename@1200.jpg`
    */
    'filename_spacer' => '@',

    /*
    | The `'container_css_class_name' => 'img-container',` line is setting the value of the
    | `'container_css_class_name'` configuration option to `'img-container'`. This configuration
    | option is used to specify the CSS class name that will be applied to the container element of
    | the responsive image. By default, the container element will have the CSS class name
    | `'img-container'`.
    */
    'container_css_class_name' => 'img-container',

    /*
    | `'scss_path' => resource_path('/scss/utilities'),` is setting the value of the `'scss_path'`
    | configuration option to the path `/scss/utilities` within the `resources` directory. This
    | configuration option is used to specify the path to the SCSS where .scss files should be copy
    */
    'scss_path' => resource_path('/scss/utilities'),

    /*
    | The line `'js_path' => resource_path('/js'),` is setting the value of the `'js_path'`
    | configuration option to the path `/js` within the `resources` directory. This configuration
    | option is used to specify the path to the JavaScript files that should be copied.
    */
    'js_path' => resource_path('/js'),
];
