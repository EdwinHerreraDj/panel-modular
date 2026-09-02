import { useEffect, type RefObject } from 'react'

/**
 * Cierra un elemento flotante al pulsar fuera de él o al pulsar Escape.
 */
export function useClickOutside<T extends HTMLElement>(
    ref: RefObject<T | null>,
    active: boolean,
    onClose: () => void,
): void {
    useEffect(() => {
        if (!active) {
            return
        }

        function handlePointerDown(event: MouseEvent | TouchEvent) {
            if (ref.current && !ref.current.contains(event.target as Node)) {
                onClose()
            }
        }

        function handleKeyDown(event: KeyboardEvent) {
            if (event.key === 'Escape') {
                onClose()
            }
        }

        document.addEventListener('mousedown', handlePointerDown)
        document.addEventListener('touchstart', handlePointerDown)
        document.addEventListener('keydown', handleKeyDown)

        return () => {
            document.removeEventListener('mousedown', handlePointerDown)
            document.removeEventListener('touchstart', handlePointerDown)
            document.removeEventListener('keydown', handleKeyDown)
        }
    }, [ref, active, onClose])
}
