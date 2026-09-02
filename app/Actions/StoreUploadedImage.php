<?php

declare(strict_types=1);

namespace App\Actions;

use App\Support\ImageConstraints;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Guarda una imagen subida por el usuario.
 *
 * La validación del Form Request ya comprueba tipo, tamaño y dimensiones; aquí
 * se vuelve a verificar el contenido antes de escribir nada, porque este método
 * también puede llamarse desde comandos o trabajos en cola donde no ha pasado
 * por un formulario.
 *
 * El nombre del archivo se genera siempre: el que envía el navegador no se usa
 * nunca, ni siquiera saneado.
 */
final class StoreUploadedImage
{
    /**
     * @param  string  $type  Clave de `config/media.images`.
     * @return string Ruta relativa dentro del disco.
     */
    public function handle(UploadedFile $file, string $type): string
    {
        $constraints = ImageConstraints::for($type);

        $mimeType = $this->verifiedMimeType($file, $constraints);
        $this->verifyDimensions($file, $constraints);

        /** @var array<string, string> $extensions */
        $extensions = config('media.extensions');
        $extension = $extensions[$mimeType] ?? 'bin';

        $path = trim($constraints->directory, '/').'/'.Str::lower(Str::ulid()->toString()).'.'.$extension;

        $stream = fopen($file->getRealPath(), 'rb');

        if ($stream === false) {
            throw new RuntimeException('No se ha podido leer el archivo subido.');
        }

        try {
            // `private` explícito: la visibilidad pública la da el disco, no el
            // archivo, para no exponer nada por accidente si cambia el disco.
            Storage::disk($this->disk())->put($path, $stream);
        } finally {
            fclose($stream);
        }

        return $path;
    }

    /**
     * Borra una imagen anterior. Nunca falla si ya no existe.
     */
    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        Storage::disk($this->disk())->delete($path);
    }

    /**
     * Tipo real del contenido, obtenido con finfo y contrastado con la lista
     * blanca. No se mira la extensión en ningún momento.
     */
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
     * Comprueba que el archivo es realmente una imagen decodificable y que sus
     * dimensiones están dentro de lo permitido.
     */
    private function verifyDimensions(UploadedFile $file, ImageConstraints $constraints): void
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
    }

    private function disk(): string
    {
        return (string) config('media.disk', 'public');
    }
}
