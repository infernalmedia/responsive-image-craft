<?php

namespace Infernalmedia\ResponsiveImageCraft\Exceptions;

use Exception;

final class InvalidDiskException extends Exception
{
    public static function urlIsMissing(): self
    {
        return new self('The selected disk does not have a defined url.');
    }

    public static function urlIsNotSet(): self
    {
        return new self('The selected disk url is empty');
    }
}
