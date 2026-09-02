import { Link } from '@inertiajs/react'
import type { ReactNode } from 'react'

type Variant = 'primary' | 'outline'

interface LinkButtonProps {
    href: string
    variant?: Variant
    icon?: ReactNode
    children: ReactNode
}

const VARIANTS: Record<Variant, string> = {
    primary: 'btn btn-primary',
    outline: 'btn btn-outline-primary',
}

/**
 * Enlace con aspecto de botón, usando las clases `.btn` de la plantilla.
 */
export default function LinkButton({ href, variant = 'primary', icon, children }: LinkButtonProps) {
    return (
        <Link href={href} className={`${VARIANTS[variant]} items-center gap-2 rounded-lg px-5 py-2.5 text-sm`}>
            {icon}
            {children}
        </Link>
    )
}
