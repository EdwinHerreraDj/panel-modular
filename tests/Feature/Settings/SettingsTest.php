<?php

declare(strict_types=1);

use App\Enums\ActivityEvent;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->superAdmin = User::factory()->superAdmin()->create();
});

// `settingsPayload()` vive en tests/Pest.php: lo comparten varias pruebas.

it('crea la configuración por defecto la primera vez', function (): void {
    expect(Setting::query()->count())->toBe(0);

    $setting = Setting::current();

    expect(Setting::query()->count())->toBe(1)
        ->and($setting->site_name)->not->toBeEmpty()
        ->and($setting->title_template)->toBe(':title | :blog')
        ->and($setting->is_indexable)->toBeTrue();
});

it('muestra la pantalla de configuración al superadministrador', function (): void {
    $this->actingAs($this->superAdmin)
        ->get(route('settings.edit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Settings/Edit')
            ->has('settings')
            ->has('imageConstraints.logo.hint')
            ->has('localeOptions')
            ->has('networkOptions'));
});

it('guarda la configuración y lo deja en la auditoría', function (): void {
    Setting::current();

    $this->actingAs($this->superAdmin)
        ->post(route('settings.update'), settingsPayload())
        ->assertRedirect(route('settings.edit'))
        ->assertSessionHasNoErrors();

    $setting = Setting::query()->firstOrFail();

    expect($setting->site_name)->toBe('Blog de Alminares')
        ->and($setting->public_url)->toBe('https://www.alminares.es/blog');

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => (new Setting)->getMorphClass(),
        'subject_id' => $setting->id,
        'event' => ActivityEvent::Updated->value,
        'user_id' => $this->superAdmin->id,
    ]);
});

it('cachea atributos y no el modelo, para que sobreviva a la serialización', function (): void {
    Setting::current();

    $cached = Cache::get(Setting::CACHE_KEY);

    // Si aquí hubiera un modelo, los almacenes que serializan (base de datos,
    // Redis, archivo) devolverían un __PHP_Incomplete_Class al recuperarlo.
    expect($cached)->toBeArray()
        ->and($cached)->toHaveKey('site_name');

    // Se simula el viaje completo por un almacén que serializa.
    /** @var array<string, mixed> $roundTripped */
    $roundTripped = unserialize(serialize($cached));

    Cache::forever(Setting::CACHE_KEY, $roundTripped);

    expect(Setting::current())->toBeInstanceOf(Setting::class)
        ->and(Setting::current()->site_name)->toBe($cached['site_name'])
        ->and(Setting::current()->exists)->toBeTrue();
});

it('invalida la caché al guardar', function (): void {
    $setting = Setting::current();

    expect(Cache::has(Setting::CACHE_KEY))->toBeTrue();

    $this->actingAs($this->superAdmin)
        ->post(route('settings.update'), settingsPayload(['site_name' => 'Nombre nuevo']));

    // La entrada anterior se ha borrado y la siguiente lectura trae el dato nuevo.
    expect(Setting::current()->site_name)->toBe('Nombre nuevo')
        ->and($setting->fresh()->site_name)->toBe('Nombre nuevo');
});

it('exige los campos obligatorios en español', function (): void {
    Setting::current();

    $this->actingAs($this->superAdmin)
        ->from(route('settings.edit'))
        ->post(route('settings.update'), [])
        ->assertSessionHasErrors([
            'site_name' => 'El campo nombre del sitio es obligatorio.',
            'locale' => 'El campo idioma es obligatorio.',
            'timezone' => 'El campo zona horaria es obligatorio.',
        ]);
});

it('exige que la plantilla de título contenga el marcador', function (): void {
    Setting::current();

    $this->actingAs($this->superAdmin)
        ->from(route('settings.edit'))
        ->post(route('settings.update'), settingsPayload(['title_template' => 'Solo el nombre del blog']))
        ->assertSessionHasErrors([
            'title_template' => 'La plantilla debe contener :title, que se sustituye por el título de cada página.',
        ]);
});

it('rechaza una URL pública que no lo sea', function (): void {
    Setting::current();

    $this->actingAs($this->superAdmin)
        ->from(route('settings.edit'))
        ->post(route('settings.update'), settingsPayload(['public_url' => 'esto-no-es-una-url']))
        ->assertSessionHasErrors([
            'public_url' => 'Introduce una URL completa, por ejemplo: https://www.miempresa.es',
        ]);
});

it('limita la longitud de los metadatos', function (): void {
    Setting::current();

    $this->actingAs($this->superAdmin)
        ->from(route('settings.edit'))
        ->post(route('settings.update'), settingsPayload([
            'meta_description' => str_repeat('a', 161),
        ]))
        ->assertSessionHasErrors('meta_description');
});

it('guarda los perfiles sociales y valida cada uno', function (): void {
    Setting::current();

    $this->actingAs($this->superAdmin)
        ->post(route('settings.update'), settingsPayload([
            'social_profiles' => [
                ['network' => 'instagram', 'url' => 'https://www.instagram.com/alminares'],
                ['network' => 'linkedin', 'url' => 'https://www.linkedin.com/company/alminares'],
            ],
        ]))
        ->assertSessionHasNoErrors();

    expect(Setting::query()->firstOrFail()->social_profiles)->toHaveCount(2);

    $this->actingAs($this->superAdmin)
        ->from(route('settings.edit'))
        ->post(route('settings.update'), settingsPayload([
            'social_profiles' => [
                ['network' => 'red-inventada', 'url' => 'http://sin-cifrar.test'],
            ],
        ]))
        ->assertSessionHasErrors([
            'social_profiles.0.network',
            'social_profiles.0.url',
        ]);
});

it('deniega el acceso a la configuración a quien no es superadministrador', function (string $state): void {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)->get(route('settings.edit'))->assertForbidden();
    $this->actingAs($user)->post(route('settings.update'), settingsPayload())->assertForbidden();
})->with(['editor', 'reviewer', 'author', 'reader']);

it('aplica la plantilla al componer el título de una página', function (): void {
    $setting = Setting::current();
    $setting->update(['site_name' => 'Blog de Alminares', 'title_template' => ':title · :blog']);

    expect($setting->formatTitle('Cómo elegir persianas'))->toBe('Cómo elegir persianas · Blog de Alminares')
        ->and($setting->formatTitle(null))->toBe('Blog de Alminares');
});
