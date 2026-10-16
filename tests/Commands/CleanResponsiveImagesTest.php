<?php

use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('target');
    $this->seedFixtureImage();
});

it('deletes variants whose source no longer exists', function () {
    Storage::disk('target')->put('images/photo@50.webp', 'x');
    Storage::disk('target')->put('images/gone@100.jpg', 'x');
    Storage::disk('target')->put('images/gone.webp', 'x');
    Storage::disk('target')->put('images/images-log.json', '{}');

    $this->artisan('responsive-image-craft:clean')->assertSuccessful();

    Storage::disk('target')->assertExists('images/photo@50.webp');
    Storage::disk('target')->assertMissing('images/gone@100.jpg');
    Storage::disk('target')->assertMissing('images/gone.webp');
    Storage::disk('target')->assertExists('images/images-log.json');
});

it('lists stale files without deleting on --dry-run', function () {
    Storage::disk('target')->put('images/gone@100.jpg', 'x');

    $this->artisan('responsive-image-craft:clean', ['--dry-run' => true])
        ->expectsOutputToContain('images/gone@100.jpg')
        ->expectsOutputToContain('would be deleted')
        ->assertSuccessful();

    Storage::disk('target')->assertExists('images/gone@100.jpg');
});

it('reports when nothing is stale', function () {
    Storage::disk('target')->put('images/photo@50.jpg', 'x');

    $this->artisan('responsive-image-craft:clean')
        ->expectsOutputToContain('No stale variant found')
        ->assertSuccessful();
});

it('ignores files that are not images', function () {
    Storage::disk('target')->put('images/notes.txt', 'x');

    $this->artisan('responsive-image-craft:clean')
        ->expectsOutputToContain('No stale variant found')
        ->assertSuccessful();

    Storage::disk('target')->assertExists('images/notes.txt');
});
