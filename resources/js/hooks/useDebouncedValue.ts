import { useEffect, useState } from 'react'

/**
 * Devuelve el valor tras `delay` milisegundos sin cambios.
 * Evita lanzar una petición por cada tecla en los buscadores.
 */
export function useDebouncedValue<T>(value: T, delay = 300): T {
    const [debounced, setDebounced] = useState(value)

    useEffect(() => {
        const timer = window.setTimeout(() => setDebounced(value), delay)

        return () => window.clearTimeout(timer)
    }, [value, delay])

    return debounced
}
