<?php

namespace Infernalmedia\ResponsiveImageCraft\Events;

use Illuminate\Foundation\Events\Dispatchable;

final class ResponsiveImageGenerated
{
    use Dispatchable;

    /**
     * @param  array<string, string[]>  $generatedFiles  Map of extension => generated target paths
     */
    public function __construct(
        public readonly string $file,
        public readonly array $generatedFiles = []
    ) {}
}
