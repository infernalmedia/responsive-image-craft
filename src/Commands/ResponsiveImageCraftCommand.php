<?php

namespace Infernalmedia\ResponsiveImageCraft\Commands;

use Illuminate\Console\Command;

class ResponsiveImageCraftCommand extends Command
{
    public $signature = 'responsive-image-craft';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
