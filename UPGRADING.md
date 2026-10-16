# Upgrading

## From 0.x to v1

v1 is a major release with breaking changes.

### Requirements

- PHP `^8.4` (was `^8.2`)
- Laravel `^12.0` or `^13.0` (was 9–13)
- Dev tooling: Pest 5 / PHPUnit 13, Larastan 3, Testbench 10–11

### Configuration keys renamed

Publish the new config file (`php artisan vendor:publish --tag=responsive-image-craft-config --force`) and migrate your values:

| 0.x key                     | v1 key               |
| --------------------------- | -------------------- |
| `use_responsive_images`     | `enabled`            |
| `extensions`                | `output_formats`     |
| `extensions_filters_rules`  | `skip_formats`       |
| `extensions_to_ignore`      | `ignored_extensions` |
| `filename_to_ignore`        | `ignored_filenames`  |
| `supported_file_extensions` | `input_formats`      |

**Fallback:** an old key still present in a published config takes precedence over the new key's default, so a 0.x config file keeps working — but migrate it, fallback may be removed in a future major. Env var names are unchanged.

New options: `optimize` (set `false` to skip `spatie/image-optimizer` and its binaries) and `quality` (per-format compression map). `sizes` is now normalized to integers.

### Behavior changes

- **`--source-disk` now works end to end** — 0.x only used it for listing files while reading them from the configured disk. Both listing and reading now honor the option (and remote source disks are supported via streaming, no local-path requirement anymore).
- **`getCssVariables()` uses the target disk `url`** when `enabled` is true (previously the source disk), matching the component's behavior. When disabled it now prefixes with the source disk `url` instead of returning a relative path.
- **Component attributes** (`class`, `id`, …) are merged onto `<img>` for both rendering modes — 0.x put them on `<picture>`. `img-attributes` is rendered raw (unescaped).
- **`width`/`height` are auto-detected** on local source disks when omitted; on remote disks they stay unset and `srcset` uses every configured size.
- **Invalid `image/pjpg` MIME type removed** — `pjpg` now maps to `image/jpeg`, and `<source>` elements with an unknown type are skipped.
- **Generation decodes each source once per output format** and sizes are resized descending — significantly fewer image loads per run.
- **Incremental runs**: images whose full-size variants already exist on the target disk are skipped (use `--force` to regenerate).
- Per-image failures return `FAILURE` exit code (still logged to `images-log.json`).

### New in v1

- Command options: `--path` (single file), `--force`, `--sizes`, `--extensions`, `--dry-run`, `--queue` (dispatches `GenerateResponsiveImageJob` per image).
- Command alias: `images:craft`.
- `responsive-image-craft:clean` — deletes target variants whose source no longer exists (`--dry-run` supported).
- Events: `ResponsiveImageGenerated`, `ResponsiveImageGenerationFailed`.
- Component: `fetch-priority` attribute (`high`/`low`/`auto`).

### Deprecated

- Passing `pjpg` as a distinct MIME type.
