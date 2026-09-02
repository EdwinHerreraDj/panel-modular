import { Link, router, usePage } from '@inertiajs/react'
import { useRef, useState } from 'react'
import IconClose from '~icons/radix-icons/cross-1'
import IconPower from '~icons/lucide/power'
import IconUser from '~icons/solar/user-linear'
import IconLock from '~icons/solar/lock-password-outline'
import IconHelp from '~icons/solar/question-circle-outline'
import { useClickOutside } from '@/hooks/useClickOutside'

/**
 * Menú de usuario de la barra superior. Reproduce `#dropdownProfile` de la
 * plantilla, pero controlado por React en lugar de por Flowbite.
 */
export default function UserMenu() {
    const { auth } = usePage().props
    const [open, setOpen] = useState(false)
    const containerRef = useRef<HTMLDivElement>(null)

    useClickOutside(containerRef, open, () => setOpen(false))

    if (!auth.user) {
        return null
    }

    const user = auth.user

    function logout() {
        router.post(route('logout'))
    }

    return (
        <div className="relative" ref={containerRef}>
            <button
                type="button"
                onClick={() => setOpen((current) => !current)}
                className="flex items-center justify-center rounded-full"
                aria-haspopup="menu"
                aria-expanded={open}
                aria-label="Abrir menú de usuario"
            >
                {user.avatar_url ? (
                    <img src={user.avatar_url} alt="" className="h-10 w-10 rounded-full object-cover" />
                ) : (
                    <span className="flex h-10 w-10 items-center justify-center rounded-full bg-primary-600 text-sm font-semibold text-white">
                        {user.initials}
                    </span>
                )}
            </button>

            {open && (
                <div
                    role="menu"
                    className="dropdown-menu-sm absolute end-0 top-[calc(100%+0.5rem)] z-20 min-w-[240px] rounded-lg bg-white p-3 shadow-lg dark:bg-neutral-700"
                >
                    <div className="mb-4 flex items-center justify-between gap-2 rounded-lg bg-primary-50 px-4 py-3 dark:bg-primary-600/25">
                        <div className="min-w-0">
                            <h6 className="mb-0 truncate text-lg font-semibold text-neutral-900">{user.name}</h6>
                            <span className="truncate text-neutral-500">
                                {user.role_label ?? user.email}
                            </span>
                        </div>
                        <button
                            type="button"
                            onClick={() => setOpen(false)}
                            className="hover:text-danger-600"
                            aria-label="Cerrar menú"
                        >
                            <IconClose className="icon text-xl" />
                        </button>
                    </div>

                    <ul className="flex flex-col">
                        <li>
                            <Link
                                href={route('profile.edit')}
                                onClick={() => setOpen(false)}
                                role="menuitem"
                                className="flex items-center gap-4 px-0 py-2 text-black hover:text-primary-600 dark:text-white"
                            >
                                <IconUser className="icon text-xl" />
                                Mi cuenta
                            </Link>
                        </li>
                        <li>
                            <Link
                                href={route('password.change')}
                                onClick={() => setOpen(false)}
                                role="menuitem"
                                className="flex items-center gap-4 px-0 py-2 text-black hover:text-primary-600 dark:text-white"
                            >
                                <IconLock className="icon text-xl" />
                                Cambiar contraseña
                            </Link>
                        </li>
                        <li>
                            <Link
                                href={route('help.index')}
                                onClick={() => setOpen(false)}
                                role="menuitem"
                                className="flex items-center gap-4 px-0 py-2 text-black hover:text-primary-600 dark:text-white"
                            >
                                <IconHelp className="icon text-xl" />
                                Ayuda
                            </Link>
                        </li>
                        <li>
                            <button
                                type="button"
                                onClick={logout}
                                role="menuitem"
                                className="flex w-full items-center gap-4 px-0 py-2 text-black hover:text-danger-600 dark:text-white"
                            >
                                <IconPower className="icon text-xl" />
                                Cerrar sesión
                            </button>
                        </li>
                    </ul>
                </div>
            )}
        </div>
    )
}
