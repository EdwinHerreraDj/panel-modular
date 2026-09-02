<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Blog\Enums\BlogPermission;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('concede al superadministrador cualquier permiso', function (): void {
    requiresModule('blog');

    $user = User::factory()->superAdmin()->create();

    expect($user->can(Permission::UsersManage->value))->toBeTrue()
        ->and($user->can(Permission::ApiKeysManage->value))->toBeTrue()
        ->and($user->can(BlogPermission::ArticlesPublish->value))->toBeTrue()
        // Incluso un permiso que todavía no existe: el Gate::before lo resuelve antes.
        ->and($user->can('permiso.inventado'))->toBeTrue();
});

it('da al editor el control del contenido pero no de la administración', function (): void {
    requiresModule('blog');

    $user = User::factory()->editor()->create();

    expect($user->can(BlogPermission::ArticlesPublish->value))->toBeTrue()
        ->and($user->can(BlogPermission::ArticlesUpdateAny->value))->toBeTrue()
        ->and($user->can(BlogPermission::CategoriesManage->value))->toBeTrue()
        ->and($user->can(Permission::UsersManage->value))->toBeFalse()
        ->and($user->can(Permission::ApiKeysManage->value))->toBeFalse();
});

it('permite al revisor aprobar pero no publicar', function (): void {
    requiresModule('blog');

    $user = User::factory()->reviewer()->create();

    expect($user->can(BlogPermission::ArticlesApprove->value))->toBeTrue()
        ->and($user->can(BlogPermission::ArticlesPublish->value))->toBeFalse()
        ->and($user->can(BlogPermission::ArticlesCreate->value))->toBeFalse();
});

it('permite al autor crear y editar lo suyo, pero no publicar', function (): void {
    requiresModule('blog');

    $user = User::factory()->author()->create();

    expect($user->can(BlogPermission::ArticlesCreate->value))->toBeTrue()
        ->and($user->can(BlogPermission::ArticlesUpdateOwn->value))->toBeTrue()
        ->and($user->can(BlogPermission::ArticlesUpdateAny->value))->toBeFalse()
        ->and($user->can(BlogPermission::ArticlesPublish->value))->toBeFalse()
        ->and($user->can(BlogPermission::ArticlesApprove->value))->toBeFalse();
});

it('deja al lector solo consultar', function (): void {
    requiresModule('blog');

    $user = User::factory()->reader()->create();

    expect($user->can(BlogPermission::ArticlesView->value))->toBeTrue()
        ->and($user->can(BlogPermission::ArticlesCreate->value))->toBeFalse()
        ->and($user->can(Permission::MediaManage->value))->toBeFalse();
});

it('deniega la gestión de usuarios a todos los roles menos al superadministrador', function (string $state): void {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)->get(route('users.index'))->assertForbidden();
    $this->actingAs($user)->get(route('users.create'))->assertForbidden();
    $this->actingAs($user)->post(route('users.store'), [])->assertForbidden();
})->with(['editor', 'reviewer', 'author', 'reader']);

it('deniega editar a otra persona a quien no es superadministrador', function (): void {
    $editor = User::factory()->editor()->create();
    $other = User::factory()->author()->create();

    $this->actingAs($editor)->get(route('users.edit', $other))->assertForbidden();

    $this->actingAs($editor)
        ->put(route('users.update', $other), [
            'name' => 'Cambiado',
            'email' => $other->email,
            'role' => RoleName::Editor->value,
            'status' => UserStatus::Active->value,
        ])
        ->assertForbidden();

    expect($other->fresh()->name)->not->toBe('Cambiado');
});

it('un usuario no puede ascenderse a sí mismo cambiando su rol', function (): void {
    $author = User::factory()->author()->create();

    $this->actingAs($author)
        ->put(route('users.update', $author), [
            'name' => $author->name,
            'email' => $author->email,
            'role' => RoleName::SuperAdmin->value,
            'status' => UserStatus::Active->value,
        ])
        ->assertForbidden();

    expect($author->fresh()->roleName())->toBe(RoleName::Author);
});

it('el superadministrador tampoco puede cambiar su propio rol', function (): void {
    $superAdmin = User::factory()->superAdmin()->create();
    User::factory()->superAdmin()->create(); // otro, para no ser el último

    $this->actingAs($superAdmin)
        ->from(route('users.edit', $superAdmin))
        ->put(route('users.update', $superAdmin), [
            'name' => $superAdmin->name,
            'email' => $superAdmin->email,
            'role' => RoleName::Reader->value,
            'status' => UserStatus::Active->value,
        ])
        ->assertSessionHasErrors(['role' => 'No puedes cambiar tu propio rol.']);

    expect($superAdmin->fresh()->roleName())->toBe(RoleName::SuperAdmin);
});

it('el superadministrador no puede suspenderse a sí mismo', function (): void {
    $superAdmin = User::factory()->superAdmin()->create();
    User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->from(route('users.edit', $superAdmin))
        ->put(route('users.update', $superAdmin), [
            'name' => $superAdmin->name,
            'email' => $superAdmin->email,
            'role' => RoleName::SuperAdmin->value,
            'status' => UserStatus::Suspended->value,
        ])
        ->assertSessionHasErrors(['status' => 'No puedes cambiar tu propio estado.']);

    expect($superAdmin->fresh()->status)->toBe(UserStatus::Active);
});

it('el superadministrador no puede enviarse a sí mismo a la papelera', function (): void {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->delete(route('users.destroy', $superAdmin))
        ->assertForbidden();

    expect($superAdmin->fresh()->trashed())->toBeFalse();
});

it('permite degradar a un superadministrador mientras quede otro activo', function (): void {
    $first = User::factory()->superAdmin()->create();
    $second = User::factory()->superAdmin()->create();

    $this->actingAs($second)
        ->put(route('users.update', $first), [
            'name' => $first->name,
            'email' => $first->email,
            'role' => RoleName::Editor->value,
            'status' => UserStatus::Active->value,
        ])
        ->assertSessionHasNoErrors();

    expect($first->fresh()->roleName())->toBe(RoleName::Editor);
});

it('no permite degradar al último superadministrador activo', function (): void {
    $onlyOne = User::factory()->superAdmin()->create();
    $another = User::factory()->superAdmin()->create();

    // Queda una sola cuenta con control total.
    $another->assignSingleRole(RoleName::Editor);

    $this->actingAs($another->fresh())
        ->from(route('users.edit', $onlyOne))
        ->put(route('users.update', $onlyOne), [
            'name' => $onlyOne->name,
            'email' => $onlyOne->email,
            'role' => RoleName::Editor->value,
            'status' => UserStatus::Active->value,
        ])
        // El editor ya no puede gestionar usuarios: se corta antes.
        ->assertForbidden();

    // Y si quien lo intenta sí tiene permisos, lo frena el guardián.
    $another->assignSingleRole(RoleName::SuperAdmin);
    $onlyOne->assignSingleRole(RoleName::Editor);

    $this->actingAs($onlyOne->fresh())
        ->from(route('users.edit', $another))
        ->put(route('users.update', $another), [
            'name' => $another->name,
            'email' => $another->email,
            'role' => RoleName::Editor->value,
            'status' => UserStatus::Active->value,
        ])
        ->assertForbidden();

    expect($another->fresh()->roleName())->toBe(RoleName::SuperAdmin);
});

it('no permite suspender al último superadministrador activo', function (): void {
    $onlyOne = User::factory()->superAdmin()->create();
    $other = User::factory()->superAdmin()->create();

    $this->actingAs($other)
        ->from(route('users.edit', $onlyOne))
        ->put(route('users.update', $onlyOne), [
            'name' => $onlyOne->name,
            'email' => $onlyOne->email,
            'role' => RoleName::SuperAdmin->value,
            'status' => UserStatus::Suspended->value,
        ])
        ->assertSessionHasNoErrors();

    // Ahora `$other` es la única cuenta activa con control total.
    $this->actingAs($other)
        ->from(route('users.index'))
        ->delete(route('users.destroy', $other))
        ->assertForbidden();

    expect($other->fresh()->trashed())->toBeFalse();
});

it('no permite enviar a la papelera al último superadministrador activo', function (): void {
    $onlyOne = User::factory()->superAdmin()->create();
    $second = User::factory()->superAdmin()->create();

    // El editor no llega ni a intentarlo.
    $editor = User::factory()->editor()->create();
    $this->actingAs($editor)->delete(route('users.destroy', $onlyOne))->assertForbidden();

    // `$second` elimina a `$onlyOne`: queda él, así que se permite.
    $this->actingAs($second)
        ->delete(route('users.destroy', $onlyOne))
        ->assertSessionHasNoErrors();

    expect($onlyOne->fresh()->trashed())->toBeTrue();

    // Restaurado y degradado, `$second` pasa a ser el único: el guardián lo protege.
    $onlyOne->restore();
    $onlyOne->assignSingleRole(RoleName::Editor);

    $this->actingAs($second)
        ->from(route('users.index'))
        ->delete(route('users.destroy', $second))
        ->assertForbidden();

    expect($second->fresh()->trashed())->toBeFalse();
});
