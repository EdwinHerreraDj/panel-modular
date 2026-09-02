const DATE_TIME = new Intl.DateTimeFormat('es-ES', { dateStyle: 'long', timeStyle: 'short' })
const RELATIVE = new Intl.RelativeTimeFormat('es-ES', { numeric: 'auto' })

/**
 * «hace 3 horas», «ayer»… Más legible que una fecha completa para algo reciente.
 */
export function relativeTime(iso: string | null): string {
    if (!iso) return '—'

    const seconds = (new Date(iso).getTime() - Date.now()) / 1000
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['second', 60],
        ['minute', 60],
        ['hour', 24],
        ['day', 7],
        ['week', 4.35],
        ['month', 12],
    ]

    let value = seconds

    for (const [unit, step] of units) {
        if (Math.abs(value) < step) return RELATIVE.format(Math.round(value), unit)
        value /= step
    }

    return RELATIVE.format(Math.round(value), 'year')
}

/** Fecha y hora largas, para algo que se lee una vez. */
export function dateTime(iso: string): string {
    return DATE_TIME.format(new Date(iso))
}
