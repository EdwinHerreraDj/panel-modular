<?php

declare(strict_types=1);

use App\Actions\Media\SyncMediaUsage;
use App\Models\Media;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Modules\Blog\Models\Article;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->superAdmin = User::factory()->superAdmin()->create();
    Storage::fake('public');
});

/**
 * Crea un archivo en la papelera con sus ficheros escritos en disco.
 */
function trashedMedia(string $name = 'Descartada'): Media
{
    $media = Media::factory()->create(['name' => $name]);

    foreach ($media->allPaths() as $path) {
        Storage::disk('public')->put($path, 'contenido');
    }

    $media->delete();

    return $media;
}

it('elimina definitivamente un archivo de la papelera', function (): void {
    $media = trashedMedia();

    $this->actingAs($this->superAdmin)
        ->from(route('media.index'))
        ->delete(route('media.purge', $media))
        ->assertSessionHasNoErrors();

    foreach ($media->allPaths() as $path) {
        Storage::disk('public')->assertMissing($path);
    }

    expect(Media::withTrashed()->whereKey($media->id)->exists())->toBeFalse();
});

it('vacía la papelera entera', function (): void {
    $first = trashedMedia('Una');
    $second = trashedMedia('Otra');
    $active = Media::factory()->create(['name' => 'Activa']);

    $this->actingAs($this->superAdmin)
        ->from(route('media.index'))
        ->delete(route('media.trash.empty'))
        ->assertSessionHas('success');

    expect(session('success'))->toContain('2 archivos');

    expect(Media::withTrashed()->count())->toBe(1)
        ->and(Media::query()->whereKey($active->id)->exists())->toBeTrue();

    foreach ([...$first->allPaths(), ...$second->allPaths()] as $path) {
        Storage::disk('public')->assertMissing($path);
    }
});

it('conserva al vaciar los archivos que han vuelto a usarse', function (): void {
    requiresModule('blog');

    $reused = trashedMedia('Recuperada');
    trashedMedia('Descartada de verdad');

    // Alguien la ha vuelto a usar mientras estaba en la papelera.
    $article = Article::factory()->create();
    app(SyncMediaUsage::class)->set($article, 'featured', $reused->id);

    $this->actingAs($this->superAdmin)
        ->from(route('media.index'))
        ->delete(route('media.trash.empty'))
        ->assertSessionHas('success');

    expect(session('success'))
        ->toContain('1 archivo')
        ->toContain('Se ha conservado 1');

    expect(Media::withTrashed()->whereKey($reused->id)->exists())->toBeTrue();

    foreach ($reused->allPaths() as $path) {
        Storage::disk('public')->assertExists($path);
    }
});

it('avisa cuando la papelera ya estaba vacía', function (): void {
    $this->actingAs($this->superAdmin)
        ->from(route('media.index'))
        ->delete(route('media.trash.empty'))
        ->assertSessionHas('success', 'La papelera ya estaba vacía.');
});

it('no toca los archivos activos', function (): void {
    $active = Media::factory()->create();

    foreach ($active->allPaths() as $path) {
        Storage::disk('public')->put($path, 'contenido');
    }

    $this->actingAs($this->superAdmin)
        ->from(route('media.index'))
        ->delete(route('media.trash.empty'));

    expect(Media::query()->whereKey($active->id)->exists())->toBeTrue();
    Storage::disk('public')->assertExists($active->path);
});

it('solo el superadministrador puede vaciar la papelera', function (string $state): void {
    $user = User::factory()->{$state}()->create();
    $media = trashedMedia();

    $this->actingAs($user)->delete(route('media.trash.empty'))->assertForbidden();
    $this->actingAs($user)->delete(route('media.purge', $media))->assertForbidden();

    expect(Media::withTrashed()->whereKey($media->id)->exists())->toBeTrue();
})->with(['editor', 'author', 'reader']);

it('la pantalla informa de cuántos archivos hay en la papelera', function (): void {
    trashedMedia('Una');
    trashedMedia('Otra');

    $this->actingAs($this->superAdmin)
        ->get(route('media.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('trashedCount', 2)
            ->where('can.purge', true));
});

it('el editor no ve la opción de borrado definitivo', function (): void {
    $editor = User::factory()->editor()->create();
    trashedMedia();

    $this->actingAs($editor)
        ->get(route('media.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can.purge', false));
});
