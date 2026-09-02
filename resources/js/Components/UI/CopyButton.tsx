import { useEffect, useState } from 'react'

interface CopyButtonProps {
    value: string
    label?: string
    /** Botón solo con icono, para barras muy apretadas. */
    iconOnly?: boolean
}

/**
 * Copia un texto al portapapeles y lo confirma.
 *
 * Sin confirmación visible nadie sabe si el clic ha hecho algo, y se acaba
 * pulsando tres veces.
 */
export default function CopyButton({ value, label = 'Copiar', iconOnly = false }: CopyButtonProps) {
    const [copied, setCopied] = useState(false)
    const [failed, setFailed] = useState(false)

    useEffect(() => {
        if (!copied && !failed) {
            return
        }

        const timer = window.setTimeout(() => {
            setCopied(false)
            setFailed(false)
        }, 2000)

        return () => window.clearTimeout(timer)
    }, [copied, failed])

    async function copy() {
        try {
            await navigator.clipboard.writeText(value)
            setCopied(true)
        } catch {
            // Sin HTTPS el portapapeles no está disponible; se avisa en lugar
            // de fingir que ha funcionado.
            setFailed(true)
        }
    }

    return (
        <button
            type="button"
            onClick={() => void copy()}
            className={`flex shrink-0 items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition ${
                copied
                    ? 'border-success-300 text-success-700 dark:border-success-600/50 dark:text-success-400'
                    : failed
                      ? 'border-danger-300 text-danger-700 dark:border-danger-600/50 dark:text-danger-400'
                      : 'border-neutral-300 text-neutral-600 hover:border-primary-400 hover:text-primary-600 dark:border-neutral-600 dark:text-neutral-300'
            }`}
            aria-label={iconOnly ? label : undefined}
        >
            <i
                className={copied ? 'ri-check-line' : failed ? 'ri-error-warning-line' : 'ri-file-copy-line'}
                aria-hidden="true"
            />
            {!iconOnly && <span>{copied ? 'Copiado' : failed ? 'No se ha podido' : label}</span>}
        </button>
    )
}
