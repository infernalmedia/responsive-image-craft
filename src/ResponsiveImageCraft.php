<?php

namespace Infernalmedia\ResponsiveImageCraft;

class ResponsiveImageCraft
{
    /**
     * The function `getCssVariables` generates CSS variables for a given image file and its resized
     * versions based on the maximum width provided.
     * The variable is intended to be used in the `background-image` css property.
     *
     * @param string file The file parameter is a string that represents the path to an image file.
     * @param int maxWidth The  parameter is an integer that represents the maximum width of the
     * image. It is used to filter the available image sizes and generate CSS variables for each size that
     * is smaller or equal to the maximum width.
     * @param array extensions An array of file extensions (e.g., ['jpg', 'avif', 'webp']) that will be
     * used to generate CSS variables for different image formats.
     *
     * @return string a string containing CSS variables.
     */
    public function getCssVariables(string $file, int $maxWidth, array $extensions = ['jpg', 'avif', 'webp']): string
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

    /**
     * The function generates CSS variables for a given path, array of extensions, and width.
     *
     * @param string path The path parameter is a string that represents the base path or URL of the image
     * file.
     * @param array extensions An array of file extensions (e.g., ['jpg', 'png', 'svg']).
     * @param string width The `` parameter can be either a string or an integer. It represents the
     * width of an element in CSS.
     *
     * @return string a string containing CSS variables.
     */
    private function generateCssVariables(string $path, array $extensions, string|int $width): string
    {
        $cssVariables = '';
        foreach ($extensions as $extension) {
            $cssVariables .= "--{$extension}-{$width}:url($path.$extension);";
        }

        return $cssVariables;
    }
}
