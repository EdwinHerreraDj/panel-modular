<?php

declare(strict_types=1);

namespace App\Modules\Values;

use App\Enums\RoleName;

/**
 * Un permiso aportado por un módulo.
 *
 * Declara a la vez el permiso y a qué roles se concede de fábrica. Sin esa
 * segunda parte, la matriz de RoleName seguiría siendo una lista cerrada del
 * núcleo que nombra permisos de módulos que quizá no estén instalados.
 *
 * El valor va prefijado por el módulo (`blog.articles.update`) para que un
 * cliente pueda dar el catálogo a una persona sin darle el blog.
 */
final readonly class ModulePermission
{
    /**
     * @param  list<RoleName>  $roles  Roles que lo reciben al sembrar
     */
    public function __construct(
        public string $value,
        public string $label,
        public string $group,
        public array $roles = [],
    ) {}
}
