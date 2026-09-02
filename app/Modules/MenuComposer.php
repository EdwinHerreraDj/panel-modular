<?php

declare(strict_types=1);

namespace App\Modules;

use App\Models\User;
use App\Modules\Values\MenuItem;
use Illuminate\Http\Request;

/**
 * Compone el menú lateral sumando el núcleo y los módulos activos.
 *
 * El menú pasa a ser un dato: el componente ya no decide qué entradas hay ni
 * cuáles se ven, solo las pinta. Es un cambio que mejora el código aunque nunca
 * se añada un segundo módulo, porque los permisos dejan de estar escritos en
 * dos sitios.
 *
 * El estado activo se resuelve aquí y no en el navegador: el patrón viaja con
 * la entrada y el servidor ya sabe en qué ruta está.
 */
final class MenuComposer
{
    public function __construct(private readonly ModuleRegistry $registry) {}

    /**
     * @return list<array{
     *     title: string|null,
     *     items: list<array{label: string, href: string, icon: string, active: bool}>
     * }>
     */
    public function forRequest(Request $request): array
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return [];
        }

        /** @var array<string, list<array{label: string, href: string, icon: string, active: bool}>> $grouped */
        $grouped = [];

        foreach ($this->visibleItems($user) as $item) {
            $grouped[$item->group][] = [
                'label' => $item->label,
                'href' => route($item->route),
                'icon' => $item->icon,
                'active' => $request->routeIs($item->activePattern),
            ];
        }

        return $this->ordered($grouped);
    }

    /**
     * Entradas que esta persona puede usar, ya ordenadas por posición.
     *
     * @return list<MenuItem>
     */
    private function visibleItems(User $user): array
    {
        $items = [...CoreMenu::items(), ...$this->registry->menu()];

        $items = array_values(array_filter(
            $items,
            fn (MenuItem $item): bool => $item->permission === null || $user->can($item->permission),
        ));

        usort($items, fn (MenuItem $a, MenuItem $b): int => $a->position <=> $b->position);

        return $items;
    }

    /**
     * Grupos en un orden fijo y previsible.
     *
     * Primero el panel, después un grupo por cada módulo de contenido —en el
     * orden en que estén declarados en `MODULES_ENABLED`—, luego las
     * herramientas que comparten todos y al final la administración.
     *
     * @param  array<string, list<array{label: string, href: string, icon: string, active: bool}>>  $grouped
     * @return list<array{title: string|null, items: list<array{label: string, href: string, icon: string, active: bool}>}>
     */
    private function ordered(array $grouped): array
    {
        $order = [
            CoreMenu::PRINCIPAL,
            ...$this->registry->menuGroups(),
            CoreMenu::HERRAMIENTAS,
            CoreMenu::ADMINISTRACION,
        ];

        $names = [
            // Los declarados, en su orden y solo si tienen algo visible.
            ...array_values(array_unique(array_filter(
                $order,
                fn (string $name): bool => isset($grouped[$name]),
            ))),
            // Y cualquier grupo suelto, detrás, para no perder nada.
            ...array_filter(array_keys($grouped), fn (string $name): bool => ! in_array($name, $order, true)),
        ];

        $menu = [];

        foreach ($names as $name) {
            $menu[] = [
                'title' => $name === CoreMenu::PRINCIPAL ? null : $name,
                'items' => $grouped[$name],
            ];
        }

        return $menu;
    }
}
