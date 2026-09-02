import '@fontsource-variable/inter'

import axios from 'axios'
import { createInertiaApp } from '@inertiajs/react'
import { createRoot } from 'react-dom/client'
import type { ComponentType } from 'react'

// Peticiones sueltas (guardado automático): Laravel debe reconocerlas como
// asíncronas y recibir el token CSRF de la cookie.
axios.defaults.withCredentials = true
axios.defaults.withXSRFToken = true
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'

const appName = import.meta.env.VITE_APP_NAME ?? 'Panel de Blogs'

type PageModule = { default: ComponentType<Record<string, never>> }

// Dos globs, uno por origen. Las páginas de un módulo se piden con el prefijo
// del módulo y dos puntos dobles —`Blog::Articles/Index`—, que es la convención
// de vistas con espacio de nombres de Laravel: deja explícita la frontera y
// evita que una carpeta del núcleo llamada igual que un módulo la pise.
const corePages = import.meta.glob<PageModule>('./Pages/**/*.tsx')
const modulePages = import.meta.glob<PageModule>('../../modules/*/resources/js/Pages/**/*.tsx')

function findPage(name: string): (() => Promise<PageModule>) | undefined {
    const separator = name.indexOf('::')

    if (separator === -1) {
        return corePages[`./Pages/${name}.tsx`]
    }

    const module = name.slice(0, separator)
    const page = name.slice(separator + 2)

    return modulePages[`../../modules/${module}/resources/js/Pages/${page}.tsx`]
}

void createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),

    resolve: async (name) => {
        const page = findPage(name)

        if (!page) {
            const where = name.includes('::')
                ? `modules/${name.slice(0, name.indexOf('::'))}/resources/js/Pages`
                : 'resources/js/Pages'

            throw new Error(`No se ha encontrado la página «${name}» en ${where}.`)
        }

        return (await page()).default
    },

    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />)
    },

    progress: {
        color: '#487FFF', // primary-600 de la plantilla
        showSpinner: false,
    },
})
