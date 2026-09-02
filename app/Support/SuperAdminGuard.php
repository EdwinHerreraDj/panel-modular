<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Impide que la aplicación se quede sin ningún superadministrador operativo.
 *
 * Sin esta comprobación bastaría con suspender, degradar o borrar la última
 * cuenta con control total para dejar la instalación sin administración.
 */
final class SuperAdminGuard
{
    /**
     * Número de superadministradores activos, excluyendo opcionalmente a uno.
     */
    public static function activeCount(?User $excluding = null): int
    {
        return User::query()
            ->role(RoleName::SuperAdmin->value)
            ->where('status', UserStatus::Active->value)
            ->when($excluding !== null, fn ($query) => $query->whereKeyNot($excluding->getKey()))
            ->count();
    }

    /**
     * ¿Es esta la última cuenta con control total todavía operativa?
     */
    public static function isLastActive(User $user): bool
    {
        if (! $user->isSuperAdmin() || $user->status !== UserStatus::Active) {
            return false;
        }

        return self::activeCount($user) === 0;
    }

    /**
     * @param  string  $field  Campo del formulario al que asociar el error.
     *
     * @throws ValidationException
     */
    public static function assertNotLastActive(User $user, string $field, string $message): void
    {
        if (self::isLastActive($user)) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
