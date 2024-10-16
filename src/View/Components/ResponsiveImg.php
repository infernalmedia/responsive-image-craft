<?php

namespace Infernalmedia\ResponsiveImageCraft\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\View\Component;
use Infernalmedia\ResponsiveImageCraft\Exceptions\InvalidDiskException;
use Infernalmedia\ResponsiveImageCraft\ImageInfoFromString;

class ResponsiveImg extends Component
{
    private const MIME_TYPES = [
        'jpg' => 'image/jpeg',
        'pjpg' => 'image/pjpg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'tiff' => 'image/tiff',
    ];

    public function __construct(
        public string $src,
        public string $alt = '',
        public ?int $width = null,
        public ?int $height = null,
        private bool $lazy = true,
        private bool $asyncDecoding = true,
        public bool $skipPictureTag = false,
        private string $containerClass = '',
        public string $imgAttributes = ''
    ) {
        //
    }

    public function render(): View|Closure|string
    {
        return view('responsive-image-craft::components.responsive-img');
    }

    /**
     * The function returns the CSS class name for a container element, combining a default class name with
     * an optional custom class name.
     *
     * @return string a string value.
     */
    public function getContainerCssClass(): string
    {
        $cssClass = config('responsive-image-craft.container_css_class_name');

        if (! empty($this->containerClass)) {
            return "$cssClass {$this->containerClass}";
        }

        return $cssClass;
    }

    /**
     * The function returns an array of filtered extensions by removing excluded extensions and the
     * original extension.
     *
     * @return array an array that contains the filtered extensions.
     */
    public function getFilteredExtensions(): array
    {
        return array_diff(
            config('responsive-image-craft.extensions'),
            $this->getExcludedExtensions(),
            [$this->getOriginalExtension()]
        );
    }

    /**
     * The getImageType function returns the MIME type of an image based on its file extension.
     *
     * @param  string  $extension  A string representing the file extension of an image file.
     * @return string a string value. If the given extension exists in the `MIME_TYPES` array, it will
     *                return the corresponding value from the array. Otherwise, it will return an empty string.
     */
    public function getImageType(string $extension): string
    {
        if (Arr::exists(self::MIME_TYPES, $extension)) {
            return self::MIME_TYPES[$extension];
        }

        return '';
    }

    /**
     * The function `getSrcset` generates a string containing a list of image URLs and their corresponding
     * sizes in the specified extension for use in the `srcset` attribute of an HTML `<img>` or `<source>` tag.
     *
     * @param  string  $extension  The parameter "extension" is a string that represents the file extension of
     *                             the image. It is used to construct the image filenames in the srcset.
     * @return string a string that represents the srcset attribute value for an image.
     */
    public function getSrcset(string $extension): string
    {
        $srcset = '';

        $path = $this->getImageInfo()->getRelativePath();
        $filename = $this->getImageInfo()->getFilenameWithoutExtension();

        foreach ($this->getFilteredSizes() as $size) {
            $name = "$filename{$this->getFilenameSpacer()}$size.$extension";
            $srcset .= "{$this->getUrlBasePath()}/$path/$name {$size}w, ";
        }
        $srcset .= "{$this->getUrlBasePath()}/$path/$filename.$extension {$this->getWidth()}w";

        return trim(Str::beforeLast($srcset, ','));
    }

    /**
     * The function checks if the use of responsive images is enabled in the configuration.
     *
     * @return bool the value of the configuration variable 'responsive-image-craft.use_responsive_images'.
     */
    public function useResponsiveImages(): bool
    {
        return config('responsive-image-craft.use_responsive_images');
    }

    /**
     * The function "getOriginalSrcset" returns the srcset attribute value for the original image
     * extension.
     *
     * @return string a string.
     */
    public function getOriginalSrcset(): string
    {
        return $this->getSrcset($this->getOriginalExtension());
    }

    /**
     * The function returns the loading type, either "lazy" or "eager", based on the value of the "lazy"
     * property.
     * Images in First Contentful Paint should be eager loaded
     *
     * @see https://developer.mozilla.org/docs/Web/API/HTMLImageElement/loading
     *
     * @return string The method is returning a string value. If the `lazy` property is true, it will
     *                return the string 'lazy'. Otherwise, it will return the string 'eager'.
     */
    public function getLoading(): string
    {
        return $this->lazy ? 'lazy' : 'eager';
    }

    /**
     * The function returns the decoding mode, either 'async' or 'auto'.
     *
     * @see https://developer.mozilla.org/docs/Web/API/HTMLImageElement/decoding
     *
     * @return string The method is returning a string value. If the value of the property `asyncDecoding`
     *                is true, then the string 'async' is returned. Otherwise, the string 'auto' is returned.
     */
    public function getDecoding(): string
    {
        return $this->asyncDecoding ? 'async' : 'auto';
    }

    /**
     * The getAltAttribute function returns the alt attribute value, using the app name if the alt
     * attribute is empty.
     *
     * @return string a string value. If the `` property is empty, it will return the value of
     *                `config('app.name')`, otherwise it will return the value of `->alt`.
     */
    public function getAltAttribute(): string
    {
        return empty($this->alt) ? config('app.name') : $this->alt;
    }

    /**
     * The function returns an instance of the ImageInfoFromString class with the source image as a
     * parameter.
     *
     * @return ImageInfoFromString An instance of the `ImageInfoFromString` class is being returned.
     */
    private function getImageInfo(): ImageInfoFromString
    {
        return new ImageInfoFromString($this->src);
    }

    /**
     * The function returns an array of filtered sizes based on the width property, or all sizes if the
     * width is empty.
     *
     * @return array an array.
     */
    private function getFilteredSizes(): array
    {
        if (! empty($this->width)) {
            return array_filter(config('responsive-image-craft.sizes'), function ($responsiveWidth) {
                return $responsiveWidth <= $this->width;
            });
        }

        return config('responsive-image-craft.sizes');
    }

    /**
     * The function "getOriginalExtension" returns the file extension of the image.
     *
     * @return string a string value.
     */
    private function getOriginalExtension(): string
    {
        return $this->getImageInfo()->getFileExtension();
    }

    /**
     * The function returns an array of excluded extensions based on the original extension of the image
     * and the ignoring rules.
     *
     * @return array an array.
     */
    private function getExcludedExtensions(): array
    {
        if (Arr::exists(config('responsive-image-craft.extensions_filters_rules', []), $this->getOriginalExtension())) {
            return Arr::get(
                config('responsive-image-craft.extensions_filters_rules', []),
                $this->getOriginalExtension()
            );
        }

        return [];
    }

    /**
     * The `getWidth` function returns the maximum width value from the filtered sizes, or the stored width
     * value if it is not empty.
     *
     * @return int the value of `->width` if it is not empty. Otherwise, it returns the highest value
     *             from the available image size.
     */
    private function getWidth(): int
    {
        if (empty($this->width)) {
            return max($this->getFilteredSizes());
        }

        return $this->width;
    }

    /**
     * The function returns the value of the 'filename_spacer' configuration option.
     *
     * @return string the value of the configuration variable 'responsive-image-craft.filename_spacer'.
     */
    private function getFilenameSpacer(): string
    {
        return config('responsive-image-craft.filename_spacer');
    }

    /**
     * The function returns the URL base path to image following the `useResponsiveImages` value.
     *
     * @return string the value of the 'url' key in the disk configuration in the 'filesystems'
     *                configuration file.
     */
    public function getUrlBasePath(): string
    {
        $disk = config('filesystems.disks.'.config('responsive-image-craft.source_disk'));

        if ($this->useResponsiveImages()) {
            $disk = config('filesystems.disks.'.config('responsive-image-craft.target_disk'));
        }

        if (! Arr::exists($disk, 'url')) {
            throw InvalidDiskException::urlIsMissing();
        }

        if (empty($disk['url'])) {
            throw InvalidDiskException::urlIsNotSet();
        }

        return $disk['url'];
    }
}
