<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Catalogo\Http\Controllers\Api\ProductApiController;

/*
|--------------------------------------------------------------------------
| API pública del catálogo
|--------------------------------------------------------------------------
|
| `api.public` aporta la clave, el límite y la caché en el orden correcto.
|
*/

Route::middleware('api.public')->group(function (): void {
    Route::get('productos', [ProductApiController::class, 'index'])->name('api.products.index');
    Route::get('productos/{slug}', [ProductApiController::class, 'show'])->name('api.products.show');
});
