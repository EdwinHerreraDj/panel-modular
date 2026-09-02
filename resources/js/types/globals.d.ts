import type { route as routeFn } from 'ziggy-js'

declare global {
    /** Helper de Ziggy inyectado por la directiva Blade `@routes`. */
    const route: typeof routeFn
}

export {}
