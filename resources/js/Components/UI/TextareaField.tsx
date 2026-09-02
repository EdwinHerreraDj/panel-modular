import { useId, type TextareaHTMLAttributes } from 'react'
import FieldError from './FieldError'

interface TextareaFieldProps extends Omit<TextareaHTMLAttributes<HTMLTextAreaElement>, 'className' | 'id'> {
    label: string
    error?: string | undefined
}

export default function TextareaField({ label, error, required, ...props }: TextareaFieldProps) {
    const id = useId()
    const errorId = `${id}-error`

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

            <textarea
                {...props}
                id={id}
                required={required}
                aria-invalid={error ? true : undefined}
                aria-describedby={error ? errorId : undefined}
                className={`form-control field-control rounded-lg ${error ? 'is-invalid' : ''}`}
            />

            <FieldError id={errorId} message={error} />
        </div>
    )
}
