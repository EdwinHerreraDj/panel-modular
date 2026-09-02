import { useTheme } from '@/hooks/useTheme'

/**
 * Conmutador de tema claro/oscuro (equivalente a `#theme-toggle` de la plantilla).
 */
export default function ThemeToggle() {
    const { theme, toggle } = useTheme()
    const isDark = theme === 'dark'

    return (
        <button
            type="button"
            onClick={toggle}
            className="flex h-10 w-10 items-center justify-center rounded-full bg-neutral-200 dark:bg-neutral-700 dark:text-white"
            aria-label={isDark ? 'Activar modo claro' : 'Activar modo oscuro'}
            title={isDark ? 'Modo claro' : 'Modo oscuro'}
        >
            <i className={isDark ? 'ri-sun-line' : 'ri-moon-line'} aria-hidden="true" />
        </button>
    )
}
