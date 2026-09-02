import { router, useForm } from '@inertiajs/react'
import { useCallback, useEffect, useMemo, useState } from 'react'
import DashboardLayout from '@/Layouts/DashboardLayout'
import Card from '@/Components/UI/Card'
import Tabs, { TabPanel } from '@/Components/UI/Tabs'
import JsonViewer from '@/Components/UI/JsonViewer'
import StatusBadge from '@/Components/UI/StatusBadge'
import ConfirmDialog from '@/Components/UI/ConfirmDialog'
import TextField from '@/Components/UI/TextField'
import CopyButton from '@/Components/UI/CopyButton'
import EmptyState from '@/Components/UI/EmptyState'
import type { Tone } from '@/types'

interface EndpointParameter {
    name: string
    description: string
    example: string
}

interface Endpoint {
    key: string
    path: string
    title: string
    description: string
    requires_key: boolean
    parameters: EndpointParameter[]
}

interface ApiKeyItem {
    id: number
    name: string
    prefix: string
    allowed_origins: string[]
    rate_limit: number | null
    requests_count: number
    last_used_at: string | null
    last_used_ip: string | null
    expires_at: string | null
    revoked_at: string | null
    is_usable: boolean
    created_by: string | null
    created_at: string | null
}

interface RunResult {
    status: number
    ok: boolean
    duration_ms: number
    size_bytes: number
    url: string
    /** «HIT» si la respuesta salió de la caché. */
    cache: string | null
    body: unknown
    raw: string
    ran_at: string
}

interface ApiIndexProps {
    connection: {
        base_url: string
        header: string
        version: string
        requires_key: boolean
        rate_limit: number
        cache_seconds: number
        cache_enabled: boolean
        /** La dirección configurada solo funciona en este ordenador. */
        address_is_local: boolean
    }
    endpoints: Endpoint[]
    globalOrigins: string[]
    keys: ApiKeyItem[]
    newKey: { id: number; name: string; plain: string } | null
    content: { key: string; label: string; count: number }[]
}

const DATE_TIME = new Intl.DateTimeFormat('es-ES', { dateStyle: 'short', timeStyle: 'short' })

function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

export default function ApiIndex({
    connection,
    endpoints,
    globalOrigins,
    keys,
    newKey,
    content,
}: ApiIndexProps) {
    const [tab, setTab] = useState(newKey ? 'keys' : 'console')
    // Se arranca en el listado de artículos: es lo que casi siempre se quiere ver.
    const [selected, setSelected] = useState<Endpoint | null>(
        () => endpoints.find((endpoint) => endpoint.key === 'articulos') ?? endpoints[0] ?? null,
    )
    const [params, setParams] = useState<Record<string, string>>({})
    const [result, setResult] = useState<RunResult | null>(null)
    const [running, setRunning] = useState(false)
    const [failure, setFailure] = useState<string | null>(null)
    const [autoRefresh, setAutoRefresh] = useState(false)

    // Estado general: se comprueba al abrir la pantalla llamando de verdad al
    // endpoint de estado, no dando por hecho que funciona.
    const [health, setHealth] = useState<RunResult | null>(null)

    const run = useCallback(
        async (endpoint: Endpoint, values: Record<string, string>, target: 'result' | 'health') => {
            if (target === 'result') {
                setRunning(true)
                setFailure(null)
            }

            // El slug va en la dirección, no en la cadena de consulta.
            const { slug, ...query } = values
            const path = endpoint.path.replace('{slug}', slug ? encodeURIComponent(slug) : '')

            try {
                const response = await fetch(route('api.connection.run'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': decodeURIComponent(
                            document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
                        ),
                    },
                    body: JSON.stringify({ path, query }),
                })

                const data = await response.json()

                if (!response.ok) {
                    if (target === 'result') {
                        setFailure(data.error ?? 'No se ha podido ejecutar la petición.')
                    }

                    return
                }

                if (target === 'health') {
                    setHealth(data)
                } else {
                    setResult(data)
                }
            } catch {
                if (target === 'result') {
                    setFailure('No se ha podido contactar con el servidor.')
                }
            } finally {
                if (target === 'result') {
                    setRunning(false)
                }
            }
        },
        [],
    )

    // Comprobación de estado al entrar.
    useEffect(() => {
        const endpoint = endpoints.find((item) => item.key === 'estado')

        if (endpoint) {
            void run(endpoint, {}, 'health')
        }
    }, [endpoints, run])

    // Refresco en vivo cada 10 segundos mientras esté activado.
    useEffect(() => {
        if (!autoRefresh || selected === null) {
            return
        }

        const timer = window.setInterval(() => {
            void run(selected, params, 'result')
        }, 10000)

        return () => window.clearInterval(timer)
    }, [autoRefresh, selected, params, run])

    function selectEndpoint(endpoint: Endpoint) {
        setSelected(endpoint)
        setParams({})
        setResult(null)
        setFailure(null)
        setAutoRefresh(false)
    }

    const requestUrl = useMemo(() => {
        if (selected === null) {
            return connection.base_url
        }

        const { slug, ...query } = params
        const path = selected.path.replace('{slug}', slug || '{slug}')
        const search = new URLSearchParams(
            Object.entries(query).filter(([, value]) => value.trim() !== ''),
        ).toString()

        return `${connection.base_url}/${path}${search ? `?${search}` : ''}`
    }, [connection.base_url, selected, params])

    const curl = useMemo(
        () =>
            connection.requires_key && selected?.requires_key
                ? `curl "${requestUrl}" \\\n  -H "${connection.header}: TU_CLAVE"`
                : `curl "${requestUrl}"`,
        [requestUrl, connection, selected],
    )

    const activeKeys = keys.filter((key) => key.is_usable)

    return (
        <DashboardLayout title="Conexión con la web" breadcrumb="Administración">
            {/* Estado general */}
            <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div
                    className={`rounded-xl border p-5 ${
                        health === null
                            ? 'border-neutral-200 bg-white dark:border-neutral-600 dark:bg-dark-2'
                            : health.ok
                              ? 'border-success-200 bg-success-100/50 dark:border-success-600/40 dark:bg-success-600/10'
                              : 'border-danger-200 bg-danger-100/50 dark:border-danger-600/40 dark:bg-danger-600/10'
                    }`}
                >
                    <span className="flex items-center gap-2 text-lg font-semibold text-neutral-900 dark:text-white">
                        {health === null ? (
                            <>
                                <i className="ri-loader-4-line animate-spin text-base" aria-hidden="true" />
                                Comprobando…
                            </>
                        ) : health.ok ? (
                            <>
                                <span
                                    className="h-2.5 w-2.5 animate-pulse rounded-full bg-success-500"
                                    aria-hidden="true"
                                />
                                Operativa
                            </>
                        ) : (
                            <>
                                <span className="h-2.5 w-2.5 rounded-full bg-danger-500" aria-hidden="true" />
                                No responde
                            </>
                        )}
                    </span>
                    <span className="text-secondary-light mt-1 block text-sm">
                        Estado de la API{health ? ` · ${health.duration_ms} ms` : ''}
                    </span>
                </div>

                {content.map((item) => (
                    <div
                        key={item.key}
                        className="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-600 dark:bg-dark-2"
                    >
                        <span className="block text-2xl font-semibold text-neutral-900 dark:text-white">
                            {item.count}
                        </span>
                        <span className="text-secondary-light mt-1 block text-sm">{item.label}</span>
                    </div>
                ))}

                <div className="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-600 dark:bg-dark-2">
                    <span className="block text-2xl font-semibold text-neutral-900 dark:text-white">
                        {activeKeys.length}
                    </span>
                    <span className="text-secondary-light mt-1 block text-sm">
                        {activeKeys.length === 1 ? 'Clave activa' : 'Claves activas'}
                        {keys.length > activeKeys.length && ` · ${keys.length - activeKeys.length} revocadas`}
                    </span>
                </div>

                <div className="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-600 dark:bg-dark-2">
                    <span className="block text-2xl font-semibold text-neutral-900 dark:text-white">
                        {connection.rate_limit}
                    </span>
                    <span className="text-secondary-light mt-1 block text-sm">
                        Peticiones por minuto y clave
                    </span>

                    {connection.cache_enabled ? (
                        <span className="text-secondary-light mt-2 flex flex-wrap items-center gap-2 text-xs">
                            Respuestas cacheadas {connection.cache_seconds} s
                            <button
                                type="button"
                                onClick={() =>
                                    router.post(
                                        route('api.connection.flush'),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                                className="font-medium text-primary-600 hover:underline dark:text-primary-400"
                            >
                                Vaciar ahora
                            </button>
                        </span>
                    ) : (
                        <span className="text-secondary-light mt-2 block text-xs">Sin caché</span>
                    )}
                </div>
            </div>

            {/* Dirección base */}
            <div className="mb-6 rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-600 dark:bg-dark-2">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="min-w-0">
                        <span className="text-secondary-light block text-xs uppercase tracking-wide">
                            Dirección base de la API · versión {connection.version}
                        </span>
                        <code className="mt-1 block truncate font-mono text-sm text-neutral-900 dark:text-white">
                            {connection.base_url}
                        </code>
                    </div>

                    <CopyButton value={connection.base_url} label="Copiar dirección" />
                </div>

                {connection.address_is_local && (
                    <p className="mt-4 mb-0 flex items-start gap-2 rounded-lg bg-warning-100/60 px-3 py-2 text-sm text-warning-800 dark:bg-warning-600/10 dark:text-warning-300">
                        <i className="ri-alert-line mt-px shrink-0" aria-hidden="true" />
                        <span>
                            Esta dirección es local: <strong>no funciona fuera de este ordenador</strong>.
                            Antes de darle la documentación a nadie, pon <code>APP_URL</code> con el dominio
                            real en el archivo <code>.env</code>.
                        </span>
                    </p>
                )}

                {!connection.requires_key && (
                    <p className="mt-4 mb-0 flex items-start gap-2 rounded-lg bg-warning-100/60 px-3 py-2 text-sm text-warning-800 dark:bg-warning-600/10 dark:text-warning-300">
                        <i className="ri-alert-line mt-px" aria-hidden="true" />
                        La API está abierta sin clave (<code>API_REQUIRE_KEY=false</code>). Está bien para
                        desarrollar, pero actívala antes de publicar.
                    </p>
                )}
            </div>

            <Tabs
                tabs={[
                    { id: 'console', label: 'Probador en vivo', icon: 'ri-play-circle-line' },
                    { id: 'endpoints', label: 'Endpoints', icon: 'ri-node-tree' },
                    {
                        id: 'keys',
                        label: `Claves y dominios${keys.length > 0 ? ` (${keys.length})` : ''}`,
                        icon: 'ri-key-2-line',
                    },
                    { id: 'guide', label: 'Cómo conectar la web', icon: 'ri-book-open-line' },
                ]}
                active={tab}
                onChange={setTab}
            >
                <TabPanel id="console" active={tab}>
                    {selected && (
                        <Console
                            endpoints={endpoints}
                            selected={selected}
                            onSelect={selectEndpoint}
                            params={params}
                            setParams={setParams}
                            requestUrl={requestUrl}
                            curl={curl}
                            result={result}
                            running={running}
                            failure={failure}
                            autoRefresh={autoRefresh}
                            setAutoRefresh={setAutoRefresh}
                            onRun={() => void run(selected, params, 'result')}
                        />
                    )}
                </TabPanel>

                <TabPanel id="endpoints" active={tab}>
                    <EndpointList endpoints={endpoints} baseUrl={connection.base_url} />
                </TabPanel>

                <TabPanel id="keys" active={tab}>
                    <KeyManager
                        keys={keys}
                        newKey={newKey}
                        globalOrigins={globalOrigins}
                        header={connection.header}
                    />
                </TabPanel>

                <TabPanel id="guide" active={tab}>
                    <Guide connection={connection} />
                </TabPanel>
            </Tabs>
        </DashboardLayout>
    )
}

/* ------------------------------------------------------------------------ */
/* Probador                                                                   */
/* ------------------------------------------------------------------------ */

interface ConsoleProps {
    endpoints: Endpoint[]
    selected: Endpoint
    onSelect: (endpoint: Endpoint) => void
    params: Record<string, string>
    setParams: (params: Record<string, string>) => void
    requestUrl: string
    curl: string
    result: RunResult | null
    running: boolean
    failure: string | null
    autoRefresh: boolean
    setAutoRefresh: (value: boolean) => void
    onRun: () => void
}

function Console({
    endpoints,
    selected,
    onSelect,
    params,
    setParams,
    requestUrl,
    curl,
    result,
    running,
    failure,
    autoRefresh,
    setAutoRefresh,
    onRun,
}: ConsoleProps) {
    return (
        <div className="grid gap-6 lg:grid-cols-5">
            {/* Petición */}
            <div className="lg:col-span-2">
                <Card header={<h6 className="mb-0 text-base font-semibold">Petición</h6>}>
                    <div className="mb-5">
                        <label htmlFor="endpoint" className="form-label mb-2 block text-sm font-medium">
                            Qué quieres consultar
                        </label>
                        <select
                            id="endpoint"
                            value={selected.key}
                            onChange={(event) => {
                                const endpoint = endpoints.find((item) => item.key === event.target.value)

                                if (endpoint) onSelect(endpoint)
                            }}
                            className="form-select rounded-lg"
                        >
                            {endpoints.map((endpoint) => (
                                <option key={endpoint.key} value={endpoint.key}>
                                    {endpoint.title}
                                </option>
                            ))}
                        </select>
                        <p className="text-secondary-light mt-2 mb-0 text-sm">{selected.description}</p>
                    </div>

                    {selected.parameters.length > 0 && (
                        <div className="mb-5 grid gap-4">
                            {selected.parameters.map((parameter) => (
                                <TextField
                                    key={parameter.name}
                                    label={parameter.description}
                                    name={parameter.name}
                                    value={params[parameter.name] ?? ''}
                                    onChange={(event) =>
                                        setParams({ ...params, [parameter.name]: event.target.value })
                                    }
                                    placeholder={parameter.example || 'Escribe un valor'}
                                    hint={
                                        parameter.name === 'slug'
                                            ? 'La dirección del artículo, tal y como sale en el listado.'
                                            : undefined
                                    }
                                />
                            ))}
                        </div>
                    )}

                    <div className="mb-4 rounded-lg bg-neutral-100 p-3 dark:bg-neutral-700">
                        <span className="text-secondary-light mb-1 block text-xs uppercase tracking-wide">
                            Se pedirá
                        </span>
                        <code className="block break-all font-mono text-xs text-neutral-800 dark:text-neutral-100">
                            GET {requestUrl}
                        </code>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <button
                            type="button"
                            onClick={onRun}
                            disabled={running}
                            className="btn btn-primary flex items-center gap-2 rounded-lg px-5 py-2.5 text-sm disabled:cursor-not-allowed disabled:opacity-70"
                        >
                            {running ? (
                                <>
                                    <i className="ri-loader-4-line animate-spin" aria-hidden="true" />
                                    Consultando…
                                </>
                            ) : (
                                <>
                                    <i className="ri-play-circle-line" aria-hidden="true" />
                                    Probar ahora
                                </>
                            )}
                        </button>

                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={autoRefresh}
                                onChange={(event) => setAutoRefresh(event.target.checked)}
                                className="form-check-input border border-neutral-300"
                            />
                            Repetir cada 10 s
                        </label>
                    </div>

                    {failure && (
                        <p className="mt-4 mb-0 flex items-start gap-2 rounded-lg bg-danger-100/60 px-3 py-2 text-sm text-danger-700 dark:bg-danger-600/10 dark:text-danger-300">
                            <i className="ri-error-warning-line mt-px" aria-hidden="true" />
                            {failure}
                        </p>
                    )}

                    <div className="mt-5 border-t border-neutral-200 pt-4 dark:border-neutral-600">
                        <div className="mb-2 flex items-center justify-between gap-3">
                            <span className="text-secondary-light text-xs uppercase tracking-wide">
                                Lo mismo desde fuera
                            </span>
                            <CopyButton value={curl} label="Copiar" />
                        </div>
                        <pre className="overflow-x-auto rounded-lg bg-neutral-900 p-3 text-xs text-neutral-300 dark:bg-black/40">
                            <code>{curl}</code>
                        </pre>
                    </div>
                </Card>
            </div>

            {/* Respuesta */}
            <div className="lg:col-span-3">
                <Card
                    header={
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <h6 className="mb-0 text-base font-semibold">Respuesta</h6>

                            {result && (
                                <div className="flex flex-wrap items-center gap-2 text-xs">
                                    <StatusBadge
                                        label={`HTTP ${result.status}`}
                                        tone={(result.ok ? 'success' : 'danger') as Tone}
                                    />
                                    <span className="text-secondary-light">{result.duration_ms} ms</span>
                                    <span className="text-secondary-light">
                                        {formatBytes(result.size_bytes)}
                                    </span>
                                    {result.cache === 'HIT' && (
                                        <span
                                            className="rounded-full bg-info-100 px-2 py-0.5 font-medium text-info-700 dark:bg-info-600/25 dark:text-info-400"
                                            title="La respuesta salió de la caché, no de la base de datos"
                                        >
                                            desde caché
                                        </span>
                                    )}
                                    <span className="text-secondary-light">
                                        {DATE_TIME.format(new Date(result.ran_at))}
                                    </span>
                                    <CopyButton value={result.raw} label="Copiar JSON" />
                                </div>
                            )}
                        </div>
                    }
                >
                    {result === null ? (
                        <EmptyState
                            title="Todavía no has probado nada"
                            description="Elige qué consultar y pulsa «Probar ahora». Verás exactamente el JSON que recibirá tu web, con los datos reales de este blog."
                        />
                    ) : (
                        <JsonViewer value={result.body} />
                    )}
                </Card>
            </div>
        </div>
    )
}

/* ------------------------------------------------------------------------ */
/* Documentación de endpoints                                                 */
/* ------------------------------------------------------------------------ */

function EndpointList({ endpoints, baseUrl }: { endpoints: Endpoint[]; baseUrl: string }) {
    return (
        <div className="grid gap-4">
            {endpoints.map((endpoint) => (
                <Card key={endpoint.key}>
                    <div className="mb-3 flex flex-wrap items-start justify-between gap-3">
                        <div className="min-w-0">
                            <h6 className="mb-1 text-base font-semibold">{endpoint.title}</h6>
                            <code className="block break-all font-mono text-sm text-primary-600 dark:text-primary-400">
                                GET {baseUrl}/{endpoint.path}
                            </code>
                        </div>

                        <StatusBadge
                            label={endpoint.requires_key ? 'Requiere clave' : 'Abierto'}
                            tone={endpoint.requires_key ? 'info' : 'neutral'}
                        />
                    </div>

                    <p className="text-secondary-light mb-0 text-sm">{endpoint.description}</p>

                    {endpoint.parameters.length > 0 && (
                        <div className="mt-4 overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-neutral-200 dark:border-neutral-600">
                                        <th className="pb-2 text-start font-medium">Parámetro</th>
                                        <th className="pb-2 text-start font-medium">Para qué sirve</th>
                                        <th className="pb-2 text-start font-medium">Ejemplo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {endpoint.parameters.map((parameter) => (
                                        <tr
                                            key={parameter.name}
                                            className="border-b border-neutral-100 last:border-0 dark:border-neutral-700"
                                        >
                                            <td className="py-2 font-mono text-xs">{parameter.name}</td>
                                            <td className="text-secondary-light py-2">
                                                {parameter.description}
                                            </td>
                                            <td className="py-2 font-mono text-xs">
                                                {parameter.example || '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>
            ))}
        </div>
    )
}

/* ------------------------------------------------------------------------ */
/* Claves                                                                     */
/* ------------------------------------------------------------------------ */

interface KeyManagerProps {
    keys: ApiKeyItem[]
    newKey: { id: number; name: string; plain: string } | null
    globalOrigins: string[]
    header: string
}

interface KeyForm {
    name: string
    allowed_origins: string[]
    rate_limit: string
    expires_at: string
    [key: string]: string | string[]
}

function KeyManager({ keys, newKey, globalOrigins, header }: KeyManagerProps) {
    const [creating, setCreating] = useState(false)
    const [origin, setOrigin] = useState('')
    const [pendingRevoke, setPendingRevoke] = useState<ApiKeyItem | null>(null)
    const [pendingDelete, setPendingDelete] = useState<ApiKeyItem | null>(null)
    const [processing, setProcessing] = useState(false)

    const { data, setData, post, reset, processing: saving, errors } = useForm<KeyForm>({
        name: '',
        allowed_origins: [],
        rate_limit: '',
        expires_at: '',
    })

    function addOrigin() {
        const value = origin.trim().replace(/\/$/, '')

        if (value === '' || data.allowed_origins.includes(value)) {
            setOrigin('')

            return
        }

        setData('allowed_origins', [...data.allowed_origins, value])
        setOrigin('')
    }

    function submit() {
        post(route('api-keys.store'), {
            preserveScroll: true,
            onSuccess: () => {
                reset()
                setCreating(false)
            },
        })
    }

    return (
        <div className="grid gap-6">
            {/* La clave recién creada, una sola vez */}
            {newKey && (
                <div className="rounded-xl border border-success-300 bg-success-100/50 p-5 dark:border-success-600/40 dark:bg-success-600/10">
                    <h6 className="mb-1 flex items-center gap-2 text-base font-semibold text-success-800 dark:text-success-300">
                        <i className="ri-key-2-line" aria-hidden="true" />
                        Clave «{newKey.name}» creada
                    </h6>
                    <p className="mb-3 text-sm text-success-800 dark:text-success-300">
                        Cópiala ahora y guárdala en la web. <strong>No se podrá volver a ver</strong>: si la
                        pierdes habrá que generar otra.
                    </p>

                    <div className="flex flex-wrap items-center gap-3 rounded-lg bg-white p-3 dark:bg-dark-2">
                        <code className="min-w-0 flex-1 break-all font-mono text-sm text-neutral-900 dark:text-white">
                            {newKey.plain}
                        </code>
                        <CopyButton value={newKey.plain} label="Copiar clave" />
                    </div>
                </div>
            )}

            <Card
                header={
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h6 className="mb-1 text-base font-semibold">Claves de acceso</h6>
                            <p className="text-secondary-light mb-0 text-sm">
                                Una clave por web que consuma la API. Se envía en la cabecera{' '}
                                <code className="font-mono text-xs">{header}</code>.
                            </p>
                        </div>

                        {!creating && (
                            <button
                                type="button"
                                onClick={() => setCreating(true)}
                                className="btn btn-primary flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm"
                            >
                                <i className="ri-add-line" aria-hidden="true" />
                                Nueva clave
                            </button>
                        )}
                    </div>
                }
            >
                {creating && (
                    <div className="mb-6 rounded-lg border border-neutral-200 p-5 dark:border-neutral-600">
                        <div className="grid gap-5">
                            <TextField
                                label="¿Para qué es esta clave?"
                                name="name"
                                value={data.name}
                                onChange={(event) => setData('name', event.target.value)}
                                error={errors.name}
                                placeholder="Web corporativa"
                                hint="Un nombre que te permita reconocerla dentro de un año."
                                required
                            />

                            <div>
                                <label htmlFor="origin" className="form-label mb-2 block text-sm font-medium">
                                    Dominios desde los que se podrá usar
                                </label>

                                <div className="flex flex-wrap gap-2">
                                    <input
                                        id="origin"
                                        type="url"
                                        value={origin}
                                        onChange={(event) => setOrigin(event.target.value)}
                                        onKeyDown={(event) => {
                                            if (event.key === 'Enter') {
                                                event.preventDefault()
                                                addOrigin()
                                            }
                                        }}
                                        placeholder="https://www.tuweb.com"
                                        className="field-control form-control min-w-[220px] flex-1 rounded-lg"
                                    />
                                    <button
                                        type="button"
                                        onClick={addOrigin}
                                        className="btn btn-outline-primary rounded-lg px-4 py-2.5 text-sm"
                                    >
                                        Añadir
                                    </button>
                                </div>

                                <p className="text-secondary-light mt-2 mb-0 text-sm">
                                    Si lo dejas vacío, la clave vale desde cualquier sitio. Indica los
                                    dominios cuando la web llame a la API desde el navegador.
                                </p>

                                {data.allowed_origins.length > 0 && (
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {data.allowed_origins.map((item) => (
                                            <span
                                                key={item}
                                                className="flex items-center gap-2 rounded-full bg-neutral-100 py-1 pe-2 ps-3 text-sm dark:bg-neutral-700"
                                            >
                                                <code className="font-mono text-xs">{item}</code>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setData(
                                                            'allowed_origins',
                                                            data.allowed_origins.filter((o) => o !== item),
                                                        )
                                                    }
                                                    className="text-neutral-500 hover:text-danger-600"
                                                    aria-label={`Quitar ${item}`}
                                                >
                                                    <i className="ri-close-line" aria-hidden="true" />
                                                </button>
                                            </span>
                                        ))}
                                    </div>
                                )}

                                {Object.keys(errors)
                                    .filter((key) => key.startsWith('allowed_origins'))
                                    .map((key) => (
                                        <p key={key} className="mt-2 mb-0 text-sm text-danger-600">
                                            {errors[key as keyof typeof errors]}
                                        </p>
                                    ))}
                            </div>

                            <div className="grid gap-5 sm:grid-cols-2">
                                <TextField
                                    label="Límite propio (peticiones por minuto)"
                                    name="rate_limit"
                                    type="number"
                                    value={data.rate_limit}
                                    onChange={(event) => setData('rate_limit', event.target.value)}
                                    error={errors.rate_limit}
                                    placeholder="Por defecto, el general"
                                />

                                <TextField
                                    label="Caduca el (opcional)"
                                    name="expires_at"
                                    type="date"
                                    value={data.expires_at}
                                    onChange={(event) => setData('expires_at', event.target.value)}
                                    error={errors.expires_at}
                                />
                            </div>
                        </div>

                        <div className="mt-5 flex flex-wrap gap-3">
                            <button
                                type="button"
                                onClick={submit}
                                disabled={saving}
                                className="btn btn-primary rounded-lg px-5 py-2.5 text-sm disabled:opacity-70"
                            >
                                {saving ? 'Creando…' : 'Crear clave'}
                            </button>
                            <button
                                type="button"
                                onClick={() => {
                                    reset()
                                    setCreating(false)
                                }}
                                className="px-2 py-2.5 text-sm font-medium text-neutral-600 hover:underline dark:text-neutral-300"
                            >
                                Cancelar
                            </button>
                        </div>
                    </div>
                )}

                {keys.length === 0 ? (
                    <EmptyState
                        title="Todavía no hay ninguna clave"
                        description="Crea una para cada web que vaya a mostrar los artículos. Así puedes cortarle el acceso a una sin afectar a las demás."
                    />
                ) : (
                    <ul className="divide-y divide-neutral-200 dark:divide-neutral-600">
                        {keys.map((key) => (
                            <li key={key.id} className="flex flex-wrap items-start justify-between gap-4 py-4 first:pt-0">
                                <div className="min-w-0 flex-1">
                                    <div className="mb-1 flex flex-wrap items-center gap-2">
                                        <span className="font-medium text-neutral-900 dark:text-white">
                                            {key.name}
                                        </span>
                                        {key.revoked_at ? (
                                            <StatusBadge label="Revocada" tone="danger" />
                                        ) : key.expires_at && new Date(key.expires_at) < new Date() ? (
                                            <StatusBadge label="Caducada" tone="warning" />
                                        ) : (
                                            <StatusBadge label="Activa" tone="success" />
                                        )}
                                    </div>

                                    <code className="text-secondary-light block font-mono text-xs">
                                        {key.prefix}…
                                    </code>

                                    <div className="text-secondary-light mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                                        <span>
                                            {key.requests_count === 0
                                                ? 'Sin usar todavía'
                                                : `${key.requests_count} peticiones`}
                                        </span>
                                        {key.last_used_at && (
                                            <span>
                                                Último uso: {DATE_TIME.format(new Date(key.last_used_at))}
                                                {key.last_used_ip && ` desde ${key.last_used_ip}`}
                                            </span>
                                        )}
                                        {key.rate_limit && <span>Límite propio: {key.rate_limit}/min</span>}
                                        {key.expires_at && (
                                            <span>
                                                Caduca el{' '}
                                                {new Intl.DateTimeFormat('es-ES', {
                                                    dateStyle: 'short',
                                                }).format(new Date(key.expires_at))}
                                            </span>
                                        )}
                                    </div>

                                    {key.allowed_origins.length > 0 ? (
                                        <div className="mt-2 flex flex-wrap gap-1.5">
                                            {key.allowed_origins.map((item) => (
                                                <code
                                                    key={item}
                                                    className="rounded bg-neutral-100 px-2 py-0.5 font-mono text-xs dark:bg-neutral-700"
                                                >
                                                    {item}
                                                </code>
                                            ))}
                                        </div>
                                    ) : (
                                        <span className="text-secondary-light mt-2 block text-xs">
                                            Sin restricción de dominio
                                        </span>
                                    )}
                                </div>

                                <div className="flex shrink-0 gap-2">
                                    {!key.revoked_at && (
                                        <button
                                            type="button"
                                            onClick={() => setPendingRevoke(key)}
                                            className="rounded-lg border border-danger-300 px-3 py-1.5 text-xs font-medium text-danger-600 hover:bg-danger-100/50 dark:border-danger-600/50 dark:hover:bg-danger-600/10"
                                        >
                                            Revocar
                                        </button>
                                    )}
                                    {key.revoked_at && (
                                        <button
                                            type="button"
                                            onClick={() => setPendingDelete(key)}
                                            className="rounded-lg border border-neutral-300 px-3 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-100 dark:border-neutral-600 dark:text-neutral-300 dark:hover:bg-neutral-700"
                                        >
                                            Borrar
                                        </button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>

            {globalOrigins.length > 0 && (
                <Card header={<h6 className="mb-0 text-base font-semibold">Dominios permitidos en todo el servidor</h6>}>
                    <p className="text-secondary-light mb-3 text-sm">
                        Configurados en <code className="font-mono text-xs">API_ALLOWED_ORIGINS</code> del
                        archivo <code className="font-mono text-xs">.env</code>. Valen para cualquier clave.
                    </p>
                    <div className="flex flex-wrap gap-2">
                        {globalOrigins.map((item) => (
                            <code
                                key={item}
                                className="rounded bg-neutral-100 px-2 py-1 font-mono text-xs dark:bg-neutral-700"
                            >
                                {item}
                            </code>
                        ))}
                    </div>
                </Card>
            )}

            <ConfirmDialog
                open={pendingRevoke !== null}
                title="Revocar la clave"
                message={
                    pendingRevoke
                        ? `La web que use «${pendingRevoke.name}» dejará de recibir artículos de inmediato. La clave no se puede reactivar: habría que crear otra.`
                        : ''
                }
                confirmLabel="Revocar"
                processing={processing}
                onConfirm={() => {
                    if (!pendingRevoke) return

                    router.post(route('api-keys.revoke', pendingRevoke.id), {}, {
                        preserveScroll: true,
                        onStart: () => setProcessing(true),
                        onFinish: () => {
                            setProcessing(false)
                            setPendingRevoke(null)
                        },
                    })
                }}
                onCancel={() => setPendingRevoke(null)}
            />

            <ConfirmDialog
                open={pendingDelete !== null}
                title="Borrar la clave del registro"
                message={
                    pendingDelete
                        ? `Se perderá el historial de uso de «${pendingDelete.name}». La clave ya está revocada, así que ninguna web se verá afectada.`
                        : ''
                }
                confirmLabel="Borrar"
                processing={processing}
                onConfirm={() => {
                    if (!pendingDelete) return

                    router.delete(route('api-keys.destroy', pendingDelete.id), {
                        preserveScroll: true,
                        onStart: () => setProcessing(true),
                        onFinish: () => {
                            setProcessing(false)
                            setPendingDelete(null)
                        },
                    })
                }}
                onCancel={() => setPendingDelete(null)}
            />
        </div>
    )
}

/* ------------------------------------------------------------------------ */
/* Guía                                                                       */
/* ------------------------------------------------------------------------ */

function Guide({ connection }: { connection: ApiIndexProps['connection'] }) {
    const example = `// Listado de artículos para la portada
const respuesta = await fetch('${connection.base_url}/articulos?por_pagina=6', {
  headers: {
    '${connection.header}': process.env.BLOG_API_KEY,
    'Accept': 'application/json',
  },
})

const { datos, paginacion } = await respuesta.json()

datos.forEach((articulo) => {
  console.log(articulo.titulo, articulo.slug, articulo.publicado_en)
})`

    const detail = `// Un artículo concreto
const respuesta = await fetch(\`${connection.base_url}/articulos/\${slug}\`, {
  headers: { '${connection.header}': process.env.BLOG_API_KEY },
})

if (respuesta.status === 404) {
  // No existe o no está publicado: muestra tu página de "no encontrado"
}

const { datos } = await respuesta.json()
// datos.contenido_html ya viene limpio y es seguro insertarlo`

    return (
        <div className="grid gap-6">
            {/* Lo primero: el documento que se le pasa a quien haga la web */}
            <div className="rounded-xl border border-primary-200 bg-primary-50 p-5 dark:border-primary-600/40 dark:bg-primary-600/10">
                <h6 className="mb-1 flex items-center gap-2 text-base font-semibold text-primary-800 dark:text-primary-300">
                    <i className="ri-file-text-line" aria-hidden="true" />
                    Documentación para quien haga la web
                </h6>
                <p className="mb-4 text-sm text-primary-800 dark:text-primary-300">
                    Un documento completo con la dirección de tu blog, todos los endpoints, ejemplos de
                    respuesta reales, los errores y el código listo para copiar. Se genera con los datos de
                    esta instalación, así que no hay nada que sustituir.{' '}
                    <strong>La clave se entrega aparte</strong>, nunca dentro del documento.
                </p>

                <div className="flex flex-wrap gap-3">
                    <a
                        href={route('api.connection.docs')}
                        className="btn btn-primary flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm"
                    >
                        <i className="ri-download-2-line" aria-hidden="true" />
                        Descargar documentación (.md)
                    </a>

                    <a
                        href={route('api.connection.docs', { formato: 'openapi' })}
                        className="btn btn-outline-primary flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm"
                    >
                        <i className="ri-code-box-line" aria-hidden="true" />
                        OpenAPI (.json)
                    </a>
                </div>

                <p className="text-secondary-light mt-3 mb-0 text-xs">
                    El archivo OpenAPI se importa en Postman o Insomnia y deja todas las llamadas
                    preparadas para probar.
                </p>
            </div>

            <Card header={<h6 className="mb-0 text-base font-semibold">Pasos para conectar la web</h6>}>
                <ol className="grid gap-4">
                    {[
                        {
                            title: 'Descarga la documentación y envíasela',
                            body: 'Lleva todo lo que necesita saber: endpoints, ejemplos, errores y código. Por correo o por donde prefieras, pero sin la clave dentro.',
                        },
                        {
                            title: 'Crea una clave',
                            body: 'En la pestaña «Claves y dominios». Ponle el nombre de la web y añade su dominio si va a llamar a la API desde el navegador. Envíasela por un canal distinto al del documento.',
                        },
                        {
                            title: 'Guárdala en la web, nunca en el código',
                            body: 'En una variable de entorno del servidor. Si la clave acaba en el JavaScript que descarga el navegador, cualquiera puede leerla.',
                        },
                        {
                            title: 'Llama a la API desde el servidor',
                            body: 'Mejor desde el servidor de la web (getStaticProps, un controlador, un cron…) que desde el navegador: así la clave no sale de tu máquina y de paso te ahorras el CORS.',
                        },
                        {
                            title: 'Comprueba que responde',
                            body: 'El endpoint de estado no pide clave, así que sirve para vigilar la API desde fuera sin exponer nada.',
                        },
                    ].map((step, index) => (
                        <li key={step.title} className="flex gap-4">
                            <span
                                className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-600 text-sm font-semibold text-white"
                                aria-hidden="true"
                            >
                                {index + 1}
                            </span>
                            <span>
                                <span className="block font-medium text-neutral-900 dark:text-white">
                                    {step.title}
                                </span>
                                <span className="text-secondary-light text-sm">{step.body}</span>
                            </span>
                        </li>
                    ))}
                </ol>
            </Card>

            <Card
                header={
                    <div className="flex items-center justify-between gap-3">
                        <h6 className="mb-0 text-base font-semibold">Ejemplo: listado de artículos</h6>
                        <CopyButton value={example} label="Copiar" />
                    </div>
                }
            >
                <pre className="overflow-x-auto rounded-lg bg-neutral-900 p-4 text-xs text-neutral-300 dark:bg-black/40">
                    <code>{example}</code>
                </pre>
            </Card>

            <Card
                header={
                    <div className="flex items-center justify-between gap-3">
                        <h6 className="mb-0 text-base font-semibold">Ejemplo: un artículo</h6>
                        <CopyButton value={detail} label="Copiar" />
                    </div>
                }
            >
                <pre className="overflow-x-auto rounded-lg bg-neutral-900 p-4 text-xs text-neutral-300 dark:bg-black/40">
                    <code>{detail}</code>
                </pre>
            </Card>

            <Card header={<h6 className="mb-0 text-base font-semibold">Cosas que conviene saber</h6>}>
                <ul className="text-secondary-light grid gap-3 text-sm">
                    <li className="flex items-start gap-2">
                        <i className="ri-eye-off-line mt-px text-primary-600" aria-hidden="true" />
                        Solo salen los artículos publicados. Un borrador o algo programado no se filtra por
                        la API ni conociendo su dirección.
                    </li>
                    <li className="flex items-start gap-2">
                        <i className="ri-links-line mt-px text-primary-600" aria-hidden="true" />
                        Los artículos «solo con enlace» no aparecen en el listado, pero sí responden si pides
                        su dirección concreta. Es justo para lo que existen.
                    </li>
                    <li className="flex items-start gap-2">
                        <i className="ri-shield-check-line mt-px text-primary-600" aria-hidden="true" />
                        El HTML del contenido se guarda ya limpio, así que se puede insertar sin volver a
                        sanearlo.
                    </li>
                    <li className="flex items-start gap-2">
                        <i className="ri-speed-line mt-px text-primary-600" aria-hidden="true" />
                        El límite es de {connection.rate_limit} peticiones por minuto y clave. Al pasarse,
                        la API responde 429 y hay que esperar.
                    </li>
                </ul>
            </Card>
        </div>
    )
}
