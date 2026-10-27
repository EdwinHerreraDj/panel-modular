<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Tienda\Http\Controllers\CategoryController;
use Modules\Tienda\Http\Controllers\ProductController;
use Modules\Tienda\Http\Controllers\PromotionController;
use Modules\Tienda\Http\Controllers\StockController;

/*
|--------------------------------------------------------------------------
| Tienda
|--------------------------------------------------------------------------
|
| Se cargan dentro del grupo autenticado de routes/web.php: el módulo no
| declara la pila de middleware, la hereda.
|
| Todo bajo `tienda/` y con nombres `tienda.*`: el blog y el catálogo ya usan
| `categorias` y `productos`, y los tres pueden estar activos a la vez.
|
*/

Route::prefix('tienda')->name('tienda.')->group(function (): void {
    Route::get('articulos', [ProductController::class, 'index'])->name('products.index');
    Route::get('articulos/crear', [ProductController::class, 'create'])->name('products.create');
    Route::post('articulos', [ProductController::class, 'store'])->name('products.store');
    Route::get('articulos/{product}/editar', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('articulos/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::post('articulos/{product}/direccion', [ProductController::class, 'regenerateSlug'])->name('products.slug');
    Route::delete('articulos/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    Route::post('articulos/{product}/restaurar', [ProductController::class, 'restore'])
        ->withTrashed()
        ->name('products.restore');

    Route::get('categorias', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('categorias/crear', [CategoryController::class, 'create'])->name('categories.create');
    Route::post('categorias', [CategoryController::class, 'store'])->name('categories.store');
    Route::get('categorias/{category}/editar', [CategoryController::class, 'edit'])->name('categories.edit');
    Route::put('categorias/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::post('categorias/{category}/direccion', [CategoryController::class, 'regenerateSlug'])->name('categories.slug');
    Route::delete('categorias/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    Route::post('categorias/{category}/restaurar', [CategoryController::class, 'restore'])
        ->withTrashed()
        ->name('categories.restore');

    Route::get('inventario', [StockController::class, 'index'])->name('stock.index');
    Route::get('inventario/movimientos', [StockController::class, 'history'])->name('stock.history');
    Route::post('inventario/{product}', [StockController::class, 'store'])->name('stock.store');

    Route::get('promociones', [PromotionController::class, 'index'])->name('promotions.index');
    Route::get('promociones/crear', [PromotionController::class, 'create'])->name('promotions.create');
    Route::post('promociones', [PromotionController::class, 'store'])->name('promotions.store');
    Route::get('promociones/{promotion}/editar', [PromotionController::class, 'edit'])->name('promotions.edit');
    Route::put('promociones/{promotion}', [PromotionController::class, 'update'])->name('promotions.update');
    Route::delete('promociones/{promotion}', [PromotionController::class, 'destroy'])->name('promotions.destroy');
});
