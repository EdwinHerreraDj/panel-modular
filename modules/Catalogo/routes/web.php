<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Catalogo\Http\Controllers\ProductController;

/*
|--------------------------------------------------------------------------
| Catálogo de productos
|--------------------------------------------------------------------------
|
| Se cargan dentro del grupo autenticado de routes/web.php: el módulo no
| declara la pila de middleware, la hereda.
|
*/

Route::get('productos', [ProductController::class, 'index'])->name('products.index');
Route::get('productos/crear', [ProductController::class, 'create'])->name('products.create');
Route::post('productos', [ProductController::class, 'store'])->name('products.store');
Route::get('productos/{product}/editar', [ProductController::class, 'edit'])->name('products.edit');
// POST y no PUT: el formulario lleva la imagen y viaja como multipart.
Route::post('productos/{product}', [ProductController::class, 'update'])->name('products.update');
Route::post('productos/{product}/direccion', [ProductController::class, 'regenerateSlug'])
    ->name('products.slug');
Route::delete('productos/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
Route::post('productos/{product}/restaurar', [ProductController::class, 'restore'])
    ->withTrashed()
    ->name('products.restore');
