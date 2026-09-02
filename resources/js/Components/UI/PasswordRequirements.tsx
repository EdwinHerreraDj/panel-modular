import type { PasswordPolicy } from '@/types'

interface PasswordRequirementsProps {
    policy: PasswordPolicy
}

/**
 * Requisitos de la contraseña, para que la persona sepa qué se le pide antes
 * de que el servidor los rechace.
 */
export default function PasswordRequirements({ policy }: PasswordRequirementsProps) {
    const rules = [
        `Al menos ${policy.min_length} caracteres`,
        policy.require_mixed_case ? 'Mayúsculas y minúsculas' : null,
        policy.require_numbers ? 'Algún número' : null,
        policy.require_symbols ? 'Algún símbolo (por ejemplo: . # @ !)' : null,
        'No debe aparecer en filtraciones de contraseñas conocidas',
    ].filter((rule): rule is string => rule !== null)

    return (
        <div className="rounded-lg bg-neutral-100 px-4 py-3 dark:bg-neutral-700">
            <p className="mb-2 text-sm font-semibold text-neutral-900 dark:text-white">
                La contraseña debe cumplir:
            </p>
            <ul className="text-secondary-light flex flex-col gap-1 text-sm">
                {rules.map((rule) => (
                    <li key={rule} className="flex items-start gap-2">
                        <i className="ri-check-line mt-px text-success-600" aria-hidden="true" />
                        {rule}
                    </li>
                ))}
            </ul>
        </div>
    )
}
