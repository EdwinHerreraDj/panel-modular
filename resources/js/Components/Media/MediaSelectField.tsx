import { useState } from 'react'
import FieldError from '@/Components/UI/FieldError'
import MediaPicker from './MediaPicker'
import type { MediaPickerItem } from '@/types'

interface MediaSelectFieldProps {
    label: string
    /** Imagen actualmente seleccionada. */
    value: { id: number; name: string; url: string } | null
    onChange: (media: MediaPickerItem | null) => void
    error?: string | undefined
    hint?: string
}

/**
 * Campo para elegir una imagen de la biblioteca.
 *
 * Sustituye a la subida directa: así la misma imagen se puede reutilizar en
 * varios sitios y la biblioteca sabe dónde se está usando.
 */
export default function MediaSelectField({ label, value, onChange, error, hint }: MediaSelectFieldProps) {
    const [pickerOpen, setPickerOpen] = useState(false)

    return (
        <div>
            <span className="form-label">{label}</span>

            <div className="flex flex-wrap items-start gap-4">
                <div className="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-neutral-300 bg-neutral-50 dark:border-neutral-600 dark:bg-dark-1">
                    {value ? (
                        <img src={value.url} alt="" className="h-full w-full object-cover" />
                    ) : (
                        <i className="ri-image-line text-2xl text-neutral-400" aria-hidden="true" />
                    )}
                </div>

                <div className="min-w-[200px] flex-1">
                    {value && (
                        <p className="mb-2 truncate font-medium text-neutral-900 dark:text-white">
                            {value.name}
                        </p>
                    )}

                    <div className="flex flex-wrap gap-3">
                        <button
                            type="button"
                            onClick={() => setPickerOpen(true)}
                            className="btn btn-outline-primary items-center gap-2 rounded-lg px-4 py-2 text-sm"
                        >
                            <i className="ri-image-add-line" aria-hidden="true" />
                            {value ? 'Cambiar imagen' : 'Elegir de la biblioteca'}
                        </button>

                        {value && (
                            <button
                                type="button"
                                onClick={() => onChange(null)}
                                className="text-sm font-medium text-danger-600 hover:underline"
                            >
                                Quitar
                            </button>
                        )}
                    </div>

                    {hint && <p className="text-secondary-light mt-2 mb-0 text-sm">{hint}</p>}
                </div>
            </div>

            <FieldError id="media-select-error" message={error} />

            <MediaPicker
                open={pickerOpen}
                onClose={() => setPickerOpen(false)}
                onSelect={onChange}
                selectedId={value?.id ?? null}
            />
        </div>
    )
}
