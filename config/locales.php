<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Idiomas disponibles para los sitios
    |--------------------------------------------------------------------------
    |
    | Cada sitio publica en un único idioma. Si un cliente necesita su blog en
    | dos idiomas, se crean dos sitios.
    |
    */

    'available' => ['es', 'ca', 'gl', 'eu', 'en', 'fr', 'pt', 'de', 'it'],

    'labels' => [
        'es' => 'Español',
        'ca' => 'Català',
        'gl' => 'Galego',
        'eu' => 'Euskara',
        'en' => 'English',
        'fr' => 'Français',
        'pt' => 'Português',
        'de' => 'Deutsch',
        'it' => 'Italiano',
    ],

    /*
    |--------------------------------------------------------------------------
    | Zonas horarias ofrecidas en el formulario de sitio
    |--------------------------------------------------------------------------
    |
    | La validación acepta cualquier zona horaria válida de PHP; esta lista es
    | solo la de uso habitual que se muestra en el desplegable.
    |
    */

    'timezones' => [
        'Europe/Madrid',
        'Atlantic/Canary',
        'Europe/Lisbon',
        'Europe/London',
        'Europe/Paris',
        'Europe/Berlin',
        'UTC',
        'America/Mexico_City',
        'America/Bogota',
        'America/Argentina/Buenos_Aires',
        'America/Santiago',
        'America/New_York',
    ],

];
