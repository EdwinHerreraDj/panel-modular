<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\MenuComposer;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * Fuerza a recargar la página cuando cambia la versión de los assets.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Datos compartidos con todas las páginas.
     *
     * Solo se expone lo estrictamente necesario: nunca el modelo completo del
     * usuario, para evitar filtrar campos internos al navegador.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),

            'app' => [
                'name' => config('app.name'),
            ],

            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'initials' => $user->initials(),
                    'avatar_url' => $user->avatar_path === null
                        ? null
                        : asset('storage/'.$user->avatar_path),
                    'is_super_admin' => $user->isSuperAdmin(),
                    'role' => $user->roleName()?->value,
                    'role_label' => $user->roleName()?->label(),
                    'must_change_password' => $user->must_change_password,
                ],
                // Permisos concedidos, para decidir qué se muestra. Nunca son
                // la única barrera: el servidor vuelve a comprobarlo todo.
                'permissions' => $user === null
                    ? []
                    : $user->getAllPermissions()->pluck('name')->values()->all(),
            ],

            // El menú lateral, compuesto en el servidor sumando el núcleo y los
            // módulos activos. El componente solo lo pinta: así los permisos de
            // cada entrada están escritos en un único sitio.
            'menu' => fn (): array => app(MenuComposer::class)->forRequest($request),

            // Mensajes efímeros mostrados como notificación tras una redirección.
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'status' => fn () => $request->session()->get('status'),
            ],

            'ziggy' => fn (): array => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }
}
