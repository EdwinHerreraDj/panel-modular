import type { ReactNode } from 'react'

interface CardProps {
    /** Cabecera opcional de la tarjeta. */
    header?: ReactNode
    /** Si es false, el contenido no recibe el relleno de `.card-body`. */
    padded?: boolean
    children: ReactNode
}

/**
 * Contenedor `.card` de la plantilla.
 */
export default function Card({ header, padded = true, children }: CardProps) {
    return (
        <div className="card h-full rounded-xl border-0">
            {header && <div className="card-header">{header}</div>}
            <div className={padded ? 'card-body' : ''}>{children}</div>
        </div>
    )
}
