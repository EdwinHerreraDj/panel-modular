<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\User;
use App\Support\SuperAdminGuard;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

/**
 * Segunda barrera para que la aplicación no se quede sin administración.
 *
 * Por las rutas actuales no debería llegar a activarse nunca —lo impiden antes
 * la policy (nadie se elimina a sí mismo) y el Form Request (nadie cambia su
 * propio rol o estado)—, pero se comprueba aquí para que siga cumpliendo su
 * función cuando aparezcan acciones masivas o comandos de consola.
 */
beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('detecta al último superadministrador activo', function (): void {
    $onlyOne = User::factory()->superAdmin()->create();

    expect(SuperAdminGuard::isLastActive($onlyOne))->toBeTrue();
});

it('no lo considera el último si hay otro activo', function (): void {
    $first = User::factory()->superAdmin()->create();
    User::factory()->superAdmin()->create();

    expect(SuperAdminGuard::isLastActive($first))->toBeFalse();
});

it('no cuenta a los superadministradores suspendidos', function (): void {
    $active = User::factory()->superAdmin()->create();
    User::factory()->superAdmin()->suspended()->create();

    expect(SuperAdminGuard::isLastActive($active))->toBeTrue()
        ->and(SuperAdminGuard::activeCount())->toBe(1);
});

it('no cuenta a los superadministradores en la papelera', function (): void {
    $active = User::factory()->superAdmin()->create();
    User::factory()->superAdmin()->create()->delete();

    expect(SuperAdminGuard::activeCount())->toBe(1)
        ->and(SuperAdminGuard::isLastActive($active))->toBeTrue();
});

it('no protege a quien no es superadministrador', function (): void {
    $editor = User::factory()->editor()->create();

    expect(SuperAdminGuard::isLastActive($editor))->toBeFalse();
});

it('lanza un error de validación sobre el campo indicado', function (): void {
    $onlyOne = User::factory()->superAdmin()->create();

    expect(fn () => SuperAdminGuard::assertNotLastActive($onlyOne, 'role', 'No queda nadie más.'))
        ->toThrow(ValidationException::class);

    try {
        SuperAdminGuard::assertNotLastActive($onlyOne, 'role', 'No queda nadie más.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('role')
            ->and($exception->errors()['role'][0])->toBe('No queda nadie más.');
    }
});

it('no lanza nada cuando queda otro superadministrador', function (): void {
    $first = User::factory()->superAdmin()->create();
    User::factory()->superAdmin()->create();

    SuperAdminGuard::assertNotLastActive($first, 'role', 'No queda nadie más.');

    expect($first->fresh()->roleName())->toBe(RoleName::SuperAdmin);
});
