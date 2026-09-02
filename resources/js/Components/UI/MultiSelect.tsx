import { useId, useMemo, useState } from 'react'
import type { SelectOption } from '@/types'
import FieldError from './FieldError'

interface MultiSelectProps {
    label: string
    options: SelectOption[]
    /** Identificadores seleccionados. */
    value: number[]
    onChange: (value: number[]) => void
    error?: string | undefined
    /** A partir de cuántas opciones aparece el buscador. */
    searchThreshold?: number
    emptyMessage?: string
}

/**
 * Selección múltiple con casillas y buscador.
 *
 * Se prefiere a un `<select multiple>` porque este último es difícil de usar
 * con el ratón y prácticamente inaccesible en móvil.
 */
export default function MultiSelect({
    label,
    options,
    value,
    onChange,
    error,
    searchThreshold = 8,
    emptyMessage = 'No hay opciones disponibles.',
}: MultiSelectProps) {
    const id = useId()
    const errorId = `${id}-error`
    const [search, setSearch] = useState('')

    const filtered = useMemo(() => {
        const term = search.trim().toLowerCase()

        if (term === '') {
            return options
        }

        return options.filter((option) => option.label.toLowerCase().includes(term))
    }, [options, search])

    function toggle(optionValue: number) {
        onChange(
            value.includes(optionValue)
                ? value.filter((current) => current !== optionValue)
                : [...value, optionValue],
        )
    }

    return (
        <fieldset aria-describedby={error ? errorId : undefined}>
            <legend className="form-label">
                {label}
                {value.length > 0 && (
                    <span className="text-secondary-light ms-2 font-normal">({value.length})</span>
                )}
            </legend>

            {options.length === 0 ? (
                <p className="text-secondary-light mb-0 text-sm">{emptyMessage}</p>
            ) : (
                <div
                    className={`rounded-lg border ${
                        error ? 'border-danger-500' : 'border-neutral-300 dark:border-neutral-600'
                    }`}
                >
                    {options.length >= searchThreshold && (
                        <div className="border-b border-neutral-200 p-2 dark:border-neutral-600">
                            <input
                                type="search"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Filtrar…"
                                aria-label={`Filtrar ${label.toLowerCase()}`}
                                className="form-control field-control rounded-md py-1.5 text-sm"
                            />
                        </div>
                    )}

                    <div className="max-h-[220px] overflow-y-auto p-2">
                        {filtered.length === 0 ? (
                            <p className="text-secondary-light mb-0 px-2 py-3 text-sm">
                                Nada coincide con «{search}».
                            </p>
                        ) : (
                            filtered.map((option) => {
                                const optionValue = Number(option.value)
                                const checked = value.includes(optionValue)

                                return (
                                    <label
                                        key={option.value}
                                        className="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-neutral-100 dark:hover:bg-neutral-600"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={checked}
                                            onChange={() => toggle(optionValue)}
                                            className="form-check-input border border-neutral-300"
                                        />
                                        {option.label}
                                    </label>
                                )
                            })
                        )}
                    </div>
                </div>
            )}

            <FieldError id={errorId} message={error} />
        </fieldset>
    )
}
