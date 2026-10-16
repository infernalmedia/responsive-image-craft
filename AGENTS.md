# AGENTS

Guidance for coding agents working on `infernalmedia/responsive-image-craft`.

## Project overview

Laravel package that generates responsive image variants (configured sizes × formats) with an Artisan command, then displays them through a Blade component, a `getCssVariables()` facade and publishable SCSS mixins.

- PHP `^8.4`, Laravel `^12|^13` (`illuminate/contracts`), PSR-4 namespace `Infernalmedia\ResponsiveImageCraft`
- Built on `spatie/laravel-package-tools`, `spatie/image`, `spatie/image-optimizer`, `spatie/temporary-directory`
- Tests: Pest 5 (+ arch plugin) on Orchestra Testbench 10–11
- Static analysis: Larastan/PHPStan level 8 — Code style: Laravel Pint

## Setup

```bash
composer install
```

There is no frontend build step: `resources/js` and `resources/scss` are publishable assets for host apps, not compiled in this repo.

## Commands

| Task                | Command                                |
| ------------------- | -------------------------------------- |
| Run tests           | `composer test` (`vendor/bin/pest`)    |
| Tests with coverage | `composer test-coverage`               |
| Static analysis     | `composer analyse` (phpstan.neon.dist) |
| Fix code style      | `composer format` (`vendor/bin/pint`)  |

## Conventions

- All code, comments and documentation in English.
- PSR-4 autoloading: `src/` maps to `Infernalmedia\ResponsiveImageCraft\`, `tests/` to `Infernalmedia\ResponsiveImageCraft\Tests\`.
- Tests use Pest syntax (`test()`/`it()`/`expect()`), not PHPUnit test classes. `tests/TestCase.php` extends Orchestra Testbench and registers the package service provider.
- `tests/ArchTest.php` forbids `dd`, `dump` and `ray` — never use them.
- Run `composer format` before committing; Pint uses its default Laravel preset (no config file).
- `composer analyse` must pass: PHPStan level 8 over `src/`, `config/` and `tests/`.
- Commit messages follow Conventional Commits in English, e.g. `feat:`, `fix(scope):`, `chore:` (see git history).
- Every tunable lives in `config/responsive-image-craft.php`; source/target disks, directories and sizes have env fallbacks. v1 config keys (`enabled`, `output_formats`, `skip_formats`, `ignored_extensions`, `ignored_filenames`, `input_formats`) are read through `Support/PackageConfig`; `Support/LegacyConfig` maps old 0.x keys when a published config still uses them.
- Generated variants are named `filename@{width}.{ext}`; the `filename_spacer` (`@`) is shared by `SrcsetGenerator`, the component, the facade and the SCSS (`$filenameSpacer`) — keep them consistent.
- `composer test` must stay at 100% line coverage (Pest `--coverage`).

## Repository layout

- `src/` — service provider, `Commands/` (generate + clean), `Jobs/`, `Support/` (PackageConfig, LegacyConfig, DiskUrlResolver, SourceImageResolver, GenerationOptions, SrcsetGenerator, ImageProcessor), `Events/`, core class, `ImageInfoFromString`, view component, facade, exception
- `config/responsive-image-craft.php` — package configuration
- `resources/views/` — Blade component template
- `resources/scss/` — publishable mixins and helper functions
- `resources/js/` — `AssetsScssFile.js` Vite helper
- `tests/` — Pest suite (`Pest.php` binds `TestCase` to the directory)

## CI (GitHub Actions)

- `run-tests.yml`: Pest on ubuntu + windows, PHP 8.4/8.5 × Laravel 12/13, prefer-lowest/prefer-stable
- `phpstan.yml`: static analysis
- `fix-php-code-style-issues.yml`: runs Pint and auto-commits fixes
- `update-changelog.yml`, `dependabot-auto-merge.yml`: release/dependency automation
