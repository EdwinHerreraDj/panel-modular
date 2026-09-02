import { forwardRef, useId, type InputHTMLAttributes, type ReactNode } from 'react'
import FieldError from './FieldError'

interface IconInputProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'className' | 'id'> {
    /** Icono mostrado dentro del campo, alineado al inicio. */
    icon: ReactNode
    /** Etiqueta accesible; se oculta visualmente si `visibleLabel` es false. */
    label: string
    visibleLabel?: boolean
    error?: string | undefined
    /** Contenido opcional alineado al final del campo (por ejemplo, un botón). */
    trailing?: ReactNode
}

/**
 * Campo de texto con icono, siguiendo el patrón `.icon-field` de la plantilla
 * (ver sign-in.html): input de 56px, esquinas rounded-xl e icono absoluto.
 */
const IconInput = forwardRef<HTMLInputElement, IconInputProps>(function IconInput(
    { icon, label, visibleLabel = false, error, trailing, ...props },
    ref,
) {
    const id = useId()
    const errorId = `${id}-error`

    return (
        <div>
            <label htmlFor={id} className={visibleLabel ? 'form-label' : 'sr-only'}>
                {label}
            </label>

            <div className="icon-field relative">
                <span
                    className="pointer-events-none absolute start-4 top-1/2 flex -translate-y-1/2 text-xl text-neutral-500 dark:text-neutral-400"
                    aria-hidden="true"
                >
                    {icon}
                </span>

                <input
                    {...props}
                    id={id}
                    ref={ref}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={error ? errorId : undefined}
                    className={`form-control h-[56px] rounded-xl border-neutral-300 bg-neutral-50 ps-11 dark:bg-dark-2 ${
                        trailing ? 'pe-12' : ''
                    } ${error ? 'is-invalid' : ''}`}
                />

                {trailing}
            </div>

            <FieldError id={errorId} message={error} />
        </div>
    )
})

export default IconInput
