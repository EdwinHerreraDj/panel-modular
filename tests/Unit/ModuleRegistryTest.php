<?php

declare(strict_types=1);

use App\Models\Media;
use App\Models\Setting;
use App\Models\User;
use App\Modules\ModuleRegistry;
use App\Modules\Values\ContentType;

/**
 * El registro por dentro, sin base de datos.
 */
it('incluye los tipos de contenido del núcleo', function (): void {
    $aliases = array_column(app(ModuleRegistry::class)->contentTypes(), 'alias');

    expect($aliases)->toContain('core.user', 'core.media', 'core.setting', 'core.api_key', 'core.redirect');
});

it('construye el mapa morph con alias como clave', function (): void {
    $map = app(ModuleRegistry::class)->morphMap();

    expect($map['core.user'])->toBe(User::class)
        ->and($map['core.media'])->toBe(Media::class);
});

it('encuentra un tipo de contenido por modelo y por alias', function (): void {
    $registry = app(ModuleRegistry::class);

    expect($registry->contentType(User::class)?->alias)->toBe('core.user')
        ->and($registry->contentType('core.user')?->model)->toBe(User::class)
        ->and($registry->contentType('no.existe'))->toBeNull();
});

it('describe un registro por su título o su nombre, sin mirar la clase', function (): void {
    $type = new ContentType(
        model: Media::class,
        alias: 'core.media',
        singular: 'Archivo',
        plural: 'Archivos',
    );

    expect($type->describeOne(new Media(['name' => 'cabecera.jpg'])))
        ->toBe('Archivo: cabecera.jpg');
});

it('usa la descripción propia cuando el modelo no tiene título ni nombre', function (): void {
    $type = app(ModuleRegistry::class)->contentType(Setting::class);

    expect($type?->describeOne(new Setting))->toBe('Configuración del blog');
});

it('solo devuelve un enlace de edición si el tipo tiene pantalla propia', function (): void {
    $registry = app(ModuleRegistry::class);

    expect($registry->contentType('core.user')?->urlFor(7))->toBe(route('users.edit', 7))
        ->and($registry->contentType('core.media')?->urlFor(3))->toBeNull();
});

it('avisa en voz alta si el .env nombra un módulo que no existe', function (): void {
    config()->set('modules.enabled', ['inventado']);

    expect(fn (): array => (new ModuleRegistry)->all())
        ->toThrow(RuntimeException::class, 'Modules\Inventado\InventadoModule');
});
