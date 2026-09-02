<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Módulos activos
    |--------------------------------------------------------------------------
    |
    | Se leen del `.env` y no de la base de datos a propósito: así se resuelven
    | al arrancar, sobreviven a `config:cache` y no dependen de una consulta.
    |
    |   MODULES_ENABLED=blog,catalogo
    |
    | Cada clave `x` se corresponde con la clase `Modules\X\XModule`. Si se
    | nombra un módulo que no existe, la aplicación lo dice en voz alta: es
    | preferible a que el contenido de un cliente desaparezca en silencio.
    |
    | Activar un módulo más adelante son tres pasos: añadirlo aquí, migrar y
    | sembrar sus permisos.
    |
    */

    'enabled' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('MODULES_ENABLED', '')),
    ), fn (string $key): bool => $key !== '')),

];
