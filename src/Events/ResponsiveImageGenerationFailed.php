<?php

namespace Infernalmedia\ResponsiveImageCraft\Events;

use Illuminate\Foundation\Events\Dispatchable;

final class ResponsiveImageGenerationFailed
{
    use Dispatchable;

    public function __construct(
        public readonly string $file,
        public readonly string $message
    ) {}
}
