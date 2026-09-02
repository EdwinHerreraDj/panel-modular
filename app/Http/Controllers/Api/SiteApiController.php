<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\ModuleRegistry;
use App\Support\SitePayload;
use Illuminate\Http\JsonResponse;

/**
 * Datos del sitio y comprobación de estado.
 */
class SiteApiController extends Controller
{
    /**
     * Lo que la web necesita para pintar la cabecera, el pie y el SEO por
     * defecto: nombre, lema, marca y datos de la empresa.
     */
    public function show(): JsonResponse
    {
        return response()->json(['datos' => SitePayload::build()]);
    }

    /**
     * Comprobación de estado.
     *
     * Pensado para que la web (o un vigilante externo) sepa si la API responde
     * sin tener que pedir un listado entero.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'estado' => 'ok',
            'version' => config('api.version'),
            // Cada módulo activo aporta su recuento.
            ...array_column(app(ModuleRegistry::class)->publicCounts(), 'count', 'key'),
            'hora' => now()->toIso8601String(),
        ]);
    }
}
