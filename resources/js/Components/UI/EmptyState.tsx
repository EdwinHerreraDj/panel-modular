import type { ReactNode } from 'react'

interface EmptyStateProps {
    title: string
    description?: string
    /** Acción sugerida, por ejemplo un botón de alta. */
    action?: ReactNode
}

/**
 * Estado de «sin resultados» para tablas y listados.
 */
export default function EmptyState({ title, description, action }: EmptyStateProps) {
    return (
        <div className="flex flex-col items-center justify-center gap-3 px-6 py-12 text-center">
            <span
                className="flex h-14 w-14 items-center justify-center rounded-full bg-neutral-100 text-2xl text-neutral-400 dark:bg-neutral-700"
                aria-hidden="true"
            >
                <i className="ri-inbox-line" />
            </span>

            <h6 className="mb-0 text-base font-semibold">{title}</h6>

            {description && <p className="text-secondary-light mb-0 max-w-md text-sm">{description}</p>}

            {action}
        </div>
    )
}
