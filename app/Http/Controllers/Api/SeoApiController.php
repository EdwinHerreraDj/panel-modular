<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Redirect;
use App\Modules\ModuleRegistry;
use Illuminate\Http\JsonResponse;

/**
 * Lo que la web pública necesita para no perder posicionamiento:
 * redirecciones y el listado de direcciones para el sitemap.
 */
class SeoApiController extends Controller
{
    /**
     * Redirecciones que la web debe aplicar.
     *
     * Se devuelven todas de una vez, no de una en una: la web las carga al
     * arrancar o las cachea, y así no hace una petición por cada visita a una
     * dirección que no existe.
     */
    public function redirects(): JsonResponse
    {
        $redirects = Redirect::query()
            ->orderBy('from_path')
            ->get();

        return response()->json([
            'datos' => $redirects->map(fn (Redirect $redirect): array => [
                'de' => $redirect->from_path,
                'a' => $redirect->to_path,
                'codigo' => $redirect->status_code,
                'externa' => $redirect->isExternal(),
            ])->values(),
            'total' => $redirects->count(),
        ]);
    }

    /**
     * Todas las direcciones publicadas con su última modificación.
     *
     * No se devuelve un XML: la web pública es la que conoce su estructura de
     * direcciones y su dominio, así que es ella quien monta el sitemap. Aquí se
     * dan los datos.
     */
    public function sitemap(): JsonResponse
    {
        return response()->json([
            // Las secciones las aportan los módulos activos: el núcleo no sabe
            // qué tipos de contenido hay publicados, solo que hay que juntarlos
            // todos en una respuesta. Con solo el blog salen «articulos»,
            // «categorias» y «etiquetas», igual que antes.
            ...app(ModuleRegistry::class)->sitemapSections(),

            'generado_en' => now()->toIso8601String(),
        ]);
    }
}
