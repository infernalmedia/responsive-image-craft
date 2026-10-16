# Architecture

Overview of how `responsive-image-craft` is organized and how images flow from a source disk to the browser.

## Layout

```
config/responsive-image-craft.php           Package configuration (env-driven)
src/
  ResponsiveImageCraftServiceProvider.php   Package wiring (spatie/laravel-package-tools)
  ResponsiveImageCraft.php                  Core class: getCssVariables()
  ImageInfoFromString.php                   Path parsing + filtering rules (disk-aware)
  Commands/GenerateResponsiveImages.php     Artisan command (orchestration only)
  Commands/CleanResponsiveImages.php        `responsive-image-craft:clean`
  Jobs/GenerateResponsiveImageJob.php       Per-image job for --queue
  Events/ResponsiveImageGenerated.php       Success event
  Events/ResponsiveImageGenerationFailed.php Failure event
  Support/PackageConfig.php                 Typed accessors over config
  Support/LegacyConfig.php                  0.x config key → v1 key fallback
  Support/DiskUrlResolver.php               Disk url + local-driver helpers
  Support/SourceImageResolver.php           Local path or remote → temp copy
  Support/GenerationOptions.php             Normalized generation settings
  Support/SrcsetGenerator.php               Variant names, urls and srcset
  Support/ImageProcessor.php                Optimize/convert/resize pipeline
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

The package has three pillars: **generation** (Artisan command + `ImageProcessor`), **rendering** (Blade component) and **CSS variables + SCSS** (facade and publishable assets).

## 1. Generation — `responsive-image-craft:generate`

`src/Commands/GenerateResponsiveImages.php` orchestrates; `Support/ImageProcessor` does the work. When run interactively, missing values are asked through Laravel Prompts (`select`/`text`/`confirm`) — every option still overrides its prompt, and `--no-interaction` disables prompting:

1. Build a `GenerationOptions` from `PackageConfig`, then apply CLI overrides or interactive answers (`--sizes`, `--extensions`, `--force`, `--dry-run`, `--queue`, `--path`, `--relative-source-path`). `--source-disk` overrides the disk used for **both** listing and reading.
2. List files on the resolved source disk (`allFiles`, or the single `--path`), wrap each in `ImageInfoFromString` and keep only supported files:
    - extension is in `input_formats`
    - extension is not in `ignored_extensions`
    - filename is not in `ignored_filenames`
3. Skip already-generated images (incremental mode): a file is fully generated when every expected full-size output exists on the target disk — unless `--force`.
4. For each accepted image:
    - `SourceImageResolver` returns the local path directly for local disks, or streams remote files into a `TemporaryDirectory`
    - `ImageProcessor` optimizes the original once (unless `optimize=false`), then decodes the source **once per output format** and resizes descending through `sizes <= width`, saving `filename{spacer}{width}.{ext}` — all stored publicly on `target_disk`
5. `--queue` dispatches one `GenerateResponsiveImageJob` per file instead; each job re-runs steps 4 and writes its own log entry.
6. Write `images-log.json` (timestamp, per-file generated variants, per-file errors, skipped files) into `target_directory` on `target_disk`.

Failures are caught per file, logged, echoed and surfaced through `ResponsiveImageGenerationFailed`; successes dispatch `ResponsiveImageGenerated`. `responsive-image-craft:clean` lists `target_directory`, keeps originals and variants whose source still exists, and deletes the rest (`--dry-run` supported).

## 2. Rendering — `<x-infernal-responsive-img>`

`src/View/Components/ResponsiveImg.php` is registered with the `infernal` prefix by the service provider and renders `resources/views/components/responsive-img.blade.php`.

- Base URL resolution: `filesystems.disks.{target_disk}.url` when `enabled` is true, otherwise `source_disk`. Throws `InvalidDiskException` when the key is missing or empty.
- When `width`/`height` are omitted, they are detected via `getimagesize()` on local source disks only (remote disks leave them unset).
- `SrcsetGenerator` builds `{base}/{path}/{name}{spacer}{size}.{ext} {size}w` entries for each filtered size (`sizes <= width`, or all sizes when no `width` is given) and appends the original at `{width}w`.
- The template emits one `<source type="{mime}" srcset sizes>` per output format (config `output_formats` minus `skip_formats` minus formats without a known mime type), then an `<img>` fallback with `alt` (falls back to `app.name`), `loading` (`lazy`/`eager`), `decoding` (`async`/`auto`), `fetchpriority` (`high`/`low`/`auto`), `width`/`height`, `sizes` and raw `img-attributes`.
- `skip-responsive` renders a bare `<img>`; all extra attributes are merged onto `<img>` in both modes; `container-class` augments the configured wrapper class (`container_css_class_name`, default `img-container`).

## 3. CSS variables + SCSS

- `ResponsiveImageCraft::getCssVariables($file, $maxWidth, $extensions)` — exposed through the `ResponsiveImageCraft` facade — returns inline CSS custom properties: `--{ext}-full:url(...)` plus one `--{ext}-{width}:url(...)` per configured size `<= $maxWidth`. Paths are prefixed with the **target** disk `url` when `enabled` (source disk otherwise); absolute URLs are left untouched.
- `resources/scss/_responsive-background-images.scss` provides:
    - `media-query($width, $type)` — min/max media query helper
    - `responsive-background-image-from-existing-css-var($sizes, $extensions, $full)` — consumes the generated `--{ext}-{size}` variables; the `full` rules are emitted first so sized `max-width` media queries can override them
    - `responsive-background-image($base-relative-path, $extensions, $breakpoints, $file-name-spacer)` — builds variant urls directly; same original-first ordering
    - the `.img-container` styles used by the Blade component
    - both mixins emit breakpoints in list order — callers pass them largest to smallest so the smallest matching variant wins the `max-width` cascade
- `resources/scss/_assets-url-helper.scss` provides pure functions: `construct-image-url`, `construct-assets-url`, `generate-image-urls`, `generate-image-variants`, `generate-image-set`, `get-extension`, `get-mime-type`, `get-mime-type-from-url`, `remove-extension`. MIME types are emitted through `type("#{get-mime-type(...)}")` interpolation — plain `type(fn())` would leak the unevaluated Sass call into the CSS.
- `resources/js/AssetsScssFile.js` is imported in the host app's `vite.config.js`; `createSCSSFile()` writes `resources/scss/_assets.scss` containing `$assetsUrl` and `$filenameSpacer` from env (`VITE_ASSETS_URL`, `VITE_RESPONSIVE_IMAGES_FILENAME_SPACER`), with a configurable destination path. Both SCSS files `@use "../assets"` to read them.
- SCSS and JS folders are published together via `php artisan vendor:publish --tag=responsive-image-craft-scss` into `scss_path` and `js_path`.

## Service provider

`ResponsiveImageCraftServiceProvider` extends `PackageServiceProvider` and declares: the config file, views, the `ResponsiveImg` component (`infernal` prefix), the `GenerateResponsiveImages` + `CleanResponsiveImages` commands, and an install command that publishes the config. `packageBooted()` additionally registers the SCSS/JS publish group with configurable target paths (`scss_path`, `js_path`).

## Data flow

```
source disk (source_directory/)
        │  php artisan responsive-image-craft:generate
        │  (local: path() · remote: stream → TemporaryDirectory)
        ▼
ImageInfoFromString filtering ──► ImageProcessor
                                  ├─ spatie/image-optimizer (original, once)
                                  └─ spatie/image: 1 decode per format,
                                     sizes resized descending
        │  stored publicly · skipped when already generated (--force overrides)
        ▼
target disk   {name}@{width}.{ext}  +  {name}.{ext}  +  images-log.json
        │
        ├─► <x-infernal-responsive-img> ──► <picture><source srcset>…<img srcset>
        └─► ResponsiveImageCraft::getCssVariables() ──► --ext-width vars
                  └─► SCSS mixins ──► background-image / image-set() media queries
```

## Configuration reference

| Key                        | Env                                  | Default                            | Purpose                                       |
| -------------------------- | ------------------------------------ | ---------------------------------- | --------------------------------------------- |
| `enabled`                  | `USE_RESPONSIVE_IMAGES`              | `true`                             | Toggle srcset / CSS vars vs. original image   |
| `source_disk`              | `RESPONSIVE_IMAGES_SOURCE_DISK`      | `public`                           | Disk scanned by the command                   |
| `target_disk`              | `RESPONSIVE_IMAGES_TARGET_DISK`      | `s3`                               | Disk receiving generated files                |
| `source_directory`         | `RESPONSIVE_IMAGES_SOURCE_DIRECTORY` | `images`                           | Root directory scanned                        |
| `target_directory`         | `RESPONSIVE_IMAGES_TARGET_DIRECTORY` | `images`                           | Directory for `images-log.json`               |
| `sizes`                    | `RESPONSIVE_IMAGES_SIZES`            | `320,640,880,1024,1200,1760,2100`  | Widths to generate (comma-separated)          |
| `output_formats`           | —                                    | `[jpg, png, avif, webp]`           | Output formats                                |
| `skip_formats`             | —                                    | `jpg→[png]`, `png→[jpg]`           | Output formats skipped per source extension   |
| `ignored_extensions`       | —                                    | `[svg]`                            | Source extensions never processed             |
| `ignored_filenames`        | —                                    | `[favicon]`                        | Filenames never processed                     |
| `input_formats`            | —                                    | `[jpg, webp, png, avif, gif]`      | Accepted source formats                       |
| `filename_spacer`          | —                                    | `@`                                | Separator before width in generated filenames |
| `container_css_class_name` | —                                    | `img-container`                    | Wrapper class used by the Blade component     |
| `optimize`                 | `RESPONSIVE_IMAGES_OPTIMIZE`         | `true`                             | Run the optimizer chain on generated files    |
| `quality`                  | —                                    | `[]`                               | Compression quality per output format         |
| `scss_path`                | —                                    | `resource_path('/scss/utilities')` | Publish target for SCSS files                 |
| `js_path`                  | —                                    | `resource_path('/js')`             | Publish target for JS files                   |

0.x key names (`use_responsive_images`, `extensions`, `extensions_filters_rules`, `extensions_to_ignore`, `filename_to_ignore`, `supported_file_extensions`) still work in published configs — `Support/LegacyConfig` translates them.

## Naming convention

`{name}{filename_spacer}{width}.{ext}` — e.g. `hero@1200.avif` with the default `@` spacer. The spacer is shared by the generation command, the Blade component, `getCssVariables()` and the SCSS `$filenameSpacer` variable, so changing it requires keeping all consumers in sync.
