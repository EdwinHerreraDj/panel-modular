<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;
use Modules\Blog\Models\Article;

/**
 * El panel de inicio se compone: el núcleo pone el saludo, la actividad y las
 * cifras de la plataforma, y cada módulo activo aporta sus tarjetas, sus avisos
 * y sus bloques, ya resueltos para quien mira.
 */
beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * @return array<string, mixed>
 */
function dashboardProps(AssertableInertia $page): array
{
    /** @var array<string, mixed> $props */
    $props = $page->toArray()['props'];

    return $props;
}

it('enseña al editor las tarjetas del blog con sus cifras', function (): void {
    requiresModule('blog');

    Article::factory()->count(3)->published()->create();
    Article::factory()->count(2)->create(); // borradores

    $this->actingAs(User::factory()->editor()->create())
        ->get(route('dashboard'))
        ->assertInertia(function (AssertableInertia $page): void {
            $cards = array_filter(
                dashboardProps($page)['cards'],
                fn (array $card): bool => str_starts_with($card['key'], 'blog.'),
            );

            expect(array_column($cards, 'count', 'key'))->toBe([
                'blog.total' => 5,
                'blog.published' => 3,
                'blog.drafts' => 2,
                'blog.scheduled' => 0,
            ]);
        });
});

it('a un autor solo le cuenta lo suyo', function (): void {
    requiresModule('blog');

    $author = User::factory()->author()->create();

    Article::factory()->count(2)->by($author)->create();
    Article::factory()->count(4)->create(); // de otras personas

    $this->actingAs($author)
        ->get(route('dashboard'))
        ->assertInertia(function (AssertableInertia $page): void {
            $cards = array_column(dashboardProps($page)['cards'], 'count', 'key');

            expect($cards['blog.total'])->toBe(2);
        })
        ->assertInertia(fn (AssertableInertia $page) => $page->where('cards.0.label', 'Artículos tuyos'));
});

it('declara los dos bloques del blog y dónde van', function (): void {
    requiresModule('blog');

    $this->actingAs(User::factory()->editor()->create())
        ->get(route('dashboard'))
        ->assertInertia(function (AssertableInertia $page): void {
            $widgets = dashboardProps($page)['widgets'];

            expect(array_column($widgets, 'area', 'component'))->toBe([
                'Blog::RecentArticles' => 'main',
                'Blog::ArticlesByStatus' => 'side',
            ]);
        });
});

it('ofrece escribir un artículo solo a quien puede crearlos', function (): void {
    requiresModule('blog');

    $this->actingAs(User::factory()->editor()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('actions.0.label', 'Escribir un artículo'));

    $this->actingAs(User::factory()->reader()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('actions', []));
});

it('avisa al revisor de lo que tiene pendiente, con su enlace', function (): void {
    requiresModule('blog');

    Article::factory()->count(2)->pendingReview()->create();

    $this->actingAs(User::factory()->reviewer()->create())
        ->get(route('dashboard'))
        ->assertInertia(function (AssertableInertia $page): void {
            $alerts = dashboardProps($page)['alerts'];

            expect($alerts)->toHaveCount(1)
                ->and($alerts[0]['key'])->toBe('blog.review')
                ->and($alerts[0]['text'])->toBe('Hay 2 artículos esperando revisión.')
                ->and($alerts[0]['action_href'])->toBe(route('articles.index', ['status' => 'pending_review']));
        });
});

it('al autor le dice que espera revisión, sin ofrecerle revisarlo', function (): void {
    requiresModule('blog');

    $author = User::factory()->author()->create();
    Article::factory()->by($author)->pendingReview()->create();

    $this->actingAs($author)
        ->get(route('dashboard'))
        ->assertInertia(function (AssertableInertia $page): void {
            $alerts = dashboardProps($page)['alerts'];

            expect($alerts)->toHaveCount(1)
                ->and($alerts[0]['key'])->toBe('blog.own-review')
                ->and($alerts[0]['action_href'])->toBeNull();
        });
});

it('no aporta nada del blog a quien no puede ver artículos', function (): void {
    // Sin rol: no puede ver artículos, así que el blog no aporta nada y el
    // panel se queda con lo del núcleo en lugar de enseñar ceros.
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cards', [])
            ->where('alerts', [])
            ->where('widgets', [])
            ->where('actions', []));
});

it('reserva la actividad y las cifras de plataforma a quien ve la auditoría', function (): void {
    $this->actingAs(User::factory()->editor()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('activity', null)
            ->where('platform', null));

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('activity')
            ->has('platform.users'));
});
