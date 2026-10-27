<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Module;
use App\Modules\ModuleRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;

/**
 * El menú lateral se compone en el servidor sumando el núcleo y los módulos
 * activos, y llega por las props compartidas.
 */
beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * @param  list<string|null>|null  $groups  Solo estos grupos, si se indican
 * @return list<string>
 */
function menuLabels(AssertableInertia $page, ?array $groups = null): array
{
    /** @var list<array{title: string|null, items: list<array{label: string}>}> $menu */
    $menu = $page->toArray()['props']['menu'];

    if ($groups !== null) {
        $menu = array_values(array_filter($menu, fn (array $group): bool => in_array($group['title'], $groups, true)));
    }

    return array_merge(...array_map(
        fn (array $group): array => array_column($group['items'], 'label'),
        $menu,
    ));
}

it('enseña el menú del núcleo y del blog, en orden', function (): void {
    requiresModule('blog');

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('dashboard'))
        ->assertInertia(function (AssertableInertia $page): void {
            $expected = [
                'Panel',
                'Artículos', 'Categorías', 'Etiquetas',
                'Redirecciones', 'Biblioteca',
                'Usuarios', 'Conexión web', 'Accesos', 'Auditoría', 'Módulos', 'Configuración',
            ];

            // Se ignora lo que aporten otros módulos —cada uno comprueba lo
            // suyo—, pero estas entradas tienen que estar y en este orden.
            // Solo se miran los grupos del núcleo y del blog: otro módulo puede
            // tener también sus «Artículos» o sus «Categorías» en su grupo.
            expect(menuLabels($page, [null, 'Blog', 'Herramientas', 'Administración']))->toBe($expected);
        });
});

it('da un grupo propio a cada módulo y deja el primero sin título', function (): void {
    requiresModule('blog');

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('dashboard'))
        ->assertInertia(function (AssertableInertia $page): void {
            /** @var list<array{title: string|null}> $menu */
            $menu = $page->toArray()['props']['menu'];

            // El panel, un grupo por módulo de contenido en el orden de
            // MODULES_ENABLED, lo que comparten todos, y la administración.
            $modules = array_map(
                fn (Module $module): string => $module->name(),
                array_values(app(ModuleRegistry::class)->all()),
            );

            expect(array_column($menu, 'title'))->toBe([
                null,
                ...$modules,
                'Herramientas',
                'Administración',
            ]);
        });
});

it('no deja ningún grupo vacío', function (): void {
    $this->actingAs(User::factory()->reader()->create())
        ->get(route('dashboard'))
        ->assertInertia(function (AssertableInertia $page): void {
            /** @var list<array{title: string|null, items: list<mixed>}> $menu */
            $menu = $page->toArray()['props']['menu'];

            foreach ($menu as $group) {
                expect($group['items'])->not->toBeEmpty();
            }

            expect(array_column($menu, 'title'))->not->toContain('Administración');
        });
});

it('esconde lo que cada rol no puede usar', function (): void {
    requiresModule('blog');

    $this->actingAs(User::factory()->author()->create())
        ->get(route('dashboard'))
        ->assertInertia(function (AssertableInertia $page): void {
            $labels = menuLabels($page);

            expect($labels)->toContain('Panel', 'Artículos', 'Biblioteca')
                ->and($labels)->not->toContain('Usuarios', 'Configuración', 'Auditoría', 'Redirecciones');
        });
});

it('deja al lector sin biblioteca ni administración', function (): void {
    requiresModule('blog');

    $this->actingAs(User::factory()->reader()->create())
        ->get(route('dashboard'))
        ->assertInertia(function (AssertableInertia $page): void {
            $labels = menuLabels($page);

            // Lo que aporte cada módulo es cosa suya; aquí lo que importa es
            // que un lector no llega a la biblioteca ni a la administración.
            expect($labels)->toContain('Panel', 'Artículos')
                ->and($labels)->not->toContain(
                    'Biblioteca',
                    'Redirecciones',
                    'Usuarios',
                    'Conexión web',
                    'Accesos',
                    'Auditoría',
                    'Configuración',
                    'Módulos',
                );
        });
});

it('marca como activa la entrada de la pantalla en la que se está', function (): void {
    requiresModule('blog');

    $this->actingAs(User::factory()->editor()->create())
        ->get(route('categories.index'))
        ->assertInertia(function (AssertableInertia $page): void {
            /** @var list<array{items: list<array{label: string, active: bool}>}> $menu */
            $menu = $page->toArray()['props']['menu'];

            $active = [];

            foreach ($menu as $group) {
                foreach ($group['items'] as $item) {
                    if ($item['active']) {
                        $active[] = $item['label'];
                    }
                }
            }

            expect($active)->toBe(['Categorías']);
        });
});

it('no comparte menú con quien no ha entrado', function (): void {
    $this->get(route('login'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('menu', []));
});
