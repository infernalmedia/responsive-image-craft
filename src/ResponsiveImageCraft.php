<?php

namespace Infernalmedia\ResponsiveImageCraft;

use Infernalmedia\ResponsiveImageCraft\Support\DiskUrlResolver;
use Infernalmedia\ResponsiveImageCraft\Support\PackageConfig;

final class ResponsiveImageCraft
{
    /**
     * Generates CSS variables for a given image and its resized variants,
     * filtered to sizes smaller than or equal to the given maximum width.
     * The variables are meant to feed `background-image` via the published
     * SCSS mixins.
     *
     * When the package is enabled, paths are prefixed with the target disk
     * `url` (where the variants live); otherwise the source disk `url` is
     * used. Absolute URLs are returned untouched.
     *
     * @param  string  $file  Path of the image relative to the disk
     * @param  int  $maxWidth  Only sizes <= this width get a variable
     * @param  string[]  $extensions  Extensions to generate variables for
     * @return string CSS custom properties, e.g. `--jpg-320:url(...);`
     */
    public function getCssVariables(string $file, int $maxWidth, array $extensions = ['jpg', 'avif', 'webp']): string
    {
        $image = new ImageInfoFromString($file);
        $path = $image->getRelativePathnameWithoutExtension();

        if (! filter_var($path, FILTER_VALIDATE_URL)) {
            $disk = PackageConfig::enabled()
                ? PackageConfig::targetDisk()
                : PackageConfig::sourceDisk();

            $path = DiskUrlResolver::baseUrl($disk).'/'.ltrim($path, './');
        }

        $spacer = PackageConfig::filenameSpacer();
        $cssVariables = $this->generateCssVariables($path, $extensions, 'full');

        foreach ($image->getFilteredSizes($maxWidth) as $width) {
            $cssVariables .= $this->generateCssVariables("{$path}{$spacer}{$width}", $extensions, (string) $width);
        }

        return $cssVariables;
    }

    /**
     * @param  string[]  $extensions
     */
    private function generateCssVariables(string $path, array $extensions, string $width): string
    {
        $cssVariables = '';

        foreach ($extensions as $extension) {
            $cssVariables .= "--{$extension}-{$width}:url({$path}.{$extension});";
        }

        return $cssVariables;
    }
}
