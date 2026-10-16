# Responsive Image Craft

[![Latest Version on Packagist](https://img.shields.io/packagist/v/infernalmedia/responsive-image-craft.svg?style=flat-square)](https://packagist.org/packages/infernalmedia/responsive-image-craft)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/infernalmedia/responsive-image-craft/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/infernalmedia/responsive-image-craft/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Code Coverage](https://img.shields.io/codecov/c/github/infernalmedia/responsive-image-craft?style=flat-square)](https://codecov.io/gh/infernalmedia/responsive-image-craft)
[![Total Downloads](https://img.shields.io/packagist/dt/infernalmedia/responsive-image-craft.svg?style=flat-square)](https://packagist.org/packages/infernalmedia/responsive-image-craft)

Tools for generating and displaying responsive images in a Laravel app:

- an Artisan command that generates variants for every image of a source disk,
- a Blade component that renders a `<picture>` with the matching `srcset`s,
- SCSS mixins that turn those variants into CSS variables for `background-image` use.

Requires PHP 8.4+ and Laravel 12 or 13. **Upgrading from 0.x? See [UPGRADING.md](UPGRADING.md).**

## Installation

```bash
composer require infernalmedia/responsive-image-craft
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=responsive-image-craft-config
```

<details>
<summary>Default configuration</summary>

```php
return [
    // Toggle responsive images (srcset / CSS variables) vs. the original.
    'enabled' => env('USE_RESPONSIVE_IMAGES', true),

    'source_disk' => env('RESPONSIVE_IMAGES_SOURCE_DISK', 'public'),
    'target_disk' => env('RESPONSIVE_IMAGES_TARGET_DISK', 's3'),
    'source_directory' => env('RESPONSIVE_IMAGES_SOURCE_DIRECTORY', 'images'),
    'target_directory' => env('RESPONSIVE_IMAGES_TARGET_DIRECTORY', 'images'),

    // Widths (px) for which variants are generated.
    'sizes' => explode(',', env('RESPONSIVE_IMAGES_SIZES', '320,640,880,1024,1200,1760,2100')),

    // Output formats generated per source image.
    'output_formats' => ['jpg', 'png', 'avif', 'webp'],

    // Output formats skipped per source extension.
    'skip_formats' => ['jpg' => ['png'], 'png' => ['jpg'], 'webp' => [], 'avif' => []],

    // Source extensions / filenames never processed.
    'ignored_extensions' => ['svg'],
    'ignored_filenames' => ['favicon'],

    // Accepted source formats.
    'input_formats' => ['jpg', 'webp', 'png', 'avif', 'gif'],

    // Spacer between filename and width (photo@880.jpg).
    'filename_spacer' => '@',

    // Wrapper class of the Blade component.
    'container_css_class_name' => 'img-container',

    // Run spatie/image-optimizer on generated files.
    'optimize' => env('RESPONSIVE_IMAGES_OPTIMIZE', true),

    // Compression quality per output format (0-100, empty = driver defaults).
    'quality' => [
        // 'jpg' => 80, 'webp' => 80, 'avif' => 65,
    ],

    // Publish targets for the package assets.
    'scss_path' => resource_path('/scss/utilities'),
    'js_path' => resource_path('/js'),
];
```

</details>

The **source and target disks need a `url` configured** in `config/filesystems.php` — it prefixes every generated URL (`<img src>`, `srcset`, CSS variables).

### Image optimization

`spatie/image-optimizer` only works with locally reachable binaries (`jpegoptim`, `optipng`, `pngquant`, `gifsicle`, `avifenc`, `cwebp`…). Install them — see [the package docs](https://github.com/spatie/image-optimizer#optimization-tools) — or disable the step:

```php
'optimize' => false,
```

## Generating variants

```bash
php artisan responsive-image-craft:generate
```

For every file under `source_directory` on `source_disk`, the command writes onto `target_disk`/`target_directory`: the original itself (in every allowed output format) plus one resized variant per configured width, named `photo@880.jpg`.

The source disk may be remote (S3, FTP…): files are streamed to a temporary directory before processing. Each image is decoded once per output format and sizes are resized descending — far fewer image loads than 0.x.

| Option                       | Effect                                                    |
| ---------------------------- | --------------------------------------------------------- |
| `--source-disk=disk`         | List **and** read originals on another disk               |
| `--relative-source-path=dir` | Subdirectory of `source_directory` to scan (default `''`) |
| `--path=dir/photo.jpg`       | Process a single file                                     |
| `--force`                    | Regenerate variants that already exist                    |
| `--sizes=320,640`            | Override the size list                                    |
| `--extensions=webp,avif`     | Override the output formats                               |
| `--dry-run`                  | List what would be generated, write nothing               |
| `--queue`                    | Dispatch one `GenerateResponsiveImageJob` per image       |

Run the command in an interactive terminal without options and it asks for the missing values: run mode (process now / dry run / queue), source disk, scope (whole directory or a single file), directory, widths, output formats and whether to regenerate existing variants. Any option passed on the command line is not asked again — and `--no-interaction` disables prompting entirely (CI, scripts).

Already-generated images are **skipped** on incremental runs (unless `--force`), and a summary is always written to `images-log.json` on the target disk:

```json
{
    "images_generated": {
        "images/photo.jpg": ["photo.jpg", "photo.webp", "photo@880.jpg"]
    },
    "images_skipped": { "images/icon.png": ["already generated"] },
    "images_in_error": { "images/broken.png": ["Unable to decode input"] }
}
```

### Queue mode

`--queue` hands the work to your queue — one `GenerateResponsiveImageJob` per image (each job resolves the file, generates its variants and writes its entry in the JSON log). Failures bubble up to Laravel's failed-jobs handling.

### Cleaning up stale variants

```bash
php artisan responsive-image-craft:clean        # delete variants whose source is gone
php artisan responsive-image-craft:clean --dry-run
```

Interactively, it asks for the source/target disks and directory, lists the stale files and asks for confirmation before deleting anything.

### Events

```php
Infernalmedia\ResponsiveImageCraft\Events\ResponsiveImageGenerated;        // file, generated[]
Infernalmedia\ResponsiveImageCraft\Events\ResponsiveImageGenerationFailed; // file, exception
```

## Displaying images

```blade
<x-infernal-responsive-img
    src="images/photo.jpg"
    alt="A photo"
    class="my-img"
    loading="lazy"
    decoding="async"
    sizes="(min-width: 1200px) 50vw, 100vw"
    fetch-priority="high"
    img-attributes='data-zoom="true" itemprop="image"'
/>
```

Attributes accepted: `alt`, `loading`, `decoding`, `sizes`, `width`, `height`, `fetch-priority`, `img-attributes`, `skip-responsive`, plus any attribute (merged onto `<img>`).

Renders:

```html
<picture class="img-container">
    <source
        type="image/webp"
        srcset="https://…/photo@320.webp 320w, …"
        sizes="…"
    />
    …
    <img
        loading="lazy"
        decoding="async"
        fetchpriority="high"
        class="my-img"
        src="https://…/photo.jpg"
        alt="A photo"
        width="800"
        height="600"
        data-zoom="true"
        …
    />
</picture>
```

`width`/`height` are **detected automatically on local source disks** when omitted — the `sizes` attribute then defaults to the matching `Npx` breakpoints. On remote disks they stay unset and the full size list is used. With `skip-responsive` (or `enabled=false`) a plain `<img>` pointing at the source disk is rendered.

### CSS variables

```php
$vars = \ResponsiveImageCraft::getCssVariables('images/photo.jpg');
// ['--image-320px' => 'url(https://…/photo@320.jpg)', …, '--image-original' => 'url(…)']
```

Each entry is prefixed with the **target disk `url`** (source disk when `enabled=false`), so it can be inlined on any element and picked up by the SCSS mixins.

## SCSS helpers

Import the helpers published by the package:

```scss
@use "responsive-image-craft/responsive-background-images" as ri;
```

`ri.background-image()` emits a `background-image` for each `--image-{size}px` variable, inside the matching `min-width` media query:

```scss
.hero {
    @include ri.background-image;
}
```

## Testing

```bash
composer test        # Pest
composer analyse     # PHPStan (level 8)
composer format      # Pint
composer test-coverage
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Credits

- [Infernal Media](https://infernal.media)
- [Guillaume Ernst](https://github.com/guillaume-infernal)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
