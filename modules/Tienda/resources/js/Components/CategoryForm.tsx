import { router, useForm } from '@inertiajs/react'
import type { FormEvent } from 'react'
import type { MediaPickerItem, SelectOption } from '@/types'
import Card from '@/Components/UI/Card'
import Checkbox from '@/Components/UI/Checkbox'
import FormSection from '@/Components/UI/FormSection'
import LinkButton from '@/Components/UI/LinkButton'
import SelectField from '@/Components/UI/SelectField'
import SlugDisplay from '@/Components/UI/SlugDisplay'
import SubmitButton from '@/Components/UI/SubmitButton'
import TextField from '@/Components/UI/TextField'
import TextareaField from '@/Components/UI/TextareaField'
import MediaSelectField from '@/Components/Media/MediaSelectField'

export interface CategoryFormValues {
    name: string
    parent_id: string
    description: string
    image_media_id: number | null
    image_url: string | null
    is_visible: boolean
    position: number
    meta_title: string
    meta_description: string
}

interface Props {
    values: CategoryFormValues
    parentOptions: SelectOption[]
    method: 'post' | 'put'
    submitUrl: string
    submitLabel: string
    slug?: string
    slugLocked?: boolean
    slugUrl?: string
}

/**
 * Formulario de una categoría de la tienda.
 */
export default function CategoryForm({
    values,
    parentOptions,
    method,
    submitUrl,
    submitLabel,
    slug,
    slugLocked = false,
    slugUrl,
}: Props) {
    const { data, setData, submit, processing, errors } = useForm(values)

    function save(event: FormEvent) {
        event.preventDefault()
        submit(method, submitUrl, { preserveScroll: true })
    }

    return (
        <form onSubmit={save}>
            <Card>
                <div className="flex flex-col gap-6">
                    <FormSection title="La categoría" description="Cómo se llama y dónde va en el menú de la tienda.">
                        <div className="grid gap-4 md:grid-cols-2">
                            <TextField
                                label="Nombre"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                error={errors.name}
                                required
                                autoFocus
                            />
                            <SelectField
                                label="Dentro de"
                                value={data.parent_id}
                                onChange={(e) => setData('parent_id', e.target.value)}
                                error={errors.parent_id}
                                options={parentOptions}
                                hint="Hasta tres niveles: Ropa › Hombre › Camisetas."
                            />
                        </div>

                        {slug && (
                            <div className="mt-4">
                                <SlugDisplay
                                    slug={slug}
                                    locked={slugLocked}
                                    processing={processing}
                                    {...(slugUrl
                                        ? {
                                              onRegenerate: () => router.post(slugUrl, {}, { preserveScroll: true }),
                                          }
                                        : {})}
                                />
                            </div>
                        )}

                        <div className="mt-4">
                            <TextareaField
                                label="Descripción"
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                error={errors.description}
                                rows={3}
                                maxLength={2000}
                            />
                        </div>

                        <div className="mt-4 max-w-xs">
                            <TextField
                                label="Orden"
                                type="number"
                                min={0}
                                value={data.position}
                                onChange={(e) => setData('position', Number(e.target.value))}
                                error={errors.position}
                                hint="Entre las de su mismo nivel, los números bajos van primero."
                            />
                        </div>
                    </FormSection>

                    <FormSection title="Imagen" description="Opcional. Para la portada de la categoría en la web.">
                        <MediaSelectField
                            label="Imagen de la categoría"
                            value={
                                data.image_media_id && data.image_url
                                    ? {
                                          id: data.image_media_id,
                                          name: data.name,
                                          url: data.image_url,
                                      }
                                    : null
                            }
                            onChange={(media: MediaPickerItem | null) =>
                                setData((current) => ({
                                    ...current,
                                    image_media_id: media?.id ?? null,
                                    image_url: media?.preview_url ?? null,
                                }))
                            }
                            error={errors.image_media_id}
                        />
                    </FormSection>

                    <FormSection
                        title="En la web"
                        description="Una categoría oculta desaparece con todo lo que cuelga de ella."
                    >
                        <Checkbox
                            label="Enseñar esta categoría en la tienda"
                            checked={data.is_visible}
                            onChange={(e) => setData('is_visible', e.target.checked)}
                        />

                        <div className="mt-4 grid gap-4 md:grid-cols-2">
                            <TextField
                                label="Título para Google"
                                value={data.meta_title}
                                maxLength={70}
                                onChange={(e) => setData('meta_title', e.target.value)}
                                error={errors.meta_title}
                            />
                            <TextField
                                label="Descripción para Google"
                                value={data.meta_description}
                                maxLength={160}
                                onChange={(e) => setData('meta_description', e.target.value)}
                                error={errors.meta_description}
                            />
                        </div>
                    </FormSection>

                    <div className="flex flex-wrap items-center gap-3">
                        <SubmitButton processing={processing} processingLabel="Guardando…">
                            {submitLabel}
                        </SubmitButton>
                        <LinkButton href={route('tienda.categories.index')} variant="outline">
                            Cancelar
                        </LinkButton>
                    </div>
                </div>
            </Card>
        </form>
    )
}
