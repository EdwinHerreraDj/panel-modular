<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * Política de contraseñas única para toda la plataforma.
 */
final class PasswordRules
{
    public static function default(): Password
    {
        /** @var array<string, mixed> $config */
        $config = config('security.passwords');

        $rule = Password::min((int) $config['min_length']);

        if ($config['require_mixed_case'] ?? false) {
            $rule = $rule->mixedCase();
        }

        if ($config['require_numbers'] ?? false) {
            $rule = $rule->numbers();
        }

        if ($config['require_symbols'] ?? false) {
            $rule = $rule->symbols();
        }

        if ($config['uncompromised'] ?? false) {
            $rule = $rule->uncompromised();
        }

        return $rule;
    }
}
