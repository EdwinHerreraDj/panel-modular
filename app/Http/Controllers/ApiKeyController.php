<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RecordActivity;
use App\Enums\ActivityEvent;
use App\Http\Requests\StoreApiKeyRequest;
use App\Models\ApiKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Alta, revocación y borrado de claves de la API.
 */
class ApiKeyController extends Controller
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function store(StoreApiKeyRequest $request): RedirectResponse
    {
        $expiresAt = $request->filled('expires_at')
            ? Carbon::parse((string) $request->string('expires_at'))
            : null;

        ['key' => $key, 'plain' => $plain] = ApiKey::generate(
            name: (string) $request->string('name'),
            allowedOrigins: $request->origins(),
            rateLimit: $request->filled('rate_limit') ? (int) $request->integer('rate_limit') : null,
            createdBy: $request->user()?->getKey(),
            expiresAt: $expiresAt,
        );

        $this->recordActivity->handle($key, ActivityEvent::Created, 'Clave de API creada: '.$key->name);

        // La clave en claro viaja una única vez, en la sesión, para poder
        // enseñarla en la pantalla siguiente. Después ya no existe en ningún
        // sitio: si se pierde, se genera otra.
        return redirect()
            ->route('api.connection')
            ->with('new_api_key', ['id' => $key->id, 'name' => $key->name, 'plain' => $plain])
            ->with('success', 'Clave creada. Cópiala ahora: no se podrá volver a ver.');
    }

    /**
     * Revocar deja la clave inservible pero conserva su rastro: cuántas
     * peticiones hizo y cuándo se usó por última vez.
     */
    public function revoke(ApiKey $apiKey): RedirectResponse
    {
        if ($apiKey->isRevoked()) {
            return back()->with('error', 'Esa clave ya estaba revocada.');
        }

        $apiKey->revoke();

        $this->recordActivity->handle($apiKey, ActivityEvent::Updated, 'Clave de API revocada: '.$apiKey->name);

        return back()->with('success', 'Clave revocada. La web que la usara dejará de recibir datos.');
    }

    public function destroy(Request $request, ApiKey $apiKey): RedirectResponse
    {
        // Borrar una clave activa apagaría una web sin dejar rastro de por qué.
        if (! $apiKey->isRevoked()) {
            return back()->with('error', 'Revoca la clave antes de borrarla, para no dejar una web sin servicio sin querer.');
        }

        $name = $apiKey->name;
        $apiKey->delete();

        $this->recordActivity->handle($apiKey, ActivityEvent::Deleted, 'Clave de API eliminada: '.$name);

        return back()->with('success', 'Clave eliminada del registro.');
    }
}
