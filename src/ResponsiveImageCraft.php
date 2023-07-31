<?php

namespace Infernalmedia\ResponsiveImageCraft;

use Infernalmedia\ResponsiveImageCraft\ImageInfoFromString;

class ResponsiveImageCraft
{
    public function getCssVariables(string $file, int $maxWidth, array $extensions = ['jpg', 'avif', 'webp'])
    {
        $image = new ImageInfoFromString($file);
        $path = $image->getRelativePathnameWithoutExtension();

        $fileNameSpacer = config('responsive-images.filename_spacer');

        $cssVariables = $this->generateCssVariables($path, $extensions, 'full');

        foreach ($image->getFilteredSizes($maxWidth) as $width) {
            $file = $path . $fileNameSpacer . $width;
            $cssVariables .= $this->generateCssVariables($file, $extensions, $width);
        }

        return $cssVariables;
    }

    private function generateCssVariables(string $path, array $extensions, string|int $width): string
    {
        $cssVariables = '';
        foreach ($extensions as $extension) {
            $cssVariables .= "--{$extension}-{$width}:url($path.$extension);";
        }

        return $cssVariables;
    }
}
