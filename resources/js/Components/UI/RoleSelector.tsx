import { useId } from 'react'
import type { RoleName, RoleOption } from '@/types'
import FieldError from './FieldError'

interface RoleSelectorProps {
    value: string
    onChange: (role: RoleName) => void
    options: RoleOption[]
    error?: string | undefined
    disabled?: boolean
    /** Motivo por el que el selector está bloqueado. */
    disabledReason?: string
}

/**
 * Selección de rol con su descripción, para que quede claro qué se está
 * concediendo antes de guardar.
 */
export default function RoleSelector({
    value,
    onChange,
    options,
    error,
    disabled = false,
    disabledReason,
}: RoleSelectorProps) {
    const name = useId()
    const errorId = `${name}-error`

    return (
        <fieldset disabled={disabled} aria-describedby={error ? errorId : undefined}>
            <legend className="form-label">Rol</legend>

            {disabled && disabledReason && (
                <p className="text-secondary-light mb-3 text-sm">{disabledReason}</p>
            )}

            <div className="grid gap-3 md:grid-cols-2">
                {options.map((option) => {
                    const checked = value === option.value

                    return (
                        <label
                            key={option.value}
                            className={`flex cursor-pointer gap-3 rounded-xl border p-4 transition ${
                                checked
                                    ? 'border-primary-600 bg-primary-50 dark:bg-primary-600/20'
                                    : 'border-neutral-300 hover:border-primary-600 dark:border-neutral-600'
                            } ${disabled ? 'cursor-not-allowed opacity-60' : ''}`}
                        >
                            <input
                                type="radio"
                                name={name}
                                value={option.value}
                                checked={checked}
                                onChange={() => onChange(option.value)}
                                className="form-check-input mt-1 shrink-0 border border-neutral-300"
                            />

                            <span className="min-w-0">
                                <span className="block font-semibold text-neutral-900 dark:text-white">
                                    {option.label}
                                </span>
                                <span className="text-secondary-light block text-sm">
                                    {option.description}
                                </span>
                            </span>
                        </label>
                    )
                })}
            </div>

            <FieldError id={errorId} message={error} />
        </fieldset>
    )
}
