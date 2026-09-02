import type { AutosaveState } from '@/hooks/useAutosave'

interface AutosaveIndicatorProps {
    state: AutosaveState
    savedAt: string | null
}

const TIME_FORMAT = new Intl.DateTimeFormat('es-ES', { timeStyle: 'short' })

/**
 * Estado del guardado automático, junto al botón de guardar.
 */
export default function AutosaveIndicator({ state, savedAt }: AutosaveIndicatorProps) {
    if (state === 'idle' && savedAt === null) {
        return null
    }

    if (state === 'saving') {
        return (
            <span className="text-secondary-light flex items-center gap-2 text-sm" aria-live="polite">
                <span
                    className="inline-block h-3 w-3 animate-spin rounded-full border-2 border-neutral-400 border-t-transparent"
                    aria-hidden="true"
                />
                Guardando copia…
            </span>
        )
    }

    if (state === 'error') {
        return (
            <span className="flex items-center gap-2 text-sm text-danger-600" role="alert">
                <i className="ri-error-warning-line" aria-hidden="true" />
                No se ha podido guardar la copia automática.
            </span>
        )
    }

    if (savedAt === null) {
        return null
    }

    return (
        <span className="text-secondary-light flex items-center gap-2 text-sm" aria-live="polite">
            <i className="ri-checkbox-circle-line text-success-600" aria-hidden="true" />
            Copia guardada a las {TIME_FORMAT.format(new Date(savedAt))}
        </span>
    )
}
