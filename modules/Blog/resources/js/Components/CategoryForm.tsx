import { Link, router, useForm } from '@inertiajs/react'
import { useState, type FormEvent } from 'react'
import Alert from '@/Components/UI/Alert'
import FormSection from '@/Components/UI/FormSection'
import TextField from '@/Components/UI/TextField'
import TextareaField from '@/Components/UI/TextareaField'
import SlugDisplay from '@/Components/UI/SlugDisplay'

export interface CategoryFormValues {
    name: string
    description: string
    meta_title: string
    meta_description: string
    position: number
    [key: string]: string | number
}

interface CategoryFormProps {
    initialValues: CategoryFormValues
    submit: { method: 'post' | 'put'; url: string }
    submitLabel: string
    cancelUrl: string
    /** Solo al editar: dirección pública actual. */
    slug?: { value: string; locked: boolean; regenerateUrl: string }
}

/**
 * Formulario compartido por el alta y la edición de categorías.
 */
export default function CategoryForm({
    initialValues,
    submit,
    submitLabel,
    cancelUrl,
    slug,
}: CategoryFormProps) {
    const { data, setData, post, put, processing, errors, isDirty } =
        useForm<CategoryFormValues>(initialValues)

    const [regenerating, setRegenerating] = useState(false)

    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault()

        if (submit.method === 'post') {
            post(submit.url)
        } else {
            put(submit.url)
        }
    }

    function regenerateSlug() {
        if (!slug) {
            return
        }

        router.post(
            slug.regenerateUrl,
            {},
            {
                preserveScroll: true,
                onStart: () => setRegenerating(true),
                onFinish: () => setRegenerating(false),
            },
        )
    }

    return (
        <form onSubmit={handleSubmit} noValidate>
            {Object.keys(errors).length > 0 && (
                <Alert variant="danger">Revisa los campos marcados: hay datos que no son correctos.</Alert>
            )}

            <div className="flex flex-col gap-6">
                <FormSection
                    title="Datos de la categoría"
                    description="El nombre es lo que verán los lectores en la web."
                >
                    <div className="grid gap-5">
                        <div className="grid gap-5 md:grid-cols-[1fr_160px]">
                            <TextField
                                label="Nombre"
                                name="name"
                                value={data.name}
                                onChange={(event) => setData('name', event.target.value)}
                                error={errors.name}
                                maxLength={120}
                                autoFocus
                                required
                            />

                            <TextField
                                label="Orden"
                                name="position"
                                type="number"
                                min={0}
                                max={9999}
                                value={data.position}
                                onChange={(event) => setData('position', Number(event.target.value))}
                                error={errors.position}
                                hint="Menor, antes."
                                required
                            />
                        </div>

                        <TextareaField
                            label="Descripción"
                            name="description"
                            rows={3}
                            value={data.description}
                            onChange={(event) => setData('description', event.target.value)}
                            error={errors.description}
                            maxLength={1000}
                        />

                        {slug && (
                            <SlugDisplay
                                slug={slug.value}
                                locked={slug.locked}
                                onRegenerate={regenerateSlug}
                                processing={regenerating}
                            />
                        )}
                    </div>
                </FormSection>

                <FormSection
                    title="SEO"
                    description="Si los dejas vacíos se usan los valores por defecto de la configuración del blog."
                >
                    <div className="grid gap-5">
                        <TextField
                            label="Meta título"
                            name="meta_title"
                            value={data.meta_title}
                            onChange={(event) => setData('meta_title', event.target.value)}
                            error={errors.meta_title}
                            maxLength={70}
                            hint={`${String(data.meta_title).length}/70 caracteres`}
                        />

                        <TextareaField
                            label="Meta descripción"
                            name="meta_description"
                            rows={3}
                            value={data.meta_description}
                            onChange={(event) => setData('meta_description', event.target.value)}
                            error={errors.meta_description}
                            maxLength={160}
                        />
                    </div>
                </FormSection>
            </div>

            <div className="mt-8 flex flex-wrap items-center justify-end gap-3 border-t border-neutral-200 pt-6 dark:border-neutral-600">
                {isDirty && (
                    <p className="text-secondary-light me-auto mb-0 text-sm">Tienes cambios sin guardar.</p>
                )}

                <Link
                    href={cancelUrl}
                    className="btn rounded-lg border border-neutral-300 px-5 py-2.5 text-sm font-medium text-neutral-600 hover:bg-neutral-100 dark:border-neutral-500 dark:text-neutral-200 dark:hover:bg-neutral-600"
                >
                    Cancelar
                </Link>

                <button
                    type="submit"
                    disabled={processing}
                    aria-busy={processing}
                    className="btn btn-primary rounded-lg px-5 py-2.5 text-sm disabled:cursor-not-allowed disabled:opacity-70"
                >
                    {processing ? 'Guardando…' : submitLabel}
                </button>
            </div>
        </form>
    )
}
