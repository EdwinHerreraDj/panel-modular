<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Blog\Http\Controllers\Api\ArticleApiController;
use Modules\Blog\Http\Controllers\Api\BlogApiController;
use Modules\Blog\Http\Controllers\Api\TaxonomyApiController;

/*
|--------------------------------------------------------------------------
| API pública del blog
|--------------------------------------------------------------------------
|
| Se cargan dentro del grupo de la API del núcleo, con su prefijo y su
| versión. `api.public` aporta la clave, el límite y la caché en el orden
| correcto, así que el módulo no los repite.
|
*/

Route::middleware('api.public')->group(function (): void {
    // Heredado: `/sitio` es el que hay que usar. Se mantiene para no romper
    // las webs conectadas antes de que esto dejara de ser solo un blog.
    Route::get('blog', [BlogApiController::class, 'show'])->name('api.blog');

    Route::get('articulos', [ArticleApiController::class, 'index'])->name('api.articles.index');
    Route::get('articulos/{slug}', [ArticleApiController::class, 'show'])->name('api.articles.show');

    Route::get('categorias', [TaxonomyApiController::class, 'categories'])->name('api.categories');
    Route::get('etiquetas', [TaxonomyApiController::class, 'tags'])->name('api.tags');
});
