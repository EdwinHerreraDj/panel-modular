<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Actions\RecordActivity;
use App\Enums\ActivityEvent;
use App\Models\Media;
use App\Models\MediaUsage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Eliminación de archivos de la biblioteca.
 *
 * El borrado es lógico: los archivos siguen en disco y se pueden restaurar. Se
 * comprueba antes que nadie los esté usando, para no dejar imágenes rotas
 * repartidas por el blog.
 */
final class DeleteMedia
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    /**
     * @throws ValidationException si el archivo está en uso.
     */
    public function handle(Media $media): void
    {
        $usages = $media->usages()->with('usable')->get();

        if ($usages->isNotEmpty()) {
            throw ValidationException::withMessages([
                'media' => $this->inUseMessage($usages->all()),
            ]);
        }

        DB::transaction(function () use ($media): void {
            $media->delete();

            $this->recordActivity->handle(
                $media,
                ActivityEvent::Deleted,
                "Archivo «{$media->name}» enviado a la papelera",
            );
        });
    }

    /**
     * Borrado definitivo: quita también los archivos del disco.
     *
     * Es irreversible, así que exige que el archivo esté ya en la papelera.
     */
    public function purge(Media $media): void
    {
        if (! $media->trashed()) {
            throw ValidationException::withMessages([
                'media' => 'Envía el archivo a la papelera antes de eliminarlo definitivamente.',
            ]);
        }

        if ($media->usages()->exists()) {
            throw ValidationException::withMessages([
                'media' => 'No se puede eliminar: el archivo se ha vuelto a usar.',
            ]);
        }

        DB::transaction(function () use ($media): void {
            $disk = Storage::disk($media->disk);

            foreach ($media->allPaths() as $path) {
                $disk->delete($path);
            }

            $this->recordActivity->handle(
                $media,
                ActivityEvent::Deleted,
                "Archivo «{$media->name}» eliminado definitivamente",
            );

            $media->forceDelete();
        });
    }

    /**
     * Vacía la papelera: borra del disco todo lo que lleve tiempo descartado.
     *
     * Los archivos que se hayan vuelto a usar se conservan, aunque estén en la
     * papelera: es más seguro dejar algo de sobra que romper una página.
     *
     * @return array{purged: int, kept: int}
     */
    public function emptyTrash(): array
    {
        $trashed = Media::onlyTrashed()->withCount('usages')->get();

        $purged = 0;
        $kept = 0;

        foreach ($trashed as $media) {
            if (($media->usages_count ?? 0) > 0) {
                $kept++;

                continue;
            }

            $this->purge($media);
            $purged++;
        }

        return ['purged' => $purged, 'kept' => $kept];
    }

    /**
     * Mensaje que enumera dónde se está usando el archivo.
     *
     * @param  list<MediaUsage>  $usages
     */
    private function inUseMessage(array $usages): string
    {
        $places = array_slice(array_map(
            fn (MediaUsage $usage): string => $usage->describeUsable().' ('.$usage->roleLabel().')',
            $usages,
        ), 0, 5);

        $message = count($usages) === 1
            ? 'No se puede eliminar: el archivo se está usando en '
            : 'No se puede eliminar: el archivo se está usando en '.count($usages).' sitios, entre ellos ';

        $message .= implode('; ', $places).'.';

        return $message.' Quítalo de ahí primero.';
    }
}
