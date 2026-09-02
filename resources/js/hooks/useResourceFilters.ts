import { router } from '@inertiajs/react'
import { useEffect, useRef, useState } from 'react'
import { useDebouncedValue } from './useDebouncedValue'

export interface ResourceFilters {
    search: string
    status: string
    trashed: boolean
    sort: string
    direction: 'asc' | 'desc'
    /** Filtro adicional propio del listado (por ejemplo, el rol). */
    extra?: string
    /**
     * Filtros propios del listado con nombre propio, para cuando `extra` no
     * llega. Viajan en la URL con su nombre tal cual.
     */
    custom?: Record<string, string>
}

/**
 * Gestiona los filtros de un listado: texto con retardo, selects, ordenación y
 * recarga parcial conservando el estado y la posición de la página.
 *
 * Los filtros viajan siempre en la URL, así que un listado filtrado se puede
 * compartir o recargar sin perder el contexto.
 */
export function useResourceFilters(routeName: string, initial: ResourceFilters) {
    const [filters, setFilters] = useState<ResourceFilters>(initial)
    const [loading, setLoading] = useState(false)
    const debouncedSearch = useDebouncedValue(filters.search, 350)
    const isFirstRender = useRef(true)

    useEffect(() => {
        // No se lanza una petición nada más montar la página.
        if (isFirstRender.current) {
            isFirstRender.current = false

            return
        }

        const query: Record<string, string> = {
            sort: filters.sort,
            direction: filters.direction,
        }

        if (debouncedSearch.trim() !== '') {
            query.search = debouncedSearch.trim()
        }

        if (filters.status !== '') {
            query.status = filters.status
        }

        if (filters.trashed) {
            query.trashed = '1'
        }

        if (filters.extra !== undefined && filters.extra !== '') {
            query.extra = filters.extra
        }

        for (const [name, value] of Object.entries(filters.custom ?? {})) {
            if (value !== '') {
                query[name] = value
            }
        }

        router.get(route(routeName), query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        })
        // `filters.search` queda fuera a propósito: se reacciona al valor con retardo.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        debouncedSearch,
        filters.status,
        filters.trashed,
        filters.sort,
        filters.direction,
        filters.extra,
        // Serializado: comparar el objeto por identidad dispararía la petición
        // en cada renderizado.
        JSON.stringify(filters.custom ?? {}),
    ])

    function update(changes: Partial<ResourceFilters>) {
        setFilters((current) => ({ ...current, ...changes }))
    }

    /** Alterna la ordenación: mismo campo invierte la dirección. */
    function toggleSort(field: string) {
        setFilters((current) => ({
            ...current,
            sort: field,
            direction: current.sort === field && current.direction === 'asc' ? 'desc' : 'asc',
        }))
    }

    /** Cambia un filtro propio sin tocar los demás. */
    function updateCustom(name: string, value: string) {
        setFilters((current) => ({ ...current, custom: { ...current.custom, [name]: value } }))
    }

    function reset() {
        setFilters({
            ...initial,
            search: '',
            status: '',
            trashed: false,
            extra: '',
            custom: Object.fromEntries(Object.keys(initial.custom ?? {}).map((name) => [name, ''])),
        })
    }

    const hasActiveFilters =
        filters.search.trim() !== '' ||
        filters.status !== '' ||
        filters.trashed ||
        (filters.extra !== undefined && filters.extra !== '') ||
        Object.values(filters.custom ?? {}).some((value) => value !== '')

    return { filters, loading, update, updateCustom, toggleSort, reset, hasActiveFilters }
}
