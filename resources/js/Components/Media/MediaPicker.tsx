import axios from 'axios'
import { useCallback, useEffect, useRef, useState } from 'react'
import Modal from '@/Components/UI/Modal'
import SearchInput from '@/Components/UI/SearchInput'
import Alert from '@/Components/UI/Alert'
import EmptyState from '@/Components/UI/EmptyState'
import { useDebouncedValue } from '@/hooks/useDebouncedValue'
import type { MediaPickerItem } from '@/types'

interface MediaPickerProps {
    open: boolean
    onClose: () => void
    onSelect: (media: MediaPickerItem) => void
    /** Identificador ya seleccionado, para marcarlo. */
    selectedId?: number | null
}

/**
 * Ventana para elegir una imagen de la biblioteca o subir una nueva.
 *
 * Trabaja contra un endpoint JSON para no recargar la página en la que se está
 * editando y no perder lo escrito.
 */
export default function MediaPicker({ open, onClose, onSelect, selectedId = null }: MediaPickerProps) {
    const [items, setItems] = useState<MediaPickerItem[]>([])
    const [search, setSearch] = useState('')
    const [loading, setLoading] = useState(false)
    const [uploading, setUploading] = useState(false)
    const [error, setError] = useState<string | null>(null)
    const inputRef = useRef<HTMLInputElement>(null)

    const debouncedSearch = useDebouncedValue(search, 300)

    const load = useCallback(async (term: string) => {
        setLoading(true)
        setError(null)

        try {
            const response = await axios.get<{ data: MediaPickerItem[] }>(route('media.picker'), {
                params: { search: term },
            })

            setItems(response.data.data)
        } catch {
            setError('No se ha podido cargar la biblioteca.')
        } finally {
            setLoading(false)
        }
    }, [])

    useEffect(() => {
        if (open) {
            void load(debouncedSearch)
        }
    }, [open, debouncedSearch, load])

    async function upload(files: FileList | null) {
        if (!files || files.length === 0) {
            return
        }

        const payload = new FormData()

        for (const file of Array.from(files)) {
            payload.append('files[]', file)
        }

        setUploading(true)
        setError(null)

        try {
            const response = await axios.post<{ data: MediaPickerItem[] }>(
                route('media.picker.store'),
                payload,
            )

            // Lo recién subido va delante y se selecciona si es un único archivo.
            setItems((current) => [...response.data.data, ...current])

            const first = response.data.data[0]

            if (response.data.data.length === 1 && first) {
                onSelect(first)
                onClose()
            }
        } catch (exception) {
            setError(
                axios.isAxiosError(exception) && exception.response?.status === 422
                    ? 'Alguno de los archivos no cumple los requisitos: revisa formato, peso y dimensiones.'
                    : 'No se ha podido subir el archivo.',
            )
        } finally {
            setUploading(false)

            if (inputRef.current) {
                inputRef.current.value = ''
            }
        }
    }

    return (
        <Modal open={open} onClose={onClose} title="Elegir imagen">
            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center gap-3">
                    <div className="min-w-[200px] flex-1">
                        <SearchInput
                            value={search}
                            onChange={setSearch}
                            label="Buscar en la biblioteca"
                            placeholder="Buscar por nombre…"
                        />
                    </div>

                    <label className="btn btn-outline-primary cursor-pointer items-center gap-2 rounded-lg px-4 py-2.5 text-sm">
                        <i className="ri-upload-2-line" aria-hidden="true" />
                        {uploading ? 'Subiendo…' : 'Subir'}
                        <input
                            ref={inputRef}
                            type="file"
                            multiple
                            accept="image/png,image/jpeg,image/webp"
                            onChange={(event) => void upload(event.target.files)}
                            disabled={uploading}
                            className="sr-only"
                        />
                    </label>
                </div>

                {error && <Alert variant="danger">{error}</Alert>}

                {loading ? (
                    <p className="text-secondary-light py-8 text-center text-sm">Cargando…</p>
                ) : items.length === 0 ? (
                    <EmptyState
                        title="No hay imágenes"
                        description={
                            search.trim() === ''
                                ? 'Sube la primera imagen a la biblioteca.'
                                : 'Nada coincide con la búsqueda.'
                        }
                    />
                ) : (
                    <ul className="grid max-h-[380px] grid-cols-2 gap-3 overflow-y-auto sm:grid-cols-3">
                        {items.map((item) => {
                            const isSelected = item.id === selectedId

                            return (
                                <li key={item.id}>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            onSelect(item)
                                            onClose()
                                        }}
                                        className={`w-full overflow-hidden rounded-lg border-2 text-start transition ${
                                            isSelected
                                                ? 'border-primary-600'
                                                : 'border-transparent hover:border-primary-600'
                                        }`}
                                    >
                                        <img
                                            src={item.thumbnail_url}
                                            alt={item.alt_text ?? ''}
                                            className="aspect-square w-full bg-neutral-100 object-cover dark:bg-dark-1"
                                        />
                                        <span className="text-secondary-light block truncate px-2 py-1.5 text-xs">
                                            {item.name}
                                        </span>
                                    </button>
                                </li>
                            )
                        })}
                    </ul>
                )}
            </div>
        </Modal>
    )
}
