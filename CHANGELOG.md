# Changelog

All notable changes to `responsive-image-craft` will be documented in this file.

## v1.0.0 — v1 (modernization)

**Breaking** — see [UPGRADING.md](UPGRADING.md).

- Requirements bumped to PHP `^8.4` and Laravel `^12|^13`; dev tooling upgraded (Pest 5, Larastan 3, Testbench 10–11, PHPStan level 8).
- Configuration keys renamed (`enabled`, `output_formats`, `skip_formats`, `ignored_extensions`, `ignored_filenames`, `input_formats`) with fallback for published 0.x configs.
- New config options: `optimize` (skip the optimizer binaries) and `quality` (per-format compression).

### Fixed

- `--source-disk` is honored for file reading, not just listing.
- `getCssVariables()` prefixes with the target disk `url` (was the source disk).
- Component attributes are merged onto `<img>`; `img-attributes` renders raw instead of escaped.
- Invalid `image/pjpg` MIME type fixed; `<source>` elements without a MIME type are skipped.
- Source images on remote disks can be processed (streamed to a temporary directory).
- Same-extension output no longer overwrites the stored original image.
- SCSS helpers: missing `/` separator between `$assetsUrl` and the relative path in `construct-image-url()`.
- SCSS helpers: `image-set()` entries no longer emit the literal `get-mime-type($extension)` call — the MIME type is interpolated into `type(...)`.
- SCSS helpers: original-image rules are emitted before the `max-width` media queries so variants actually override them (was dead CSS).
- SCSS helpers: one media query per breakpoint instead of one per extension in `responsive-background-image-from-existing-css-var`; the mixin's `$file-name-spacer` argument is now propagated.

### Added

- `--path`, `--force`, `--sizes`, `--extensions`, `--dry-run`, `--queue` options on the generate command; `images:craft` alias.
- `responsive-image-craft:clean` command to delete stale variants.
- `ResponsiveImageGenerated` / `ResponsiveImageGenerationFailed` events.
- Automatic `width`/`height` detection on local source disks.
- `fetch-priority` attribute on the component.

### Changed

- Generation decodes each source once per output format; sizes resize descending.
- Incremental runs skip already-generated images.
- `sizes` config values are normalized to integers.
- SCSS helpers modernized (`map.get`, `string.index`, `math.div`).

## v0.0.10 and earlier

- `responsive-image-craft:generate` command, `<x-infernal-responsive-img>` component, SCSS mixins and CSS-variable facade.
