<?php

namespace Infernalmedia\ResponsiveImageCraft;

use Infernalmedia\ResponsiveImageCraft\Commands\GenerateResponsiveImages;
use Infernalmedia\ResponsiveImageCraft\Components\ResponsiveImg;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\LaravelPackageTools\Commands\InstallCommand;

class ResponsiveImageCraftServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('responsive-image-craft')
            ->hasConfigFile()
            ->hasViewComponent('infernal', ResponsiveImg::class)
            ->hasCommand(GenerateResponsiveImages::class)
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->endWith(function (InstallCommand $command) {
                        $command->info(
                            "After setting scss directory in 'config/responsive-image-craft.php'
                            run php artisan vendor:publish --tag=responsive-image-craft-scss"
                        );
                    });
            });
    }

    public function packageBooted(): void
    {
        $this->publishes([
            $this->package->basePath('../resources/dist')
            => config('responsive-image-craft.scss_path') ?? ressource_path("vendor/{$this->package->shortName()}"),
        ], "{$this->package->shortName()}-scss");
    }
}
