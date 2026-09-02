<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Modules\Blog\Enums\ArticleStatus;
use Modules\Blog\Models\Article;

/**
 * Red de seguridad para alojamientos sin cron: la programación debe funcionar
 * aunque el planificador no llegue a ejecutarse nunca.
 */
beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->editor = User::factory()->editor()->create();
    Cache::flush();
});

it('publica lo programado durante una visita al panel', function (): void {
    $article = Article::factory()->scheduled()->create();
    $article->forceFill(['scheduled_for' => now()->subMinute()])->save();

    $this->actingAs($this->editor)->get(route('dashboard'))->assertOk();

    expect($article->fresh()->status)->toBe(ArticleStatus::Published)
        ->and($article->fresh()->published_at)->not->toBeNull();
});

it('no toca los artículos cuya fecha aún no ha llegado', function (): void {
    $article = Article::factory()->scheduled()->create();

    $this->actingAs($this->editor)->get(route('dashboard'))->assertOk();

    expect($article->fresh()->status)->toBe(ArticleStatus::Scheduled);
});

it('comprueba como mucho una vez por minuto', function (): void {
    $this->actingAs($this->editor)->get(route('dashboard'));

    // La primera visita deja la marca; hasta que caduque no se vuelve a mirar.
    // El cerrojo es uno por tarea diferida, así que lleva la clave del módulo.
    expect(Cache::has('deferred:blog.publish-due'))->toBeTrue();

    $article = Article::factory()->scheduled()->create();
    $article->forceFill(['scheduled_for' => now()->subMinute()])->save();

    $this->actingAs($this->editor)->get(route('dashboard'));

    expect($article->fresh()->status)->toBe(ArticleStatus::Scheduled);

    // Pasado el minuto, la siguiente visita sí lo publica.
    Cache::forget('deferred:blog.publish-due');

    $this->actingAs($this->editor)->get(route('dashboard'));

    expect($article->fresh()->status)->toBe(ArticleStatus::Published);
});

it('se puede desactivar cuando el alojamiento sí tiene cron', function (): void {
    config()->set('articles.publish_on_request', false);

    $article = Article::factory()->scheduled()->create();
    $article->forceFill(['scheduled_for' => now()->subMinute()])->save();

    $this->actingAs($this->editor)->get(route('dashboard'))->assertOk();

    expect($article->fresh()->status)->toBe(ArticleStatus::Scheduled);
});

it('una visita normal no publica nada si no hay nada pendiente', function (): void {
    Article::factory()->count(3)->create();

    $this->actingAs($this->editor)->get(route('dashboard'))->assertOk();

    expect(Article::query()->where('status', ArticleStatus::Published->value)->count())->toBe(0);
});
