import type { ButtonHTMLAttributes, ReactNode } from 'react'

interface SubmitButtonProps extends Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'className' | 'type'> {
    processing: boolean
    /** Texto mostrado mientras se procesa la petición. */
    processingLabel?: string
    children: ReactNode
}

/**
 * Botón principal de formulario con estado de carga.
 * Reproduce `.btn.btn-primary` de la plantilla en ancho completo.
 */
export default function SubmitButton({
    processing,
    processingLabel = 'Procesando…',
    children,
    disabled,
    ...props
}: SubmitButtonProps) {
    return (
        <button
            {...props}
            type="submit"
            disabled={processing || disabled}
            aria-busy={processing}
            className="btn btn-primary mt-8 w-full justify-center rounded-xl px-3 py-4 text-sm disabled:cursor-not-allowed disabled:opacity-70"
        >
            {processing && (
                <span
                    className="me-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white align-[-2px]"
                    aria-hidden="true"
                />
            )}
            {processing ? processingLabel : children}
        </button>
    )
}
