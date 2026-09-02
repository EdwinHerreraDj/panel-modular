interface FieldErrorProps {
    id: string
    message?: string | undefined
}

/**
 * Mensaje de error de un campo. Se anuncia a los lectores de pantalla y se
 * enlaza con el input mediante aria-describedby.
 */
export default function FieldError({ id, message }: FieldErrorProps) {
    if (!message) {
        return null
    }

    return (
        <p id={id} className="invalid-feedback" role="alert">
            <i className="ri-error-warning-line mt-px shrink-0 text-base leading-none" aria-hidden="true" />
            <span>{message}</span>
        </p>
    )
}
