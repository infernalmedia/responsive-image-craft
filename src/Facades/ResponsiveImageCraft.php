<?php

namespace Infernalmedia\ResponsiveImageCraft\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string getCssVariables(string $file, int $maxWidth, array $extensions)
 *
 * @see \Infernalmedia\ResponsiveImageCraft\ResponsiveImageCraft
 */
class ResponsiveImageCraft extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Infernalmedia\ResponsiveImageCraft\ResponsiveImageCraft::class;
    }
}
