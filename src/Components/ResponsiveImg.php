<?php

namespace Infernalmedia\ResponsiveImageCraft\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\View\Component;
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

    /**
     * Create a new component instance.
     */
    public function __construct(
        public string $src,
        public string $alt = '',
        public ?int $width = null,
        public ?int $height = null,
        public array $sizes = [],
        private bool $lazy = true,
        private bool $asyncDecoding = true,
        public bool $skipPictureTag = false,
        private string $containerClass = '',
        public string $imgAttributes = ''
    ) {
        //
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('infernal::components.responsive-img');
    }

    public function getContainerCssClass(): string
    {
        $cssClass = config('responsive-images.container_css_class_name');

        if (!empty($this->containerClass)) {
            return "$cssClass {$this->containerClass}";
        }

        return $cssClass;
    }

    public function getFilteredExtensions(): array
    {
        return array_diff(
            config('responsive-images.extensions'),
            $this->getExcludedExtensions(),
            [$this->getOriginalExtension()]
        );
    }

    public function getImageType(string $extension): string
    {
        if (Arr::exists(self::MIME_TYPES, $extension)) {
            return self::MIME_TYPES[$extension];
        }

        return '';
    }

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

    public function useResponsiveImages(): bool
    {
        return config('responsive-images.use_responsive_images');
    }

    public function getOriginalSrcset(): string
    {
        return $this->getSrcset($this->getOriginalExtension());
    }

    public function getLoading(): string
    {
        return $this->lazy ? 'lazy' : 'eager';
    }

    public function getDecoding(): string
    {
        return $this->asyncDecoding ? 'async' : 'auto';
    }

    public function getAltAttribute(): string
    {
        return empty($this->alt) ? config('app.name') : $this->alt;
    }

    private function getImageInfo(): ImageInfoFromString
    {
        return new ImageInfoFromString($this->src);
    }

    private function getFilteredSizes(): array
    {
        if (!empty($this->width)) {
            return array_filter(config('responsive-images.sizes'), function ($responsiveWidth) {
                return $responsiveWidth <= $this->width;
            });
        }

        return config('responsive-images.sizes');
    }

    private function getOriginalExtension(): string
    {
        return $this->getImageInfo()->getFileExtension();
    }

    private function getExcludedExtensions(): array
    {
        if (Arr::exists(config('responsive-images.extensions_filters_rules', []), $this->getOriginalExtension())) {
            return Arr::get(config('responsive-images.extensions_filters_rules', []), $this->getOriginalExtension());
        }

        return [];
    }

    private function getWidth(): int
    {
        if (empty($this->width)) {
            return max($this->getFilteredSizes());
        }

        return $this->width;
    }

    private function getFilenameSpacer(): string
    {
        return config('responsive-images.filename_spacer');
    }

    public function getUrlBasePath(): string
    {
        return config('filesystems.disks.do.url');
    }
}
