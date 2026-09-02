<?php

declare(strict_types=1);

use App\Models\Media;
use App\Models\User;
use App\Rules\ImageFitsInMemory;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * En un hosting compartido la memoria es el límite real: el peso del archivo no
 * dice nada de lo que cuesta abrirlo.
 *
 * El límite se fija por configuración en lugar de tocar `memory_limit`, que no
 * se puede bajar por debajo de lo que el propio proceso ya está usando.
 */
beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->editor = User::factory()->editor()->create();
    Storage::fake('public');
});

it('acepta una imagen que cabe holgadamente en memoria', function (): void {
    config()->set('media.max_processing_memory', '256M');

    $file = UploadedFile::fake()->image('normal.png', 1600, 900);

    $validator = Validator::make(['file' => $file], ['file' => [new ImageFitsInMemory]]);

    expect($validator->passes())->toBeTrue();
});

it('rechaza una imagen que no cabría en la memoria disponible', function (): void {
    // 3000×3000 son unos 86 MB al procesarla: con 64M no entra.
    config()->set('media.max_processing_memory', '64M');

    $file = UploadedFile::fake()->image('enorme.png', 3000, 3000);

    $validator = Validator::make(['file' => $file], ['file' => [new ImageFitsInMemory]]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('file'))
        ->toContain('demasiado grande para procesarla')
        ->toContain('3000×3000');
});

it('admite la misma imagen si el servidor tiene memoria de sobra', function (): void {
    config()->set('media.max_processing_memory', '512M');

    $file = UploadedFile::fake()->image('enorme.png', 3000, 3000);

    $validator = Validator::make(['file' => $file], ['file' => [new ImageFitsInMemory]]);

    expect($validator->passes())->toBeTrue();
});

it('no bloquea nada cuando no hay límite de memoria', function (): void {
    config()->set('media.max_processing_memory', '-1');

    $file = UploadedFile::fake()->image('enorme.png', 4000, 4000);

    $validator = Validator::make(['file' => $file], ['file' => [new ImageFitsInMemory]]);

    expect($validator->passes())->toBeTrue();
});

it('deja pasar lo que no es una imagen: de eso se encarga otra regla', function (): void {
    config()->set('media.max_processing_memory', '64M');

    $path = tempnam(sys_get_temp_dir(), 'upload');
    file_put_contents($path, 'esto no es una imagen');

    $file = new UploadedFile($path, 'falsa.png', 'image/png', null, true);

    $validator = Validator::make(['file' => $file], ['file' => [new ImageFitsInMemory]]);

    expect($validator->passes())->toBeTrue();
});

it('el formulario de subida devuelve el aviso en español', function (): void {
    config()->set('media.max_processing_memory', '64M');

    $this->actingAs($this->editor)
        ->from(route('media.index'))
        ->post(route('media.store'), [
            'files' => [UploadedFile::fake()->image('enorme.png', 3000, 3000)],
        ])
        ->assertSessionHasErrors('files.0');

    expect(session('errors')->first('files.0'))->toContain('demasiado grande');
    expect(Media::query()->count())->toBe(0);
});

it('rechaza dimensiones por encima del máximo configurado', function (): void {
    $this->actingAs($this->editor)
        ->from(route('media.index'))
        ->post(route('media.store'), [
            'files' => [UploadedFile::fake()->image('gigante.png', 5000, 3000)],
        ])
        ->assertSessionHasErrors('files.0');
});
