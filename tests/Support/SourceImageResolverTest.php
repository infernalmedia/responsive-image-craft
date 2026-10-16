<?php

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Infernalmedia\ResponsiveImageCraft\ImageInfoFromString;
use Infernalmedia\ResponsiveImageCraft\Support\SourceImageResolver;
use Spatie\TemporaryDirectory\TemporaryDirectory;

it('returns the local path for local disks', function () {
    Storage::fake('public');
    $this->seedFixtureImage();

    $temporaryDirectory = (new TemporaryDirectory)->create();
    $image = new ImageInfoFromString('images/photo.jpg', 'public');

    $path = (new SourceImageResolver($temporaryDirectory))->resolve($image);

    expect($path)->toBe(Storage::disk('public')->path('images/photo.jpg'));

    $temporaryDirectory->delete();
});

it('downloads remote files into the temporary directory', function () {
    config()->set('filesystems.disks.remote', ['driver' => 's3']);

    $adapter = Mockery::mock(FilesystemAdapter::class);
    $adapter->shouldReceive('get')
        ->with('images/photo.jpg')
        ->andReturn('fake-image-bytes');

    Storage::shouldReceive('disk')->with('remote')->andReturn($adapter);

    $temporaryDirectory = (new TemporaryDirectory)->create();
    $image = new ImageInfoFromString('images/photo.jpg', 'remote');

    $path = (new SourceImageResolver($temporaryDirectory))->resolve($image);

    expect($path)->toStartWith($temporaryDirectory->path('source-'))
        ->and(file_get_contents($path))->toBe('fake-image-bytes');

    $temporaryDirectory->delete();
});
