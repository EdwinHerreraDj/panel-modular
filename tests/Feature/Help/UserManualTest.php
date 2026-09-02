<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\User;
use App\Support\UserManual;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;
use Modules\Blog\Enums\ArticleStatus;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * Claves de las secciones que ve esta persona.
 *
 * @return list<string>
 */
function sectionKeys(User $user): array
{
    return array_map(
        fn (array $section): string => (string) $section['key'],
        UserManual::forUser($user),
    );
}

/*
|--------------------------------------------------------------------------
| Cada rol ve lo que le sirve
|--------------------------------------------------------------------------
*/

it('el superadministrador ve el manual completo', function (): void {
    $keys = sectionKeys(User::factory()->superAdmin()->create());

    expect($keys)->toHaveCount(count(UserManual::sections()))
        ->and($keys)->toContain('usuarios', 'configuracion');
});

it('el autor no ve las secciones que no puede usar', function (): void {
    requiresModule('blog');

    $keys = sectionKeys(User::factory()->author()->create());

    expect($keys)
        ->toContain('primeros-pasos', 'escribir', 'estados', 'dudas')
        ->not->toContain('usuarios')
        ->not->toContain('configuracion');
});

it('el lector ve al menos lo básico', function (): void {
    $keys = sectionKeys(User::factory()->reader()->create());

    // Nadie se queda sin ayuda: lo que vale para cualquiera no lleva permiso.
    expect($keys)->toContain('primeros-pasos', 'papelera', 'dudas');
});

/*
|--------------------------------------------------------------------------
| La pantalla
|--------------------------------------------------------------------------
*/

it('la ayuda está disponible para cualquiera que entre al panel', function (string $role): void {
    $user = User::factory()->{$role}()->create();

    $this->actingAs($user)
        ->get(route('help.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Help/Index')
            ->has('sections')
            ->has('siteName'));
})->with(['superAdmin', 'editor', 'author', 'reader']);

it('un invitado no entra a la ayuda', function (): void {
    $this->get(route('help.index'))->assertRedirect(route('login'));
});

/*
|--------------------------------------------------------------------------
| El contenido
|--------------------------------------------------------------------------
*/

it('explica los seis estados de un artículo', function (): void {
    requiresModule('blog');

    $markdown = UserManual::markdown();

    foreach (ArticleStatus::cases() as $status) {
        expect($markdown)->toContain($status->label());
    }
});

it('explica los cinco roles', function (): void {
    $markdown = UserManual::markdown();

    foreach (RoleName::cases() as $role) {
        expect($markdown)->toContain($role->label());
    }
});

it('responde a las dudas que más se preguntan', function (string $duda): void {
    expect(UserManual::markdown())->toContain($duda);
})->with([
    'no se ve en la web' => 'no se ve en la web',
    'la dirección no cambia' => 'la dirección sigue igual',
    'no deja borrar una imagen' => 'No me deja borrar una imagen',
    'trabajo perdido' => 'He perdido lo que estaba escribiendo',
]);

it('avisa de que el borrado definitivo no tiene vuelta atrás', function (): void {
    expect(UserManual::markdown())->toContain('no tiene vuelta atrás');
});

it('no usa jerga técnica que el cliente no tiene por qué conocer', function (string $palabra): void {
    expect(mb_strtolower(UserManual::markdown()))->not->toContain(mb_strtolower($palabra));
})->with(['slug', 'endpoint', 'middleware', 'caché de Redis', 'polimórfic', 'CORS']);

/*
|--------------------------------------------------------------------------
| Descarga y comando
|--------------------------------------------------------------------------
*/

it('se descarga como archivo con el nombre del blog', function (): void {
    $response = $this->actingAs(User::factory()->editor()->create())
        ->get(route('help.download'))
        ->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('attachment')
        ->and($response->headers->get('Content-Type'))->toContain('text/markdown')
        ->and($response->content())->toContain('# Manual de');
});

it('el comando escribe el manual en el repositorio', function (): void {
    requiresModule('blog');

    $path = 'tests/tmp/MANUAL-PRUEBA.md';

    $this->artisan('panel:manual', ['--ruta' => $path])->assertSuccessful();

    expect(file_exists(base_path($path)))->toBeTrue()
        ->and(file_get_contents(base_path($path)))->toContain('Escribir un artículo');

    unlink(base_path($path));
    rmdir(base_path('tests/tmp'));
});
