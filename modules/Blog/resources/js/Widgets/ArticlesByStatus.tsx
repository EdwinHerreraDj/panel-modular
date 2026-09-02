import type { Tone } from '@/types'
import Card from '@/Components/UI/Card'

interface StatusStat {
    status: string
    label: string
    tone: Tone
    count: number
}

interface Props {
    stats: StatusStat[]
    total: number
}

const BARS: Record<string, string> = {
    neutral: 'bg-neutral-400',
    info: 'bg-info-500',
    warning: 'bg-warning-500',
    success: 'bg-success-500',
    danger: 'bg-danger-500',
}

/**
 * Desglose de artículos por estado, con una barra por cada uno.
 */
export default function ArticlesByStatus({ stats, total }: Props) {
    return (
        <Card header={<h6 className="mb-0 text-base font-semibold">Por estado</h6>}>
            {total === 0 ? (
                <p className="text-secondary-light mb-0 text-sm">Aún no hay nada que contar.</p>
            ) : (
                <ul className="flex flex-col gap-3">
                    {stats.map((stat) => (
                        <li key={stat.status}>
                            <div className="mb-1 flex items-center justify-between gap-3 text-sm">
                                <span className="text-neutral-700 dark:text-neutral-200">{stat.label}</span>
                                <span className="font-semibold text-neutral-900 dark:text-white">
                                    {stat.count}
                                </span>
                            </div>

                            <div
                                className="h-1.5 overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-600"
                                role="presentation"
                            >
                                <div
                                    className={`h-full rounded-full ${BARS[stat.tone] ?? BARS.neutral}`}
                                    style={{ width: `${Math.round((stat.count / total) * 100)}%` }}
                                />
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </Card>
    )
}
