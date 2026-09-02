import { useCallback, useEffect, useState } from 'react'

export type ColorTheme = 'light' | 'dark'

const STORAGE_KEY = 'color-theme'

function resolveInitialTheme(): ColorTheme {
    if (typeof window === 'undefined') {
        return 'light'
    }

    const stored = window.localStorage.getItem(STORAGE_KEY)

    if (stored === 'dark' || stored === 'light') {
        return stored
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

/**
 * Modo claro/oscuro con la misma clave de almacenamiento que la plantilla
 * (`color-theme`) y la clase `dark` en <html>, para que el SCSS heredado siga
 * funcionando sin cambios.
 */
export function useTheme() {
    const [theme, setTheme] = useState<ColorTheme>(resolveInitialTheme)

    useEffect(() => {
        document.documentElement.classList.toggle('dark', theme === 'dark')
        window.localStorage.setItem(STORAGE_KEY, theme)
    }, [theme])

    const toggle = useCallback(() => {
        setTheme((current) => (current === 'dark' ? 'light' : 'dark'))
    }, [])

    return { theme, toggle }
}
