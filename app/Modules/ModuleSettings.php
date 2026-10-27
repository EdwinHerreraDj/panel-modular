<?php

declare(strict_types=1);

namespace App\Modules;

use App\Modules\Values\ModuleSetting;
use App\Support\ApiCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Los ajustes que ha guardado cada módulo.
 *
 * Se leen todos de una vez y se cachean, como la configuración del sitio: son
 * un puñado de filas y se consultan en casi cada petición.
 *
 * Igual que en Setting, lo que se guarda en caché son valores planos y nunca un
 * objeto: con la caché en base de datos, un modelo serializado revive como
 * `__PHP_Incomplete_Class`.
 */
final class ModuleSettings
{
    private const CACHE_KEY = 'module-settings';

    /**
     * Todos los valores guardados, por módulo.
     *
     * @return array<string, array<string, string|null>>
     */
    public function all(): array
    {
        /** @var array<string, array<string, string|null>> $stored */
        $stored = Cache::rememberForever(self::CACHE_KEY, function (): array {
            $values = [];

            foreach (DB::table('module_settings')->get(['module', 'key', 'value']) as $row) {
                $values[$row->module][$row->key] = $row->value;
            }

            return $values;
        });

        return $stored;
    }

    /**
     * Un ajuste concreto, con el valor por defecto que declare el módulo.
     */
    public function get(string $module, string $key, string|int|bool|null $default = null): string|int|bool|null
    {
        $stored = $this->all()[$module][$key] ?? null;

        if ($stored === null) {
            return $default ?? $this->declaredDefault($module, $key);
        }

        $setting = $this->declared($module, $key);

        return $setting === null ? $stored : $setting->cast($stored);
    }

    /**
     * Guarda los ajustes de un módulo, solo los que ese módulo declara.
     *
     * @param  array<string, mixed>  $values
     */
    public function put(string $module, array $values): void
    {
        foreach (app(ModuleRegistry::class)->settingsFor($module) as $setting) {
            if (! array_key_exists($setting->key, $values)) {
                continue;
            }

            $casted = $setting->cast($values[$setting->key]);

            DB::table('module_settings')->updateOrInsert(
                ['module' => $module, 'key' => $setting->key],
                [
                    'value' => $casted === null ? null : (string) (is_bool($casted) ? (int) $casted : $casted),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        $this->flush();

        // Un ajuste de módulo puede cambiar lo que sirve la API —el número de
        // WhatsApp de la tienda, por ejemplo—, igual que la configuración del
        // sitio. Sin esto, la web lo vería tarde.
        ApiCache::flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function declared(string $module, string $key): ?ModuleSetting
    {
        foreach (app(ModuleRegistry::class)->settingsFor($module) as $setting) {
            if ($setting->key === $key) {
                return $setting;
            }
        }

        return null;
    }

    private function declaredDefault(string $module, string $key): string|int|bool|null
    {
        return $this->declared($module, $key)?->default;
    }
}
