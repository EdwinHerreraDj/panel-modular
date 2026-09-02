<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Prefijo y versión
    |--------------------------------------------------------------------------
    |
    | Toda la API pública cuelga de aquí. La versión va en la URL para poder
    | publicar una v2 el día de mañana sin romper la web que ya consume la v1.
    |
    */

    'prefix' => env('API_PREFIX', 'api/v1'),

    'version' => '1.0',

    /*
    |--------------------------------------------------------------------------
    | Autenticación
    |--------------------------------------------------------------------------
    |
    | El contenido de un blog es público por definición, pero exigir una clave
    | permite saber quién consume la API, cortar el grifo a un consumidor
    | concreto y aplicarle su propio límite. Se envía en la cabecera
    | `X-API-Key`.
    |
    | Si se desactiva, la API responde a cualquiera: úsalo solo mientras
    | desarrollas.
    |
    */

    'require_key' => (bool) env('API_REQUIRE_KEY', true),

    'header' => 'X-API-Key',

    /*
    |--------------------------------------------------------------------------
    | Límite de peticiones
    |--------------------------------------------------------------------------
    |
    | Peticiones por minuto y por clave (o por IP cuando no se exige clave).
    | Cada clave puede sobrescribirlo con su propio límite.
    |
    */

    'rate_limit' => (int) env('API_RATE_LIMIT', 120),

    /*
    |--------------------------------------------------------------------------
    | Paginación
    |--------------------------------------------------------------------------
    */

    'per_page' => (int) env('API_PER_PAGE', 12),

    'max_per_page' => (int) env('API_MAX_PER_PAGE', 50),

    /*
    |--------------------------------------------------------------------------
    | Caché de respuestas
    |--------------------------------------------------------------------------
    |
    | Segundos que se guarda una respuesta de listado. Se invalida sola al
    | publicar o modificar un artículo. Cero desactiva la caché.
    |
    */

    'cache_seconds' => (int) env('API_CACHE_SECONDS', 300),

    /*
    |--------------------------------------------------------------------------
    | Orígenes permitidos (CORS)
    |--------------------------------------------------------------------------
    |
    | Dominios desde los que el navegador puede llamar a la API. Cada clave
    | añade además los suyos. En desarrollo se suelen añadir los locales.
    |
    */

    'allowed_origins' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('API_ALLOWED_ORIGINS', ''))),
    )),

];
