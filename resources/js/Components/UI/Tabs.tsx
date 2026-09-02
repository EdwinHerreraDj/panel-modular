import type { ReactNode } from 'react'

export interface TabDefinition {
    id: string
    label: string
    icon?: string
    /** Número de errores de validación en esta pestaña. */
    errorCount?: number
}

interface TabsProps {
    tabs: TabDefinition[]
    active: string
    onChange: (id: string) => void
    children: ReactNode
}

/**
 * Pestañas accesibles con el aspecto de la plantilla.
 * Los paneles se mantienen montados para no perder lo escrito al cambiar.
 */
export default function Tabs({ tabs, active, onChange, children }: TabsProps) {
    return (
        <div>
            <div
                role="tablist"
                aria-label="Secciones de la configuración"
                className="flex flex-wrap gap-1 border-b border-neutral-200 dark:border-neutral-600"
            >
                {tabs.map((tab) => {
                    const isActive = tab.id === active

                    return (
                        <button
                            key={tab.id}
                            type="button"
                            role="tab"
                            id={`tab-${tab.id}`}
                            aria-selected={isActive}
                            aria-controls={`panel-${tab.id}`}
                            onClick={() => onChange(tab.id)}
                            className={`-mb-px flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium transition ${
                                isActive
                                    ? 'border-primary-600 text-primary-600'
                                    : 'border-transparent text-neutral-600 hover:text-primary-600 dark:text-neutral-300'
                            }`}
                        >
                            {tab.icon && <i className={`${tab.icon} text-lg`} aria-hidden="true" />}
                            {tab.label}

                            {tab.errorCount !== undefined && tab.errorCount > 0 && (
                                <span
                                    className="flex h-5 min-w-5 items-center justify-center rounded-full bg-danger-600 px-1.5 text-xs font-semibold text-white"
                                    aria-label={`${tab.errorCount} campos con error`}
                                >
                                    {tab.errorCount}
                                </span>
                            )}
                        </button>
                    )
                })}
            </div>

            <div className="pt-6">{children}</div>
        </div>
    )
}

interface TabPanelProps {
    id: string
    active: string
    children: ReactNode
}

export function TabPanel({ id, active, children }: TabPanelProps) {
    const isActive = id === active

    return (
        <div
            role="tabpanel"
            id={`panel-${id}`}
            aria-labelledby={`tab-${id}`}
            hidden={!isActive}
            className={isActive ? 'flex flex-col gap-6' : ''}
        >
            {children}
        </div>
    )
}
