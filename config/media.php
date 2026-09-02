<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Disco de almacenamiento
    |--------------------------------------------------------------------------
    |
    | Local durante el desarrollo. Al pasar a S3 basta con cambiar esta variable:
    | nada del código hace referencia al disco directamente.
    |
    */

    'disk' => env('MEDIA_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Restricciones por tipo de imagen
    |--------------------------------------------------------------------------
    |
    | `mimetypes` se comprueba sobre el contenido real del archivo (finfo), no
    | sobre la extensión. No se admite SVG en ningún caso: puede contener
    | scripts y se serviría desde nuestro propio dominio.
    |
    | Los tamaños están en kilobytes y las dimensiones en píxeles.
    |
    */

    'images' => [

        // Biblioteca multimedia.
        //
        // El límite de dimensiones está pensado para un hosting compartido: al
        // procesarla, una imagen ocupa en memoria unos 4 bytes por píxel, así
        // que 4000×4000 son ya 64 MB solo para abrirla. Para un blog rara vez
        // hace falta más de 2000 px de ancho.
        'library' => [
            'directory' => 'biblioteca',
            'mimetypes' => ['image/png', 'image/jpeg', 'image/webp'],
            'max_kilobytes' => 5120,
            'min_width' => 16,
            'min_height' => 16,
            'max_width' => 4000,
            'max_height' => 4000,
        ],

        'logo' => [
            'directory' => 'settings',
            'mimetypes' => ['image/png', 'image/jpeg', 'image/webp'],
            'max_kilobytes' => 1024,
            'min_width' => 32,
            'min_height' => 32,
            'max_width' => 1600,
            'max_height' => 600,
        ],

        'favicon' => [
            'directory' => 'settings',
            // Solo PNG: los navegadores actuales lo admiten y evita los
            // formatos que no se pueden verificar con fiabilidad.
            'mimetypes' => ['image/png'],
            'max_kilobytes' => 256,
            'min_width' => 16,
            'min_height' => 16,
            'max_width' => 512,
            'max_height' => 512,
        ],

        // Imagen destacada de un artículo. Comparte requisitos con la de Open
        // Graph, porque acaba usándose para compartir el artículo.
        'article_image' => [
            'directory' => 'articulos',
            'mimetypes' => ['image/png', 'image/jpeg', 'image/webp'],
            'max_kilobytes' => 2048,
            'min_width' => 600,
            'min_height' => 315,
            'max_width' => 2400,
            'max_height' => 1260,
        ],

        'og_image' => [
            'directory' => 'settings',
            'mimetypes' => ['image/png', 'image/jpeg', 'image/webp'],
            'max_kilobytes' => 2048,
            // Mínimo recomendado por Open Graph para que no se recorte mal.
            'min_width' => 600,
            'min_height' => 315,
            'max_width' => 2400,
            'max_height' => 1260,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Biblioteca multimedia
    |--------------------------------------------------------------------------
    |
    | Restricciones de lo que se puede subir a la biblioteca y variantes que se
    | generan de cada imagen.
    |
    */

    'library' => [
        'directory' => 'biblioteca',
        // Archivos por subida.
        'max_files' => 10,
    ],

    /*
    | Anchos de las variantes. La altura se calcula manteniendo la proporción y
    | nunca se agranda una imagen: si el original es menor, se deja como está.
    */

    'variants' => [
        'thumbnail' => 300,
        'medium' => 768,
        'large' => 1400,
    ],

    /*
    | Calidad de recompresión. 82 es el punto habitual donde deja de notarse la
    | pérdida y el archivo ya pesa bastante menos.
    */

    'quality' => [
        'jpeg' => 82,
        'webp' => 80,
        'png' => 90,
    ],

    /*
    | Además de cada variante en su formato original se genera una en WebP, que
    | pesa bastante menos y admiten todos los navegadores actuales.
    */

    'generate_webp' => true,

    /*
    |--------------------------------------------------------------------------
    | Extensión asociada a cada tipo detectado
    |--------------------------------------------------------------------------
    |
    | El nombre del archivo se genera siempre a partir de aquí, nunca del
    | nombre que envía el navegador.
    |
    */

    'extensions' => [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ],

    /*
    |--------------------------------------------------------------------------
    | Procesamiento
    |--------------------------------------------------------------------------
    |
    | Las variantes se generan en un trabajo en cola.
    |
    | Por defecto la conexión es `sync`: el procesado ocurre durante la subida y
    | la biblioteca funciona sin arrancar nada. En producción, con volumen de
    | imágenes, conviene poner MEDIA_QUEUE_CONNECTION=database y dejar corriendo
    | `php artisan queue:work`: la subida responde al instante y las variantes se
    | generan detrás.
    |
    | Con una conexión en segundo plano las imágenes aparecen unos segundos como
    | «Procesando» hasta que el worker las atiende.
    |
    */

    'queue_connection' => env('MEDIA_QUEUE_CONNECTION', 'sync'),

    'queue' => env('MEDIA_QUEUE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Memoria disponible para procesar imágenes
    |--------------------------------------------------------------------------
    |
    | Vacío significa «usa el memory_limit de PHP», que es lo normal. Se puede
    | fijar a mano (por ejemplo `96M`) cuando el alojamiento declara un límite
    | que en la práctica no sostiene, para rechazar antes las imágenes grandes
    | con un aviso claro en lugar de morir con una pantalla en blanco.
    |
    */

    'max_processing_memory' => env('MEDIA_MAX_PROCESSING_MEMORY'),

];
