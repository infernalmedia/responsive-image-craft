<?php

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Infernalmedia\ResponsiveImageCraft\Events\ResponsiveImageGenerationFailed;
use Infernalmedia\ResponsiveImageCraft\Jobs\GenerateResponsiveImageJob;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('target');
    $this->seedFixtureImage();
});

it('generates all expected variants on the target disk', function () {
    $this->artisan('responsive-image-craft:generate')->assertSuccessful();

    Storage::disk('target')->assertExists('images/photo.jpg');
    Storage::disk('target')->assertExists('images/photo.webp');
    Storage::disk('target')->assertExists('images/photo.avif');
    Storage::disk('target')->assertExists('images/photo@50.jpg');
    Storage::disk('target')->assertExists('images/photo@100.webp');
    Storage::disk('target')->assertMissing('images/photo.png');
});

it('writes the images-log.json report', function () {
    $this->artisan('responsive-image-craft:generate')->assertSuccessful();

    Storage::disk('target')->assertExists('images/images-log.json');

    $log = json_decode(Storage::disk('target')->get('images/images-log.json'), true);

    expect($log['generated_on'])->not->toBeNull()
        ->and($log['generated'])->toHaveKey('images/photo.jpg');
});

it('succeeds with a warning when no image is found', function () {
    Storage::fake('empty-public');
    config()->set('filesystems.disks.empty-public', ['driver' => 'local', 'root' => storage_path('framework/testing/disks/empty-public')]);

    $this->artisan('responsive-image-craft:generate', ['--source-disk' => 'empty-public'])
        ->expectsOutputToContain('No supported image found')
        ->assertSuccessful();
});

it('skips fully generated images on the second run', function () {
    $this->artisan('responsive-image-craft:generate')->assertSuccessful();

    $this->artisan('responsive-image-craft:generate')
        ->expectsOutputToContain('1 images skipped')
        ->assertSuccessful();
});

it('regenerates with --force', function () {
    $this->artisan('responsive-image-craft:generate')->assertSuccessful();
    Storage::disk('target')->assertExists('images/photo@50.jpg');

    Storage::disk('target')->delete('images/photo@50.jpg');

    $this->artisan('responsive-image-craft:generate')
        ->expectsOutputToContain('1 images skipped')
        ->assertSuccessful();

    // The variant is still missing because only full-size existence is checked
    Storage::disk('target')->assertMissing('images/photo@50.jpg');

    $this->artisan('responsive-image-craft:generate', ['--force' => true])->assertSuccessful();
    Storage::disk('target')->assertExists('images/photo@50.jpg');
});

it('processes a single file with --path', function () {
    $this->seedFixtureImage('public', 'other/second.jpg');

    $this->artisan('responsive-image-craft:generate', ['--path' => 'other/second.jpg'])
        ->assertSuccessful();

    Storage::disk('target')->assertExists('other/second.jpg');
    Storage::disk('target')->assertMissing('images/photo.jpg');
});

it('honours --sizes and --extensions overrides', function () {
    $this->artisan('responsive-image-craft:generate', [
        '--sizes' => '75',
        '--extensions' => 'webp',
    ])->assertSuccessful();

    Storage::disk('target')->assertExists('images/photo@75.webp');
    Storage::disk('target')->assertMissing('images/photo@50.jpg');
});

it('lists planned files without writing on --dry-run', function () {
    $this->artisan('responsive-image-craft:generate', ['--dry-run' => true])
        ->expectsOutputToContain('images/photo@50.jpg')
        ->expectsOutputToContain('1 images would be processed')
        ->assertSuccessful();

    Storage::disk('target')->assertMissing('images/photo.jpg');
    Storage::disk('target')->assertMissing('images/images-log.json');
});

it('dispatches jobs with --queue', function () {
    Bus::fake();

    $this->artisan('responsive-image-craft:generate', ['--queue' => 'images'])
        ->assertSuccessful();

    Bus::assertDispatched(
        GenerateResponsiveImageJob::class,
        fn (GenerateResponsiveImageJob $job) => $job->file === 'images/photo.jpg' && $job->queue === 'images'
    );

    Storage::disk('target')->assertMissing('images/photo.jpg');
});

it('uses the source disk given by --source-disk for both listing and reading', function () {
    config()->set('filesystems.disks.alt-source', [
        'driver' => 'local',
        'root' => storage_path('framework/testing/disks/alt-source'),
        'url' => 'https://alt.test',
    ]);
    Storage::fake('alt-source');
    $this->seedFixtureImage('alt-source', 'media/pic.jpg');

    $this->artisan('responsive-image-craft:generate', [
        '--source-disk' => 'alt-source',
        '--relative-source-path' => 'media',
    ])->assertSuccessful();

    Storage::disk('target')->assertExists('media/pic.jpg');
});

it('logs errors and fails when an image cannot be processed', function () {
    Event::fake();
    Storage::disk('public')->put('images/broken.jpg', 'not-an-image');

    $this->artisan('responsive-image-craft:generate')->assertFailed();

    $log = json_decode(Storage::disk('target')->get('images/images-log.json'), true);
    expect($log['errors'])->toHaveKey('images/broken.jpg');

    Event::assertDispatched(
        ResponsiveImageGenerationFailed::class,
        fn (ResponsiveImageGenerationFailed $event) => $event->file === 'images/broken.jpg'
    );
});

it('keeps processing when the target disk cannot be listed', function () {
    $manager = app('filesystem');

    $target = Mockery::mock(FilesystemAdapter::class);
    $target->shouldReceive('allFiles')->andThrow(new RuntimeException('cannot list'));
    $target->shouldReceive('putFileAs')->andReturn('/path');
    $target->shouldReceive('put')->andReturnTrue();

    Storage::shouldReceive('disk')->andReturnUsing(
        fn (?string $name = null) => $name === 'target' ? $target : $manager->disk($name)
    );

    $this->artisan('responsive-image-craft:generate')->assertSuccessful();
});

it('lists full size paths on --dry-run for remote source disks', function () {
    config()->set('filesystems.disks.remote', ['driver' => 's3']);
    config()->set('responsive-image-craft.source_disk', 'remote');

    $manager = app('filesystem');

    $adapter = Mockery::mock(FilesystemAdapter::class);
    $adapter->shouldReceive('allFiles')->with('images')->andReturn(['images/photo.jpg']);

    Storage::shouldReceive('disk')->andReturnUsing(
        fn (?string $name = null) => $name === 'remote' ? $adapter : $manager->disk($name)
    );

    $this->artisan('responsive-image-craft:generate', ['--dry-run' => true])
        ->expectsOutputToContain('images/photo.jpg')
        ->assertSuccessful();
});

it('runs the package install command', function () {
    $this->artisan('responsive-image-craft:install')->assertSuccessful();

    $published = config_path('responsive-image-craft.php');

    try {
        expect(file_exists($published))->toBeTrue();
    } finally {
        @unlink($published);
    }
});
