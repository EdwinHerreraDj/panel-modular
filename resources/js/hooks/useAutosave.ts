import axios from 'axios'
import { useCallback, useEffect, useRef, useState } from 'react'

export type AutosaveState = 'idle' | 'saving' | 'saved' | 'error'

interface AutosavePayload {
    title: string
    excerpt: string
    content: string
}

interface UseAutosaveOptions {
    /** URL del endpoint; si es null, el autoguardado está desactivado. */
    url: string | null
    data: AutosavePayload
    /** Segundos entre copias. */
    intervalSeconds: number
    /** Se desactiva mientras se está guardando el formulario de verdad. */
    paused?: boolean
}

/**
 * Guardado automático del artículo.
 *
 * Guarda una copia aparte en el historial: el artículo no cambia hasta que se
 * pulsa Guardar, así que una edición a medias de algo publicado nunca llega a
 * la web.
 *
 * Solo envía si el contenido ha cambiado desde la última copia.
 */
export function useAutosave({ url, data, intervalSeconds, paused = false }: UseAutosaveOptions) {
    const [state, setState] = useState<AutosaveState>('idle')
    const [savedAt, setSavedAt] = useState<string | null>(null)

    // Se guardan en refs para que el temporizador lea siempre lo último sin
    // reiniciarse con cada tecla.
    const dataRef = useRef(data)
    const lastSaved = useRef<string | null>(null)
    const pausedRef = useRef(paused)

    dataRef.current = data
    pausedRef.current = paused

    const save = useCallback(async () => {
        if (url === null || pausedRef.current) {
            return
        }

        const snapshot = JSON.stringify(dataRef.current)

        // Nada que guardar: ni ha cambiado nada ni hay contenido.
        if (snapshot === lastSaved.current) {
            return
        }

        if (dataRef.current.title.trim() === '' && dataRef.current.content.trim() === '') {
            return
        }

        setState('saving')

        try {
            const response = await axios.post<{ saved_at: string }>(url, dataRef.current)

            lastSaved.current = snapshot
            setSavedAt(response.data.saved_at)
            setState('saved')
        } catch {
            setState('error')
        }
    }, [url])

    useEffect(() => {
        if (url === null) {
            return
        }

        const timer = window.setInterval(() => void save(), intervalSeconds * 1000)

        return () => window.clearInterval(timer)
    }, [url, intervalSeconds, save])

    return { state, savedAt, saveNow: save }
}
