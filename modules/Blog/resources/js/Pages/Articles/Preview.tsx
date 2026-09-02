import { Head, Link } from '@inertiajs/react'
import Alert from '@/Components/UI/Alert'
import StatusBadge from '@/Components/UI/StatusBadge'

interface PreviewProps {
    article: {
        id: number
        title: string
        excerpt: string | null
        content: string
        status_label: string
        reading_minutes: number
        published_at: string | null
        author_name: string | null
        featured_image_url: string | null
        featured_image_alt: string | null
        categories: string[]
        tags: string[]
    }
    meta: {
        title: string
        description: string | null
        is_indexable: boolean
        canonical_url: string | null
    }
    fromAutosave: boolean
}

const DATE_FORMAT = new Intl.DateTimeFormat('es-ES', { dateStyle: 'long' })

/**
 * Vista previa del artículo.
 *
 * La web pública es externa y consume la API, así que esto no es la página
 * real: es el contenido con la maquetación que tendrá el texto, para revisarlo
 * antes de publicar.
 */
export default function Preview({ article, meta, fromAutosave }: PreviewProps) {
    return (
        <>
            <Head title={`Vista previa · ${article.title}`} />

            <div className="min-h-screen bg-neutral-100 py-8 dark:bg-neutral-800">
                <div className="mx-auto w-full max-w-[820px] px-6">
                    <div className="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-neutral-200 bg-white px-5 py-4 dark:border-neutral-600 dark:bg-dark-2">
                        <div className="flex flex-wrap items-center gap-3">
                            <StatusBadge label="Vista previa" tone="warning" />
                            <span className="text-secondary-light text-sm">
                                Estado: {article.status_label}
                            </span>
                        </div>

                        <Link
                            href={route('articles.edit', article.id)}
                            className="text-sm font-medium text-primary-600 hover:underline"
                        >
                            Volver a la edición
                        </Link>
                    </div>

                    {fromAutosave && (
                        <Alert variant="info">
                            Estás viendo la copia automática más reciente, que todavía no se ha guardado en
                            el artículo.
                        </Alert>
                    )}

                    {!meta.is_indexable && (
                        <Alert variant="danger">
                            Este artículo está marcado como <strong>no indexable</strong>: no aparecerá en
                            los buscadores.
                        </Alert>
                    )}

                    <article className="rounded-xl border border-neutral-200 bg-white p-8 dark:border-neutral-600 dark:bg-dark-2">
                        {article.featured_image_url && (
                            <img
                                src={article.featured_image_url}
                                alt={article.featured_image_alt ?? ''}
                                className="mb-8 w-full rounded-lg"
                            />
                        )}

                        {article.categories.length > 0 && (
                            <p className="mb-3 text-sm font-medium text-primary-600">
                                {article.categories.join(' · ')}
                            </p>
                        )}

                        <h1 className="mb-4 !text-4xl">{article.title}</h1>

                        <div className="text-secondary-light mb-8 flex flex-wrap items-center gap-3 text-sm">
                            {article.author_name && <span>{article.author_name}</span>}
                            {article.published_at && (
                                <span>{DATE_FORMAT.format(new Date(article.published_at))}</span>
                            )}
                            {article.reading_minutes > 0 && (
                                <span>{article.reading_minutes} min de lectura</span>
                            )}
                        </div>

                        {article.excerpt && (
                            <p className="text-secondary-light mb-8 border-s-4 border-primary-600 ps-4 text-lg">
                                {article.excerpt}
                            </p>
                        )}

                        {/*
                            El contenido ya está sanitizado en el servidor con lista blanca
                            de elementos, atributos y dominios.
                        */}
                        <div
                            className="tiptap-content !min-h-0 !px-0 !py-0"
                            dangerouslySetInnerHTML={{ __html: article.content }}
                        />

                        {article.tags.length > 0 && (
                            <div className="mt-10 flex flex-wrap gap-2 border-t border-neutral-200 pt-6 dark:border-neutral-600">
                                {article.tags.map((tag) => (
                                    <span
                                        key={tag}
                                        className="rounded-full bg-neutral-100 px-3 py-1 text-sm text-neutral-600 dark:bg-neutral-700 dark:text-neutral-200"
                                    >
                                        {tag}
                                    </span>
                                ))}
                            </div>
                        )}
                    </article>

                    <div className="mt-6 rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-600 dark:bg-dark-2">
                        <h6 className="mb-3 text-base font-semibold">Cómo se verá en Google</h6>

                        <div className="max-w-[600px]">
                            <p className="mb-1 truncate text-sm text-success-700 dark:text-success-400">
                                {meta.canonical_url ?? 'tu-dominio.es › blog'}
                            </p>
                            <p className="mb-1 truncate text-lg text-info-700 dark:text-info-400">
                                {meta.title}
                            </p>
                            <p className="text-secondary-light mb-0 line-clamp-2 text-sm">
                                {meta.description ?? 'Sin meta descripción.'}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </>
    )
}
