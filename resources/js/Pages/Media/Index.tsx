import axios from 'axios'
import { router, useForm } from '@inertiajs/react'
import { useRef, useState, type FormEvent } from 'react'
import DashboardLayout from '@/Layouts/DashboardLayout'
import Card from '@/Components/UI/Card'
import SearchInput from '@/Components/UI/SearchInput'
import StatusBadge from '@/Components/UI/StatusBadge'
import Pagination from '@/Components/UI/Pagination'
import EmptyState from '@/Components/UI/EmptyState'
import Modal from '@/Components/UI/Modal'
import ConfirmDialog from '@/Components/UI/ConfirmDialog'
import TextField from '@/Components/UI/TextField'
import TextareaField from '@/Components/UI/TextareaField'
import { useResourceFilters } from '@/hooks/useResourceFilters'
import type { ImageConstraint, MediaListItem, Paginated, ResourceFilterValues } from '@/types'

interface MediaIndexProps {
    media: Paginated<MediaListItem>
    filters: ResourceFilterValues
    constraints: ImageConstraint
    maxFiles: number
    trashedCount: number
    can: { create: boolean; delete: boolean; purge: boolean }
}

interface UsageDetail {
    label: string
    role: string
}

interface EditForm {
    name: string
    alt_text: string
    description: string
    [key: string]: string
}

const DATE_FORMAT = new Intl.DateTimeFormat('es-ES', { dateStyle: 'short' })

export default function MediaIndex({
    media,
    filters: initial,
    constraints,
    maxFiles,
    trashedCount,
    can,
}: MediaIndexProps) {
    const { filters, loading, update, reset, hasActiveFilters } = useResourceFilters('media.index', {
        search: initial.search,
        status: '',
        trashed: initial.trashed,
        sort: initial.sort,
        direction: initial.direction,
        extra: initial.extra ?? '',
    })

    const [editing, setEditing] = useState<MediaListItem | null>(null)
    const [pendingDelete, setPendingDelete] = useState<MediaListItem | null>(null)
    const [pendingPurge, setPendingPurge] = useState<MediaListItem | null>(null)
    const [emptyingTrash, setEmptyingTrash] = useState(false)
    const [deleting, setDeleting] = useState(false)
    const [deleteError, setDeleteError] = useState<string | null>(null)
    const [usages, setUsages] = useState<UsageDetail[] | null>(null)
    const uploadRef = useRef<HTMLInputElement>(null)

    const form = useForm<EditForm>({ name: '', alt_text: '', description: '' })
    const upload = useForm<{ files: File[] }>({ files: [] })

    function openEditor(item: MediaListItem) {
        form.setData({
            name: item.name,
            alt_text: item.alt_text ?? '',
            description: item.description ?? '',
        })
        form.clearErrors()
        setEditing(item)
        setUsages(null)

        // Los usos se piden aparte: solo interesan al abrir la ficha.
        void axios
            .get<{ usages: UsageDetail[] }>(route('media.show', item.id))
            .then((response) => setUsages(response.data.usages))
            .catch(() => setUsages([]))
    }

    function submitEdit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault()

        if (!editing) {
            return
        }

        form.put(route('media.update', editing.id), {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        })
    }

    function submitUpload(files: FileList | null) {
        if (!files || files.length === 0) {
            return
        }

        upload.setData('files', Array.from(files))

        router.post(
            route('media.store'),
            { files: Array.from(files) },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => {
                    if (uploadRef.current) {
                        uploadRef.current.value = ''
                    }
                },
            },
        )
    }

    function confirmPurge() {
        if (!pendingPurge) {
            return
        }

        setDeleteError(null)

        router.delete(route('media.purge', pendingPurge.id), {
            preserveScroll: true,
            onStart: () => setDeleting(true),
            onError: (errors) =>
                setDeleteError(errors.media ?? 'No se ha podido eliminar el archivo.'),
            onSuccess: () => setPendingPurge(null),
            onFinish: () => setDeleting(false),
        })
    }

    function confirmEmptyTrash() {
        router.delete(route('media.trash.empty'), {
            preserveScroll: true,
            onStart: () => setDeleting(true),
            onSuccess: () => setEmptyingTrash(false),
            onFinish: () => setDeleting(false),
        })
    }

    function confirmDelete() {
        if (!pendingDelete) {
            return
        }

        setDeleteError(null)

        router.delete(route('media.destroy', pendingDelete.id), {
            preserveScroll: true,
            onStart: () => setDeleting(true),
            onError: (errors) => {
                // El servidor devuelve aquí el motivo cuando el archivo se usa.
                setDeleteError(errors.media ?? 'No se ha podido eliminar el archivo.')
            },
            onSuccess: () => setPendingDelete(null),
            onFinish: () => setDeleting(false),
        })
    }

    return (
        <DashboardLayout title="Biblioteca" breadcrumb="Contenido">
            <Card
                header={
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h6 className="mb-0 text-base font-semibold">Biblioteca multimedia</h6>

                        {can.create && (
                            <label className="btn btn-primary cursor-pointer items-center gap-2 rounded-lg px-5 py-2.5 text-sm">
                                <i className="ri-upload-2-line" aria-hidden="true" />
                                {upload.processing ? 'Subiendo…' : 'Subir imágenes'}
                                <input
                                    ref={uploadRef}
                                    type="file"
                                    multiple
                                    accept="image/png,image/jpeg,image/webp"
                                    onChange={(event) => submitUpload(event.target.files)}
                                    className="sr-only"
                                />
                            </label>
                        )}
                    </div>
                }
                padded={false}
            >
                <div className="flex flex-wrap items-end gap-3 px-6 py-4">
                    <div className="min-w-[220px] flex-1">
                        <SearchInput
                            value={filters.search}
                            onChange={(value) => update({ search: value })}
                            label="Buscar imágenes"
                            placeholder="Buscar por nombre o descripción…"
                        />
                    </div>

                    <div className="w-full sm:w-[190px]">
                        <select
                            value={filters.extra ?? ''}
                            onChange={(event) => update({ extra: event.target.value })}
                            className="form-select field-control h-[52px] rounded-lg"
                            aria-label="Filtrar por uso"
                        >
                            <option value="">Todas</option>
                            <option value="used">En uso</option>
                            <option value="unused">Sin usar</option>
                        </select>
                    </div>

                    <label className="flex items-center gap-2 py-2.5 text-sm">
                        <input
                            type="checkbox"
                            checked={filters.trashed}
                            onChange={(event) => update({ trashed: event.target.checked })}
                            className="form-check-input border border-neutral-300"
                        />
                        Ver papelera
                        {trashedCount > 0 && (
                            <span className="text-secondary-light">({trashedCount})</span>
                        )}
                    </label>

                    {filters.trashed && can.purge && trashedCount > 0 && (
                        <button
                            type="button"
                            onClick={() => setEmptyingTrash(true)}
                            className="btn items-center gap-2 rounded-lg border border-danger-600 px-4 py-2.5 text-sm font-medium text-danger-600 hover:bg-danger-50"
                        >
                            <i className="ri-delete-bin-7-line" aria-hidden="true" />
                            Vaciar papelera
                        </button>
                    )}

                    {hasActiveFilters && (
                        <button
                            type="button"
                            onClick={reset}
                            className="py-2.5 text-sm font-medium text-primary-600 hover:underline"
                        >
                            Limpiar filtros
                        </button>
                    )}
                </div>

                <div className="px-6 pb-6">
                    <p className="text-secondary-light mb-4 text-sm">{constraints.hint} · hasta {maxFiles} archivos por subida</p>

                    {media.data.length === 0 ? (
                        <EmptyState
                            title={
                                hasActiveFilters
                                    ? 'No hay imágenes que coincidan con los filtros'
                                    : 'La biblioteca está vacía'
                            }
                            description={
                                hasActiveFilters
                                    ? 'Prueba a cambiar la búsqueda o a quitar algún filtro.'
                                    : 'Sube las imágenes que vayas a usar en los artículos.'
                            }
                        />
                    ) : (
                        <ul
                            className={`grid grid-cols-2 gap-4 transition-opacity sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 ${
                                loading ? 'opacity-60' : ''
                            }`}
                        >
                            {media.data.map((item) => (
                                <li
                                    key={item.id}
                                    className="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-600"
                                >
                                    <button
                                        type="button"
                                        onClick={() => openEditor(item)}
                                        className="block w-full text-start"
                                    >
                                        <img
                                            src={item.thumbnail_url}
                                            alt={item.alt_text ?? ''}
                                            className="aspect-square w-full bg-neutral-100 object-cover dark:bg-dark-1"
                                        />
                                    </button>

                                    <div className="p-3">
                                        <p className="mb-1 truncate text-sm font-medium text-neutral-900 dark:text-white">
                                            {item.name}
                                        </p>

                                        <p className="text-secondary-light mb-2 text-xs">
                                            {item.size}
                                            {item.dimensions && ` · ${item.dimensions}`}
                                        </p>

                                        <div className="flex flex-wrap items-center gap-1.5">
                                            {item.deleted_at ? (
                                                <StatusBadge label="En la papelera" tone="danger" />
                                            ) : (
                                                <>
                                                    {item.status !== 'done' && (
                                                        <StatusBadge
                                                            label={item.status_label}
                                                            tone={item.status_tone}
                                                        />
                                                    )}
                                                    <StatusBadge
                                                        label={
                                                            item.usages_count === 0
                                                                ? 'Sin usar'
                                                                : `En uso (${item.usages_count})`
                                                        }
                                                        tone={item.usages_count === 0 ? 'neutral' : 'success'}
                                                    />
                                                </>
                                            )}
                                        </div>

                                        <div className="mt-3 flex flex-wrap gap-3">
                                            {item.deleted_at ? (
                                                <>
                                                    {item.can.restore && (
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                router.post(
                                                                    route('media.restore', item.id),
                                                                    {},
                                                                    { preserveScroll: true },
                                                                )
                                                            }
                                                            className="text-xs font-medium text-primary-600 hover:underline"
                                                        >
                                                            Restaurar
                                                        </button>
                                                    )}

                                                    {item.can.purge && (
                                                        <button
                                                            type="button"
                                                            onClick={() => setPendingPurge(item)}
                                                            className="text-xs font-medium text-danger-600 hover:underline"
                                                        >
                                                            Eliminar definitivamente
                                                        </button>
                                                    )}
                                                </>
                                            ) : (
                                                <>
                                                    {item.can.update && (
                                                        <button
                                                            type="button"
                                                            onClick={() => openEditor(item)}
                                                            className="text-xs font-medium text-primary-600 hover:underline"
                                                        >
                                                            Editar
                                                        </button>
                                                    )}
                                                    {item.can.delete && (
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                setDeleteError(null)
                                                                setPendingDelete(item)
                                                            }}
                                                            className="text-xs font-medium text-danger-600 hover:underline"
                                                        >
                                                            Eliminar
                                                        </button>
                                                    )}
                                                </>
                                            )}
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}

                    <Pagination
                        links={media.links}
                        from={media.from}
                        to={media.to}
                        total={media.total}
                        itemsLabel="archivos"
                    />
                </div>
            </Card>

            <Modal open={editing !== null} onClose={() => setEditing(null)} title="Detalles del archivo">
                {editing && (
                    <form onSubmit={submitEdit} noValidate>
                        <img
                            src={editing.preview_url}
                            alt={editing.alt_text ?? ''}
                            className="mb-5 max-h-[220px] w-full rounded-lg bg-neutral-100 object-contain dark:bg-dark-1"
                        />

                        <div className="flex flex-col gap-4">
                            <TextField
                                label="Nombre"
                                value={form.data.name}
                                onChange={(event) => form.setData('name', event.target.value)}
                                error={form.errors.name}
                                maxLength={150}
                                required
                            />

                            <TextField
                                label="Texto alternativo"
                                value={form.data.alt_text}
                                onChange={(event) => form.setData('alt_text', event.target.value)}
                                error={form.errors.alt_text}
                                maxLength={255}
                                hint="Describe la imagen para quien no puede verla."
                            />

                            <TextareaField
                                label="Descripción"
                                rows={2}
                                value={form.data.description}
                                onChange={(event) => form.setData('description', event.target.value)}
                                error={form.errors.description}
                                maxLength={1000}
                            />
                        </div>

                        <div className="mt-5 rounded-lg bg-neutral-100 px-4 py-3 dark:bg-neutral-700">
                            <p className="mb-2 text-sm font-semibold text-neutral-900 dark:text-white">
                                Dónde se usa
                            </p>

                            {usages === null ? (
                                <p className="text-secondary-light mb-0 text-sm">Comprobando…</p>
                            ) : usages.length === 0 ? (
                                <p className="text-secondary-light mb-0 text-sm">
                                    No se está usando en ninguna parte.
                                </p>
                            ) : (
                                <ul className="text-secondary-light flex flex-col gap-1 text-sm">
                                    {usages.map((usage, index) => (
                                        <li key={index}>
                                            {usage.label} · {usage.role}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>

                        <p className="text-secondary-light mt-3 mb-0 text-xs">
                            {editing.mime_type} · {editing.size}
                            {editing.dimensions && ` · ${editing.dimensions}`}
                            {editing.created_at &&
                                ` · subida el ${DATE_FORMAT.format(new Date(editing.created_at))}`}
                        </p>

                        <div className="mt-6 flex flex-wrap justify-end gap-3">
                            <button
                                type="button"
                                onClick={() => setEditing(null)}
                                className="btn rounded-lg border border-neutral-300 px-5 py-2.5 text-sm font-medium text-neutral-600 dark:border-neutral-500 dark:text-neutral-200"
                            >
                                Cerrar
                            </button>

                            <button
                                type="submit"
                                disabled={form.processing}
                                className="btn btn-primary rounded-lg px-5 py-2.5 text-sm disabled:opacity-70"
                            >
                                {form.processing ? 'Guardando…' : 'Guardar'}
                            </button>
                        </div>
                    </form>
                )}
            </Modal>

            <ConfirmDialog
                open={pendingPurge !== null}
                title="Eliminar definitivamente"
                message={
                    deleteError ??
                    (pendingPurge
                        ? `«${pendingPurge.name}» y todas sus versiones se borrarán del disco. Esta acción no se puede deshacer.`
                        : '')
                }
                confirmLabel={deleteError ? 'Entendido' : 'Eliminar para siempre'}
                processing={deleting}
                onConfirm={deleteError ? () => setPendingPurge(null) : confirmPurge}
                onCancel={() => {
                    setPendingPurge(null)
                    setDeleteError(null)
                }}
            />

            <ConfirmDialog
                open={emptyingTrash}
                title="Vaciar la papelera"
                message={`Se borrarán del disco los ${trashedCount} archivos de la papelera junto con todas sus versiones. Los que hayan vuelto a usarse se conservarán. Esta acción no se puede deshacer.`}
                confirmLabel="Vaciar papelera"
                processing={deleting}
                onConfirm={confirmEmptyTrash}
                onCancel={() => setEmptyingTrash(false)}
            />

            <ConfirmDialog
                open={pendingDelete !== null}
                title="Eliminar archivo"
                message={
                    deleteError ??
                    (pendingDelete
                        ? `«${pendingDelete.name}» se enviará a la papelera. Si se está usando en algún artículo, la operación se cancelará.`
                        : '')
                }
                confirmLabel={deleteError ? 'Entendido' : 'Enviar a la papelera'}
                processing={deleting}
                onConfirm={deleteError ? () => setPendingDelete(null) : confirmDelete}
                onCancel={() => {
                    setPendingDelete(null)
                    setDeleteError(null)
                }}
            />
        </DashboardLayout>
    )
}
