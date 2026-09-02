<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
|
| En el servidor hace falta una única entrada en cron:
|
|   * * * * * cd /ruta/del/proyecto && php artisan schedule:run >> /dev/null 2>&1
|
| Aquí solo van las del núcleo. Cada módulo programa las suyas en su
| proveedor de servicios, y solo se registran si el módulo está activo.
|
*/

// De madrugada, cuando no molesta: los registros antiguos no corren prisa.
Schedule::command('panel:limpiar-registros')
    ->dailyAt('03:30')
    ->withoutOverlapping();
