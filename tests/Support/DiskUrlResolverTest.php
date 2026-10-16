<?php

use Infernalmedia\ResponsiveImageCraft\Exceptions\InvalidDiskException;
use Infernalmedia\ResponsiveImageCraft\Support\DiskUrlResolver;

it('returns the disk url without trailing slash', function () {
    config()->set('filesystems.disks.media.url', 'https://cdn.example.com/');

    expect(DiskUrlResolver::baseUrl('media'))->toBe('https://cdn.example.com');
});

it('throws when the disk has no url key', function () {
    config()->set('filesystems.disks.nobase', ['driver' => 'local']);

    DiskUrlResolver::baseUrl('nobase');
})->throws(InvalidDiskException::class, 'does not have a defined url');

it('throws when the disk url is empty', function () {
    config()->set('filesystems.disks.emptyurl', ['driver' => 'local', 'url' => '']);

    DiskUrlResolver::baseUrl('emptyurl');
})->throws(InvalidDiskException::class, 'url is empty');

it('detects local disks', function () {
    config()->set('filesystems.disks.remote', ['driver' => 's3']);

    expect(DiskUrlResolver::isLocal('public'))->toBeTrue()
        ->and(DiskUrlResolver::isLocal('remote'))->toBeFalse()
        ->and(DiskUrlResolver::isLocal('unknown-disk'))->toBeFalse();
});
