<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Blog\Http\Controllers\ArticleAutosaveController;
use Modules\Blog\Http\Controllers\ArticleBulkController;
use Modules\Blog\Http\Controllers\ArticleController;
use Modules\Blog\Http\Controllers\ArticlePreviewController;
use Modules\Blog\Http\Controllers\ArticleRevisionController;
use Modules\Blog\Http\Controllers\CategoryController;
use Modules\Blog\Http\Controllers\TagController;

/*
|--------------------------------------------------------------------------
| Rutas del blog
|--------------------------------------------------------------------------
|
| Todo lo propio de los artículos y sus taxonomías.
|
| Se cargan dentro del grupo autenticado de routes/web.php: el módulo no
| declara la pila de middleware, la hereda.
|
*/

/*
|--------------------------------------------------------------------------
| Artículos
|--------------------------------------------------------------------------
*/
Route::get('articulos', [ArticleController::class, 'index'])->name('articles.index');
Route::get('articulos/crear', [ArticleController::class, 'create'])->name('articles.create');
Route::post('articulos', [ArticleController::class, 'store'])->name('articles.store');
Route::get('articulos/{article}/editar', [ArticleController::class, 'edit'])->name('articles.edit');
// POST y no PUT: el formulario lleva la imagen destacada y viaja como multipart.
Route::post('articulos/{article}', [ArticleController::class, 'update'])->name('articles.update');
Route::post('articulos/{article}/direccion', [ArticleController::class, 'regenerateSlug'])
    ->name('articles.slug');
Route::post('articulos/{article}/duplicar', [ArticleController::class, 'duplicate'])
    ->name('articles.duplicate');
Route::delete('articulos/{article}', [ArticleController::class, 'destroy'])->name('articles.destroy');
Route::post('articulos/{article}/restaurar', [ArticleController::class, 'restore'])
    ->withTrashed()
    ->name('articles.restore');

// Vista previa, historial y guardado automático
Route::get('articulos/{article}/vista-previa', ArticlePreviewController::class)
    ->name('articles.preview');
Route::get('articulos/{article}/versiones', [ArticleRevisionController::class, 'index'])
    ->name('articles.revisions.index');
Route::get('articulos/{article}/versiones/{revision}', [ArticleRevisionController::class, 'show'])
    ->name('articles.revisions.show');
Route::post('articulos/{article}/versiones/{revision}/restaurar', [ArticleRevisionController::class, 'restore'])
    ->name('articles.revisions.restore');
Route::post('articulos/{article}/autoguardado', [ArticleAutosaveController::class, 'store'])
    ->name('articles.autosave');
Route::delete('articulos/{article}/autoguardado', [ArticleAutosaveController::class, 'destroy'])
    ->name('articles.autosave.discard');

// Acciones masivas
Route::post('articulos/acciones/estado', [ArticleBulkController::class, 'updateStatus'])
    ->name('articles.bulk.status');

/*
|--------------------------------------------------------------------------
| Categorías
|--------------------------------------------------------------------------
*/
Route::get('categorias', [CategoryController::class, 'index'])->name('categories.index');
Route::get('categorias/crear', [CategoryController::class, 'create'])->name('categories.create');
Route::post('categorias', [CategoryController::class, 'store'])->name('categories.store');
Route::get('categorias/{category}/editar', [CategoryController::class, 'edit'])->name('categories.edit');
Route::put('categorias/{category}', [CategoryController::class, 'update'])->name('categories.update');
Route::post('categorias/{category}/direccion', [CategoryController::class, 'regenerateSlug'])
    ->name('categories.slug');
Route::delete('categorias/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
Route::post('categorias/{category}/restaurar', [CategoryController::class, 'restore'])
    ->withTrashed()
    ->name('categories.restore');

/*
|--------------------------------------------------------------------------
| Etiquetas
|--------------------------------------------------------------------------
*/
Route::get('etiquetas', [TagController::class, 'index'])->name('tags.index');
Route::post('etiquetas', [TagController::class, 'store'])->name('tags.store');
Route::put('etiquetas/{tag}', [TagController::class, 'update'])->name('tags.update');
Route::post('etiquetas/{tag}/direccion', [TagController::class, 'regenerateSlug'])->name('tags.slug');
Route::delete('etiquetas/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');
Route::post('etiquetas/{tag}/restaurar', [TagController::class, 'restore'])
    ->withTrashed()
    ->name('tags.restore');
