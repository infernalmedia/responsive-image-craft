<?php

namespace Infernalmedia\ResponsiveImageCraft\Exceptions;

use Exception;

final class InvalidDiskException extends Exception
{
    public static function urlIsMissing(): self
    {
        return new static('The selected disk does not have a defined url.');
    }
}
