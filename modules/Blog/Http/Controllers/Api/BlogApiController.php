<?php

declare(strict_types=1);

namespace Modules\Blog\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\SitePayload;
use Illuminate\Http\JsonResponse;
use Modules\Blog\BlogModule;

/**
 * `GET /blog`, que se mantiene por compatibilidad.
 *
 * Nació cuando el panel solo servía para un blog y devuelve los datos del
 * sitio. Hoy el endpoint bueno es `/sitio`, del núcleo, pero este sigue
 * respondiendo igual para no romper las webs que ya estaban conectadas.
 *
 * Vive en el módulo del blog por dos razones: es el único que sabe qué es
 * `articulos_por_pagina`, y en una instalación sin blog no tiene sentido que
 * exista una dirección llamada así.
 */
class BlogApiController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'datos' => [
                ...SitePayload::build(),
                'articulos_por_pagina' => BlogModule::articlesPerPage(),
            ],
        ]);
    }
}
