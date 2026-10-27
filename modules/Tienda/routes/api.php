<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Tienda\Http\Controllers\Api\CategoryApiController;
use Modules\Tienda\Http\Controllers\Api\ProductApiController;
use Modules\Tienda\Http\Controllers\Api\StoreApiController;

/*
|--------------------------------------------------------------------------
| API pública de la tienda
|--------------------------------------------------------------------------
|
| Solo lectura, como el resto de la API. `api.public` aporta la clave, el
| límite y la caché en el orden correcto.
|
*/

Route::middleware('api.public')->prefix('tienda')->name('api.tienda.')->group(function (): void {
    Route::get('configuracion', [StoreApiController::class, 'settings'])->name('settings');
    Route::get('promociones', [StoreApiController::class, 'promotions'])->name('promotions');
    Route::get('categorias', [CategoryApiController::class, 'index'])->name('categories.index');
    Route::get('categorias/{slug}', [CategoryApiController::class, 'show'])->name('categories.show');
    Route::get('articulos', [ProductApiController::class, 'index'])->name('products.index');
    Route::get('articulos/{slug}', [ProductApiController::class, 'show'])->name('products.show');
});
