import { Link } from '@inertiajs/react'
import type { ReactNode } from 'react'
import type { Tone } from '@/types'

interface StatCardProps {
    label: string
    value: number | string
    icon: ReactNode
    tone?: Tone | 'primary'
    /** Si se indica, la tarjeta entera es un enlace. */
    href?: string | null
    /** Matiz bajo la etiqueta: «En los próximos 7 días». */
    hint?: string | null
}

const TONES: Record<string, string> = {
    neutral: 'bg-neutral-200 text-neutral-600 dark:bg-neutral-600 dark:text-neutral-200',
    info: 'bg-info-100 text-info-600 dark:bg-info-600/20 dark:text-info-400',
    warning: 'bg-warning-100 text-warning-600 dark:bg-warning-600/20 dark:text-warning-400',
    success: 'bg-success-100 text-success-600 dark:bg-success-600/20 dark:text-success-400',
    danger: 'bg-danger-100 text-danger-600 dark:bg-danger-600/20 dark:text-danger-400',
    primary: 'bg-primary-100 text-primary-600 dark:bg-primary-600/20 dark:text-primary-300',
}

/**
 * Cifra con icono. La usan el panel de inicio y las tarjetas que aporta cada
 * módulo, así que vive en el sistema de diseño y no en una pantalla.
 */
export default function StatCard({ label, value, icon, tone = 'neutral', href, hint }: StatCardProps) {
    const body = (
        <div className="flex h-full items-center gap-4 rounded-xl border border-neutral-200 bg-white p-5 transition hover:border-primary-300 hover:shadow-sm dark:border-neutral-600 dark:bg-dark-2 dark:hover:border-primary-600">
            <span
                className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-lg text-2xl ${TONES[tone] ?? TONES.neutral}`}
                aria-hidden="true"
            >
                {icon}
            </span>

            <span className="min-w-0">
                <span className="block text-2xl font-semibold leading-tight text-neutral-900 dark:text-white">
                    {value}
                </span>
                <span className="text-secondary-light block truncate text-sm">{label}</span>
                {hint && <span className="text-secondary-light block truncate text-xs">{hint}</span>}
            </span>
        </div>
    )

    return href ? (
        <Link href={href} className="block h-full">
            {body}
        </Link>
    ) : (
        body
    )
}
