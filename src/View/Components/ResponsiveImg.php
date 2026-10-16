<?php

namespace Infernalmedia\ResponsiveImageCraft\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\View\Component;
use Infernalmedia\ResponsiveImageCraft\ImageInfoFromString;
use Infernalmedia\ResponsiveImageCraft\Support\DiskUrlResolver;
use Infernalmedia\ResponsiveImageCraft\Support\PackageConfig;
use Infernalmedia\ResponsiveImageCraft\Support\SrcsetGenerator;
use Override;
use Throwable;

final class ResponsiveImg extends Component
{
    private const array MIME_TYPES = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'pjpg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'tiff' => 'image/tiff',
    ];

    private const array FETCH_PRIORITIES = ['high', 'low', 'auto'];

    private ?ImageInfoFromString $imageInfo = null;

    private ?SrcsetGenerator $srcsetGenerator = null;

    public function __construct(
        public string $src,
        public string $alt = '',
        public ?int $width = null,
        public ?int $height = null,
        public bool $lazy = true,
        public bool $asyncDecoding = true,
        public bool $skipPictureTag = false,
        public string $containerClass = '',
        public string $imgAttributes = '',
        public ?string $sizes = null,
        public ?string $fetchPriority = null,
    ) {
        $this->detectDimensions();
    }

    #[Override]
    public function render(): View
    {
        return view('responsive-image-craft::components.responsive-img'); // @phpstan-ignore argument.type
    }

    /**
     * Container CSS class: the configured base class plus the optional
     * custom one.
     */
    public function getContainerCssClass(): string
    {
        $cssClass = PackageConfig::containerCssClassName();

        if ($this->containerClass !== '') {
            return "{$cssClass} {$this->containerClass}";
        }

        return $cssClass;
    }

    /**
     * Extra formats rendered as `<source>` elements: configured formats minus
     * the ones skipped for the original extension, minus the original itself.
     *
     * @return string[]
     */
    public function getFilteredExtensions(): array
    {
        return array_values(array_diff(
            PackageConfig::outputFormats(),
            $this->getExcludedExtensions(),
            [$this->getOriginalExtension()]
        ));
    }

    /**
     * MIME type for the given extension, or an empty string when unknown —
     * the template skips `<source>` elements with an empty type.
     */
    public function getImageType(string $extension): string
    {
        return Arr::get(self::MIME_TYPES, $extension, '');
    }

    /**
     * srcset value for the given extension.
     */
    public function getSrcset(string $extension): string
    {
        return $this->srcsetGenerator()->generate($this->getImageInfo(), $extension, $this->getWidth());
    }

    /**
     * Whether responsive variants should be used.
     */
    public function enabled(): bool
    {
        return PackageConfig::enabled();
    }

    /**
     * srcset value for the original image extension.
     */
    public function getOriginalSrcset(): string
    {
        return $this->getSrcset($this->getOriginalExtension());
    }

    /**
     * `lazy` or `eager`. Images in the first contentful paint should be eager.
     *
     * @see https://developer.mozilla.org/docs/Web/API/HTMLImageElement/loading
     */
    public function getLoading(): string
    {
        return $this->lazy ? 'lazy' : 'eager';
    }

    /**
     * `async` or `auto`.
     *
     * @see https://developer.mozilla.org/docs/Web/API/HTMLImageElement/decoding
     */
    public function getDecoding(): string
    {
        return $this->asyncDecoding ? 'async' : 'auto';
    }

    /**
     * `high`, `low` or `auto`, or null when unset/invalid.
     *
     * @see https://developer.mozilla.org/docs/Web/API/HTMLImageElement/fetchPriority
     */
    public function getFetchPriority(): ?string
    {
        return in_array($this->fetchPriority, self::FETCH_PRIORITIES, true)
            ? $this->fetchPriority
            : null;
    }

    /**
     * Alt attribute value; falls back to the app name when empty.
     */
    public function getAltAttribute(): string
    {
        return $this->alt === '' ? (string) config('app.name') : $this->alt;
    }

    /**
     * Base URL of the disk holding the images to display: target disk when
     * responsive images are enabled, source disk otherwise.
     */
    public function getUrlBasePath(): string
    {
        return DiskUrlResolver::baseUrl(
            $this->enabled() ? PackageConfig::targetDisk() : PackageConfig::sourceDisk()
        );
    }

    private function getImageInfo(): ImageInfoFromString
    {
        return $this->imageInfo ??= new ImageInfoFromString($this->src);
    }

    private function getOriginalExtension(): string
    {
        return $this->getImageInfo()->getFileExtension();
    }

    private function srcsetGenerator(): SrcsetGenerator
    {
        return $this->srcsetGenerator ??= new SrcsetGenerator(
            $this->getUrlBasePath(),
            PackageConfig::sizes(),
            PackageConfig::filenameSpacer()
        );
    }

    /**
     * Largest entry width for srcset descriptors: the given width, or the
     * largest configured size when none is provided.
     */
    private function getWidth(): int
    {
        return $this->width ?? (int) max(PackageConfig::sizes() ?: [0]);
    }

    /**
     * @return string[]
     */
    private function getExcludedExtensions(): array
    {
        return Arr::get(PackageConfig::skipFormats(), $this->getOriginalExtension(), []);
    }

    /**
     * Fills width/height from the source image when not provided. Only reads
     * local disks — on remote disks the attributes stay empty.
     */
    private function detectDimensions(): void
    {
        if ($this->width !== null && $this->height !== null) {
            return;
        }

        if (! DiskUrlResolver::isLocal(PackageConfig::sourceDisk())) {
            return;
        }

        try {
            $size = @getimagesize($this->getImageInfo()->getAbsolutePathname());
        } catch (Throwable) {
            return;
        }

        if ($size !== false) {
            $this->width ??= $size[0];
            $this->height ??= $size[1];
        }
    }
}
