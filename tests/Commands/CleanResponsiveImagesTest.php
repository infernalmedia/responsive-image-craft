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

    $this->artisan('responsive-image-craft:clean', ['--no-interaction' => true])->assertSuccessful();

    Storage::disk('target')->assertExists('images/photo@50.webp');
    Storage::disk('target')->assertMissing('images/gone@100.jpg');
    Storage::disk('target')->assertMissing('images/gone.webp');
    Storage::disk('target')->assertExists('images/images-log.json');
});

it('lists stale files without deleting on --dry-run', function () {
    Storage::disk('target')->put('images/gone@100.jpg', 'x');

    $this->artisan('responsive-image-craft:clean', ['--no-interaction' => true, '--dry-run' => true])
        ->expectsOutputToContain('images/gone@100.jpg')
        ->expectsOutputToContain('would be deleted')
        ->assertSuccessful();

    Storage::disk('target')->assertExists('images/gone@100.jpg');
});

it('reports when nothing is stale', function () {
    Storage::disk('target')->put('images/photo@50.jpg', 'x');

    $this->artisan('responsive-image-craft:clean', ['--no-interaction' => true])
        ->expectsOutputToContain('No stale variant found')
        ->assertSuccessful();
});

it('ignores files that are not images', function () {
    Storage::disk('target')->put('images/notes.txt', 'x');

    $this->artisan('responsive-image-craft:clean', ['--no-interaction' => true])
        ->expectsOutputToContain('No stale variant found')
        ->assertSuccessful();

    Storage::disk('target')->assertExists('images/notes.txt');
});

it('asks for missing values and confirmation before deleting', function () {
    Storage::disk('target')->put('images/gone@100.jpg', 'x');
    Storage::disk('target')->put('images/gone.webp', 'x');

    $this->artisan('responsive-image-craft:clean')
        ->expectsChoice('Which disk contains the source images?', 'public', ['local', 'public', 's3', 'target'])
        ->expectsChoice('Which disk contains the generated variants?', 'target', ['local', 'public', 's3', 'target'])
        ->expectsQuestion('Which directory on the source disk should be scanned?', 'images')
        ->expectsConfirmation('Delete these 2 stale variants?', 'yes')
        ->assertSuccessful();

    Storage::disk('target')->assertMissing('images/gone@100.jpg');
    Storage::disk('target')->assertMissing('images/gone.webp');
});

it('aborts when the deletion is declined interactively', function () {
    Storage::disk('target')->put('images/gone@100.jpg', 'x');

    $this->artisan('responsive-image-craft:clean')
        ->expectsChoice('Which disk contains the source images?', 'public', ['local', 'public', 's3', 'target'])
        ->expectsChoice('Which disk contains the generated variants?', 'target', ['local', 'public', 's3', 'target'])
        ->expectsQuestion('Which directory on the source disk should be scanned?', 'images')
        ->expectsConfirmation('Delete these 1 stale variants?', 'no')
        ->expectsOutputToContain('Aborted')
        ->assertSuccessful();

    Storage::disk('target')->assertExists('images/gone@100.jpg');
});
