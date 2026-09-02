<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\ModuleActivator;
use App\Modules\ModuleRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

/**
 * Qué módulos tiene esta instalación y cuáles están encendidos.
 *
 * Solo para el superadministrador: activar un módulo aplica migraciones y
 * siembra permisos, y desactivarlo hace desaparecer pantallas del panel de
 * todo el mundo.
 */
class ModuleController extends Controller
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly ModuleActivator $activator,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Modules/Index', [
            'modules' => $this->modules(),
            'envIsWritable' => $this->activator->isWritable(),
            'envPath' => $this->activator->envPath(),
            // `php artisan serve` vigila el .env y reinicia el servidor al
            // verlo cambiar, así que en desarrollo la respuesta se pierde. El
            // trabajo ya está hecho para entonces; solo hay que recargar.
            'isLocal' => app()->environment('local'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['present', 'array'],
            'enabled.*' => ['string'],
        ], [], ['enabled' => 'módulos']);

        /** @var list<string> $enabled */
        $enabled = array_values(array_intersect($validated['enabled'], $this->registry->available()));

        try {
            $done = $this->activator->apply($enabled);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('modules.index')
            ->with('success', 'Módulos actualizados. '.implode('. ', $done).'.');
    }

    /**
     * Lo que hay en el disco, con lo que aporta cada uno.
     *
     * @return list<array<string, mixed>>
     */
    private function modules(): array
    {
        $active = $this->registry->keys();

        return array_map(function (string $key) use ($active): array {
            $module = $this->registry->describe($key);
            $isActive = in_array($key, $active, true);

            return [
                'key' => $key,
                'name' => $module?->name() ?? $key,
                'active' => $isActive,
                'contributes' => $this->contributions($key),
            ];
        }, $this->registry->available());
    }

    /**
     * Resumen de lo que pone cada módulo en el panel.
     *
     * Se pregunta también a los que están apagados, para que se pueda decidir
     * con conocimiento antes de encenderlos. Lo que consulte la base de datos
     * se deja fuera a propósito: sus tablas pueden no existir todavía.
     *
     * @return array<string, int>
     */
    private function contributions(string $key): array
    {
        $module = $this->registry->describe($key);

        if ($module === null) {
            return [];
        }

        try {
            return [
                'permisos' => count($module->permissions()),
                'pantallas' => count($module->menu()),
                'endpoints' => count($module->apiEndpoints()),
                'tipos' => count($module->contentTypes()),
            ];
        } catch (Throwable) {
            return [];
        }
    }
}
