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

interface AuthLogEntry {
    id: number
    event: string
    event_label: string
    event_tone: Tone
    user_id: number | null
    user_name: string | null
    email: string | null
    ip_address: string | null
    device: string
    user_agent: string | null
    created_at: string | null
}

interface AuthLogsIndexProps {
    logs: Paginated<AuthLogEntry>
    filters: ResourceFilterValues
    eventOptions: SelectOption[]
    periodOptions: SelectOption[]
    summary: {
        logins_today: number
        failed_today: number
        people_today: number
        failed_week: number
    }
}

const DATE_FORMAT = new Intl.DateTimeFormat('es-ES', { dateStyle: 'short', timeStyle: 'medium' })

export default function AuthLogsIndex({
    logs,
    filters: initial,
    eventOptions,
    periodOptions,
    summary,
}: AuthLogsIndexProps) {
    const { filters, loading, update, reset, hasActiveFilters } = useResourceFilters('auth-logs.index', {
        search: initial.search,
        status: initial.status,
        trashed: false,
        sort: 'created_at',
        direction: 'desc',
        extra: initial.extra ?? '',
    })

    const cards = [
        { label: 'Accesos hoy', value: summary.logins_today, tone: 'success' as Tone },
        { label: 'Personas distintas hoy', value: summary.people_today, tone: 'info' as Tone },
        {
            label: 'Intentos fallidos hoy',
            value: summary.failed_today,
            tone: (summary.failed_today > 0 ? 'warning' : 'neutral') as Tone,
        },
        {
            label: 'Intentos fallidos (7 días)',
            value: summary.failed_week,
            tone: (summary.failed_week > 0 ? 'danger' : 'neutral') as Tone,
        },
    ]

    const columns: Column<AuthLogEntry>[] = [
        {
            key: 'created_at',
            header: 'Fecha y hora',
            align: 'start',
            cell: (log) => (
                <span className="whitespace-nowrap text-sm">
                    {log.created_at ? DATE_FORMAT.format(new Date(log.created_at)) : '—'}
                </span>
            ),
        },
        {
            key: 'event',
            header: 'Evento',
            cell: (log) => <StatusBadge label={log.event_label} tone={log.event_tone} />,
        },
        {
            key: 'user',
            header: 'Quién',
            align: 'start',
            cell: (log) => (
                <span className="block min-w-0">
                    {log.user_name ? (
                        <>
                            <span className="block truncate font-medium text-neutral-900 dark:text-white">
                                {log.user_id ? (
                                    <Link
                                        href={route('users.edit', log.user_id)}
                                        className="hover:text-primary-600 dark:hover:text-primary-400"
                                    >
                                        {log.user_name}
                                    </Link>
                                ) : (
                                    log.user_name
                                )}
                            </span>
                            <span className="text-secondary-light block truncate text-xs">{log.email}</span>
                        </>
                    ) : (
                        <>
                            <span className="block truncate text-neutral-700 dark:text-neutral-200">
                                {log.email ?? 'Sin identificar'}
                            </span>
                            {/* Un correo sin usuario es un intento contra una cuenta que no existe. */}
                            <span className="text-secondary-light block text-xs">Cuenta inexistente</span>
                        </>
                    )}
                </span>
            ),
        },
        {
            key: 'ip_address',
            header: 'Dirección IP',
            cell: (log) => (
                <span className="whitespace-nowrap font-mono text-xs">{log.ip_address ?? '—'}</span>
            ),
        },
        {
            key: 'device',
            header: 'Dispositivo',
            align: 'start',
            cell: (log) => (
                <span className="text-sm" title={log.user_agent ?? undefined}>
                    {log.device}
                </span>
            ),
        },
    ]

    return (
        <DashboardLayout title="Registro de accesos" breadcrumb="Administración">
            <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {cards.map((card) => (
                    <div
                        key={card.label}
                        className="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-600 dark:bg-dark-2"
                    >
                        <span className="block text-2xl font-semibold leading-tight text-neutral-900 dark:text-white">
                            {card.value}
                        </span>
                        <span className="text-secondary-light mt-1 flex items-center gap-2 text-sm">
                            <span
                                className={`h-2 w-2 shrink-0 rounded-full ${
                                    {
                                        success: 'bg-success-500',
                                        info: 'bg-info-500',
                                        warning: 'bg-warning-500',
                                        danger: 'bg-danger-500',
                                        neutral: 'bg-neutral-400',
                                    }[card.tone]
                                }`}
                                aria-hidden="true"
                            />
                            {card.label}
                        </span>
                    </div>
                ))}
            </div>

            <Card
                header={
                    <div>
                        <h6 className="mb-1 text-base font-semibold">Quién ha entrado en la plataforma</h6>
                        <p className="text-secondary-light mb-0 text-sm">
                            Entradas, salidas, intentos fallidos y cambios de contraseña. Nunca se guarda
                            ninguna contraseña.
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
                            label="Buscar en el registro"
                            placeholder="Buscar por nombre, correo o IP…"
                        />
                    </div>

                    <div className="w-full sm:w-[220px]">
                        <select
                            value={filters.status}
                            onChange={(event) => update({ status: event.target.value })}
                            className="form-select rounded-lg"
                            aria-label="Filtrar por tipo de evento"
                        >
                            <option value="">Todos los eventos</option>
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
                                        ? 'No hay accesos que coincidan con los filtros'
                                        : 'Todavía no hay accesos registrados'
                                }
                                description={
                                    hasActiveFilters
                                        ? 'Prueba a cambiar la búsqueda o a ampliar el periodo.'
                                        : 'Aquí aparecerá cada entrada y cada intento fallido.'
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
                        itemsLabel="registros"
                    />
                </div>
            </Card>
        </DashboardLayout>
    )
}
