import type { PasswordPolicy } from '@/types'

interface PasswordStrengthProps {
    /** Contraseña que se está escribiendo. */
    value: string
    policy: PasswordPolicy
    /** Repetición, para avisar en cuanto deja de coincidir. */
    confirmation?: string
}

interface Rule {
    label: string
    met: boolean
}

/**
 * Requisitos de la contraseña que se van cumpliendo según se escribe.
 *
 * Repite exactamente lo que valida el servidor, salvo la comprobación de
 * filtraciones, que solo se puede hacer allí. La idea es que nadie descubra que
 * su contraseña no vale después de enviar el formulario.
 */
export default function PasswordStrength({ value, policy, confirmation }: PasswordStrengthProps) {
    const rules: Rule[] = [
        { label: `Al menos ${policy.min_length} caracteres`, met: value.length >= policy.min_length },
        policy.require_mixed_case
            ? { label: 'Mayúsculas y minúsculas', met: /[a-zà-ÿ]/.test(value) && /[A-ZÀ-Ý]/.test(value) }
            : null,
        policy.require_numbers ? { label: 'Algún número', met: /\d/.test(value) } : null,
        policy.require_symbols
            ? { label: 'Algún símbolo (. # @ !)', met: /[^\p{L}\p{N}]/u.test(value) }
            : null,
    ].filter((rule): rule is Rule => rule !== null)

    const met = rules.filter((rule) => rule.met).length
    const percent = rules.length === 0 ? 0 : Math.round((met / rules.length) * 100)
    const complete = met === rules.length

    // Solo se avisa del desajuste cuando ya se ha empezado a repetir.
    const mismatch = confirmation !== undefined && confirmation !== '' && confirmation !== value

    return (
        <div className="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-600 dark:bg-dark-1">
            <div className="mb-3 flex items-center justify-between gap-3">
                <span className="text-sm font-semibold text-neutral-900 dark:text-white">
                    Requisitos de la contraseña
                </span>
                <span
                    className={`text-xs font-medium ${
                        complete
                            ? 'text-success-600 dark:text-success-400'
                            : 'text-secondary-light'
                    }`}
                >
                    {met} de {rules.length}
                </span>
            </div>

            <div
                className="mb-3 h-1.5 overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-600"
                role="progressbar"
                aria-valuenow={percent}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-label="Requisitos cumplidos"
            >
                <div
                    className={`h-full rounded-full transition-all duration-300 ${
                        complete ? 'bg-success-500' : percent >= 50 ? 'bg-warning-500' : 'bg-danger-500'
                    }`}
                    style={{ width: `${percent}%` }}
                />
            </div>

            <ul className="flex flex-col gap-1.5">
                {rules.map((rule) => (
                    <li
                        key={rule.label}
                        className={`flex items-start gap-2 text-sm transition-colors ${
                            rule.met
                                ? 'text-success-700 dark:text-success-400'
                                : 'text-secondary-light'
                        }`}
                    >
                        <i
                            className={`mt-px ${rule.met ? 'ri-checkbox-circle-fill' : 'ri-checkbox-blank-circle-line'}`}
                            aria-hidden="true"
                        />
                        <span>{rule.label}</span>
                        <span className="sr-only">{rule.met ? '(cumplido)' : '(pendiente)'}</span>
                    </li>
                ))}

                <li className="text-secondary-light flex items-start gap-2 text-sm">
                    <i className="ri-shield-check-line mt-px" aria-hidden="true" />
                    <span>No debe aparecer en filtraciones conocidas (se comprueba al guardar)</span>
                </li>
            </ul>

            {mismatch && (
                <p className="mt-3 mb-0 flex items-start gap-2 text-sm text-danger-600 dark:text-danger-400">
                    <i className="ri-error-warning-line mt-px" aria-hidden="true" />
                    Las dos contraseñas no coinciden todavía.
                </p>
            )}
        </div>
    )
}
