<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipos de evento registrados en la tabla `auth_logs`.
 */
enum AuthEvent: string
{
    case Login = 'login';
    case LoginFailed = 'login_failed';
    case LoginBlocked = 'login_blocked';
    case Logout = 'logout';
    case PasswordResetRequested = 'password_reset_requested';
    case PasswordReset = 'password_reset';
    case PasswordChanged = 'password_changed';

    public function label(): string
    {
        return match ($this) {
            self::Login => 'Inicio de sesión',
            self::LoginFailed => 'Intento fallido',
            self::LoginBlocked => 'Acceso bloqueado',
            self::Logout => 'Cierre de sesión',
            self::PasswordResetRequested => 'Solicitud de restablecimiento',
            self::PasswordReset => 'Contraseña restablecida',
            self::PasswordChanged => 'Contraseña cambiada',
        };
    }
}
