<?php

namespace Infernalmedia\ResponsiveImageCraft\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Infernalmedia\ResponsiveImageCraft\Events\ResponsiveImageGenerationFailed;
use Infernalmedia\ResponsiveImageCraft\ImageInfoFromString;
use Infernalmedia\ResponsiveImageCraft\Support\GenerationOptions;
use Infernalmedia\ResponsiveImageCraft\Support\ImageProcessor;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Throwable;

final class GenerateResponsiveImageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * @param  array<string, mixed>  $options  Serialized GenerationOptions
     */
    public function __construct(
        public readonly string $file,
        public readonly array $options
    ) {}

    public function handle(): void
    {
        $options = GenerationOptions::fromArray($this->options);
        $image = new ImageInfoFromString($this->file, $options->sourceDisk);
        $temporaryDirectory = (new TemporaryDirectory)->create();

        try {
            (new ImageProcessor($options))->process($image, $temporaryDirectory);
        } catch (Throwable $exception) {
            ResponsiveImageGenerationFailed::dispatch($this->file, $exception->getMessage());

            throw $exception;
        } finally {
            $temporaryDirectory->delete();
        }
    }
}
