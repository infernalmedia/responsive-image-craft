# Architecture

Overview of how `responsive-image-craft` is organized and how images flow from a source disk to the browser.

## Layout

```
config/responsive-image-craft.php           Package configuration (env-driven)
src/
  ResponsiveImageCraftServiceProvider.php   Package wiring (spatie/laravel-package-tools)
  ResponsiveImageCraft.php                  Core class: getCssVariables()
  ImageInfoFromString.php                   Path parsing + filtering rules
  Commands/GenerateResponsiveImages.php     Artisan command (generation pipeline)
  View/Components/ResponsiveImg.php         Blade component class
  Facades/ResponsiveImageCraft.php          Laravel facade
  Exceptions/InvalidDiskException.php       Disk url validation errors
resources/
  views/components/responsive-img.blade.php <picture> markup template
  scss/_responsive-background-images.scss   background-image mixins
  scss/_assets-url-helper.scss              url / image-set() helper functions
  js/AssetsScssFile.js                      writes _assets.scss from Vite env
tests/                                      Pest tests (Orchestra Testbench)
```

The package has three pillars: **generation** (Artisan command), **rendering** (Blade component) and **CSS variables + SCSS** (facade and publishable assets).

## 1. Generation — `responsive-image-craft:generate`

`src/Commands/GenerateResponsiveImages.php` implements a batch pipeline:

1. List all files on `source_disk` under `source_directory`. Both are overridable per run via `--source-disk=` and `--relative-source-path=`.
2. Wrap each path in an `ImageInfoFromString` value object and keep only supported files:
    - extension is in `supported_file_extensions`
    - extension is not in `extensions_to_ignore`
    - filename is not in `filename_to_ignore`
3. For each accepted image (inside a progress bar, in a fresh `TemporaryDirectory`):
    - optimize the original via `spatie/image-optimizer` and copy it to `target_disk`
    - for each configured `extensions` minus the rules in `extensions_filters_rules` (e.g. a `jpg` source skips `png` output): convert + optimize the original to that format, then for every width in `sizes` that is `<=` the image width, resize with `spatie/image` and save `filename{spacer}{width}.{ext}`
    - store every file publicly on `target_disk`, mirroring the source relative path
4. Write `images-log.json` (timestamp, per-file generated variants, per-file errors) into `target_directory` on `target_disk`.

Failures are caught per file, logged to the JSON report and echoed to the console; the run continues and ends with success/error counters.

## 2. Rendering — `<x-infernal-responsive-img>`

`src/View/Components/ResponsiveImg.php` is registered with the `infernal` prefix by the service provider and renders `resources/views/components/responsive-img.blade.php`.

- Base URL resolution: `filesystems.disks.{target_disk}.url` when `use_responsive_images` is true, otherwise `source_disk`. Throws `InvalidDiskException` when the key is missing or empty.
- `getSrcset($extension)` builds `{base}/{path}/{name}{spacer}{size}.{ext} {size}w` entries for each filtered size (`sizes <= width`, or all sizes when no `width` is given) and appends the original at `{width}w`.
- The template emits one `<source type="{mime}" srcset sizes>` per filtered extension (config `extensions` minus `extensions_filters_rules` minus the original extension), then an `<img>` fallback with `alt` (falls back to `app.name`), `loading` (`lazy`/`eager`), `decoding` (`async`/`auto`), `width`/`height`, `sizes` and free-form `img-attributes`.
- `skip-picture-tag` renders a bare `<img>` and merges extra HTML attributes onto it; `container-class` augments the configured wrapper class (`container_css_class_name`, default `img-container`).

## 3. CSS variables + SCSS

- `ResponsiveImageCraft::getCssVariables($file, $maxWidth, $extensions)` — exposed through the `ResponsiveImageCraft` facade — returns inline CSS custom properties: `--{ext}-full:url(...)` plus one `--{ext}-{width}:url(...)` per configured size `<= $maxWidth`. Paths are prefixed with the disk `url` unless the file is already an absolute URL or `use_responsive_images` is disabled.
- `resources/scss/_responsive-background-images.scss` provides:
    - `media-query($width, $type)` — min/max media query helper
    - `responsive-background-image-from-existing-css-var($sizes, $extensions, $full)` — consumes the generated `--{ext}-{size}` variables
    - `responsive-background-image($base-relative-path, $extensions, $breakpoints, $file-name-spacer)` — builds variant urls directly
    - the `.img-container` styles used by the Blade component
- `resources/scss/_assets-url-helper.scss` provides pure functions: `construct-image-url`, `construct-assets-url`, `generate-image-urls`, `generate-image-variants`, `generate-image-set`, `get-extension`, `get-mime-type`, `remove-extension`.
- `resources/js/AssetsScssFile.js` is imported in the host app's `vite.config.js`; `createSCSSFile()` writes `resources/scss/_assets.scss` containing `$assetsUrl` and `$filenameSpacer` from env (`VITE_ASSETS_URL`, `VITE_RESPONSIVE_IMAGES_FILENAME_SPACER`). Both SCSS files `@use "../assets"` to read them.
- SCSS and JS folders are published together via `php artisan vendor:publish --tag=responsive-image-craft-scss` into `scss_path` and `js_path`.

## Service provider

`ResponsiveImageCraftServiceProvider` extends `PackageServiceProvider` and declares: the config file, views, the `ResponsiveImg` component (`infernal` prefix), the `GenerateResponsiveImages` command, and an install command that publishes the config. `packageBooted()` additionally registers the SCSS/JS publish group with configurable target paths.

## Data flow

```
source disk (source_directory/)
        │  php artisan responsive-image-craft:generate
        ▼
ImageInfoFromString filtering ──► spatie/image-optimizer (original)
                                └► spatie/image format+width per extension/size
        │  files staged in TemporaryDirectory, stored publicly
        ▼
target disk   {name}@{width}.{ext}  +  {name}.{ext}  +  images-log.json
        │
        ├─► <x-infernal-responsive-img> ──► <picture><source srcset>…<img srcset>
        └─► ResponsiveImageCraft::getCssVariables() ──► --ext-width vars
                  └─► SCSS mixins ──► background-image / image-set() media queries
```

## Configuration reference

| Key                         | Env                                  | Default                            | Purpose                                       |
| --------------------------- | ------------------------------------ | ---------------------------------- | --------------------------------------------- |
| `use_responsive_images`     | `USE_RESPONSIVE_IMAGES`              | `true`                             | Toggle srcset / CSS vars vs. original image   |
| `source_disk`               | `RESPONSIVE_IMAGES_SOURCE_DISK`      | `public`                           | Disk scanned by the command                   |
| `target_disk`               | `RESPONSIVE_IMAGES_TARGET_DISK`      | `s3`                               | Disk receiving generated files                |
| `source_directory`          | `RESPONSIVE_IMAGES_SOURCE_DIRECTORY` | `images`                           | Root directory scanned                        |
| `target_directory`          | `RESPONSIVE_IMAGES_TARGET_DIRECTORY` | `images`                           | Directory for `images-log.json`               |
| `sizes`                     | `RESPONSIVE_IMAGES_SIZES`            | `320,640,880,1024,1200,1760,2100`  | Widths to generate (comma-separated)          |
| `extensions`                | —                                    | `[jpg, png, avif, webp]`           | Output formats                                |
| `extensions_filters_rules`  | —                                    | `jpg→[png]`, `png→[jpg]`           | Output formats skipped per source extension   |
| `extensions_to_ignore`      | —                                    | `[svg]`                            | Source extensions never processed             |
| `filename_to_ignore`        | —                                    | `[favicon]`                        | Filenames never processed                     |
| `supported_file_extensions` | —                                    | `[jpg, webp, png, avif, gif]`      | Accepted source formats                       |
| `filename_spacer`           | —                                    | `@`                                | Separator before width in generated filenames |
| `container_css_class_name`  | —                                    | `img-container`                    | Wrapper class used by the Blade component     |
| `scss_path`                 | —                                    | `resource_path('/scss/utilities')` | Publish target for SCSS files                 |
| `js_path`                   | —                                    | `resource_path('/js')`             | Publish target for JS files                   |

## Naming convention

`{name}{filename_spacer}{width}.{ext}` — e.g. `hero@1200.avif` with the default `@` spacer. The spacer is shared by the generation command, the Blade component, `getCssVariables()` and the SCSS `$filenameSpacer` variable, so changing it requires keeping all consumers in sync.
