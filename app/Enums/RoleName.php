<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Roles de la plataforma. Cada usuario tiene exactamente uno.
 */
enum RoleName: string
{
    /** Control total, incluida la zona técnica: usuarios, API, CORS y auditoría. */
    case SuperAdmin = 'superadministrador';

    /** Responsable del contenido: publica, programa y archiva. */
    case Editor = 'editor';

    /** Aprueba o rechaza contenido, pero no publica. */
    case Reviewer = 'revisor';

    /** Escribe y edita sus propios artículos; no publica. */
    case Author = 'autor';

    /** Solo consulta. */
    case Reader = 'lector';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Superadministrador',
            self::Editor => 'Editor',
            self::Reviewer => 'Revisor',
            self::Author => 'Autor',
            self::Reader => 'Lector',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Control total de la aplicación, incluidos usuarios, claves de API y dominios permitidos.',
            self::Editor => 'Gestiona todo el contenido: crea, revisa, publica, programa y archiva artículos.',
            self::Reviewer => 'Revisa el contenido y lo aprueba o lo devuelve al autor. No publica.',
            self::Author => 'Escribe y edita sus propios artículos y los envía a revisión. No publica.',
            self::Reader => 'Acceso de solo lectura al área privada.',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::SuperAdmin => 'danger',
            self::Editor => 'success',
            self::Reviewer => 'warning',
            self::Author => 'info',
            self::Reader => 'neutral',
        };
    }

    /**
     * Permisos del núcleo concedidos a cada rol.
     *
     * Aquí solo está lo que no depende del tipo de contenido. Lo que cada rol
     * puede hacer con los artículos, el catálogo o lo que venga lo declara el
     * módulo correspondiente en sus `ModulePermission`, y el sembrador suma las
     * dos cosas.
     *
     * Antes esta lista nombraba permisos del blog, lo que significaba que
     * desinstalarlo dejaba al enum del núcleo apuntando a casos inexistentes.
     *
     * El superadministrador no aparece aquí: su acceso se resuelve antes, en el
     * Gate::before de AppServiceProvider.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin => Permission::cases(),

            self::Editor => [
                Permission::RedirectsManage,
                Permission::MediaManage,
                Permission::MediaDelete,
            ],

            self::Reviewer => [
                Permission::MediaManage,
            ],

            self::Author => [
                Permission::MediaManage,
            ],

            self::Reader => [],
        };
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
                'description' => $case->description(),
            ],
            self::cases(),
        );
    }
}
