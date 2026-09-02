<?php

declare(strict_types=1);

use App\Actions\PruneLogs;
use App\Enums\ActivityEvent;
use App\Models\ActivityLog;
use App\Models\AuthLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;
use Modules\Blog\Models\Article;
use Modules\Blog\Models\Category;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->superAdmin = User::factory()->superAdmin()->create();
});

/**
 * @param  array<string, mixed>  $overrides
 */
function activityEntry(array $overrides = []): ActivityLog
{
    return ActivityLog::create(array_merge([
        'user_id' => null,
        'subject_type' => (new Article)->getMorphClass(),
        'subject_id' => 1,
        'event' => ActivityEvent::Updated,
        'description' => 'Artículo modificado',
        'changes' => ['title' => 'Nuevo'],
        'ip_address' => '10.0.0.1',
    ], $overrides));
}

/*
|--------------------------------------------------------------------------
| Acceso
|--------------------------------------------------------------------------
*/

it('solo entra quien puede ver la auditoría', function (string $role): void {
    $user = User::factory()->{$role}()->create();

    $this->actingAs($user)->get(route('activity-logs.index'))->assertForbidden();
})->with(['editor', 'author', 'reader']);

it('muestra los cambios registrados', function (): void {
    requiresModule('blog');

    activityEntry(['description' => 'Artículo creado', 'event' => ActivityEvent::Created]);

    $this->actingAs($this->superAdmin)
        ->get(route('activity-logs.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('ActivityLogs/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.description', 'Artículo creado')
            ->where('logs.data.0.event_label', 'Creación'));
});

/*
|--------------------------------------------------------------------------
| Filtros
|--------------------------------------------------------------------------
*/

it('filtra por tipo de cambio', function (): void {
    requiresModule('blog');

    activityEntry(['event' => ActivityEvent::Created]);
    activityEntry(['event' => ActivityEvent::Deleted]);

    $this->actingAs($this->superAdmin)
        ->get(route('activity-logs.index', ['status' => 'deleted']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.event', 'deleted'));
});

it('filtra por tipo de elemento', function (): void {
    requiresModule('blog');

    activityEntry(['subject_type' => (new Article)->getMorphClass()]);
    activityEntry(['subject_type' => (new Category)->getMorphClass()]);

    $this->actingAs($this->superAdmin)
        ->get(route('activity-logs.index', ['subject' => (new Category)->getMorphClass()]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.subject_label', 'Categoría'));
});

it('ignora un tipo de elemento que no existe en lugar de fallar', function (): void {
    requiresModule('blog');

    activityEntry();

    $this->actingAs($this->superAdmin)
        ->get(route('activity-logs.index', ['subject' => 'App\\Models\\Inventado']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('logs.data', 1));
});

it('filtra por periodo', function (): void {
    requiresModule('blog');

    activityEntry(['description' => 'Reciente']);
    activityEntry(['description' => 'Antiguo'])->forceFill(['created_at' => now()->subMonths(2)])->save();

    $this->actingAs($this->superAdmin)
        ->get(route('activity-logs.index', ['extra' => 'week']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.description', 'Reciente'));
});

it('busca por descripción y por persona', function (): void {
    requiresModule('blog');

    $autor = User::factory()->author()->create(['name' => 'Lucía Ramos']);

    activityEntry(['description' => 'Artículo sobre pizzas', 'user_id' => $autor->id]);
    activityEntry(['description' => 'Otra cosa']);

    $this->actingAs($this->superAdmin)
        ->get(route('activity-logs.index', ['search' => 'pizzas']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('logs.data', 1));

    $this->actingAs($this->superAdmin)
        ->get(route('activity-logs.index', ['search' => 'Lucía']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('logs.data', 1));
});

/*
|--------------------------------------------------------------------------
| Contenido de la pantalla
|--------------------------------------------------------------------------
*/

it('traduce al español los nombres de los campos modificados y los ordena', function (): void {
    requiresModule('blog');

    activityEntry(['changes' => ['title' => 'x', 'meta_description' => 'y', 'campo_raro' => 'z']]);

    // Ordenados: MySQL no conserva el orden de las claves de una columna JSON,
    // así que sin ordenar la pantalla sería impredecible.
    $this->actingAs($this->superAdmin)
        ->get(route('activity-logs.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('logs.data.0.changes', ['campo raro', 'descripción SEO', 'título']));
});

it('no vuelca el contenido de los campos, solo sus nombres', function (): void {
    requiresModule('blog');

    activityEntry(['changes' => ['content' => '<p>Un texto secreto muy largo.</p>']]);

    $response = $this->actingAs($this->superAdmin)->get(route('activity-logs.index'));

    expect($response->content())->not->toContain('Un texto secreto muy largo');
});

it('enlaza al elemento cuando tiene pantalla propia', function (): void {
    requiresModule('blog');

    $article = Article::factory()->create();

    activityEntry(['subject_type' => (new Article)->getMorphClass(), 'subject_id' => $article->id]);

    $this->actingAs($this->superAdmin)
        ->get(route('activity-logs.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('logs.data.0.subject_url', route('articles.edit', $article->id)));
});

/*
|--------------------------------------------------------------------------
| Poda de los registros
|--------------------------------------------------------------------------
*/

it('borra los registros más antiguos que el plazo', function (): void {
    requiresModule('blog');

    config()->set('security.logs.retention_days', 30);

    activityEntry(['description' => 'Reciente']);
    activityEntry(['description' => 'Antiguo'])->forceFill(['created_at' => now()->subDays(60)])->save();

    AuthLog::create(['event' => 'login', 'email' => 'nuevo@ejemplo.com']);
    AuthLog::create(['event' => 'login', 'email' => 'viejo@ejemplo.com'])
        ->forceFill(['created_at' => now()->subDays(60)])->save();

    $result = app(PruneLogs::class)->handle();

    expect($result)->toBe(['auth' => 1, 'activity' => 1])
        ->and(ActivityLog::query()->count())->toBe(1)
        ->and(ActivityLog::query()->first()?->description)->toBe('Reciente')
        ->and(AuthLog::query()->count())->toBe(1);
});

it('no borra nada si la conservación está desactivada', function (): void {
    requiresModule('blog');

    config()->set('security.logs.retention_days', 0);

    activityEntry()->forceFill(['created_at' => now()->subYears(5)])->save();

    expect(app(PruneLogs::class)->handle())->toBe(['auth' => 0, 'activity' => 0])
        ->and(app(PruneLogs::class)->hasStale())->toBeFalse()
        ->and(ActivityLog::query()->count())->toBe(1);
});

it('detecta si hay algo que podar antes de ponerse', function (): void {
    requiresModule('blog');

    config()->set('security.logs.retention_days', 30);

    activityEntry();
    expect(app(PruneLogs::class)->hasStale())->toBeFalse();

    activityEntry()->forceFill(['created_at' => now()->subDays(60)])->save();
    expect(app(PruneLogs::class)->hasStale())->toBeTrue();
});

it('el comando informa de lo que ha borrado', function (): void {
    requiresModule('blog');

    config()->set('security.logs.retention_days', 30);

    activityEntry()->forceFill(['created_at' => now()->subDays(60)])->save();

    $this->artisan('panel:limpiar-registros')
        ->expectsOutputToContain('Borrados 0 accesos y 1 cambios')
        ->assertSuccessful();
});

it('el comando acepta un plazo propio', function (): void {
    requiresModule('blog');

    activityEntry()->forceFill(['created_at' => now()->subDays(10)])->save();

    $this->artisan('panel:limpiar-registros', ['--dias' => 5])->assertSuccessful();

    expect(ActivityLog::query()->count())->toBe(0);
});
