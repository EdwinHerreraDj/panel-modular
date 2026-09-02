<?php

declare(strict_types=1);

use App\Actions\RecordSlugChange;
use App\Models\Redirect;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;
use Modules\Blog\Models\Article;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->superAdmin = User::factory()->superAdmin()->create();
});

/*
|--------------------------------------------------------------------------
| Captura automática
|--------------------------------------------------------------------------
*/

it('guarda una redirección al regenerar la dirección de un artículo publicado', function (): void {
    requiresModule('blog');

    // Un artículo publicado tiene la dirección congelada: cambiar el título no
    // la toca. Solo se mueve con la acción explícita de regenerarla, que es
    // justo cuando hace falta la redirección.
    $article = Article::factory()->published()->create(['title' => 'Guía de persianas']);
    $original = $article->slug;

    $article->update(['title' => 'Guía completa de persianas']);
    expect($article->refresh()->slug)->toBe($original);

    $article->regenerateSlug();

    expect($article->refresh()->slug)->not->toBe($original);

    $redirect = Redirect::query()->where('from_path', $original)->firstOrFail();

    expect($redirect->to_path)->toBe($article->slug)
        ->and($redirect->status_code)->toBe(301)
        ->and($redirect->is_automatic)->toBeTrue()
        ->and($redirect->redirectable_type)->toBe($article->getMorphClass())
        ->and($redirect->redirectable_id)->toBe($article->id)
        // La relación polimórfica devuelve el artículo, no un identificador suelto.
        ->and($redirect->redirectable?->is($article))->toBeTrue();
});

it('no guarda nada si la dirección no cambia', function (): void {
    requiresModule('blog');

    $article = Article::factory()->published()->create();

    $article->update(['excerpt' => 'Otro extracto']);

    expect(Redirect::query()->count())->toBe(0);
});

it('no guarda redirecciones de un borrador que nunca se publicó', function (): void {
    requiresModule('blog');

    // Renombrar mientras se escribe cambia la dirección muchas veces, pero
    // ninguna de esas direcciones llegó a ser pública: guardarlas llenaría de
    // basura la lista que se descarga la web.
    $draft = Article::factory()->create(['title' => 'Primer intento', 'status' => 'draft']);

    $draft->update(['title' => 'Segundo intento']);
    $draft->update(['title' => 'Tercer intento']);

    expect($draft->refresh()->slug)->toBe('tercer-intento')
        ->and(Redirect::query()->count())->toBe(0);
});

it('no guarda nada al crear un artículo', function (): void {
    requiresModule('blog');

    Article::factory()->published()->create();

    expect(Redirect::query()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Cadenas y bucles: lo que de verdad se rompe
|--------------------------------------------------------------------------
*/

it('no encadena redirecciones: siempre lleva al destino final en un salto', function (): void {
    requiresModule('blog');

    $article = Article::factory()->published()->create(['title' => 'Uno']);
    $primera = $article->slug;

    $article->update(['title' => 'Dos']);
    $article->regenerateSlug();
    $segunda = $article->refresh()->slug;

    $article->update(['title' => 'Tres']);
    $article->regenerateSlug();
    $tercera = $article->refresh()->slug;

    // Las dos direcciones viejas deben apuntar a la última, no una a la otra.
    expect(Redirect::query()->where('from_path', $primera)->value('to_path'))->toBe($tercera)
        ->and(Redirect::query()->where('from_path', $segunda)->value('to_path'))->toBe($tercera)
        ->and(Redirect::query()->count())->toBe(2);
});

it('elimina la redirección si la dirección vuelve a un valor anterior', function (): void {
    requiresModule('blog');

    $article = Article::factory()->published()->create(['title' => 'Original']);
    $original = $article->slug;

    $article->update(['title' => 'Cambiado']);
    $article->regenerateSlug();
    expect(Redirect::query()->where('from_path', $original)->exists())->toBeTrue();

    // Se vuelve atrás: ahora el artículo vive otra vez en la dirección original,
    // así que una redirección desde ahí mandaría al visitante fuera.
    $article->update(['title' => 'Original']);
    $article->regenerateSlug();

    expect($article->refresh()->slug)->toBe($original)
        ->and(Redirect::query()->where('from_path', $original)->exists())->toBeFalse();
});

it('nunca crea una redirección de una dirección a sí misma', function (): void {
    requiresModule('blog');

    $article = Article::factory()->published()->create();

    app(RecordSlugChange::class)->handle($article, 'misma', 'misma');

    expect(Redirect::query()->count())->toBe(0);
});

it('reaprovecha una redirección que ya existía para esa dirección', function (): void {
    requiresModule('blog');

    $article = Article::factory()->published()->create(['title' => 'Primero']);
    $viejo = $article->slug;

    Redirect::factory()->create(['from_path' => $viejo, 'to_path' => 'algo-antiguo']);

    $article->update(['title' => 'Segundo']);
    $article->regenerateSlug();

    expect(Redirect::query()->where('from_path', $viejo)->count())->toBe(1)
        ->and(Redirect::query()->where('from_path', $viejo)->value('to_path'))->toBe($article->refresh()->slug);
});

/*
|--------------------------------------------------------------------------
| Normalización
|--------------------------------------------------------------------------
*/

it('normaliza las direcciones para poder compararlas', function (string $entrada, string $esperado): void {
    expect(Redirect::normalize($entrada))->toBe($esperado);
})->with([
    'barras sobrantes' => ['/blog/mi-post/', 'blog/mi-post'],
    'mayúsculas' => ['/Blog/Mi-Post', 'blog/mi-post'],
    'espacios' => ['  mi-post  ', 'mi-post'],
    'cadena de consulta' => ['mi-post?utm_source=x', 'mi-post'],
    'url externa' => ['https://otra.com/algo/', 'https://otra.com/algo'],
]);

/*
|--------------------------------------------------------------------------
| Pantalla del panel
|--------------------------------------------------------------------------
*/

it('el editor puede gestionar las redirecciones', function (): void {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->get(route('redirects.index'))->assertOk();
});

it('el autor no puede', function (string $role): void {
    $user = User::factory()->{$role}()->create();

    $this->actingAs($user)->get(route('redirects.index'))->assertForbidden();
})->with(['author', 'reader']);

it('añade una redirección a mano y la normaliza', function (): void {
    $this->actingAs($this->superAdmin)
        ->from(route('redirects.index'))
        ->post(route('redirects.store'), [
            'from_path' => '/Blog/Articulo-Viejo/',
            'to_path' => '/articulo-nuevo',
            'status_code' => '301',
        ])
        ->assertSessionHasNoErrors();

    $redirect = Redirect::query()->firstOrFail();

    expect($redirect->from_path)->toBe('blog/articulo-viejo')
        ->and($redirect->to_path)->toBe('articulo-nuevo')
        ->and($redirect->is_automatic)->toBeFalse();
});

it('no acepta una redirección a sí misma', function (): void {
    $this->actingAs($this->superAdmin)
        ->from(route('redirects.index'))
        ->post(route('redirects.store'), ['from_path' => '/mismo/', 'to_path' => 'mismo'])
        ->assertSessionHasErrors('to_path');

    expect(Redirect::query()->count())->toBe(0);
});

it('no acepta dos redirecciones desde la misma dirección', function (): void {
    Redirect::factory()->create(['from_path' => 'repetida']);

    $this->actingAs($this->superAdmin)
        ->from(route('redirects.index'))
        ->post(route('redirects.store'), ['from_path' => '/Repetida/', 'to_path' => 'otra-cosa'])
        ->assertSessionHasErrors('from_path');

    expect(Redirect::query()->count())->toBe(1);
});

it('acepta una URL externa como destino', function (): void {
    $this->actingAs($this->superAdmin)
        ->from(route('redirects.index'))
        ->post(route('redirects.store'), [
            'from_path' => 'articulo-retirado',
            'to_path' => 'https://www.otraweb.com/sustituto',
        ])
        ->assertSessionHasNoErrors();

    expect(Redirect::query()->firstOrFail()->isExternal())->toBeTrue();
});

it('elimina una redirección', function (): void {
    $redirect = Redirect::factory()->create();

    $this->actingAs($this->superAdmin)
        ->from(route('redirects.index'))
        ->delete(route('redirects.destroy', $redirect))
        ->assertSessionHas('success');

    expect(Redirect::query()->count())->toBe(0);
});

it('la pantalla resume cuántas hay y de dónde vienen', function (): void {
    Redirect::factory()->count(2)->automatic()->create();
    Redirect::factory()->create(['hits' => 5]);

    $this->actingAs($this->superAdmin)
        ->get(route('redirects.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Redirects/Index')
            ->where('counts.total', 3)
            ->where('counts.automatic', 2)
            ->where('counts.used', 1));
});

/*
|--------------------------------------------------------------------------
| API
|--------------------------------------------------------------------------
*/

it('la API devuelve las redirecciones para que la web las aplique', function (): void {
    [$headers] = apiKeyHeaders();

    Redirect::factory()->create(['from_path' => 'vieja', 'to_path' => 'nueva']);
    Redirect::factory()->create(['from_path' => 'fuera', 'to_path' => 'https://otra.com/x']);

    $response = $this->withHeaders($headers)->getJson(apiUrl('redirecciones'))->assertOk();

    expect($response->json('total'))->toBe(2)
        ->and($response->json('datos.1'))->toBe([
            'de' => 'vieja',
            'a' => 'nueva',
            'codigo' => 301,
            'externa' => false,
        ])
        ->and($response->json('datos.0.externa'))->toBeTrue();
});

it('la API devuelve las direcciones para el sitemap', function (): void {
    requiresModule('blog');

    [$headers] = apiKeyHeaders();

    $published = Article::factory()->published()->create();
    Article::factory()->create(['status' => 'draft']);

    $response = $this->withHeaders($headers)->getJson(apiUrl('sitemap'))->assertOk();

    expect($response->json('articulos'))->toHaveCount(1)
        ->and($response->json('articulos.0.slug'))->toBe($published->slug)
        ->and($response->json('articulos.0.indexable'))->toBeTrue()
        ->and($response->json('generado_en'))->not->toBeNull();
});

it('el sitemap y las redirecciones exigen clave', function (string $path): void {
    $this->getJson(apiUrl($path))->assertUnauthorized();
})->with(['redirecciones', 'sitemap']);

it('la caché de la API se invalida al añadir una redirección', function (): void {
    config()->set('api.cache_seconds', 300);
    [$headers] = apiKeyHeaders();

    $this->withHeaders($headers)->getJson(apiUrl('redirecciones'));
    expect($this->withHeaders($headers)->getJson(apiUrl('redirecciones'))->headers->get('X-Cache'))->toBe('HIT');

    Redirect::factory()->create();

    $despues = $this->withHeaders($headers)->getJson(apiUrl('redirecciones'))->assertOk();

    expect($despues->headers->get('X-Cache'))->toBe('MISS')
        ->and($despues->json('total'))->toBe(1);
});
