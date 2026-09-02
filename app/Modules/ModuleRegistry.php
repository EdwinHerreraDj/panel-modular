<?php

declare(strict_types=1);

namespace App\Modules;

use App\Models\User;
use App\Modules\Values\ApiEndpoint;
use App\Modules\Values\ContentType;
use App\Modules\Values\DashboardAction;
use App\Modules\Values\DashboardAlert;
use App\Modules\Values\DashboardCard;
use App\Modules\Values\DashboardContribution;
use App\Modules\Values\DashboardWidget;
use App\Modules\Values\DeferredTask;
use App\Modules\Values\ManualSection;
use App\Modules\Values\MenuItem;
use App\Modules\Values\ModulePermission;
use App\Modules\Values\ModuleSetting;
use App\Modules\Values\PublicCount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Los módulos activos de esta instalación y lo que aportan entre todos.
 *
 * Se resuelve como singleton: la lista sale de la configuración —cacheable con
 * `config:cache`— y las clases se instancian una sola vez por petición.
 *
 * Solo se registran las rutas, migraciones y permisos de los activos. Un
 * módulo presente en el código pero ausente del `.env` es como si no estuviera.
 */
final class ModuleRegistry
{
    /** @var array<string, Module>|null */
    private ?array $modules = null;

    /** @var list<ContentType>|null */
    private ?array $contentTypes = null;

    /**
     * Módulos activos, indexados por su clave.
     *
     * @return array<string, Module>
     */
    public function all(): array
    {
        if ($this->modules !== null) {
            return $this->modules;
        }

        $modules = [];

        /** @var list<string> $enabled */
        $enabled = config('modules.enabled', []);

        foreach ($enabled as $key) {
            $modules[$key] = $this->resolve($key);
        }

        return $this->modules = $modules;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function get(string $key): ?Module
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * Claves de los módulos activos.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    /**
     * Módulos que hay en el disco, estén activos o no.
     *
     * Lo usa la pantalla de módulos: para poder activar uno hay que poder
     * verlo antes. Se ordenan para que la lista no baile entre sistemas.
     *
     * @return list<string>
     */
    public function available(): array
    {
        $keys = [];

        foreach (glob(base_path('modules/*'), GLOB_ONLYDIR) ?: [] as $directory) {
            $studly = basename($directory);

            if (is_file($directory.'/'.$studly.'Module.php')) {
                $keys[] = Str::snake($studly);
            }
        }

        sort($keys);

        return $keys;
    }

    /**
     * Instancia un módulo aunque no esté activo, para poder describirlo.
     */
    public function describe(string $key): ?Module
    {
        return $this->all()[$key] ?? ($this->exists($key) ? $this->resolve($key) : null);
    }

    public function exists(string $key): bool
    {
        return in_array($key, $this->available(), true);
    }

    /**
     * Carpeta de un módulo activo.
     */
    public function path(string $key, string $relative = ''): string
    {
        $base = base_path('modules/'.Str::studly($key));

        return $relative === '' ? $base : $base.'/'.ltrim($relative, '/');
    }

    /**
     * Archivos de rutas de los módulos activos que existan.
     *
     * @param  string  $kind  'web' o 'api'
     * @return list<string>
     */
    public function routeFiles(string $kind): array
    {
        $files = [];

        foreach ($this->keys() as $key) {
            $file = $this->path($key, 'routes/'.$kind.'.php');

            if (is_file($file)) {
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * @return list<ModulePermission>
     */
    public function permissions(): array
    {
        return $this->collect(fn (Module $module): array => $module->permissions());
    }

    /**
     * Entradas de menú de todos los módulos, ordenadas por posición.
     *
     * Una entrada que no diga a qué grupo va acaba en el grupo del propio
     * módulo: con dos módulos de contenido, meterlo todo en un cajón común deja
     * de tener sentido. Un módulo que quiera colarse en un grupo del núcleo
     * puede seguir haciéndolo indicándolo.
     *
     * @return list<MenuItem>
     */
    public function menu(): array
    {
        $items = [];

        foreach ($this->all() as $module) {
            foreach ($module->menu() as $item) {
                $items[] = $item->group === null
                    ? new MenuItem(
                        label: $item->label,
                        route: $item->route,
                        icon: $item->icon,
                        activePattern: $item->activePattern,
                        permission: $item->permission,
                        group: $module->name(),
                        position: $item->position,
                    )
                    : $item;
            }
        }

        usort($items, fn (MenuItem $a, MenuItem $b): int => $a->position <=> $b->position);

        return $items;
    }

    /**
     * Nombres de los grupos que aporta cada módulo, en el orden en que están
     * declarados en `MODULES_ENABLED`.
     *
     * Ese orden es el que acaba viéndose en el menú, así que se cambia
     * reordenando la línea del `.env`.
     *
     * @return list<string>
     */
    public function menuGroups(): array
    {
        $groups = [];

        foreach ($this->all() as $module) {
            foreach ($module->menu() as $item) {
                $name = $item->group ?? $module->name();

                if (! in_array($name, $groups, true)) {
                    $groups[] = $name;
                }
            }
        }

        return $groups;
    }

    /**
     * @return list<ApiEndpoint>
     */
    public function apiEndpoints(): array
    {
        return $this->collect(fn (Module $module): array => $module->apiEndpoints());
    }

    /**
     * Tipos de contenido del núcleo más los de los módulos activos.
     *
     * @return list<ContentType>
     */
    public function contentTypes(): array
    {
        return $this->contentTypes ??= [
            ...CoreContentTypes::all(),
            ...$this->collect(fn (Module $module): array => $module->contentTypes()),
        ];
    }

    /**
     * Tipo de contenido de un modelo o de un alias guardado.
     */
    public function contentType(Model|string $subject): ?ContentType
    {
        $alias = $subject instanceof Model ? $subject->getMorphClass() : $subject;

        foreach ($this->contentTypes() as $type) {
            if ($type->alias === $alias || $type->model === $alias) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Mapa para `Relation::enforceMorphMap`: alias corto => clase del modelo.
     *
     * @return array<string, class-string<Model>>
     */
    public function morphMap(): array
    {
        $map = [];

        foreach ($this->contentTypes() as $type) {
            $map[$type->alias] = $type->model;
        }

        return $map;
    }

    /**
     * Todo lo que los módulos activos ponen en el panel de inicio, ya ordenado.
     */
    public function dashboard(User $user): DashboardContribution
    {
        $cards = [];
        $alerts = [];
        $widgets = [];
        $actions = [];

        foreach ($this->all() as $module) {
            $contribution = $module->dashboard($user);

            $cards = [...$cards, ...$contribution->cards];
            $alerts = [...$alerts, ...$contribution->alerts];
            $widgets = [...$widgets, ...$contribution->widgets];
            $actions = [...$actions, ...$contribution->actions];
        }

        usort($cards, fn (DashboardCard $a, DashboardCard $b): int => $a->position <=> $b->position);
        usort($alerts, fn (DashboardAlert $a, DashboardAlert $b): int => $a->position <=> $b->position);
        usort($widgets, fn (DashboardWidget $a, DashboardWidget $b): int => $a->position <=> $b->position);
        usort($actions, fn (DashboardAction $a, DashboardAction $b): int => $a->position <=> $b->position);

        return new DashboardContribution($cards, $alerts, $widgets, $actions);
    }

    /**
     * @return list<ManualSection>
     */
    public function manualSections(): array
    {
        $sections = $this->collect(fn (Module $module): array => $module->manualSections());

        usort($sections, fn (ManualSection $a, ManualSection $b): int => $a->position <=> $b->position);

        return $sections;
    }

    /**
     * Los ajustes que declara un módulo concreto.
     *
     * @return list<ModuleSetting>
     */
    public function settingsFor(string $key): array
    {
        return $this->get($key)?->settings() ?? [];
    }

    /**
     * Módulos activos que tienen algo que configurar, con sus ajustes.
     *
     * @return array<string, list<ModuleSetting>>
     */
    public function settings(): array
    {
        $settings = [];

        foreach ($this->all() as $key => $module) {
            $declared = $module->settings();

            if ($declared !== []) {
                $settings[$key] = $declared;
            }
        }

        return $settings;
    }

    /**
     * @return list<DeferredTask>
     */
    public function deferredTasks(): array
    {
        return $this->collect(fn (Module $module): array => $module->deferredTasks());
    }

    /**
     * Recuentos de lo publicado por todos los módulos activos.
     *
     * @return list<PublicCount>
     */
    public function publicCounts(): array
    {
        return $this->collect(fn (Module $module): array => $module->publicCounts());
    }

    /**
     * Secciones del sitemap de todos los módulos activos.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function sitemapSections(): array
    {
        $sections = [];

        foreach ($this->all() as $module) {
            $sections = [...$sections, ...$module->sitemapSections()];
        }

        return $sections;
    }

    /**
     * Instancia la clase del módulo a partir de su clave.
     *
     * La correspondencia es fija: `catalogo` => `Modules\Catalogo\CatalogoModule`.
     * Sin escanear el disco, para que `config:cache` siga bastando.
     */
    private function resolve(string $key): Module
    {
        $studly = Str::studly($key);

        /** @var class-string $class */
        $class = sprintf('Modules\%1$s\%1$sModule', $studly);

        if (! class_exists($class)) {
            throw new RuntimeException(
                "MODULES_ENABLED nombra el módulo «{$key}», pero no existe la clase {$class}. "
                ."Comprueba que la carpeta modules/{$studly} está subida y que se ha ejecutado "
                .'`composer dump-autoload`.',
            );
        }

        $module = app($class);

        if (! $module instanceof Module) {
            throw new RuntimeException("{$class} tiene que implementar ".Module::class.'.');
        }

        return $module;
    }

    /**
     * Suma lo que devuelve cada módulo activo.
     *
     * @template T
     *
     * @param  callable(Module): list<T>  $each
     * @return list<T>
     */
    private function collect(callable $each): array
    {
        $all = [];

        foreach ($this->all() as $module) {
            foreach ($each($module) as $item) {
                $all[] = $item;
            }
        }

        return $all;
    }
}
