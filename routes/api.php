<?php

declare(strict_types=1);

use App\Http\Controllers\Api\SeoApiController;
use App\Http\Controllers\Api\SiteApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API pública
|--------------------------------------------------------------------------
|
| Lo que consume la web que enseña los artículos. Es de solo lectura: desde
| aquí no se puede crear ni modificar nada. El prefijo y la versión salen de
| config/api.php.
|
| El estado se deja fuera de la autenticación a propósito, para poder
| comprobar que la API responde aunque la clave esté mal.
|
*/

Route::get('estado', [SiteApiController::class, 'health'])->name('api.health');

// `api.public` es la clave, el límite y la caché en el orden correcto. Se
// define en bootstrap/app.php para que los módulos usen exactamente el mismo.
Route::middleware('api.public')->group(function (): void {
    Route::get('sitio', [SiteApiController::class, 'show'])->name('api.site');

    Route::get('redirecciones', [SeoApiController::class, 'redirects'])->name('api.redirects');
    Route::get('sitemap', [SeoApiController::class, 'sitemap'])->name('api.sitemap');
});
