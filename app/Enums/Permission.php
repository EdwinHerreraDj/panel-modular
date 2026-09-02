<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Permisos del núcleo.
 *
 * Solo lo que no depende del tipo de contenido: la biblioteca, los usuarios,
 * la configuración, la API y la auditoría. Lo que aporta cada módulo lo declara
 * el módulo, y quien quiera la lista completa se la pide al ModuleRegistry, no
 * a este enum.
 *
 * Los permisos del núcleo no llevan prefijo: el núcleo no es un módulo y
 * renombrarlos sería re-sembrar cada instalación a cambio de nada.
 *
 * Se comprueban tanto en el servidor (policies y middleware) como en la
 * interfaz, pero la interfaz nunca es la única barrera.
 */
enum Permission: string
{
    // Contenido que no depende del tipo de contenido
    case RedirectsManage = 'redirects.manage';

    // Multimedia
    case MediaManage = 'media.manage';
    case MediaDelete = 'media.delete';

    // Zona del superadministrador
    case UsersManage = 'users.manage';
    case SettingsManage = 'settings.manage';
    case ApiKeysManage = 'apikeys.manage';
    case AuditView = 'audit.view';
    case ModulesManage = 'modules.manage';

    public function label(): string
    {
        return match ($this) {
            self::RedirectsManage => 'Gestionar redirecciones',
            self::MediaManage => 'Gestionar medios',
            self::MediaDelete => 'Eliminar medios',
            self::UsersManage => 'Gestionar usuarios',
            self::SettingsManage => 'Configurar el blog',
            self::ApiKeysManage => 'Gestionar claves de API y dominios',
            self::AuditView => 'Consultar la auditoría',
            self::ModulesManage => 'Activar y desactivar módulos',
        };
    }

    /**
     * Agrupación para mostrar los permisos ordenados en la interfaz.
     */
    public function group(): string
    {
        return match ($this) {
            self::RedirectsManage => 'Contenido',

            self::MediaManage, self::MediaDelete => 'Multimedia',

            self::UsersManage, self::SettingsManage,
            self::ApiKeysManage, self::AuditView,
            self::ModulesManage => 'Administración',
        };
    }
}
