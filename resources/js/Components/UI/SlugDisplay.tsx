interface SlugDisplayProps {
    slug: string
    locked: boolean
    /** Acción para volver a generarlo; si no se pasa, solo se muestra. */
    onRegenerate?: () => void
    processing?: boolean
}

/**
 * Muestra la dirección pública del contenido.
 *
 * El slug nunca es un campo editable: se genera a partir del nombre. Aquí solo
 * se enseña, y quien tenga permiso puede regenerarlo de forma explícita.
 */
export default function SlugDisplay({ slug, locked, onRegenerate, processing = false }: SlugDisplayProps) {
    return (
        <div className="rounded-lg bg-neutral-100 px-4 py-3 dark:bg-neutral-700">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-secondary-light mb-1 text-sm">Dirección pública</p>
                    <p className="mb-0 truncate font-mono text-sm text-neutral-900 dark:text-white">
                        /{slug}
                    </p>
                </div>

                {onRegenerate && (
                    <button
                        type="button"
                        onClick={onRegenerate}
                        disabled={processing}
                        className="text-sm font-medium text-primary-600 hover:underline disabled:opacity-60"
                    >
                        {processing ? 'Actualizando…' : 'Actualizar según el nombre'}
                    </button>
                )}
            </div>

            <p className="text-secondary-light mt-2 mb-0 text-sm">
                {locked
                    ? 'Se mantiene fija porque ya se está usando: cambiarla rompería los enlaces publicados.'
                    : 'Se genera sola a partir del nombre mientras no se use en ningún artículo.'}
            </p>
        </div>
    )
}
