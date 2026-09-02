<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission as PermissionEnum;
use App\Enums\RoleName;
use App\Modules\ModuleRegistry;
use App\Modules\Values\ModulePermission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crea los roles y permisos de la plataforma y sincroniza su matriz.
 *
 * Suma dos fuentes: los permisos del núcleo, que están en el enum, y los que
 * declara cada módulo activo. Sembrar después de activar un módulo le da sus
 * permisos a los roles que le correspondan sin tocar los demás.
 *
 * Es idempotente: se puede volver a ejecutar tras añadir un permiso nuevo y
 * cada rol quedará con exactamente los permisos que le corresponden.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $modulePermissions = app(ModuleRegistry::class)->permissions();

        foreach ($this->allNames($modulePermissions) as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // La caché se refresca antes de sincronizar: los permisos recién
        // creados no están todavía en la del registrar.
        $registrar->forgetCachedPermissions();

        foreach (RoleName::cases() as $roleName) {
            $role = Role::findOrCreate($roleName->value, 'web');

            $role->syncPermissions($this->namesFor($roleName, $modulePermissions));
        }

        $registrar->forgetCachedPermissions();
    }

    /**
     * Todos los nombres de permiso que deben existir.
     *
     * @param  list<ModulePermission>  $modulePermissions
     * @return list<string>
     */
    private function allNames(array $modulePermissions): array
    {
        return [
            ...array_map(fn (PermissionEnum $permission): string => $permission->value, PermissionEnum::cases()),
            ...array_map(fn (ModulePermission $permission): string => $permission->value, $modulePermissions),
        ];
    }

    /**
     * Los que le tocan a un rol, del núcleo y de los módulos.
     *
     * @param  list<ModulePermission>  $modulePermissions
     * @return list<string>
     */
    private function namesFor(RoleName $roleName, array $modulePermissions): array
    {
        // El superadministrador lo recibe todo. Su acceso ya lo resuelve el
        // Gate::before, pero conviene que la matriz también lo diga: así una
        // pantalla que enseñe los permisos de un rol no miente.
        if ($roleName === RoleName::SuperAdmin) {
            return $this->allNames($modulePermissions);
        }

        $names = array_map(
            fn (PermissionEnum $permission): string => $permission->value,
            $roleName->permissions(),
        );

        foreach ($modulePermissions as $permission) {
            if (in_array($roleName, $permission->roles, true)) {
                $names[] = $permission->value;
            }
        }

        return $names;
    }
}
