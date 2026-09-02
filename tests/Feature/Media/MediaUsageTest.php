<?php

declare(strict_types=1);

use App\Actions\Media\SyncMediaUsage;
use App\Models\Media;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;
use Modules\Blog\Models\Article;

/**
 * Comprobación de uso antes de eliminar: la biblioteca no debe dejar borrar
 * algo que está publicado.
 */
beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->editor = User::factory()->editor()->create();
    Storage::fake('public');
});

it('registra el uso al asignar la imagen destacada de un artículo', function (): void {
    requiresModule('blog');

    $media = Media::factory()->create();
    $article = Article::factory()->by($this->editor)->create();

    $this->actingAs($this->editor)
        ->post(route('articles.update', $article), articlePayload([
            'title' => $article->title,
            'featured_media_id' => $media->id,
        ]))
        ->assertSessionHasNoErrors();

    expect($article->fresh()->featured_media_id)->toBe($media->id);

    $this->assertDatabaseHas('media_usages', [
        'media_id' => $media->id,
        'usable_type' => (new Article)->getMorphClass(),
        'usable_id' => $article->id,
        'role' => 'featured',
    ]);
});

it('libera el uso al quitar la imagen destacada', function (): void {
    requiresModule('blog');

    $media = Media::factory()->create();
    $article = Article::factory()->by($this->editor)->create();

    $this->actingAs($this->editor)->post(route('articles.update', $article), articlePayload([
        'title' => $article->title,
        'featured_media_id' => $media->id,
    ]));

    $this->actingAs($this->editor)->post(route('articles.update', $article), articlePayload([
        'title' => $article->title,
        'featured_media_id' => null,
    ]));

    $this->assertDatabaseMissing('media_usages', [
        'media_id' => $media->id,
        'usable_id' => $article->id,
    ]);
});

it('no registra dos veces el mismo uso al guardar varias veces', function (): void {
    requiresModule('blog');

    $media = Media::factory()->create();
    $article = Article::factory()->by($this->editor)->create();

    foreach (range(1, 3) as $ignored) {
        $this->actingAs($this->editor)->post(route('articles.update', $article), articlePayload([
            'title' => $article->title,
            'featured_media_id' => $media->id,
        ]));
    }

    expect($media->fresh()->usages()->count())->toBe(1);
});

it('impide eliminar un archivo que se está usando', function (): void {
    requiresModule('blog');

    $media = Media::factory()->create(['name' => 'Portada']);
    $article = Article::factory()->by($this->editor)->create(['title' => 'Guía de persianas']);

    app(SyncMediaUsage::class)->set($article, 'featured', $media->id);

    $this->actingAs($this->editor)
        ->from(route('media.index'))
        ->delete(route('media.destroy', $media))
        ->assertSessionHasErrors('media');

    expect(session('errors')->first('media'))
        ->toContain('Guía de persianas')
        ->toContain('Imagen destacada');

    expect($media->fresh()->trashed())->toBeFalse();
});

it('permite eliminar un archivo sin usar', function (): void {
    $media = Media::factory()->create();

    $this->actingAs($this->editor)
        ->from(route('media.index'))
        ->delete(route('media.destroy', $media))
        ->assertSessionHasNoErrors();

    expect($media->fresh()->trashed())->toBeTrue();
});

it('conserva el archivo en disco al enviarlo a la papelera', function (): void {
    $media = Media::factory()->create();
    Storage::disk('public')->put($media->path, 'contenido');

    $this->actingAs($this->editor)
        ->from(route('media.index'))
        ->delete(route('media.destroy', $media));

    // El borrado es lógico: se puede restaurar.
    Storage::disk('public')->assertExists($media->path);
});

it('restaura un archivo de la papelera', function (): void {
    $media = Media::factory()->create();
    $media->delete();

    $this->actingAs($this->editor)
        ->from(route('media.index'))
        ->post(route('media.restore', $media))
        ->assertSessionHasNoErrors();

    expect($media->fresh()->trashed())->toBeFalse();
});

it('el borrado definitivo exige pasar antes por la papelera', function (): void {
    $superAdmin = User::factory()->superAdmin()->create();
    $media = Media::factory()->create();

    $this->actingAs($superAdmin)
        ->from(route('media.index'))
        ->delete(route('media.purge', $media))
        ->assertSessionHasErrors('media');

    expect(Media::query()->whereKey($media->id)->exists())->toBeTrue();
});

it('el borrado definitivo elimina el original y sus variantes del disco', function (): void {
    $superAdmin = User::factory()->superAdmin()->create();
    $media = Media::factory()->create();

    foreach ($media->allPaths() as $path) {
        Storage::disk('public')->put($path, 'contenido');
    }

    $media->delete();

    $this->actingAs($superAdmin)
        ->from(route('media.index'))
        ->delete(route('media.purge', $media))
        ->assertSessionHasNoErrors();

    foreach ($media->allPaths() as $path) {
        Storage::disk('public')->assertMissing($path);
    }

    expect(Media::withTrashed()->whereKey($media->id)->exists())->toBeFalse();
});

it('solo el superadministrador puede borrar definitivamente', function (): void {
    $media = Media::factory()->create();
    $media->delete();

    $this->actingAs($this->editor)->delete(route('media.purge', $media))->assertForbidden();
});

it('la duplicación de un artículo comparte la imagen y registra el uso', function (): void {
    requiresModule('blog');

    $media = Media::factory()->create();
    $article = Article::factory()->by($this->editor)->create();

    $this->actingAs($this->editor)->post(route('articles.update', $article), articlePayload([
        'title' => $article->title,
        'featured_media_id' => $media->id,
    ]));

    $this->actingAs($this->editor)->post(route('articles.duplicate', $article));

    $copy = Article::query()->where('id', '!=', $article->id)->firstOrFail();

    // Se comparte el archivo, que es justo lo que aporta la biblioteca, y los
    // dos usos quedan registrados para que no se pueda borrar por error.
    expect($copy->featured_media_id)->toBe($media->id)
        ->and($media->fresh()->usages()->count())->toBe(2);

    $this->actingAs($this->editor)
        ->from(route('media.index'))
        ->delete(route('media.destroy', $media))
        ->assertSessionHasErrors('media');
});
