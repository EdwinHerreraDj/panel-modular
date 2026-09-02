import { useId, useState, type InputHTMLAttributes, type ReactNode } from 'react'
import FieldError from './FieldError'

interface PasswordFieldProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'className' | 'id' | 'type'> {
    label: string
    error?: string | undefined
    /** Texto de ayuda mostrado bajo el campo cuando no hay error. */
    hint?: ReactNode
}

/**
 * Campo de contraseña para formularios del área privada.
 *
 * Comparte el aspecto de `TextField` (a diferencia de `PasswordInput`, que usa
 * el estilo alto y redondeado de las pantallas de acceso) para que dentro de
 * una misma tarjeta todos los campos se vean iguales.
 */
export default function PasswordField({ label, error, hint, required, ...props }: PasswordFieldProps) {
    const id = useId()
    const errorId = `${id}-error`
    const hintId = `${id}-hint`
    const [visible, setVisible] = useState(false)

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

            <div className="relative">
                <input
                    {...props}
                    id={id}
                    type={visible ? 'text' : 'password'}
                    required={required}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={error ? errorId : hint ? hintId : undefined}
                    className={`form-control field-control h-[52px] rounded-lg pe-12 ${error ? 'is-invalid' : ''}`}
                />

                <button
                    type="button"
                    onClick={() => setVisible((current) => !current)}
                    className="absolute end-0 top-1/2 me-3 -translate-y-1/2 rounded p-1 text-lg leading-none text-neutral-500 hover:text-primary-600 dark:text-neutral-400"
                    aria-label={visible ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                    aria-pressed={visible}
                    tabIndex={-1}
                >
                    <i className={visible ? 'ri-eye-off-line' : 'ri-eye-line'} aria-hidden="true" />
                </button>
            </div>

            {!error && hint && (
                <p id={hintId} className="text-secondary-light mt-1.5 text-sm">
                    {hint}
                </p>
            )}

            <FieldError id={errorId} message={error} />
        </div>
    )
}
