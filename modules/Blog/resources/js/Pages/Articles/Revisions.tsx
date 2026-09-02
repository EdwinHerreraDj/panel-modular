import { Link, router } from '@inertiajs/react'
import { useState } from 'react'
import DashboardLayout from '@/Layouts/DashboardLayout'
import Card from '@/Components/UI/Card'
import Alert from '@/Components/UI/Alert'
import StatusBadge from '@/Components/UI/StatusBadge'
import EmptyState from '@/Components/UI/EmptyState'
import ConfirmDialog from '@/Components/UI/ConfirmDialog'

interface RevisionItem {
    id: number
    type: 'manual' | 'autosave'
    type_label: string
    author_name: string | null
    created_at: string | null
    title: string
}

interface RevisionsProps {
    article: { id: number; title: string; status_label: string }
    revisions: RevisionItem[]
}

const DATE_FORMAT = new Intl.DateTimeFormat('es-ES', { dateStyle: 'long', timeStyle: 'short' })

export default function Revisions({ article, revisions }: RevisionsProps) {
    const [pendingRestore, setPendingRestore] = useState<RevisionItem | null>(null)
    const [processing, setProcessing] = useState(false)

    function confirmRestore() {
        if (!pendingRestore) {
            return
        }

        router.post(
            route('articles.revisions.restore', [article.id, pendingRestore.id]),
            {},
            {
                onStart: () => setProcessing(true),
                onFinish: () => {
                    setProcessing(false)
                    setPendingRestore(null)
                },
            },
        )
    }

    return (
        <DashboardLayout title="Historial de versiones" breadcrumb={article.title}>
            <div className="mx-auto w-full max-w-[900px]">
                <Card
                    header={
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h6 className="mb-1 text-base font-semibold">{article.title}</h6>
                                <span className="text-secondary-light text-sm">
                                    Estado actual: {article.status_label}
                                </span>
                            </div>

                            <Link
                                href={route('articles.edit', article.id)}
                                className="btn btn-outline-primary rounded-lg px-5 py-2.5 text-sm"
                            >
                                Volver al artículo
                            </Link>
                        </div>
                    }
                >
                    <Alert variant="info">
                        Restaurar una versión recupera el texto, la clasificación y el SEO, pero{' '}
                        <strong>no cambia el estado de publicación</strong>. Antes de sobrescribir se guarda
                        una copia del estado actual, así que siempre se puede volver atrás.
                    </Alert>

                    {revisions.length === 0 ? (
                        <EmptyState
                            title="Todavía no hay versiones"
                            description="Se guarda una versión cada vez que guardas el artículo."
                        />
                    ) : (
                        <ol className="flex flex-col">
                            {revisions.map((revision, index) => (
                                <li
                                    key={revision.id}
                                    className="flex flex-wrap items-center gap-3 border-b border-neutral-200 py-4 last:border-b-0 dark:border-neutral-600"
                                >
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-medium text-neutral-900 dark:text-white">
                                                {revision.created_at
                                                    ? DATE_FORMAT.format(new Date(revision.created_at))
                                                    : 'Fecha desconocida'}
                                            </span>

                                            {index === 0 && (
                                                <StatusBadge label="Más reciente" tone="success" />
                                            )}

                                            {revision.type === 'autosave' && (
                                                <StatusBadge label={revision.type_label} tone="warning" />
                                            )}
                                        </div>

                                        <span className="text-secondary-light block truncate text-sm">
                                            {revision.title}
                                            {revision.author_name && ` · ${revision.author_name}`}
                                        </span>
                                    </div>

                                    <div className="flex shrink-0 gap-3">
                                        <Link
                                            href={route('articles.revisions.show', [
                                                article.id,
                                                revision.id,
                                            ])}
                                            className="text-sm font-medium text-primary-600 hover:underline"
                                        >
                                            Ver
                                        </Link>

                                        <button
                                            type="button"
                                            onClick={() => setPendingRestore(revision)}
                                            className="text-sm font-medium text-neutral-600 hover:underline dark:text-neutral-300"
                                        >
                                            Restaurar
                                        </button>
                                    </div>
                                </li>
                            ))}
                        </ol>
                    )}
                </Card>
            </div>

            <ConfirmDialog
                open={pendingRestore !== null}
                title="Restaurar esta versión"
                message={
                    pendingRestore
                        ? `El artículo volverá al contenido del ${
                              pendingRestore.created_at
                                  ? DATE_FORMAT.format(new Date(pendingRestore.created_at))
                                  : 'momento seleccionado'
                          }. El estado de publicación no cambia y el contenido actual queda guardado en el historial.`
                        : ''
                }
                confirmLabel="Restaurar"
                tone="primary"
                processing={processing}
                onConfirm={confirmRestore}
                onCancel={() => setPendingRestore(null)}
            />
        </DashboardLayout>
    )
}
