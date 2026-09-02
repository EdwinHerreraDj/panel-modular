import { Link } from '@inertiajs/react'
import DashboardLayout from '@/Layouts/DashboardLayout'
import Card from '@/Components/UI/Card'
import DataTable, { type Column } from '@/Components/UI/DataTable'
import SearchInput from '@/Components/UI/SearchInput'
import StatusBadge from '@/Components/UI/StatusBadge'
import Pagination from '@/Components/UI/Pagination'
import EmptyState from '@/Components/UI/EmptyState'
import { useResourceFilters } from '@/hooks/useResourceFilters'
import type { Paginated, ResourceFilterValues, SelectOption, Tone } from '@/types'

interface ActivityEntry {
    id: number
    event: string
    event_label: string
    event_tone: Tone
    description: string
    subject_label: string
    subject_id: number
    subject_url: string | null
    user_name: string | null
    ip_address: string | null
    changes: string[]
    created_at: string | null
}

interface ActivityLogsIndexProps {
    logs: Paginated<ActivityEntry>
    filters: ResourceFilterValues & { subject?: string }
    eventOptions: SelectOption[]
    subjectOptions: SelectOption[]
    periodOptions: SelectOption[]
}

const DATE_FORMAT = new Intl.DateTimeFormat('es-ES', { dateStyle: 'short', timeStyle: 'short' })

export default function ActivityLogsIndex({
    logs,
    filters: initial,
    eventOptions,
    subjectOptions,
    periodOptions,
}: ActivityLogsIndexProps) {
    // `status` lleva el tipo de cambio, `extra` el periodo y `subject` el tipo
    // de elemento.
    const { filters, loading, update, updateCustom, reset, hasActiveFilters } = useResourceFilters(
        'activity-logs.index',
        {
            search: initial.search,
            status: initial.status,
            trashed: false,
            sort: 'created_at',
            direction: 'desc',
            extra: initial.extra ?? '',
            custom: { subject: initial.subject ?? '' },
        },
    )

    const columns: Column<ActivityEntry>[] = [
        {
            key: 'created_at',
            header: 'Cuándo',
            align: 'start',
            cell: (log) => (
                <span className="whitespace-nowrap text-sm">
                    {log.created_at ? DATE_FORMAT.format(new Date(log.created_at)) : '—'}
                </span>
            ),
        },
        {
            key: 'event',
            header: 'Qué pasó',
            cell: (log) => <StatusBadge label={log.event_label} tone={log.event_tone} />,
        },
        {
            key: 'subject',
            header: 'Sobre qué',
            align: 'start',
            cell: (log) => (
                <span className="block min-w-0">
                    <span className="block truncate text-neutral-900 dark:text-white">
                        {log.subject_url ? (
                            <Link
                                href={log.subject_url}
                                className="hover:text-primary-600 dark:hover:text-primary-400"
                            >
                                {log.description}
                            </Link>
                        ) : (
                            log.description
                        )}
                    </span>
                    <span className="text-secondary-light block text-xs">
                        {log.subject_label} #{log.subject_id}
                    </span>
                </span>
            ),
        },
        {
            key: 'changes',
            header: 'Campos modificados',
            align: 'start',
            cell: (log) =>
                log.changes.length === 0 ? (
                    <span className="text-neutral-400">—</span>
                ) : (
                    <span className="flex flex-wrap gap-1">
                        {log.changes.slice(0, 4).map((field) => (
                            <span
                                key={field}
                                className="rounded bg-neutral-100 px-1.5 py-0.5 text-xs dark:bg-neutral-700"
                            >
                                {field}
                            </span>
                        ))}
                        {log.changes.length > 4 && (
                            <span
                                className="text-secondary-light text-xs"
                                title={log.changes.slice(4).join(', ')}
                            >
                                y {log.changes.length - 4} más
                            </span>
                        )}
                    </span>
                ),
        },
        {
            key: 'user',
            header: 'Quién',
            cell: (log) => (
                <span className="block">
                    <span className="block text-sm">{log.user_name ?? 'Sistema'}</span>
                    {log.ip_address && (
                        <span className="text-secondary-light block font-mono text-xs">
                            {log.ip_address}
                        </span>
                    )}
                </span>
            ),
        },
    ]

    return (
        <DashboardLayout title="Auditoría del contenido" breadcrumb="Administración">
            <Card
                header={
                    <div>
                        <h6 className="mb-1 text-base font-semibold">Quién ha cambiado qué</h6>
                        <p className="text-secondary-light mb-0 text-sm">
                            Cada alta, modificación, envío a la papelera y restauración del contenido. Se
                            guarda qué campos cambiaron, no su contenido.
                        </p>
                    </div>
                }
                padded={false}
            >
                <div className="flex flex-wrap items-end gap-3 px-6 py-4">
                    <div className="min-w-[220px] flex-1">
                        <SearchInput
                            value={filters.search}
                            onChange={(value) => update({ search: value })}
                            label="Buscar en la auditoría"
                            placeholder="Buscar por descripción o persona…"
                        />
                    </div>

                    <div className="w-full sm:w-[180px]">
                        <select
                            value={filters.custom?.subject ?? ''}
                            onChange={(event) => updateCustom('subject', event.target.value)}
                            className="form-select rounded-lg"
                            aria-label="Filtrar por tipo de elemento"
                        >
                            <option value="">Todo el contenido</option>
                            {subjectOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="w-full sm:w-[180px]">
                        <select
                            value={filters.status}
                            onChange={(event) => update({ status: event.target.value })}
                            className="form-select rounded-lg"
                            aria-label="Filtrar por tipo de cambio"
                        >
                            <option value="">Todos los cambios</option>
                            {eventOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="w-full sm:w-[180px]">
                        <select
                            value={filters.extra ?? ''}
                            onChange={(event) => update({ extra: event.target.value })}
                            className="form-select rounded-lg"
                            aria-label="Filtrar por periodo"
                        >
                            <option value="">Desde siempre</option>
                            {periodOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </div>

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
                    <DataTable
                        columns={columns}
                        rows={logs.data}
                        rowKey={(log) => log.id}
                        loading={loading}
                        empty={
                            <EmptyState
                                title={
                                    hasActiveFilters
                                        ? 'No hay cambios que coincidan con los filtros'
                                        : 'Todavía no se ha registrado ningún cambio'
                                }
                                description={
                                    hasActiveFilters
                                        ? 'Prueba a cambiar la búsqueda o a ampliar el periodo.'
                                        : 'Aquí aparecerá cada cambio sobre el contenido.'
                                }
                                action={
                                    hasActiveFilters ? (
                                        <button
                                            type="button"
                                            onClick={reset}
                                            className="text-sm font-medium text-primary-600 hover:underline"
                                        >
                                            Limpiar filtros
                                        </button>
                                    ) : undefined
                                }
                            />
                        }
                    />

                    <Pagination
                        links={logs.links}
                        from={logs.from}
                        to={logs.to}
                        total={logs.total}
                        itemsLabel="cambios"
                    />
                </div>
            </Card>
        </DashboardLayout>
    )
}
