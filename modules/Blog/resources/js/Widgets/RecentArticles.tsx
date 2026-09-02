import { Link } from '@inertiajs/react'
import type { Tone } from '@/types'
import { relativeTime } from '@/lib/format'
import Card from '@/Components/UI/Card'
import EmptyState from '@/Components/UI/EmptyState'
import StatusBadge from '@/Components/UI/StatusBadge'

interface RecentArticle {
    id: number
    title: string
    status_label: string
    status_tone: Tone
    author_name: string | null
    updated_at: string | null
    /** Null si esta persona no puede editarlo. */
    edit_url: string | null
}

interface Props {
    title: string
    allUrl: string
    articles: RecentArticle[]
}

/**
 * Bloque del panel de inicio con los últimos artículos.
 *
 * Vive en el módulo pero usa los componentes compartidos del núcleo: se ve
 * igual que el resto del panel sin duplicar nada.
 */
export default function RecentArticles({ title, allUrl, articles }: Props) {
    return (
        <Card
            header={
                <div className="flex items-center justify-between gap-3">
                    <h6 className="mb-0 text-base font-semibold">{title}</h6>
                    <Link
                        href={allUrl}
                        className="text-primary-600 text-sm font-medium hover:underline dark:text-primary-400"
                    >
                        Ver todos
                    </Link>
                </div>
            }
        >
            {articles.length === 0 ? (
                <EmptyState
                    title="Todavía no hay artículos"
                    description="Cuando publiques el primero aparecerá aquí."
                />
            ) : (
                <ul className="divide-y divide-neutral-200 dark:divide-neutral-600">
                    {articles.map((article) => (
                        <li
                            key={article.id}
                            className="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                        >
                            <div className="min-w-0 flex-1">
                                {article.edit_url ? (
                                    <Link
                                        href={article.edit_url}
                                        className="block truncate font-medium text-neutral-900 hover:text-primary-600 dark:text-white dark:hover:text-primary-400"
                                    >
                                        {article.title}
                                    </Link>
                                ) : (
                                    <span className="block truncate font-medium text-neutral-900 dark:text-white">
                                        {article.title}
                                    </span>
                                )}

                                <span className="text-secondary-light text-xs">
                                    {article.author_name && <>{article.author_name} · </>}
                                    {relativeTime(article.updated_at)}
                                </span>
                            </div>

                            <StatusBadge tone={article.status_tone} label={article.status_label} />
                        </li>
                    ))}
                </ul>
            )}
        </Card>
    )
}
