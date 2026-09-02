<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Modules\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

// Las pruebas de cada módulo viven dentro del módulo y se comportan igual que
// las del núcleo: mismo TestCase y misma base de datos limpia por prueba.
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in(__DIR__.'/../modules');

// Cada módulo puede traer sus propios ayudantes de prueba. Se cargan aquí
// porque son funciones globales y tienen que existir antes de que Pest lea los
// archivos que las usan.
foreach (glob(__DIR__.'/../modules/*/Tests/Pest.php') ?: [] as $modulePest) {
    require_once $modulePest;
}

/**
 * Claves de un array, en profundidad, en formato «a.b.c».
 *
 * @param  array<mixed>  $data
 * @return list<string>
 */
function deepKeys(array $data, string $prefix = ''): array
{
    $keys = [];

    foreach ($data as $key => $value) {
        // En las listas solo interesa la forma del primer elemento.
        $path = is_int($key) ? $prefix : ($prefix === '' ? (string) $key : $prefix.'.'.$key);

        if (is_int($key) && $key > 0) {
            continue;
        }

        if (! is_int($key)) {
            $keys[] = $path;
        }

        if (is_array($value)) {
            $keys = [...$keys, ...deepKeys($value, $path)];
        }
    }

    return array_values(array_unique($keys));
}

/**
 * Salta la prueba si el módulo no está instalado.
 *
 * Algunas cosas del núcleo —que un archivo en uso no se pueda borrar, por
 * ejemplo— solo se pueden comprobar con contenido, y el contenido lo aportan
 * los módulos. La alternativa sería que el núcleo trajera un tipo de contenido
 * de mentira solo para las pruebas, que es peor.
 */
function requiresModule(string $key): void
{
    if (! app(ModuleRegistry::class)->has($key)) {
        test()->markTestSkipped("Necesita el módulo «{$key}»: sin él no hay contenido con el que probar.");
    }
}

/*
|--------------------------------------------------------------------------
| Datos de formulario reutilizables
|--------------------------------------------------------------------------
|
| Conjuntos válidos mínimos para las pruebas que envían formularios largos.
| Cada prueba sobrescribe solo lo que quiere comprobar, de modo que se lee de
| un vistazo qué está en juego.
|
*/

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function settingsPayload(array $overrides = []): array
{
    return array_merge([
        'site_name' => 'Blog de Alminares',
        'tagline' => 'Novedades del sector',
        'public_url' => 'https://www.alminares.es/blog',
        'locale' => 'es',
        'timezone' => 'Europe/Madrid',
        'meta_title' => 'Blog de Alminares',
        'meta_description' => 'Artículos sobre el sector.',
        'title_template' => ':title | :blog',
        'is_indexable' => true,
        'twitter_card' => 'summary_large_image',
        'organization_name' => 'Alminares S.L.',
        'social_profiles' => [],
    ], $overrides);
}

/**
 * Dirección absoluta de un endpoint de la API pública.
 */
function apiUrl(string $path): string
{
    return '/'.trim((string) config('api.prefix'), '/').'/'.ltrim($path, '/');
}

/**
 * Cabecera con una clave de API válida recién generada.
 *
 * @param  list<string>  $origins
 * @return array{0: array<string, string>, 1: ApiKey}
 */
function apiKeyHeaders(array $origins = []): array
{
    ['key' => $key, 'plain' => $plain] = ApiKey::generate('Web de pruebas', $origins);

    return [[(string) config('api.header') => $plain], $key];
}
