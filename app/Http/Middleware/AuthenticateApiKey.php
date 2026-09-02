<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Comprueba la clave de la API pública.
 *
 * Los errores se devuelven en el mismo formato que el resto de la API y en
 * español: quien integra la web tiene que entender qué le falta sin abrir el
 * código.
 */
class AuthenticateApiKey
{
    /** Atributo donde se deja la clave para el resto de la petición. */
    public const ATTRIBUTE = 'api_key';

    /**
     * Marca que pone el probador del panel para saltarse la clave.
     *
     * Es seguro porque los atributos de la petición no se pueden rellenar
     * desde fuera: no hay cabecera ni parámetro que los alcance. Solo el
     * propio servidor, construyendo la petición, puede ponerla.
     */
    public const TRUSTED = 'api_trusted_console';

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('api.require_key') || $request->attributes->get(self::TRUSTED) === true) {
            return $next($request);
        }

        $plain = $this->extract($request);

        if ($plain === null) {
            return $this->error(
                'clave_ausente',
                'Falta la clave de API. Envíala en la cabecera '.config('api.header').'.',
                401,
            );
        }

        $key = ApiKey::findByPlain($plain);

        if ($key === null) {
            return $this->error('clave_invalida', 'La clave de API no es válida.', 401);
        }

        if (! $key->isUsable()) {
            return $this->error('clave_no_utilizable', (string) $key->unusableReason(), 403);
        }

        // Con navegador de por medio, la clave solo vale desde sus dominios.
        $origin = $request->headers->get('Origin');

        if ($origin !== null && ! $key->allowsOrigin($origin)) {
            return $this->error(
                'origen_no_permitido',
                'Esta clave no acepta peticiones desde '.$origin.'. Añade el dominio en el panel.',
                403,
            );
        }

        $key->recordUsage($request->ip());

        $request->attributes->set(self::ATTRIBUTE, $key);

        return $next($request);
    }

    /**
     * La clave se acepta en la cabecera propia o como `Authorization: Bearer`,
     * que es lo que muchos clientes envían por costumbre.
     */
    private function extract(Request $request): ?string
    {
        $header = (string) config('api.header');

        $value = $request->header($header)
            ?? $request->bearerToken();

        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'error' => ['codigo' => $code, 'mensaje' => $message],
        ], $status);
    }
}
