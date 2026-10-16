<?php

namespace Infernalmedia\ResponsiveImageCraft\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string getCssVariables(string $file, int $maxWidth, string[] $extensions)
 *
 * @see \Infernalmedia\ResponsiveImageCraft\ResponsiveImageCraft
 */
final class ResponsiveImageCraft extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Infernalmedia\ResponsiveImageCraft\ResponsiveImageCraft::class;
    }
}
