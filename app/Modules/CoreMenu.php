<?php

declare(strict_types=1);

namespace App\Modules;

use App\Enums\Permission;
use App\Modules\Values\MenuItem;

/**
 * Entradas de menú del núcleo.
 *
 * Las posiciones van de diez en diez para que un módulo pueda colocarse entre
 * dos sin renumerar nada. El blog ocupa de la 10 a la 30 del grupo «Contenido»,
 * y lo que es del núcleo empieza en la 40.
 */
final class CoreMenu
{
    /** Grupo sin título, el primero de todos. */
    public const PRINCIPAL = '';

    /** Lo que usan todos los módulos de contenido: la biblioteca y el SEO. */
    public const HERRAMIENTAS = 'Herramientas';

    public const ADMINISTRACION = 'Administración';

    /**
     * Orden de los grupos. Un grupo que no esté aquí se pinta al final.
     *
     * @var list<string>
     */
    public const GROUPS = [self::PRINCIPAL, self::HERRAMIENTAS, self::ADMINISTRACION];

    /**
     * @return list<MenuItem>
     */
    public static function items(): array
    {
        return [
            new MenuItem(
                label: 'Panel',
                route: 'dashboard',
                icon: 'home',
                activePattern: 'dashboard',
                group: self::PRINCIPAL,
                position: 10,
            ),

            new MenuItem(
                label: 'Redirecciones',
                route: 'redirects.index',
                icon: 'redirect',
                activePattern: 'redirects.*',
                permission: Permission::RedirectsManage->value,
                group: self::HERRAMIENTAS,
                position: 40,
            ),
            new MenuItem(
                label: 'Biblioteca',
                route: 'media.index',
                icon: 'gallery',
                activePattern: 'media.*',
                permission: Permission::MediaManage->value,
                group: self::HERRAMIENTAS,
                position: 50,
            ),

            new MenuItem(
                label: 'Usuarios',
                route: 'users.index',
                icon: 'users',
                activePattern: 'users.*',
                permission: Permission::UsersManage->value,
                group: self::ADMINISTRACION,
                position: 10,
            ),
            new MenuItem(
                label: 'Conexión web',
                route: 'api.connection',
                icon: 'plug',
                activePattern: 'api.*',
                permission: Permission::ApiKeysManage->value,
                group: self::ADMINISTRACION,
                position: 20,
            ),
            new MenuItem(
                label: 'Accesos',
                route: 'auth-logs.index',
                icon: 'shield',
                activePattern: 'auth-logs.*',
                permission: Permission::AuditView->value,
                group: self::ADMINISTRACION,
                position: 30,
            ),
            new MenuItem(
                label: 'Auditoría',
                route: 'activity-logs.index',
                icon: 'history',
                activePattern: 'activity-logs.*',
                permission: Permission::AuditView->value,
                group: self::ADMINISTRACION,
                position: 40,
            ),
            new MenuItem(
                label: 'Módulos',
                route: 'modules.index',
                icon: 'puzzle',
                activePattern: 'modules.*',
                permission: Permission::ModulesManage->value,
                group: self::ADMINISTRACION,
                position: 45,
            ),
            new MenuItem(
                label: 'Configuración',
                route: 'settings.edit',
                icon: 'settings',
                activePattern: 'settings.*',
                permission: Permission::SettingsManage->value,
                group: self::ADMINISTRACION,
                position: 50,
            ),
        ];
    }
}
