<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Media;
use App\Models\User;

/**
 * Autorización sobre la biblioteca multimedia.
 */
class MediaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::MediaManage->value);
    }

    public function view(User $user, Media $media): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::MediaManage->value);
    }

    /**
     * Cambiar el nombre y el texto alternativo. Quien puede subir puede
     * describir lo que hay.
     */
    public function update(User $user, Media $media): bool
    {
        return $user->can(Permission::MediaManage->value);
    }

    /**
     * Enviar a la papelera está más restringido que subir: afecta a lo que ya
     * está publicado.
     */
    public function delete(User $user, Media $media): bool
    {
        return $user->can(Permission::MediaDelete->value);
    }

    public function restore(User $user, Media $media): bool
    {
        return $user->can(Permission::MediaDelete->value);
    }

    /**
     * El borrado definitivo borra los archivos del disco: solo el
     * superadministrador, que se resuelve en el Gate::before.
     */
    public function forceDelete(User $user, Media $media): bool
    {
        return false;
    }
}
