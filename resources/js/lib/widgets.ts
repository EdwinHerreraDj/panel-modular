import type { ComponentType } from 'react'

/**
 * Bloques que los módulos ponen en el panel de inicio.
 *
 * Mismo mecanismo que las páginas: el servidor manda un nombre con el prefijo
 * del módulo (`Blog::RecentArticles`) y aquí se resuelve contra la carpeta
 * `Widgets` de cada módulo. Es lo que permite que un módulo pinte un bloque
 * propio sin que el panel sepa qué contiene.
 */
type WidgetModule = { default: ComponentType<Record<string, unknown>> }

const widgets = import.meta.glob<WidgetModule>('../../../modules/*/resources/js/Widgets/**/*.tsx')

export function resolveWidget(name: string): (() => Promise<WidgetModule>) | undefined {
    const separator = name.indexOf('::')

    if (separator === -1) return undefined

    const module = name.slice(0, separator)
    const widget = name.slice(separator + 2)

    return widgets[`../../../modules/${module}/resources/js/Widgets/${widget}.tsx`]
}
