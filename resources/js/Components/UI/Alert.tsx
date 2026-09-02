import type { ReactNode } from 'react'

type AlertVariant = 'success' | 'danger' | 'info'

interface AlertProps {
    variant: AlertVariant
    /** Clases extra, normalmente para separarlo del bloque anterior. */
    className?: string
    children: ReactNode
}

const ICONS: Record<AlertVariant, string> = {
    success: 'ri-checkbox-circle-line',
    danger: 'ri-error-warning-line',
    info: 'ri-information-line',
}

/**
 * Aviso general de un formulario o de una página.
 */
export default function Alert({ variant, className = '', children }: AlertProps) {
    return (
        <div
            className={`form-alert form-alert-${variant} ${className}`}
            role={variant === 'danger' ? 'alert' : 'status'}
            aria-live={variant === 'danger' ? 'assertive' : 'polite'}
        >
            <i className={`${ICONS[variant]} mt-px shrink-0 text-lg leading-none`} aria-hidden="true" />
            <span>{children}</span>
        </div>
    )
}
