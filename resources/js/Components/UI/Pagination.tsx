import { Link } from '@inertiajs/react'
import type { PaginationLink } from '@/types'

interface PaginationProps {
    links: PaginationLink[]
    from: number | null
    to: number | null
    total: number
    /** Nombre de lo que se está paginando, para el resumen textual. */
    itemsLabel?: string
}

/**
 * Paginación de la plantilla. Los enlaces conservan los filtros porque el
 * servidor los devuelve con `withQueryString()`.
 */
export default function Pagination({ links, from, to, total, itemsLabel = 'resultados' }: PaginationProps) {
    if (total === 0) {
        return null
    }

    return (
        <div className="mt-6 flex flex-wrap items-center justify-between gap-3">
            <p className="text-secondary-light mb-0 text-sm">
                Mostrando {from ?? 0}–{to ?? 0} de {total} {itemsLabel}
            </p>

            {links.length > 3 && (
                <nav aria-label="Paginación">
                    <ul className="flex flex-wrap items-center gap-1">
                        {links.map((link, index) => {
                            const label = link.label
                                .replace('&laquo; Anterior', 'Anterior')
                                .replace('Siguiente &raquo;', 'Siguiente')
                                .replace('&laquo;', '‹')
                                .replace('&raquo;', '›')

                            const classes = [
                                'flex h-9 min-w-9 items-center justify-center rounded-lg px-3 text-sm font-medium transition',
                                link.active
                                    ? 'bg-primary-600 text-white'
                                    : 'bg-neutral-100 text-neutral-600 hover:bg-primary-50 hover:text-primary-600 dark:bg-neutral-700 dark:text-neutral-200',
                            ].join(' ')

                            return (
                                <li key={`${link.label}-${index}`}>
                                    {link.url === null ? (
                                        <span
                                            className={`${classes} cursor-not-allowed opacity-50`}
                                            aria-disabled="true"
                                        >
                                            {label}
                                        </span>
                                    ) : (
                                        <Link
                                            href={link.url}
                                            preserveScroll
                                            preserveState
                                            className={classes}
                                            aria-current={link.active ? 'page' : undefined}
                                        >
                                            {label}
                                        </Link>
                                    )}
                                </li>
                            )
                        })}
                    </ul>
                </nav>
            )}
        </div>
    )
}
