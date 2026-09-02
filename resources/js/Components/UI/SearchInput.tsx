import { useId } from 'react'
import IconSearch from '~icons/ion/search-outline'

interface SearchInputProps {
    value: string
    onChange: (value: string) => void
    label?: string
    placeholder?: string
}

/**
 * Buscador con el aspecto de `.navbar-search` de la plantilla.
 * El retardo lo aplica quien lo usa mediante `useDebouncedValue`.
 */
export default function SearchInput({
    value,
    onChange,
    label = 'Buscar',
    placeholder = 'Buscar…',
}: SearchInputProps) {
    const id = useId()

    return (
        <div className="relative">
            <label htmlFor={id} className="sr-only">
                {label}
            </label>

            <span
                className="pointer-events-none absolute start-3 top-1/2 flex -translate-y-1/2 text-lg text-neutral-500 dark:text-neutral-300"
                aria-hidden="true"
            >
                <IconSearch />
            </span>

            <input
                id={id}
                type="search"
                value={value}
                onChange={(event) => onChange(event.target.value)}
                placeholder={placeholder}
                className="form-control rounded-lg ps-10"
            />
        </div>
    )
}
