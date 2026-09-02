<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\User;
use App\Support\ApiCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;
use Modules\Blog\Models\Article;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->superAdmin = User::factory()->superAdmin()->create();
});

/*
|--------------------------------------------------------------------------
| Acceso
|--------------------------------------------------------------------------
*/

it('solo el superadministrador entra en la conexión con la web', function (string $role): void {
    $user = User::factory()->{$role}()->create();

    $this->actingAs($user)->get(route('api.connection'))->assertForbidden();
})->with(['editor', 'author', 'reader']);

it('muestra la pantalla con la información de conexión', function (): void {
    requiresModule('blog');

    Article::factory()->count(2)->published()->create();

    $this->actingAs($this->superAdmin)
        ->get(route('api.connection'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Api/Index')
            // `content` es ahora una lista: una entrada por módulo activo que
            // publique algo, en lugar de un recuento de artículos escrito a mano.
            ->where('content.0.key', 'articulos_publicados')
            ->where('content.0.count', 2)
            ->where('connection.requires_key', true)
            ->has('endpoints', count(ApiCatalog::endpoints()))
            ->has('keys'));
});

/*
|--------------------------------------------------------------------------
| Claves
|--------------------------------------------------------------------------
*/

it('crea una clave y la enseña una sola vez', function (): void {
    $this->actingAs($this->superAdmin)
        ->post(route('api-keys.store'), [
            'name' => 'Web corporativa',
            'allowed_origins' => ['https://www.miweb.com'],
        ])
        ->assertRedirect(route('api.connection'));

    $plain = session('new_api_key')['plain'];

    expect($plain)->toStartWith(ApiKey::TOKEN_PREFIX);

    $key = ApiKey::query()->firstOrFail();

    expect($key->name)->toBe('Web corporativa')
        ->and($key->allowed_origins)->toBe(['https://www.miweb.com'])
        ->and($key->key_hash)->toBe(hash('sha256', $plain));

    // La pantalla siguiente la enseña…
    $this->actingAs($this->superAdmin)
        ->get(route('api.connection'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('newKey.plain', $plain));

    // …y al recargar ya no está: solo se puede copiar una vez.
    $this->actingAs($this->superAdmin)
        ->get(route('api.connection'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('newKey', null));
});

it('exige un dominio bien escrito', function (string $origin): void {
    $this->actingAs($this->superAdmin)
        ->post(route('api-keys.store'), ['name' => 'Web', 'allowed_origins' => [$origin]])
        ->assertSessionHasErrors('allowed_origins.0');

    expect(ApiKey::query()->count())->toBe(0);
})->with([
    'sin esquema' => 'www.miweb.com',
    'con ruta' => 'https://www.miweb.com/blog',
    'no es una dirección' => 'esto no vale',
]);

it('acepta un dominio con barra final y la quita', function (): void {
    $this->actingAs($this->superAdmin)
        ->post(route('api-keys.store'), ['name' => 'Web', 'allowed_origins' => ['https://www.miweb.com/']])
        ->assertSessionHasNoErrors();

    expect(ApiKey::query()->firstOrFail()->allowed_origins)->toBe(['https://www.miweb.com']);
});

it('revoca una clave y deja de servir', function (): void {
    requiresModule('blog');

    ['key' => $key, 'plain' => $plain] = ApiKey::generate('Web');

    $this->withHeaders([(string) config('api.header') => $plain])
        ->getJson('/'.trim((string) config('api.prefix'), '/').'/articulos')
        ->assertOk();

    $this->actingAs($this->superAdmin)
        ->from(route('api.connection'))
        ->post(route('api-keys.revoke', $key))
        ->assertSessionHas('success');

    $this->withHeaders([(string) config('api.header') => $plain])
        ->getJson('/'.trim((string) config('api.prefix'), '/').'/articulos')
        ->assertForbidden();
});

it('no borra una clave que sigue activa', function (): void {
    $key = ApiKey::factory()->create();

    $this->actingAs($this->superAdmin)
        ->from(route('api.connection'))
        ->delete(route('api-keys.destroy', $key))
        ->assertSessionHas('error');

    expect(ApiKey::query()->whereKey($key->id)->exists())->toBeTrue();
});

it('borra una clave ya revocada', function (): void {
    $key = ApiKey::factory()->revoked()->create();

    $this->actingAs($this->superAdmin)
        ->from(route('api.connection'))
        ->delete(route('api-keys.destroy', $key))
        ->assertSessionHas('success');

    expect(ApiKey::query()->whereKey($key->id)->exists())->toBeFalse();
});

it('el editor no puede crear ni revocar claves', function (): void {
    $editor = User::factory()->editor()->create();
    $key = ApiKey::factory()->create();

    $this->actingAs($editor)->post(route('api-keys.store'), ['name' => 'Web'])->assertForbidden();
    $this->actingAs($editor)->post(route('api-keys.revoke', $key))->assertForbidden();

    expect(ApiKey::query()->count())->toBe(1)
        ->and($key->refresh()->isRevoked())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Probador en vivo
|--------------------------------------------------------------------------
*/

it('ejecuta un endpoint y devuelve la respuesta real', function (): void {
    requiresModule('blog');

    Article::factory()->published()->create(['title' => 'Artículo de prueba']);

    $response = $this->actingAs($this->superAdmin)
        ->postJson(route('api.connection.run'), ['path' => 'articulos'])
        ->assertOk();

    expect($response->json('status'))->toBe(200)
        ->and($response->json('ok'))->toBeTrue()
        ->and($response->json('body.datos.0.titulo'))->toBe('Artículo de prueba')
        ->and($response->json('duration_ms'))->toBeInt();
});

it('el probador funciona aunque la API exija clave', function (): void {
    requiresModule('blog');

    config()->set('api.require_key', true);
    Article::factory()->published()->create();

    $this->actingAs($this->superAdmin)
        ->postJson(route('api.connection.run'), ['path' => 'articulos'])
        ->assertOk()
        ->assertJsonPath('status', 200);
});

it('pasa los parámetros al endpoint', function (): void {
    requiresModule('blog');

    Article::factory()->count(5)->published()->create();

    $response = $this->actingAs($this->superAdmin)
        ->postJson(route('api.connection.run'), [
            'path' => 'articulos',
            'query' => ['por_pagina' => '2'],
        ])
        ->assertOk();

    expect($response->json('body.datos'))->toHaveCount(2)
        ->and($response->json('body.paginacion.total'))->toBe(5);
});

it('devuelve el error tal cual cuando el endpoint falla', function (): void {
    requiresModule('blog');

    $response = $this->actingAs($this->superAdmin)
        ->postJson(route('api.connection.run'), ['path' => 'articulos/no-existe'])
        ->assertOk();

    expect($response->json('status'))->toBe(404)
        ->and($response->json('ok'))->toBeFalse()
        ->and($response->json('body.error.codigo'))->toBe('no_encontrado');
});

it('el probador no alcanza direcciones fuera de la API pública', function (string $path): void {
    $this->actingAs($this->superAdmin)
        ->postJson(route('api.connection.run'), ['path' => $path])
        ->assertStatus(422);
})->with([
    'panel' => 'panel',
    'usuarios' => '../usuarios',
    'inventada' => 'articulos/algo/mas',
]);

it('deja la petición original en su sitio tras probar', function (): void {
    requiresModule('blog');

    Article::factory()->published()->create();

    // Si el probador no restaurase la petición, la respuesta siguiente
    // se generaría con la simulada y esto fallaría.
    $this->actingAs($this->superAdmin)
        ->postJson(route('api.connection.run'), ['path' => 'articulos'])
        ->assertOk();

    $this->actingAs($this->superAdmin)
        ->get(route('api.connection'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Api/Index'));
});
