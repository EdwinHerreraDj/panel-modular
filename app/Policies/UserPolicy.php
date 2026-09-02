<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Autorización sobre las cuentas de usuario.
 *
 * Esta policy es deliberadamente autosuficiente: el Gate::before de
 * AppServiceProvider no la atajaria, porque contiene salvaguardas que deben
 * aplicarse también al superadministrador.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, User $target): bool
    {
        return $user->isSuperAdmin() || $user->is($target);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, User $target): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Nadie puede enviarse a sí mismo a la papelera, ni siquiera el
     * superadministrador: evita dejar la aplicación sin administración.
     */
    public function delete(User $user, User $target): bool
    {
        return $user->isSuperAdmin() && ! $user->is($target);
    }

    public function restore(User $user, User $target): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * El borrado definitivo no se ofrece: las cuentas quedan en la papelera
     * para conservar la trazabilidad de lo que hicieron.
     */
    public function forceDelete(User $user, User $target): bool
    {
        return false;
    }

    /**
     * Fijar una contraseña nueva a otra persona. Sobre uno mismo se usa el
     * cambio de contraseña normal, que exige conocer la actual.
     */
    public function resetPassword(User $user, User $target): bool
    {
        return $user->isSuperAdmin() && ! $user->is($target);
    }
}
