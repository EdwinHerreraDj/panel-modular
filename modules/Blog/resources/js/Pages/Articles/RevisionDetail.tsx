import { Link } from '@inertiajs/react'
import DashboardLayout from '@/Layouts/DashboardLayout'
import Card from '@/Components/UI/Card'

interface RevisionDetailProps {
    article: { id: number; title: string }
    revision: {
        id: number
        type_label: string
        author_name: string | null
        created_at: string | null
        title: string
        excerpt: string | null
        content: string
    }
}

const DATE_FORMAT = new Intl.DateTimeFormat('es-ES', { dateStyle: 'long', timeStyle: 'short' })

export default function RevisionDetail({ article, revision }: RevisionDetailProps) {
    return (
        <DashboardLayout title="Versión guardada" breadcrumb={article.title}>
            <div className="mx-auto w-full max-w-[860px]">
                <Card
                    header={
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h6 className="mb-1 text-base font-semibold">
                                    {revision.created_at
                                        ? DATE_FORMAT.format(new Date(revision.created_at))
                                        : 'Fecha desconocida'}
                                </h6>
                                <span className="text-secondary-light text-sm">
                                    {revision.type_label}
                                    {revision.author_name && ` · ${revision.author_name}`}
                                </span>
                            </div>

                            <Link
                                href={route('articles.revisions.index', article.id)}
                                className="btn btn-outline-primary rounded-lg px-5 py-2.5 text-sm"
                            >
                                Volver al historial
                            </Link>
                        </div>
                    }
                >
                    <h4 className="mb-3">{revision.title}</h4>

                    {revision.excerpt && (
                        <p className="text-secondary-light mb-6 text-lg">{revision.excerpt}</p>
                    )}

                    {/*
                        El contenido se guardó ya sanitizado y se vuelve a limpiar al
                        restaurarlo, así que aquí solo se muestra.
                    */}
                    <div
                        className="tiptap-content !min-h-0 !px-0 !py-0"
                        dangerouslySetInnerHTML={{ __html: revision.content }}
                    />
                </Card>
            </div>
        </DashboardLayout>
    )
}
