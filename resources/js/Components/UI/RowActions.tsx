import { Link } from '@inertiajs/react'
import type { ReactNode } from 'react'

interface ActionLinkProps {
    href: string
    label: string
    icon: string
    tone?: 'primary' | 'danger'
}

interface ActionButtonProps {
    onClick: () => void
    label: string
    icon: string
    tone?: 'primary' | 'danger'
}

const TONES = {
    primary: 'text-neutral-600 hover:bg-primary-50 hover:text-primary-600 dark:text-neutral-200',
    danger: 'text-neutral-600 hover:bg-danger-50 hover:text-danger-600 dark:text-neutral-200',
} as const

const BASE = 'inline-flex h-9 w-9 items-center justify-center rounded-lg text-lg transition'

/** Enlace de acción dentro de una fila de tabla. */
export function ActionLink({ href, label, icon, tone = 'primary' }: ActionLinkProps) {
    return (
        <Link href={href} className={`${BASE} ${TONES[tone]}`} title={label} aria-label={label}>
            <i className={icon} aria-hidden="true" />
        </Link>
    )
}

/** Botón de acción dentro de una fila de tabla. */
export function ActionButton({ onClick, label, icon, tone = 'primary' }: ActionButtonProps) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`${BASE} ${TONES[tone]}`}
            title={label}
            aria-label={label}
        >
            <i className={icon} aria-hidden="true" />
        </button>
    )
}

/** Contenedor de las acciones de una fila. */
export function RowActions({ children }: { children: ReactNode }) {
    return <div className="flex items-center justify-center gap-1">{children}</div>
}
