import { useId, type ReactNode, type SelectHTMLAttributes } from 'react'
import type { SelectOption } from '@/types'
import FieldError from './FieldError'

interface SelectFieldProps extends Omit<SelectHTMLAttributes<HTMLSelectElement>, 'className' | 'id'> {
    label: string
    options: SelectOption[]
    /** Opción vacía inicial (por ejemplo, «Todos»). */
    placeholder?: string
    error?: string | undefined
    /** Texto de ayuda mostrado bajo el campo cuando no hay error. */
    hint?: ReactNode
}

export default function SelectField({
    label,
    options,
    placeholder,
    error,
    hint,
    required,
    ...props
}: SelectFieldProps) {
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

            <select
                {...props}
                id={id}
                required={required}
                aria-invalid={error ? true : undefined}
                aria-describedby={error ? errorId : hint ? hintId : undefined}
                className={`form-select field-control h-[52px] rounded-lg ${error ? 'is-invalid' : ''}`}
            >
                {placeholder !== undefined && <option value="">{placeholder}</option>}
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>

            {!error && hint && (
                <p id={hintId} className="text-secondary-light mt-1.5 text-sm">
                    {hint}
                </p>
            )}

            <FieldError id={errorId} message={error} />
        </div>
    )
}
