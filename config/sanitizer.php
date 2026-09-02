<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Elementos permitidos en el contenido de los artículos
    |--------------------------------------------------------------------------
    |
    | Lista blanca: todo lo que no aparezca aquí se elimina. Nunca se confía en
    | el HTML que llega del navegador, aunque lo haya generado nuestro propio
    | editor: cualquiera puede enviar otra cosa.
    |
    | El formato es `elemento => [atributos permitidos]`.
    |
    */

    'allowed_elements' => [
        'p' => [],
        'br' => [],
        'strong' => [],
        'em' => [],
        'u' => [],
        's' => [],
        'code' => [],
        'pre' => [],
        'blockquote' => ['cite'],
        'h2' => [],
        'h3' => [],
        'h4' => [],
        'ul' => [],
        'ol' => ['start'],
        'li' => [],
        'hr' => [],
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'loading'],
        'figure' => [],
        'figcaption' => [],
        'table' => [],
        'thead' => [],
        'tbody' => [],
        'tr' => [],
        'th' => ['colspan', 'rowspan'],
        'td' => ['colspan', 'rowspan'],
        'iframe' => ['src', 'width', 'height', 'title', 'allow', 'allowfullscreen', 'frameborder'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dominios permitidos en enlaces e incrustaciones
    |--------------------------------------------------------------------------
    |
    | `media_hosts` limita de dónde pueden venir imágenes e iframes. Dejarlo
    | abierto permitiría incrustar un iframe de cualquier sitio dentro de
    | nuestras páginas.
    |
    | `link_hosts` a null significa que se admite cualquier destino en los
    | enlaces de texto, que no ejecutan nada.
    |
    */

    'link_hosts' => null,

    'media_hosts' => [
        'www.youtube.com',
        'youtube.com',
        'www.youtube-nocookie.com',
        'youtu.be',
        'player.vimeo.com',
        'vimeo.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | Longitud máxima del contenido
    |--------------------------------------------------------------------------
    |
    | Corta el HTML resultante para evitar que una petición enorme llene la
    | base de datos.
    |
    */

    'max_length' => 500_000,

    /*
    |--------------------------------------------------------------------------
    | Palabras por minuto
    |--------------------------------------------------------------------------
    |
    | Base para calcular el tiempo estimado de lectura.
    |
    */

    'words_per_minute' => 200,

];
