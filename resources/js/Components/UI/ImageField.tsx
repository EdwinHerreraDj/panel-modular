import { useEffect, useId, useRef, useState } from 'react'
import FieldError from './FieldError'
import type { ImageConstraint } from '@/types'

interface ImageFieldProps {
    label: string
    /** URL de la imagen ya guardada, si la hay. */
    currentUrl: string | null
    /** Archivo pendiente de subir. */
    file: File | null
    onSelect: (file: File | null) => void
    /** Marca de eliminación de la imagen guardada. */
    removed: boolean
    onRemovedChange: (removed: boolean) => void
    constraints: ImageConstraint
    error?: string | undefined
    /** Fondo oscuro en la vista previa, para logotipos claros. */
    darkPreview?: boolean
}

/**
 * Campo de imagen con vista previa, sustitución y borrado.
 *
 * Los requisitos se muestran antes de elegir el archivo, para no rechazarlo
 * después de haberlo subido.
 */
export default function ImageField({
    label,
    currentUrl,
    file,
    onSelect,
    removed,
    onRemovedChange,
    constraints,
    error,
    darkPreview = false,
}: ImageFieldProps) {
    const id = useId()
    const errorId = `${id}-error`
    const inputRef = useRef<HTMLInputElement>(null)
    const [previewUrl, setPreviewUrl] = useState<string | null>(null)

    // La vista previa del archivo elegido se libera al cambiar o al desmontar.
    useEffect(() => {
        if (!file) {
            setPreviewUrl(null)

            return
        }

        const url = URL.createObjectURL(file)
        setPreviewUrl(url)

        return () => URL.revokeObjectURL(url)
    }, [file])

    const shownUrl = previewUrl ?? (removed ? null : currentUrl)
    const hasImage = shownUrl !== null

    function clearSelection() {
        onSelect(null)

        if (inputRef.current) {
            inputRef.current.value = ''
        }
    }

    return (
        <div>
            <span className="form-label">{label}</span>

            <div className="flex flex-wrap items-start gap-4">
                <div
                    className={`flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-neutral-300 dark:border-neutral-600 ${
                        darkPreview ? 'bg-dark-2' : 'bg-neutral-50 dark:bg-dark-1'
                    }`}
                >
                    {hasImage ? (
                        <img src={shownUrl} alt="" className="max-h-full max-w-full object-contain" />
                    ) : (
                        <i
                            className="ri-image-line text-2xl text-neutral-400"
                            aria-hidden="true"
                            title="Sin imagen"
                        />
                    )}
                </div>

                <div className="min-w-[200px] flex-1">
                    <input
                        ref={inputRef}
                        id={id}
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        aria-invalid={error ? true : undefined}
                        aria-describedby={error ? errorId : undefined}
                        onChange={(event) => {
                            const selected = event.target.files?.[0] ?? null
                            onSelect(selected)

                            if (selected) {
                                onRemovedChange(false)
                            }
                        }}
                        className="form-control field-control rounded-lg py-2 file:me-3 file:rounded-md file:border-0 file:bg-primary-600 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-white hover:file:bg-primary-700"
                    />

                    <p className="text-secondary-light mt-1.5 text-sm">{constraints.hint}</p>

                    <div className="mt-2 flex flex-wrap gap-3">
                        {file && (
                            <button
                                type="button"
                                onClick={clearSelection}
                                className="text-sm font-medium text-neutral-600 hover:underline dark:text-neutral-300"
                            >
                                Cancelar la sustitución
                            </button>
                        )}

                        {currentUrl && !file && !removed && (
                            <button
                                type="button"
                                onClick={() => onRemovedChange(true)}
                                className="text-sm font-medium text-danger-600 hover:underline"
                            >
                                Eliminar la imagen
                            </button>
                        )}

                        {removed && (
                            <button
                                type="button"
                                onClick={() => onRemovedChange(false)}
                                className="text-sm font-medium text-primary-600 hover:underline"
                            >
                                Conservar la imagen actual
                            </button>
                        )}
                    </div>
                </div>
            </div>

            <FieldError id={errorId} message={error} />
        </div>
    )
}
