<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | CORS del framework: desactivado a propósito
    |--------------------------------------------------------------------------
    |
    | Por defecto Laravel aplica CORS a `api/*` con `allowed_origins => ['*']`,
    | es decir, permitiendo que cualquier web del mundo lea la API desde el
    | navegador. Aquí eso no vale: los dominios se autorizan uno a uno desde el
    | panel, y esa comprobación la hace App\Http\Middleware\HandleApiCors.
    |
    | Dejando `paths` vacío, el middleware del framework no toca ninguna
    | respuesta y no puede sobrescribir la cabecera que calcula el nuestro.
    |
    */

    'paths' => [],

    'allowed_methods' => [],

    'allowed_origins' => [],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
