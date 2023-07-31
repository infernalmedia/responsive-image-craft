<?php

namespace Infernalmedia\ResponsiveImageCraft\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Infernalmedia\ResponsiveImageCraft\ResponsiveImageCraftServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Infernalmedia\\ResponsiveImageCraft\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            ResponsiveImageCraftServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        /*
        $migration = include __DIR__.'/../database/migrations/create_responsive-image-craft_table.php.stub';
        $migration->up();
        */
    }
}
