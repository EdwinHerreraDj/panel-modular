<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Models\MediaUsage;
use Illuminate\Database\Eloquent\Model;

/**
 * Registro de dónde se usa cada archivo de la biblioteca.
 *
 * Se llama al guardar cualquier cosa que apunte a un archivo, de modo que la
 * biblioteca siempre sepa qué está publicado y qué no.
 */
final class SyncMediaUsage
{
    /**
     * Fija el archivo que ocupa un papel concreto de un elemento.
     *
     * Pasar null libera ese papel.
     */
    public function set(Model $usable, string $role, ?int $mediaId): void
    {
        MediaUsage::query()
            ->where('usable_type', $usable->getMorphClass())
            ->where('usable_id', $usable->getKey())
            ->where('role', $role)
            ->delete();

        if ($mediaId === null) {
            return;
        }

        MediaUsage::query()->create([
            'media_id' => $mediaId,
            'usable_type' => $usable->getMorphClass(),
            'usable_id' => $usable->getKey(),
            'role' => $role,
        ]);
    }

    /**
     * Elimina todos los usos registrados de un elemento.
     *
     * Se llama al borrar definitivamente algo; con el borrado lógico los usos
     * se conservan, porque el elemento puede volver.
     */
    public function forget(Model $usable): void
    {
        MediaUsage::query()
            ->where('usable_type', $usable->getMorphClass())
            ->where('usable_id', $usable->getKey())
            ->delete();
    }
}
