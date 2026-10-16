<?php

namespace Infernalmedia\ResponsiveImageCraft\Support;

/**
 * Immutable set of options driving a responsive images generation run.
 * Serializable so it can travel with GenerateResponsiveImageJob.
 */
final readonly class GenerationOptions
{
    /**
     * @param  int[]  $sizes
     * @param  string[]  $extensions
     * @param  array<string, int>  $quality
     */
    public function __construct(
        public string $sourceDisk,
        public string $sourceDirectory,
        public string $targetDisk,
        public string $targetDirectory,
        public array $sizes,
        public array $extensions,
        public array $quality = [],
        public bool $optimize = true,
        public bool $force = false,
        public string $filenameSpacer = '@',
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            sourceDisk: PackageConfig::sourceDisk(),
            sourceDirectory: PackageConfig::sourceDirectory(),
            targetDisk: PackageConfig::targetDisk(),
            targetDirectory: PackageConfig::targetDirectory(),
            sizes: PackageConfig::sizes(),
            extensions: PackageConfig::outputFormats(),
            quality: (array) config('responsive-image-craft.quality', []),
            optimize: PackageConfig::optimize(),
            filenameSpacer: PackageConfig::filenameSpacer(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sourceDisk' => $this->sourceDisk,
            'sourceDirectory' => $this->sourceDirectory,
            'targetDisk' => $this->targetDisk,
            'targetDirectory' => $this->targetDirectory,
            'sizes' => $this->sizes,
            'extensions' => $this->extensions,
            'quality' => $this->quality,
            'optimize' => $this->optimize,
            'force' => $this->force,
            'filenameSpacer' => $this->filenameSpacer,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            sourceDisk: (string) ($data['sourceDisk'] ?? 'public'),
            sourceDirectory: (string) ($data['sourceDirectory'] ?? 'images'),
            targetDisk: (string) ($data['targetDisk'] ?? 's3'),
            targetDirectory: (string) ($data['targetDirectory'] ?? 'images'),
            sizes: array_map('intval', (array) ($data['sizes'] ?? [])),
            extensions: (array) ($data['extensions'] ?? []),
            quality: (array) ($data['quality'] ?? []),
            optimize: (bool) ($data['optimize'] ?? true),
            force: (bool) ($data['force'] ?? false),
            filenameSpacer: (string) ($data['filenameSpacer'] ?? '@'),
        );
    }
}
