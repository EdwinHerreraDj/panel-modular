import type { ReactNode } from 'react'

export interface Column<T> {
    key: string
    header: string
    /** Contenido de la celda. */
    cell: (row: T) => ReactNode
    /** Alineación; la plantilla centra todas las columnas menos la primera. */
    align?: 'start' | 'center' | 'end'
    /** Si se indica, la cabecera permite ordenar por este campo. */
    sortable?: boolean
}

interface DataTableProps<T> {
    columns: Column<T>[]
    rows: T[]
    rowKey: (row: T) => string | number
    /** Campo y dirección de ordenación actuales. */
    sort?: { field: string; direction: 'asc' | 'desc' }
    onSort?: (field: string) => void
    /** Contenido mostrado cuando no hay filas. */
    empty?: ReactNode
    /** Atenúa la tabla mientras se recarga. */
    loading?: boolean
    /** Identificadores seleccionados; activa la columna de casillas. */
    selected?: number[]
    onSelectedChange?: (selected: number[]) => void
    /** Filas que no se pueden seleccionar (por ejemplo, sin permiso). */
    isSelectable?: (row: T) => boolean
}

/**
 * Tabla del área privada con las clases `.table` de la plantilla.
 */
export default function DataTable<T>({
    columns,
    rows,
    rowKey,
    sort,
    onSort,
    empty,
    loading = false,
    selected,
    onSelectedChange,
    isSelectable = () => true,
}: DataTableProps<T>) {
    if (rows.length === 0 && empty) {
        return <>{empty}</>
    }

    const selectable = selected !== undefined && onSelectedChange !== undefined
    const selectableRows = selectable ? rows.filter(isSelectable) : []
    const selectableIds = selectableRows.map((row) => Number(rowKey(row)))
    const allSelected = selectableIds.length > 0 && selectableIds.every((id) => selected?.includes(id))

    function toggleAll() {
        if (!onSelectedChange) {
            return
        }

        onSelectedChange(allSelected ? [] : selectableIds)
    }

    function toggleRow(id: number) {
        if (!onSelectedChange || !selected) {
            return
        }

        onSelectedChange(
            selected.includes(id) ? selected.filter((current) => current !== id) : [...selected, id],
        )
    }

    return (
        <div className="table-responsive" aria-busy={loading}>
            <table className={`table style-three transition-opacity ${loading ? 'opacity-60' : ''}`}>
                <thead>
                    <tr>
                        {selectable && (
                            <th scope="col" className="w-12 text-start">
                                <input
                                    type="checkbox"
                                    checked={allSelected}
                                    onChange={toggleAll}
                                    disabled={selectableIds.length === 0}
                                    aria-label="Seleccionar todo"
                                    className="form-check-input border border-neutral-300"
                                />
                            </th>
                        )}

                        {columns.map((column) => {
                            const isSorted = sort?.field === column.key
                            const alignment =
                                column.align === 'end'
                                    ? 'text-end'
                                    : column.align === 'start'
                                      ? 'text-start'
                                      : ''

                            if (!column.sortable || !onSort) {
                                return (
                                    <th key={column.key} scope="col" className={alignment}>
                                        {column.header}
                                    </th>
                                )
                            }

                            return (
                                <th
                                    key={column.key}
                                    scope="col"
                                    className={alignment}
                                    aria-sort={
                                        isSorted
                                            ? sort.direction === 'asc'
                                                ? 'ascending'
                                                : 'descending'
                                            : 'none'
                                    }
                                >
                                    <button
                                        type="button"
                                        onClick={() => onSort(column.key)}
                                        className="inline-flex items-center gap-1 font-medium hover:text-primary-600"
                                    >
                                        {column.header}
                                        <i
                                            className={
                                                isSorted
                                                    ? sort.direction === 'asc'
                                                        ? 'ri-arrow-up-s-line'
                                                        : 'ri-arrow-down-s-line'
                                                    : 'ri-expand-up-down-line opacity-40'
                                            }
                                            aria-hidden="true"
                                        />
                                    </button>
                                </th>
                            )
                        })}
                    </tr>
                </thead>

                <tbody>
                    {rows.map((row) => (
                        <tr key={rowKey(row)}>
                            {selectable && (
                                <td className="text-start">
                                    <input
                                        type="checkbox"
                                        checked={selected?.includes(Number(rowKey(row))) ?? false}
                                        onChange={() => toggleRow(Number(rowKey(row)))}
                                        disabled={!isSelectable(row)}
                                        aria-label="Seleccionar fila"
                                        className="form-check-input border border-neutral-300"
                                    />
                                </td>
                            )}

                            {columns.map((column) => (
                                <td
                                    key={column.key}
                                    className={
                                        column.align === 'end'
                                            ? 'text-end'
                                            : column.align === 'start'
                                              ? 'text-start'
                                              : ''
                                    }
                                >
                                    {column.cell(row)}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    )
}
