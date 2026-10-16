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

The package ships two Sass partials for responsive `background-image` usage. Publish them with:

```bash
php artisan vendor:publish --tag=responsive-image-craft-scss
```

The SCSS lands in `scss_path` (`resources/scss/utilities` by default) and `AssetsScssFile.js` in `js_path` (`resources/js`).

### The `_assets.scss` partial

Both helpers `@use "../assets"`, so they expect a `_assets.scss` partial **one directory above** them (`resources/scss/_assets.scss` by default) exposing two variables:

```scss
// resources/scss/_assets.scss
$assetsUrl: "https://cdn.example.com"; // or null for relative URLs
$filenameSpacer: "@"; // must match the filename_spacer config
```

`AssetsScssFile` generates this file at build time from your env vars — call it in `vite.config.js` or a build script:

```js
import { AssetsScssFile } from "./resources/js/AssetsScssFile";

new AssetsScssFile().createSCSSFile(
    new Map([
        ["$assetsUrl", process.env.VITE_ASSETS_URL],
        ["$filenameSpacer", "@"],
    ]),
);
// writes ./resources/scss/_assets.scss — pass a custom path to the
// constructor if your layout differs (one level above the mixins)
```

### `responsive-background-image()`

Builds every variant URL from a relative path — the most straightforward option for static assets:

```scss
@use "utilities/responsive-background-images" as ri;

.card {
    @include ri.responsive-background-image(
        "images/photo.jpg",
        // path relative to $assetsUrl
        (jpg, webp, avif),
        // formats to include in image-set()
        (640, 320) // breakpoints — largest first
    );
}
```

Compiles to (simplified):

```css
.card {
    background-image: url("https://cdn.example.com/images/photo.jpg");
    background-image: image-set(
        url("…/photo.jpg") type("image/jpeg"),
        url("…/photo.webp") type("image/webp"),
        url("…/photo.avif") type("image/avif")
    );
}
@media only screen and (max-width: 639px) {
    .card {
        background-image: url("…/photo@640.jpg");
        background-image: image-set(…);
    }
}
@media only screen and (max-width: 319px) {
    .card {
        background-image: url("…/photo@320.jpg");
        background-image: image-set(…);
    }
}
```

The breakpoints are the generated `name@width.ext` widths — **unitless numbers**, listed **largest to smallest** so the smallest matching variant wins the `max-width` cascade.

### `responsive-background-image-from-existing-css-var()`

Pairs with `ResponsiveImageCraft::getCssVariables()` for dynamic images (CMS-driven, user uploads). Render the variables inline:

```blade
<div class="hero" style="{!! ResponsiveImageCraft::getCssVariables('images/hero.jpg', 880) !!}">
    …
</div>
```

`getCssVariables()` emits `--{ext}-full` and `--{ext}-{width}` custom properties (`--jpg-full:url(…)`, `--webp-640:url(…)`, …). The mixin turns them into media queries:

```scss
.hero {
    @include ri.responsive-background-image-from-existing-css-var(
        (full, 880, 640, 320),
        // full first, sizes largest to smallest
        (jpg, webp),
        // must match the extensions passed in PHP
        full // the marker used for the original image
    );
}
```

```css
.hero {
    background-image: var(--jpg-full);
    background-image: image-set(
        var(--jpg-full) type("image/jpeg"),
        var(--webp-full) type("image/webp")
    );
}
@media only screen and (max-width: 879px) {
    .hero {
        background-image: var(--jpg-880);
        background-image: image-set(…);
    }
}
@media only screen and (max-width: 639px) {
    .hero {
        background-image: var(--jpg-640);
        background-image: image-set(…);
    }
}
```

The **first extension in the list** is the plain `background-image` fallback — put the most compatible format first (e.g. `jpg`).

### `media-query()`

A small helper also used internally — handy on its own:

```scss
@include ri.media-query(640px) { … }      // min-width (default)
@include ri.media-query(640px, max) { … } // max-width: 639px
```

### Lower-level functions

`_assets-url-helper.scss` exposes URL helpers under the same namespace if you need them in your own Sass: `construct-image-url()`, `construct-assets-url()`, `generate-image-urls()`, `generate-image-variants()`, `generate-image-set()`, `get-extension()`, `get-mime-type()`, `get-mime-type-from-url()`, `remove-extension()`.

The partials also emit a `.img-container` utility class (`max-width: 100%` with a responsive nested `img`).

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
