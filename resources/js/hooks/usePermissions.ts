import { usePage } from '@inertiajs/react'
import { useCallback } from 'react'

/**
 * Comprobación de permisos en la interfaz.
 *
 * Sirve únicamente para decidir qué se muestra: el servidor vuelve a comprobar
 * cada acción con sus policies, así que ocultar algo aquí nunca es la
 * protección real.
 */
export function usePermissions() {
    const { auth } = usePage().props

    const isSuperAdmin = auth.user?.is_super_admin === true

    const can = useCallback(
        (permission: string): boolean => isSuperAdmin || auth.permissions.includes(permission),
        [isSuperAdmin, auth.permissions],
    )

    const canAny = useCallback(
        (permissions: string[]): boolean => permissions.some((permission) => can(permission)),
        [can],
    )

    return { can, canAny, isSuperAdmin }
}
