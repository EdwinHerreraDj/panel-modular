<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\MediaProcessingStatus;
use App\Models\Media;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\EncoderInterface;
use Throwable;

/**
 * Genera las variantes de tamaño de una imagen de la biblioteca.
 *
 * Se ejecuta en cola para que la subida responda al instante. Con
 * QUEUE_CONNECTION=sync se procesa en el momento, así que en desarrollo
 * funciona sin arrancar ningún worker.
 *
 * Nunca agranda una imagen: si el original es más pequeño que la variante, se
 * omite esa variante y se usa el original.
 */
class GenerateMediaVariants implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public readonly int $mediaId) {}

    public function handle(): void
    {
        $media = Media::query()->find($this->mediaId);

        if ($media === null) {
            return;
        }

        $disk = Storage::disk($media->disk);

        if (! $disk->exists($media->path)) {
            $this->markFailed($media, 'El archivo original ya no está en el disco.');

            return;
        }

        try {
            $manager = new ImageManager(new Driver);
            $original = (string) $disk->get($media->path);
            $image = $manager->decodeBinary($original);

            $media->width = $image->width();
            $media->height = $image->height();

            /** @var array<string, int> $sizes */
            $sizes = config('media.variants', []);
            $variants = [];

            foreach ($sizes as $name => $width) {
                // No tiene sentido generar una variante mayor que el original.
                if ($image->width() <= $width) {
                    continue;
                }

                // Se vuelve a decodificar en cada vuelta: `scaleDown` modifica
                // la instancia y encadenar reducciones degradaría la calidad.
                $resized = $manager->decodeBinary($original)->scaleDown(width: $width);

                $variants[$name] = $this->store(
                    $media,
                    $name,
                    $resized->encode($this->encoderFor($media->mime_type)),
                    $media->extension,
                );

                if (config('media.generate_webp', true)) {
                    $variants["{$name}.webp"] = $this->store(
                        $media,
                        $name,
                        $resized->encode(new WebpEncoder(quality: (int) config('media.quality.webp', 80))),
                        'webp',
                    );
                }
            }

            // WebP del original completo: es lo que más ahorra en las portadas.
            if (config('media.generate_webp', true) && $media->mime_type !== 'image/webp') {
                $variants['original.webp'] = $this->store(
                    $media,
                    'original',
                    $image->encode(new WebpEncoder(quality: (int) config('media.quality.webp', 80))),
                    'webp',
                );
            }

            $media->variants = $variants;
            $media->processing_status = MediaProcessingStatus::Done;
            $media->processing_error = null;
            $media->save();
        } catch (Throwable $exception) {
            // El original sigue siendo usable: se deja constancia del fallo sin
            // perder el archivo.
            $this->markFailed($media, $exception->getMessage());

            throw $exception;
        }
    }

    /**
     * Guarda una variante y devuelve su ruta.
     */
    private function store(Media $media, string $name, mixed $encoded, string $extension): string
    {
        $base = pathinfo($media->path, PATHINFO_FILENAME);
        $directory = trim((string) config('media.library.directory', 'biblioteca'), '/');
        $path = "{$directory}/variantes/{$base}-{$name}.{$extension}";

        Storage::disk($media->disk)->put($path, (string) $encoded);

        return $path;
    }

    private function encoderFor(string $mimeType): EncoderInterface
    {
        return match ($mimeType) {
            'image/png' => new PngEncoder,
            'image/webp' => new WebpEncoder(quality: (int) config('media.quality.webp', 80)),
            default => new JpegEncoder(quality: (int) config('media.quality.jpeg', 82)),
        };
    }

    private function markFailed(Media $media, string $reason): void
    {
        $media->processing_status = MediaProcessingStatus::Failed;
        $media->processing_error = mb_substr($reason, 0, 1000);
        $media->save();
    }
}
