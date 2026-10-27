import { useState } from 'react'
import FieldError from '@/Components/UI/FieldError'
import MediaPicker from '@/Components/Media/MediaPicker'
import type { MediaPickerItem } from '@/types'
import type { GalleryImage } from '../types'

interface Props {
    images: GalleryImage[]
    onChange: (images: GalleryImage[]) => void
    /** Errores de validación por clave (`images.0.media_id`…). */
    errors: Record<string, string | undefined>
    hint: string
    /** Nombre del artículo, para proponer el texto alternativo. */
    productName: string
}

/**
 * Galería de un artículo: varias fotos de la biblioteca, en orden.
 *
 * La primera es la portada. Se reordena con flechas y no arrastrando: las
 * flechas funcionan igual con teclado, en móvil y con lector de pantalla.
 */
export default function GalleryField({ images, onChange, errors, hint, productName }: Props) {
    const [pickerOpen, setPickerOpen] = useState(false)

    function add(media: MediaPickerItem) {
        setPickerOpen(false)

        if (images.some((image) => image.media_id === media.id)) {
            return
        }

        onChange([
            ...images,
            {
                media_id: media.id,
                alt: media.alt_text ?? productName,
                url: media.thumbnail_url,
            },
        ])
    }

    function move(index: number, offset: -1 | 1) {
        const target = index + offset

        if (target < 0 || target >= images.length) {
            return
        }

        const next = [...images]
        const [moved] = next.splice(index, 1)

        if (moved) {
            next.splice(target, 0, moved)
            onChange(next)
        }
    }

    function updateAlt(index: number, alt: string) {
        onChange(images.map((image, current) => (current === index ? { ...image, alt } : image)))
    }

    return (
        <div>
            {images.length === 0 ? (
                <p className="text-secondary-light mb-4 text-sm">Todavía no tiene fotos.</p>
            ) : (
                <ul className="mb-4 flex flex-col gap-3">
                    {images.map((image, index) => (
                        <li
                            key={image.media_id}
                            className="flex flex-wrap items-center gap-3 rounded-xl border border-neutral-200 p-3 dark:border-neutral-600"
                        >
                            <div className="relative h-16 w-16 shrink-0 overflow-hidden rounded-lg bg-neutral-100 dark:bg-neutral-700">
                                {image.url && <img src={image.url} alt="" className="h-full w-full object-cover" />}
                                {index === 0 && (
                                    <span className="absolute inset-x-0 bottom-0 bg-primary-600/90 text-center text-[10px] font-semibold text-white">
                                        Portada
                                    </span>
                                )}
                            </div>

                            <div className="min-w-[200px] flex-1">
                                <label className="form-label mb-1 text-xs" htmlFor={`gallery-alt-${image.media_id}`}>
                                    Texto alternativo
                                </label>
                                <input
                                    id={`gallery-alt-${image.media_id}`}
                                    className="form-control h-10 rounded-lg"
                                    value={image.alt}
                                    maxLength={255}
                                    onChange={(e) => updateAlt(index, e.target.value)}
                                />
                                <FieldError
                                    id={`gallery-error-${image.media_id}`}
                                    message={errors[`images.${index}.media_id`] ?? errors[`images.${index}.alt`]}
                                />
                            </div>

                            <div className="flex gap-1">
                                <IconButton
                                    icon="ri-arrow-up-line"
                                    label="Subir"
                                    disabled={index === 0}
                                    onClick={() => move(index, -1)}
                                />
                                <IconButton
                                    icon="ri-arrow-down-line"
                                    label="Bajar"
                                    disabled={index === images.length - 1}
                                    onClick={() => move(index, 1)}
                                />
                                <IconButton
                                    icon="ri-delete-bin-line"
                                    label="Quitar foto"
                                    tone="danger"
                                    onClick={() => onChange(images.filter((_, current) => current !== index))}
                                />
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            <button
                type="button"
                onClick={() => setPickerOpen(true)}
                className="btn btn-outline-primary items-center gap-2 rounded-lg px-4 py-2 text-sm"
            >
                <i className="ri-image-add-line" aria-hidden="true" />
                Añadir foto
            </button>

            <p className="text-secondary-light mb-0 mt-2 text-sm">{hint}</p>
            <FieldError id="gallery-error" message={errors.images} />

            <MediaPicker open={pickerOpen} onClose={() => setPickerOpen(false)} onSelect={add} />
        </div>
    )
}

function IconButton({
    icon,
    label,
    onClick,
    disabled = false,
    tone = 'primary',
}: {
    icon: string
    label: string
    onClick: () => void
    disabled?: boolean
    tone?: 'primary' | 'danger'
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            aria-label={label}
            title={label}
            className={`inline-flex h-9 w-9 items-center justify-center rounded-lg text-neutral-600 disabled:opacity-30 dark:text-neutral-200 ${
                tone === 'danger'
                    ? 'hover:bg-danger-50 hover:text-danger-600'
                    : 'hover:bg-primary-50 hover:text-primary-600'
            }`}
        >
            <i className={icon} aria-hidden="true" />
        </button>
    )
}
