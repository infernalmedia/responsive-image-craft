<?php

namespace Infernalmedia\ResponsiveImageCraft\Support;

use Infernalmedia\ResponsiveImageCraft\ImageInfoFromString;

/**
 * Builds `srcset` attribute values for generated responsive images, shared
 * between the Blade component and the ResponsiveImageCraft facade.
 */
final class SrcsetGenerator
{
    /**
     * @param  int[]  $sizes
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly array $sizes,
        private readonly string $spacer = '@'
    ) {}

    /**
     * Builds the srcset entries for one extension:
     * `{base}/{path}/{name}{spacer}{size}.{ext} {size}w, ..., {base}/{path}/{name}.{ext} {max}w`
     */
    public function generate(ImageInfoFromString $image, string $extension, ?int $maxWidth = null): string
    {
        $entries = [];

        foreach ($this->filteredSizes($maxWidth) as $size) {
            $entries[] = $this->variantUrl($image, $extension, $size)." {$size}w";
        }

        $fullWidth = $maxWidth ?? (empty($this->sizes) ? 0 : max($this->sizes));
        $entries[] = $this->variantUrl($image, $extension)." {$fullWidth}w";

        return implode(', ', $entries);
    }

    /**
     * URL of a generated variant, or of the converted original when
     * `$size` is null.
     */
    public function variantUrl(ImageInfoFromString $image, string $extension, ?int $size = null): string
    {
        $relativePath = $image->getRelativePath();
        $base = "{$this->baseUrl}".($relativePath === '' ? '' : "/{$relativePath}");
        $suffix = $size === null ? '' : "{$this->spacer}{$size}";

        return "{$base}/{$image->getFilenameWithoutExtension()}{$suffix}.{$extension}";
    }

    /**
     * @return int[]
     */
    private function filteredSizes(?int $maxWidth): array
    {
        if ($maxWidth === null) {
            return $this->sizes;
        }

        return array_values(array_filter(
            $this->sizes,
            fn (int $size) => $size <= $maxWidth
        ));
    }
}
