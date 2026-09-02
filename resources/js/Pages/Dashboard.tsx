import { Link } from '@inertiajs/react'
import { Suspense, lazy, useMemo, type ReactNode } from 'react'
import type { Tone } from '@/types'
import { relativeTime, dateTime } from '@/lib/format'
import { resolveWidget } from '@/lib/widgets'
import DashboardLayout from '@/Layouts/DashboardLayout'
import Card from '@/Components/UI/Card'
import StatCard from '@/Components/UI/StatCard'
import IconArticle from '~icons/solar/document-text-outline'
import IconCheck from '~icons/solar/check-circle-outline'
import IconClock from '~icons/solar/clock-circle-outline'
import IconUsers from '~icons/solar/users-group-rounded-outline'
import IconGallery from '~icons/solar/gallery-outline'
import IconLogin from '~icons/solar/login-3-outline'
import IconShield from '~icons/solar/shield-warning-outline'
import IconPen from '~icons/solar/pen-new-square-outline'
import IconBox from '~icons/solar/box-minimalistic-outline'
import IconDefault from '~icons/solar/widget-outline'

/** Iconos que puede pedir una tarjeta o una acción de un módulo. */
const icons: Record<string, ReactNode> = {
    article: <IconArticle />,
    check: <IconCheck />,
    clock: <IconClock />,
    pen: <IconPen />,
    users: <IconUsers />,
    gallery: <IconGallery />,
    login: <IconLogin />,
    shield: <IconShield />,
    box: <IconBox />,
}

function icon(key: string): ReactNode {
    return icons[key] ?? <IconDefault />
}

interface DashboardCard {
    key: string
    label: string
    count: number
    icon: string
    tone: Tone | 'primary'
    hint: string | null
    href: string | null
}

interface DashboardAlert {
    key: string
    text: string
    tone: string
    action_label: string | null
    action_href: string | null
}

interface DashboardWidget {
    component: string
    props: Record<string, unknown>
    area: 'main' | 'side'
}

interface ActivityEntry {
    id: number
    description: string
    author_name: string | null
    created_at: string | null
}

interface DashboardProps {
    greeting: { name: string; role: string | null; last_login_at: string | null }
    actions: { label: string; href: string; icon: string }[]
    cards: DashboardCard[]
    alerts: DashboardAlert[]
    widgets: DashboardWidget[]
    activity: ActivityEntry[] | null
    platform: { users: number; media: number; logins_today: number; failed_today: number } | null
}

/**
 * Pinta un bloque aportado por un módulo, resolviéndolo por su nombre.
 */
function ModuleWidget({ widget }: { widget: DashboardWidget }) {
    const Component = useMemo(() => {
        const loader = resolveWidget(widget.component)

        return loader ? lazy(loader) : null
    }, [widget.component])

    if (!Component) {
        return null
    }

    return (
        <Suspense fallback={<div className="h-32 animate-pulse rounded-xl bg-neutral-100 dark:bg-dark-2" />}>
            <Component {...widget.props} />
        </Suspense>
    )
}

/**
 * Panel de inicio.
 *
 * El núcleo pone el saludo, la actividad y las cifras de la plataforma. Las
 * tarjetas, los avisos y los bloques los aportan los módulos activos: esta
 * pantalla no sabe qué es un artículo.
 */
export default function Dashboard({
    greeting,
    actions,
    cards,
    alerts,
    widgets,
    activity,
    platform,
}: DashboardProps) {
    const mainWidgets = widgets.filter((widget) => widget.area === 'main')
    const sideWidgets = widgets.filter((widget) => widget.area === 'side')

    return (
        <DashboardLayout title="Panel">
            {/* Saludo */}
            <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h5 className="mb-1 text-xl font-semibold">Hola, {greeting.name}</h5>
                    <p className="text-secondary-light mb-0 text-sm">
                        {greeting.role && <>Estás en el panel como {greeting.role.toLowerCase()}. </>}
                        {greeting.last_login_at ? (
                            <>Tu acceso anterior fue el {dateTime(greeting.last_login_at)}.</>
                        ) : (
                            <>Es tu primer acceso.</>
                        )}
                    </p>
                </div>

                {actions.map((action) => (
                    <Link
                        key={action.href}
                        href={action.href}
                        className="btn btn-primary flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm"
                    >
                        <span className="text-lg" aria-hidden="true">
                            {icon(action.icon)}
                        </span>
                        {action.label}
                    </Link>
                ))}
            </div>

            {/* Resumen */}
            {cards.length > 0 && (
                <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {cards.map((card) => (
                        <StatCard
                            key={card.key}
                            label={card.label}
                            value={card.count}
                            tone={card.tone}
                            icon={icon(card.icon)}
                            href={card.href}
                            hint={card.hint}
                        />
                    ))}
                </div>
            )}

            {/* Avisos */}
            {alerts.length > 0 && (
                <div className="mb-6 grid gap-3">
                    {alerts.map((alert) => (
                        <div
                            key={alert.key}
                            className={`flex flex-wrap items-center justify-between gap-3 rounded-lg border px-4 py-3 text-sm ${
                                alert.tone === 'warning'
                                    ? 'border-warning-200 bg-warning-100/60 text-warning-800 dark:border-warning-600/40 dark:bg-warning-600/10 dark:text-warning-300'
                                    : 'border-info-200 bg-info-100/60 text-info-800 dark:border-info-600/40 dark:bg-info-600/10 dark:text-info-300'
                            }`}
                        >
                            <span>{alert.text}</span>

                            {alert.action_href && alert.action_label && (
                                <Link
                                    href={alert.action_href}
                                    className="shrink-0 font-semibold underline underline-offset-2"
                                >
                                    {alert.action_label}
                                </Link>
                            )}
                        </div>
                    ))}
                </div>
            )}

            <div className="grid gap-6 xl:grid-cols-3">
                <div className="grid content-start gap-6 xl:col-span-2">
                    {mainWidgets.map((widget) => (
                        <ModuleWidget key={widget.component} widget={widget} />
                    ))}
                </div>

                <div className="grid content-start gap-6">
                    {sideWidgets.map((widget) => (
                        <ModuleWidget key={widget.component} widget={widget} />
                    ))}

                    {/* Actividad reciente: solo para quien ve la auditoría */}
                    {activity && (
                        <Card header={<h6 className="mb-0 text-base font-semibold">Actividad reciente</h6>}>
                            {activity.length === 0 ? (
                                <p className="text-secondary-light mb-0 text-sm">Sin movimientos todavía.</p>
                            ) : (
                                <ul className="flex flex-col gap-3">
                                    {activity.map((entry) => (
                                        <li key={entry.id} className="flex gap-3 text-sm">
                                            <span
                                                className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-primary-500"
                                                aria-hidden="true"
                                            />
                                            <span className="min-w-0">
                                                <span className="block text-neutral-700 dark:text-neutral-200">
                                                    {entry.description}
                                                </span>
                                                <span className="text-secondary-light text-xs">
                                                    {entry.author_name ?? 'Sistema'} ·{' '}
                                                    {relativeTime(entry.created_at)}
                                                </span>
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Card>
                    )}
                </div>
            </div>

            {/* Plataforma: solo superadministrador */}
            {platform && (
                <div className="mt-6">
                    <div className="mb-3 flex items-center justify-between gap-3">
                        <h6 className="mb-0 text-base font-semibold">Plataforma</h6>
                        <Link
                            href={route('auth-logs.index')}
                            className="text-primary-600 text-sm font-medium hover:underline dark:text-primary-400"
                        >
                            Ver registro de accesos
                        </Link>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <StatCard
                            label="Usuarios"
                            value={platform.users}
                            tone="primary"
                            icon={<IconUsers />}
                            href={route('users.index')}
                        />
                        <StatCard
                            label="Archivos en la biblioteca"
                            value={platform.media}
                            tone="info"
                            icon={<IconGallery />}
                            href={route('media.index')}
                        />
                        <StatCard
                            label="Accesos hoy"
                            value={platform.logins_today}
                            tone="success"
                            icon={<IconLogin />}
                            href={route('auth-logs.index', { event: 'login' })}
                        />
                        <StatCard
                            label="Intentos fallidos hoy"
                            value={platform.failed_today}
                            tone={platform.failed_today > 0 ? 'danger' : 'neutral'}
                            icon={<IconShield />}
                            href={route('auth-logs.index', { event: 'login_failed' })}
                        />
                    </div>
                </div>
            )}
        </DashboardLayout>
    )
}
