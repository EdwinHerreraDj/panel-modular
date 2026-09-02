<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Limitación de intentos de acceso
    |--------------------------------------------------------------------------
    |
    | Número de intentos de inicio de sesión permitidos por combinación de
    | correo + dirección IP antes de bloquear temporalmente el acceso.
    |
    */

    'login' => [
        'max_attempts' => (int) env('AUTH_LOGIN_MAX_ATTEMPTS', 5),
        'decay_seconds' => (int) env('AUTH_LOGIN_DECAY_SECONDS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Política de contraseñas
    |--------------------------------------------------------------------------
    |
    | Requisitos mínimos aplicados en todos los formularios que establecen o
    | cambian una contraseña. `uncompromised` consulta la base de datos pública
    | de filtraciones (k-anonimato: nunca se envía la contraseña completa).
    |
    */

    'passwords' => [
        'min_length' => (int) env('AUTH_PASSWORD_MIN_LENGTH', 12),
        'require_mixed_case' => true,
        'require_numbers' => true,
        'require_symbols' => true,
        'uncompromised' => (bool) env('AUTH_PASSWORD_UNCOMPROMISED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Superadministrador inicial
    |--------------------------------------------------------------------------
    |
    | Datos usados por SuperAdminSeeder para crear la primera cuenta. En
    | producción, en lugar de definir la contraseña aquí, usa el comando
    | `php artisan panel:crear-superadmin`, que la pide de forma interactiva y
    | no la deja escrita en ningún archivo.
    |
    */

    'super_admin' => [
        'name' => env('SEED_SUPER_ADMIN_NAME', 'Superadministrador'),
        'email' => env('SEED_SUPER_ADMIN_EMAIL', 'super@alminares.es'),
        'password' => env('SEED_SUPER_ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cabeceras de seguridad
    |--------------------------------------------------------------------------
    |
    | Se aplican a todas las respuestas del área privada mediante el middleware
    | App\Http\Middleware\SecurityHeaders.
    |
    */

    'headers' => [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'X-Permitted-Cross-Domain-Policies' => 'none',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), interest-cohort=()',
        'Cross-Origin-Opener-Policy' => 'same-origin',
    ],

    /*
    |--------------------------------------------------------------------------
    | HSTS
    |--------------------------------------------------------------------------
    |
    | Solo se envía sobre HTTPS. Se desactiva en local para no bloquear el
    | acceso por http://localhost.
    |
    */

    'hsts' => [
        'enabled' => (bool) env('SECURITY_HSTS_ENABLED', false),
        'max_age' => 31536000,
        'include_subdomains' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Conservación de los registros
    |--------------------------------------------------------------------------
    |
    | Días que se guardan los accesos y la auditoría de contenido. Estas dos
    | tablas crecen con cada visita al panel; en un alojamiento compartido, sin
    | poda, acaban pesando más que el propio blog.
    |
    | Cero desactiva la limpieza y los conserva para siempre.
    |
    */

    'logs' => [
        'retention_days' => (int) env('LOGS_RETENTION_DAYS', 365),
        // Limpieza automática cuando el alojamiento no tiene cron.
        'prune_on_request' => (bool) env('LOGS_PRUNE_ON_REQUEST', true),
    ],

];
