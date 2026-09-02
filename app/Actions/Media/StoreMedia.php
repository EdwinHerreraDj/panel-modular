<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Actions\RecordActivity;
use App\Enums\ActivityEvent;
use App\Enums\MediaProcessingStatus;
use App\Jobs\GenerateMediaVariants;
use App\Models\Media;
use App\Models\User;
use App\Support\ImageConstraints;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Alta de un archivo en la biblioteca.
 *
 * Repite la verificación del contenido antes de escribir nada: esta acción
 * también se usa desde comandos, donde no ha pasado por un formulario.
 */
final class StoreMedia
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function handle(UploadedFile $file, User $uploader, ?string $name = null): Media
    {
        $constraints = ImageConstraints::for('library');

        $mimeType = $this->verifiedMimeType($file, $constraints);
        [$width, $height] = $this->verifiedDimensions($file, $constraints);

        /** @var array<string, string> $extensions */
        $extensions = config('media.extensions');
        $extension = $extensions[$mimeType] ?? 'bin';

        $directory = trim((string) config('media.library.directory', 'biblioteca'), '/');
        $path = $directory.'/'.Str::lower(Str::ulid()->toString()).'.'.$extension;
        $disk = (string) config('media.disk', 'public');

        $stream = fopen($file->getRealPath(), 'rb');

        if ($stream === false) {
            throw new RuntimeException('No se ha podido leer el archivo subido.');
        }

        try {
            Storage::disk($disk)->put($path, $stream);
        } finally {
            fclose($stream);
        }

        $media = DB::transaction(function () use (
            $file, $uploader, $name, $mimeType, $extension, $path, $disk, $width, $height
        ): Media {
            $media = new Media;

            $media->forceFill([
                // El nombre visible parte del original saneado, pero el archivo
                // en disco nunca lo usa.
                'name' => $name ?? $this->readableName($file),
                'alt_text' => null,
                'description' => null,
                'disk' => $disk,
                'path' => $path,
                'mime_type' => $mimeType,
                'extension' => $extension,
                'size' => $file->getSize() ?: 0,
                'width' => $width,
                'height' => $height,
                'variants' => null,
                'processing_status' => MediaProcessingStatus::Pending,
                'uploaded_by' => $uploader->getKey(),
            ])->save();

            $this->recordActivity->handle(
                $media,
                ActivityEvent::Created,
                "Archivo «{$media->name}» subido a la biblioteca",
            );

            return $media;
        });

        // Con la conexión `sync` (por defecto) esto se ejecuta aquí mismo; con
        // una conexión en segundo plano, lo recoge el worker.
        GenerateMediaVariants::dispatch($media->id)
            ->onConnection((string) config('media.queue_connection', 'sync'))
            ->onQueue((string) config('media.queue', 'default'));

        return $media;
    }

    /**
     * Nombre legible a partir del archivo original.
     *
     * Solo se usa como etiqueta en el panel: nunca toca el disco, así que no
     * puede provocar un salto de directorio.
     */
    private function readableName(UploadedFile $file): string
    {
        $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $clean = trim(preg_replace('/[^\p{L}\p{N}\s\-_]+/u', ' ', $original) ?? '');
        $clean = trim((string) preg_replace('/\s+/u', ' ', $clean));

        return $clean === '' ? 'Imagen sin título' : Str::limit(Str::ucfirst($clean), 150, '');
    }

    private function verifiedMimeType(UploadedFile $file, ImageConstraints $constraints): string
    {
        if (! $file->isValid()) {
            throw new RuntimeException('El archivo subido no es válido.');
        }

        $mimeType = (string) $file->getMimeType();

        if (! in_array($mimeType, $constraints->mimeTypes, true)) {
            throw new RuntimeException("Tipo de archivo no admitido: {$mimeType}.");
        }

        return $mimeType;
    }

    /**
     * @return array{int, int}
     */
    private function verifiedDimensions(UploadedFile $file, ImageConstraints $constraints): array
    {
        $size = @getimagesize($file->getRealPath());

        if ($size === false) {
            throw new RuntimeException('El archivo no es una imagen legible.');
        }

        [$width, $height] = $size;

        $withinBounds = $width >= $constraints->minWidth
            && $height >= $constraints->minHeight
            && $width <= $constraints->maxWidth
            && $height <= $constraints->maxHeight;

        if (! $withinBounds) {
            throw new RuntimeException("Dimensiones no permitidas: {$width}×{$height} px.");
        }

        return [$width, $height];
    }
}
