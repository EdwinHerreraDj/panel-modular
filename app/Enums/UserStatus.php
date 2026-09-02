<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de la cuenta de un usuario dentro de la plataforma.
 */
enum UserStatus: string
{
    /** Cuenta creada pero pendiente de que el usuario establezca su contraseña. */
    case Invited = 'invited';

    /** Cuenta operativa. */
    case Active = 'active';

    /** Cuenta bloqueada por un administrador. */
    case Suspended = 'suspended';

    /**
     * Solo las cuentas activas pueden iniciar sesión.
     */
    public function canAuthenticate(): bool
    {
        return $this === self::Active;
    }

    public function label(): string
    {
        return match ($this) {
            self::Invited => 'Invitado',
            self::Active => 'Activo',
            self::Suspended => 'Suspendido',
        };
    }

    /**
     * Motivo que se muestra al usuario cuando no puede iniciar sesión.
     */
    public function blockedReason(): ?string
    {
        return match ($this) {
            self::Active => null,
            self::Invited => 'Tu cuenta todavía no está activada. Revisa el correo de invitación o contacta con un administrador.',
            self::Suspended => 'Tu cuenta está suspendida. Contacta con un administrador de la plataforma.',
        };
    }
}
