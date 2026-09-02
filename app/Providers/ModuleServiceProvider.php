<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\ModuleProvider;
use App\Modules\ModuleRegistry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\View\FileViewFinder;

/**
 * Arranca el sistema de módulos.
 *
 * Lee qué módulos están activos, registra el proveedor de cada uno y deja
 * enseñado al framework lo que no sabe deducir cuando el código vive fuera de
 * `app/`: los alias polimórficos y dónde están las factories.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class);

        $registry = $this->app->make(ModuleRegistry::class);

        foreach ($registry->keys() as $key) {
            $provider = sprintf('Modules\%s\ModuleServiceProvider', Str::studly($key));

            if (class_exists($provider) && is_subclass_of($provider, ModuleProvider::class)) {
                $this->app->register($provider);
            }
        }
    }

    public function boot(): void
    {
        $this->configureMorphAliases();
        $this->configureFactories();
        $this->configurePageFinder();
    }

    /**
     * Enseña a Inertia dónde están las páginas de los módulos.
     *
     * Una página de módulo se pide como `Sonda::Index`, que es la notación de
     * vistas con espacio de nombres de Laravel. El buscador de Inertia la
     * entiende en cuanto se le registra el espacio de nombres; sin esto,
     * `assertInertia()->component()` daría por inexistente una página que el
     * navegador sí carga, y el fallo aparecería solo en las pruebas.
     *
     * De paso se corrige la mayúscula de la carpeta del núcleo: el valor por
     * defecto del paquete es `js/pages` y aquí es `js/Pages`, que en Windows da
     * igual y en el servidor no.
     */
    private function configurePageFinder(): void
    {
        $registry = $this->app->make(ModuleRegistry::class);

        $this->app->bind('inertia.view-finder', function ($app) use ($registry): FileViewFinder {
            $finder = new FileViewFinder(
                $app['files'],
                [resource_path('js/Pages')],
                $app['config']->get('inertia.pages.extensions'),
            );

            foreach ($registry->keys() as $key) {
                $pages = $registry->path($key, 'resources/js/Pages');

                if (is_dir($pages)) {
                    $finder->addNamespace(Str::studly($key), $pages);
                }
            }

            return $finder;
        });
    }

    /**
     * Las columnas polimórficas guardan un alias corto, nunca la clase.
     *
     * Es lo que permite mover un modelo de espacio de nombres sin tocar un solo
     * dato de las cuatro tablas polimórficas. Se usa la versión que **obliga**:
     * un modelo polimórfico sin declarar falla en el acto, en lugar de colar un
     * nombre de clase en la base de datos y descubrirse meses después.
     */
    private function configureMorphAliases(): void
    {
        Relation::enforceMorphMap($this->app->make(ModuleRegistry::class)->morphMap());
    }

    /**
     * Las factories de un módulo viven dentro del módulo.
     *
     * Por defecto Laravel busca `Database\Factories\…` a partir de lo que sigue
     * a `App\Models\`; un modelo en `Modules\Blog\Models` no encaja y acabaría
     * buscando una clase que no existe.
     */
    private function configureFactories(): void
    {
        // Del factory al modelo: `Modules\Blog\Database\Factories\ArticleFactory`
        // resuelve a `Modules\Blog\Models\Article`. Sin esto, Laravel
        // buscaría `App\Article` y cada módulo tendría que declarar `$model` en
        // todos sus factories.
        Factory::guessModelNamesUsing(static function (Factory $factory): string {
            $class = $factory::class;

            if (str_starts_with($class, 'Modules\\')) {
                $parts = explode('\\', $class);
                $name = Str::replaceLast('Factory', '', array_pop($parts));
                $namespace = implode('\\', array_slice($parts, 0, 2));

                return $namespace.'\\Models\\'.$name;
            }

            $basename = Str::replaceLast('Factory', '', class_basename($factory));

            return 'App\\Models\\'.$basename;
        });

        // Y del modelo al factory, el camino inverso.
        Factory::guessFactoryNamesUsing(static function (string $model): string {
            // Modules\Blog\Models\Article => Modules\Blog\Database\Factories\ArticleFactory
            if (str_starts_with($model, 'Modules\\')) {
                $parts = explode('\\', $model);
                $name = array_pop($parts);
                $namespace = implode('\\', array_slice($parts, 0, 2));

                return $namespace.'\\Database\\Factories\\'.$name.'Factory';
            }

            $relative = Str::startsWith($model, 'App\\Models\\')
                ? Str::after($model, 'App\\Models\\')
                : Str::after($model, 'App\\');

            return 'Database\\Factories\\'.$relative.'Factory';
        });
    }
}
