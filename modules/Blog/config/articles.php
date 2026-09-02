<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Historial de versiones
    |--------------------------------------------------------------------------
    |
    | Número de versiones manuales que se conservan por artículo. Al superarlo
    | se descartan las más antiguas: el historial sirve para recuperar trabajo
    | reciente, no para archivar indefinidamente.
    |
    */

    'max_revisions' => (int) env('ARTICLES_MAX_REVISIONS', 30),

    /*
    |--------------------------------------------------------------------------
    | Guardado automático
    |--------------------------------------------------------------------------
    |
    | Segundos entre copias automáticas mientras se escribe. La copia se guarda
    | aparte: el artículo publicado no cambia hasta pulsar Guardar.
    |
    */

    'autosave_seconds' => (int) env('ARTICLES_AUTOSAVE_SECONDS', 30),

    /*
    |--------------------------------------------------------------------------
    | Artículos relacionados
    |--------------------------------------------------------------------------
    */

    'max_related' => 6,

    /*
    |--------------------------------------------------------------------------
    | Publicación programada sin cron
    |--------------------------------------------------------------------------
    |
    | Lo correcto es que el planificador publique los artículos programados, con
    | una única entrada en cron (ver routes/console.php). Cuando el alojamiento
    | no permite configurarla, esta red de seguridad comprueba lo pendiente
    | durante las visitas al panel, como mucho una vez por minuto.
    |
    | Con cron configurado se puede desactivar sin perder nada.
    |
    */

    'publish_on_request' => (bool) env('ARTICLES_PUBLISH_ON_REQUEST', true),

];
