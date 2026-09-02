import { useForm } from '@inertiajs/react'
import type { FormEvent } from 'react'
import DashboardLayout from '@/Layouts/DashboardLayout'
import Alert from '@/Components/UI/Alert'
import Card from '@/Components/UI/Card'
import SubmitButton from '@/Components/UI/SubmitButton'

interface ModuleRow {
    key: string
    name: string
    active: boolean
    /** Cuántos permisos, pantallas, endpoints y tipos de contenido aporta. */
    contributes: Record<string, number>
}

interface Props {
    modules: ModuleRow[]
    envIsWritable: boolean
    envPath: string
    isLocal: boolean
}

const LABELS: Record<string, string> = {
    permisos: 'permisos',
    pantallas: 'pantallas',
    endpoints: 'endpoints de API',
    tipos: 'tipos de contenido',
}

/**
 * Qué módulos tiene esta instalación y cuáles están encendidos.
 *
 * Es la pantalla de quien administra, no la del cliente: activar un módulo
 * aplica sus tablas y siembra sus permisos, y desactivarlo hace desaparecer sus
 * pantallas del panel de todo el mundo.
 */
export default function ModulesIndex({ modules, envIsWritable, envPath, isLocal }: Props) {
    const { data, setData, put, processing } = useForm<{ enabled: string[] }>({
        enabled: modules.filter((module) => module.active).map((module) => module.key),
    })

    function toggle(key: string, on: boolean) {
        setData(
            'enabled',
            on ? [...data.enabled, key] : data.enabled.filter((current) => current !== key),
        )
    }

    function submit(event: FormEvent) {
        event.preventDefault()
        put(route('modules.update'), { preserveScroll: true })
    }

    const changed =
        JSON.stringify([...data.enabled].sort()) !==
        JSON.stringify(modules.filter((m) => m.active).map((m) => m.key).sort())

    return (
        <DashboardLayout title="Módulos" breadcrumb="Módulos">
            <div className="mb-6">
                <h5 className="mb-1 text-xl font-semibold">Módulos</h5>
                <p className="text-secondary-light mb-0 text-sm">
                    Cada módulo añade sus pantallas al panel y sus endpoints a la API. Activa solo los que
                    este cliente vaya a usar.
                </p>
            </div>

            {!envIsWritable && (
                <div className="mb-6">
                    <Alert variant="danger">
                        El archivo <code>{envPath}</code> es de solo lectura, así que desde aquí no se puede
                        cambiar nada. Edita a mano la línea <code>MODULES_ENABLED</code> y ejecuta{' '}
                        <code>php artisan config:clear</code>.
                    </Alert>
                </div>
            )}

            <form onSubmit={submit}>
                <Card>
                    <ul className="divide-y divide-neutral-200 dark:divide-neutral-600">
                        {modules.map((module) => {
                            const on = data.enabled.includes(module.key)

                            return (
                                <li
                                    key={module.key}
                                    className="flex flex-wrap items-center justify-between gap-4 py-4 first:pt-0 last:pb-0"
                                >
                                    <div className="min-w-0">
                                        <span className="block font-medium text-neutral-900 dark:text-white">
                                            {module.name}
                                        </span>
                                        <span className="text-secondary-light block text-sm">
                                            {Object.entries(module.contributes)
                                                .filter(([, count]) => count > 0)
                                                .map(([key, count]) => `${count} ${LABELS[key] ?? key}`)
                                                .join(' · ') || 'Sin aportaciones declaradas'}
                                        </span>
                                    </div>

                                    <label className="flex shrink-0 items-center gap-3">
                                        <span className="text-secondary-light text-sm">
                                            {on ? 'Activo' : 'Apagado'}
                                        </span>
                                        <input
                                            type="checkbox"
                                            className="form-check-input border border-neutral-300"
                                            checked={on}
                                            disabled={!envIsWritable}
                                            onChange={(e) => toggle(module.key, e.target.checked)}
                                            aria-label={`Activar el módulo ${module.name}`}
                                        />
                                    </label>
                                </li>
                            )
                        })}
                    </ul>

                    {changed && (
                        <div className="mt-6">
                            <Alert variant="info">
                                Al guardar se aplican las tablas que falten y se siembran los permisos de los
                                módulos activos. <strong>Apagar un módulo no borra sus datos</strong>: sus
                                pantallas desaparecen del panel y sus endpoints dejan de responder, pero todo
                                sigue ahí si vuelves a encenderlo.
                            </Alert>

                            {isLocal && (
                                <p className="text-secondary-light mt-2 text-sm">
                                    En desarrollo, <code>php artisan serve</code> reinicia el servidor al ver
                                    cambiar el <code>.env</code>, así que puede que el navegador dé un error de
                                    conexión al guardar. El cambio ya se ha aplicado: recarga la página.
                                </p>
                            )}
                        </div>
                    )}

                    <div className="mt-6">
                        <SubmitButton
                            processing={processing}
                            processingLabel="Aplicando…"
                            disabled={!envIsWritable || !changed}
                        >
                            Guardar cambios
                        </SubmitButton>
                    </div>
                </Card>
            </form>
        </DashboardLayout>
    )
}
