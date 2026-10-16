<?php

namespace Infernalmedia\ResponsiveImageCraft\Tests;

use Illuminate\Support\Facades\Storage;
use Infernalmedia\ResponsiveImageCraft\ResponsiveImageCraftServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            ResponsiveImageCraftServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        config()->set('filesystems.disks.public.url', 'https://source.test');
        config()->set('filesystems.disks.target', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/target'),
            'url' => 'https://target.test',
            'throw' => false,
        ]);

        config()->set('responsive-image-craft.source_disk', 'public');
        config()->set('responsive-image-craft.target_disk', 'target');
        config()->set('responsive-image-craft.source_directory', 'images');
        config()->set('responsive-image-craft.target_directory', 'images');
        config()->set('responsive-image-craft.sizes', [50, 100, 200]);
        config()->set('responsive-image-craft.output_formats', ['webp', 'avif', 'jpg', 'png']);
        config()->set('responsive-image-craft.skip_formats', [
            'jpg' => ['png'],
            'png' => ['jpg'],
            'webp' => [],
            'avif' => [],
        ]);
        config()->set('responsive-image-craft.input_formats', ['jpg', 'webp', 'png', 'avif', 'gif']);
        config()->set('responsive-image-craft.ignored_extensions', ['svg']);
        config()->set('responsive-image-craft.ignored_filenames', ['favicon']);
        config()->set('responsive-image-craft.optimize', false);
        config()->set('responsive-image-craft.quality', []);
    }

    /**
     * Copies the bundled fixture image onto the given fake disk.
     */
    protected function seedFixtureImage(string $disk = 'public', string $path = 'images/photo.jpg'): void
    {
        Storage::disk($disk)->put(
            $path,
            file_get_contents(__DIR__.'/Fixtures/photo.jpg')
        );
    }
}
