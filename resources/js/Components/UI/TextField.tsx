import { useId, type InputHTMLAttributes, type ReactNode } from 'react'
import FieldError from './FieldError'

interface TextFieldProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'className' | 'id'> {
    label: string
    error?: string | undefined
    /** Texto de ayuda mostrado bajo el campo cuando no hay error. */
    hint?: ReactNode
}

/**
 * Campo de texto con etiqueta visible, siguiendo `.form-label` + `.form-control`
 * de la plantilla.
 */
export default function TextField({ label, error, hint, required, ...props }: TextFieldProps) {
    const id = useId()
    const errorId = `${id}-error`
    const hintId = `${id}-hint`

    return (
        <div>
            <label htmlFor={id} className="form-label">
                {label}
                {required && (
                    <span className="ms-1 text-danger-600" aria-hidden="true">
                        *
                    </span>
                )}
            </label>

            <input
                {...props}
                id={id}
                required={required}
                aria-invalid={error ? true : undefined}
                aria-describedby={error ? errorId : hint ? hintId : undefined}
                className={`form-control field-control h-[52px] rounded-lg ${error ? 'is-invalid' : ''}`}
            />

            {!error && hint && (
                <p id={hintId} className="text-secondary-light mt-1.5 text-sm">
                    {hint}
                </p>
            )}

            <FieldError id={errorId} message={error} />
        </div>
    )
}
