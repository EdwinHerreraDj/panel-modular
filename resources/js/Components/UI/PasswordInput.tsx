import { useState, type InputHTMLAttributes, type ReactNode } from 'react'
import IconInput from './IconInput'

interface PasswordInputProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'className' | 'id' | 'type'> {
    icon: ReactNode
    label: string
    visibleLabel?: boolean
    error?: string | undefined
}

/**
 * Campo de contraseña con el conmutador de visibilidad de la plantilla
 * (`.toggle-password` en sign-in.html), reimplementado en React.
 */
export default function PasswordInput({ error, ...props }: PasswordInputProps) {
    const [visible, setVisible] = useState(false)

    return (
        <IconInput
            {...props}
            type={visible ? 'text' : 'password'}
            error={error}
            trailing={
                <button
                    type="button"
                    onClick={() => setVisible((current) => !current)}
                    className="text-secondary-light absolute end-0 top-1/2 me-4 -translate-y-1/2 cursor-pointer rounded p-1 leading-none"
                    aria-label={visible ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                    aria-pressed={visible}
                    tabIndex={-1}
                >
                    <i className={visible ? 'ri-eye-off-line' : 'ri-eye-line'} aria-hidden="true" />
                </button>
            }
        />
    )
}
