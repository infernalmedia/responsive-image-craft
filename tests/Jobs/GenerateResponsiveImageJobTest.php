<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Infernalmedia\ResponsiveImageCraft\Events\ResponsiveImageGenerationFailed;
use Infernalmedia\ResponsiveImageCraft\Jobs\GenerateResponsiveImageJob;
use Infernalmedia\ResponsiveImageCraft\Support\GenerationOptions;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('target');
    $this->seedFixtureImage();
});

it('generates variants for the given file', function () {
    $job = new GenerateResponsiveImageJob(
        'images/photo.jpg',
        GenerationOptions::fromConfig()->toArray()
    );

    $job->handle();

    Storage::disk('target')->assertExists('images/photo.jpg');
    Storage::disk('target')->assertExists('images/photo@50.webp');
});

it('dispatches the failed event and rethrows on error', function () {
    Event::fake();
    Storage::disk('public')->put('images/broken.jpg', 'not-an-image');

    $job = new GenerateResponsiveImageJob(
        'images/broken.jpg',
        GenerationOptions::fromConfig()->toArray()
    );

    try {
        $job->handle();
        $this->fail('The job should have thrown.');
    } catch (Throwable) {
        // expected
    }

    Event::assertDispatched(
        ResponsiveImageGenerationFailed::class,
        fn (ResponsiveImageGenerationFailed $event) => $event->file === 'images/broken.jpg'
    );
});
