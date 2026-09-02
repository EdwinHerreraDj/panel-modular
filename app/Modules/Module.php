<?php

declare(strict_types=1);

namespace App\Modules;

use App\Models\User;
use App\Modules\Values\ApiEndpoint;
use App\Modules\Values\ContentType;
use App\Modules\Values\DashboardContribution;
use App\Modules\Values\DeferredTask;
use App\Modules\Values\ManualSection;
use App\Modules\Values\MenuItem;
use App\Modules\Values\ModulePermission;
use App\Modules\Values\ModuleSetting;
use App\Modules\Values\PublicCount;

/**
 * Lo que un módulo aporta a la plataforma.
 *
 * Cada método corresponde a una lista que antes estaba escrita a mano en el
 * núcleo y ahora se compone sumando lo que declara cada módulo activo.
 *
 * No hace falta implementarlos todos: `BaseModule` los deja vacíos y un módulo
 * sobrescribe solo lo que use.
 */
interface Module
{
    /** Identificador en el `.env` y prefijo de sus permisos: 'blog', 'catalogo'. */
    public function key(): string;

    /** Nombre para la interfaz: 'Blog', 'Catálogo'. */
    public function name(): string;

    /** @return list<ModulePermission> */
    public function permissions(): array;

    /** @return list<MenuItem> */
    public function menu(): array;

    /** @return list<ApiEndpoint> */
    public function apiEndpoints(): array;

    /** @return list<ContentType> */
    public function contentTypes(): array;

    /**
     * Lo que el módulo pone en el panel de inicio, ya resuelto para esta
     * persona: cada rol ve lo suyo, así que el filtro se hace aquí y no en la
     * pantalla.
     */
    public function dashboard(User $user): DashboardContribution;

    /** @return list<ManualSection> */
    public function manualSections(): array;

    /**
     * Ajustes propios que el módulo pone en la pantalla de Configuración.
     *
     * Solo lo que únicamente entiende él. Lo del sitio —nombre, logotipo, SEO—
     * es del núcleo y no se duplica aquí.
     *
     * @return list<ModuleSetting>
     */
    public function settings(): array;

    /** @return list<DeferredTask> */
    public function deferredTasks(): array;

    /**
     * Recuentos de lo publicado, para el endpoint de estado.
     *
     * Con un solo módulo esto era `articulos_publicados` escrito a mano en el
     * controlador. Con dos, la web pública necesita saber que hay contenido sin
     * tener que preguntar por cada módulo por separado.
     *
     * @return list<PublicCount>
     */
    public function publicCounts(): array;

    /**
     * Direcciones publicadas, para que la web monte su sitemap.xml.
     *
     * @return array<string, list<array<string, mixed>>> Sección en español => entradas
     */
    public function sitemapSections(): array;
}
