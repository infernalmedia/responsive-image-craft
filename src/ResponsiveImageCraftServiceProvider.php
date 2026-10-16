<?php

namespace Infernalmedia\ResponsiveImageCraft;

use Infernalmedia\ResponsiveImageCraft\Commands\CleanResponsiveImages;
use Infernalmedia\ResponsiveImageCraft\Commands\GenerateResponsiveImages;
use Infernalmedia\ResponsiveImageCraft\Support\LegacyConfig;
use Infernalmedia\ResponsiveImageCraft\View\Components\ResponsiveImg;
use Override;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class ResponsiveImageCraftServiceProvider extends PackageServiceProvider
{
    #[Override]
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
            ->hasViews()
            ->hasViewComponent('infernal', ResponsiveImg::class)
            ->hasCommands([
                GenerateResponsiveImages::class,
                CleanResponsiveImages::class,
            ])
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

    public function packageRegistered(): void
    {
        LegacyConfig::apply();
    }

    public function packageBooted(): void
    {
        $scssSourcePath = $this->package->basePath('../resources/scss');
        $jsSourcePath = $this->package->basePath('../resources/js');

        $packageShortname = $this->package->shortName();
        $targetFallbackPath = resource_path("vendor/{$packageShortname}");

        $scssTargetPath = config('responsive-image-craft.scss_path') ?? $targetFallbackPath;
        $jsTargetPath = config('responsive-image-craft.js_path') ?? $targetFallbackPath;

        $params = [
            $scssSourcePath => $scssTargetPath,
            $jsSourcePath => $jsTargetPath,
        ];

        $this->publishes($params, "{$packageShortname}-scss");
    }
}
