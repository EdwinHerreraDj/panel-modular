<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\ModuleActivator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;

/**
 * La pantalla de módulos: qué hay instalado y qué está encendido.
 */
beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * Un `.env` de usar y tirar, para no reescribir el de quien desarrolla.
 */
function temporaryEnv(string $contents): string
{
    $path = sys_get_temp_dir().'/panel-modulos-'.uniqid().'.env';
    file_put_contents($path, $contents);

    app()->useEnvironmentPath(dirname($path));
    app()->loadEnvironmentFrom(basename($path));

    return $path;
}

it('enseña los módulos instalados y cuáles están activos', function (): void {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('modules.index'))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page): void {
            /** @var list<array{key: string, name: string, active: bool}> $modules */
            $modules = $page->toArray()['props']['modules'];
            $byKey = array_column($modules, 'active', 'key');

            expect($page->toArray()['component'])->toBe('Modules/Index')
                ->and($byKey)->toHaveKey('blog', true)
                ->and($byKey)->toHaveKey('catalogo', true);
        });
});

it('cuenta lo que aporta cada módulo', function (): void {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('modules.index'))
        ->assertInertia(function (AssertableInertia $page): void {
            /** @var list<array{key: string, contributes: array<string, int>}> $modules */
            $modules = $page->toArray()['props']['modules'];
            $catalogo = array_values(array_filter($modules, fn (array $m): bool => $m['key'] === 'catalogo'))[0];

            expect($catalogo['contributes']['permisos'])->toBe(3)
                ->and($catalogo['contributes']['pantallas'])->toBe(1)
                ->and($catalogo['contributes']['endpoints'])->toBe(2)
                ->and($catalogo['contributes']['tipos'])->toBe(1);
        });
});

it('la reserva al superadministrador', function (string $role): void {
    $user = User::factory()->{$role}()->create();

    $this->actingAs($user)->get(route('modules.index'))->assertForbidden();
    $this->actingAs($user)->put(route('modules.update'), ['enabled' => []])->assertForbidden();
})->with(['editor', 'reviewer', 'author', 'reader']);

it('no aparece en el menú de quien no es superadministrador', function (): void {
    $this->actingAs(User::factory()->editor()->create())
        ->get(route('dashboard'))
        ->assertInertia(function (AssertableInertia $page): void {
            /** @var list<array{items: list<array{label: string}>}> $menu */
            $menu = $page->toArray()['props']['menu'];
            $labels = array_merge(...array_map(fn (array $g): array => array_column($g['items'], 'label'), $menu));

            expect($labels)->not->toContain('Módulos');
        });
});

it('sí aparece en el menú del superadministrador', function (): void {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('dashboard'))
        ->assertInertia(function (AssertableInertia $page): void {
            /** @var list<array{items: list<array{label: string}>}> $menu */
            $menu = $page->toArray()['props']['menu'];
            $labels = array_merge(...array_map(fn (array $g): array => array_column($g['items'], 'label'), $menu));

            expect($labels)->toContain('Módulos');
        });
});

/*
|--------------------------------------------------------------------------
| La escritura del .env
|--------------------------------------------------------------------------
*/

it('reescribe la línea sin tocar el resto del archivo', function (): void {
    $path = temporaryEnv("APP_NAME=Panel\nMODULES_ENABLED=blog\nDB_DATABASE=algo\n");

    app(ModuleActivator::class)->writeEnabled(['blog', 'catalogo']);

    expect(file_get_contents($path))
        ->toBe("APP_NAME=Panel\nMODULES_ENABLED=blog,catalogo\nDB_DATABASE=algo\n");

    unlink($path);
});

it('añade la línea si el archivo no la tenía', function (): void {
    $path = temporaryEnv("APP_NAME=Panel\n");

    app(ModuleActivator::class)->writeEnabled(['catalogo']);

    expect(file_get_contents($path))->toBe("APP_NAME=Panel\nMODULES_ENABLED=catalogo\n");

    unlink($path);
});

it('deja la línea vacía al apagarlos todos', function (): void {
    $path = temporaryEnv("MODULES_ENABLED=blog,catalogo\n");

    app(ModuleActivator::class)->writeEnabled([]);

    expect(file_get_contents($path))->toBe("MODULES_ENABLED=\n");

    unlink($path);
});

it('se niega a activar un módulo que no existe en el disco', function (): void {
    $path = temporaryEnv("MODULES_ENABLED=blog\n");

    expect(fn () => app(ModuleActivator::class)->apply(['inventado']))
        ->toThrow(RuntimeException::class, 'no existe en modules/');

    // Y no ha tocado el archivo.
    expect(file_get_contents($path))->toBe("MODULES_ENABLED=blog\n");

    unlink($path);
});

it('avisa si el .env es de solo lectura en lugar de fallar al pulsar', function (): void {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('modules.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('envIsWritable'));
});
