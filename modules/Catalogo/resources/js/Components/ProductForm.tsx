import { router, useForm } from '@inertiajs/react'
import type { FormEvent } from 'react'
import type { MediaPickerItem } from '@/types'
import Card from '@/Components/UI/Card'
import Checkbox from '@/Components/UI/Checkbox'
import FormSection from '@/Components/UI/FormSection'
import LinkButton from '@/Components/UI/LinkButton'
import SlugDisplay from '@/Components/UI/SlugDisplay'
import SubmitButton from '@/Components/UI/SubmitButton'
import SelectField from '@/Components/UI/SelectField'
import TextField from '@/Components/UI/TextField'
import TextareaField from '@/Components/UI/TextareaField'
import MediaSelectField from '@/Components/Media/MediaSelectField'
import RichTextEditor from '@/Components/Editor/RichTextEditor'

export interface ProductFormValues {
    name: string
    reference: string
    summary: string
    description: string
    price: string
    sale_price: string
    availability: string
    image_media_id: number | null
    image_alt: string
    image_url: string | null
    is_visible: boolean
    position: number
}

interface Props {
    values: ProductFormValues
    availabilityOptions: { value: string; label: string }[]
    /** Qué imagen conviene subir; lo calcula el servidor con los límites reales. */
    imageHint: string
    submitUrl: string
    submitLabel: string
    /** Dirección pública, solo al editar. */
    slug?: string
    slugLocked?: boolean
    slugUrl?: string
    cancelUrl: string
}

/**
 * Formulario de un producto.
 *
 * Tres bloques y nada más: lo que se ve en la web, la foto y cuándo se enseña.
 * No hay estados de publicación ni pestañas de SEO: quien lo usa quiere subir
 * un producto, no aprender un gestor de contenidos.
 */
export default function ProductForm({
    values,
    availabilityOptions,
    imageHint,
    submitUrl,
    submitLabel,
    slug,
    slugLocked = false,
    slugUrl,
    cancelUrl,
}: Props) {
    const { data, setData, post, processing, errors } = useForm(values)

    // Vista previa de la oferta, con los números tal y como se están
    // escribiendo: así se ve el efecto antes de guardar.
    const toNumber = (value: string): number =>
        Number(value.replace(/\./g, '').replace(',', '.'))

    const before = toNumber(data.price)
    const now = toNumber(data.sale_price)
    const onSale = data.price !== '' && data.sale_price !== '' && now > 0 && now < before
    const discount = onSale ? Math.round(((before - now) / before) * 100) : 0

    function submit(event: FormEvent) {
        event.preventDefault()
        post(submitUrl, { preserveScroll: true })
    }

    return (
        <form onSubmit={submit}>
            <Card>
                <div className="flex flex-col gap-6">
                    <FormSection
                        title="El producto"
                        description="Lo que verá quien entre en tu web."
                    >
                        <div className="grid gap-4 md:grid-cols-2">
                            <TextField
                                label="Nombre"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                error={errors.name}
                                required
                                autoFocus
                            />

                            <TextField
                                label="Referencia"
                                value={data.reference}
                                onChange={(e) => setData('reference', e.target.value)}
                                error={errors.reference}
                                hint="Tu código de producto, si usas uno. Puedes dejarlo vacío."
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
                                              onRegenerate: () =>
                                                  router.post(slugUrl, {}, { preserveScroll: true }),
                                          }
                                        : {})}
                                />
                            </div>
                        )}

                        <div className="mt-4">
                            <TextareaField
                                label="Resumen"
                                value={data.summary}
                                onChange={(e) => setData('summary', e.target.value)}
                                error={errors.summary}
                                rows={2}
                                maxLength={255}
                            />
                            <p className="text-secondary-light mt-1 text-sm">
                                Una línea que acompaña a la foto en el listado de la web.
                            </p>
                        </div>

                        <div className="mt-4">
                            <RichTextEditor
                                label="Descripción"
                                value={data.description}
                                onChange={(html) => setData('description', html)}
                                error={errors.description}
                                placeholder="Explica el producto: materiales, medidas, para qué sirve…"
                            />
                        </div>

                        <div className="mt-4">
                            <TextField
                                label="Orden"
                                type="number"
                                min={0}
                                value={data.position}
                                onChange={(e) => setData('position', Number(e.target.value))}
                                error={errors.position}
                                hint="Los números más bajos salen primero en la web."
                            />
                        </div>
                    </FormSection>

                    <FormSection
                        title="Precio y existencias"
                        description="Lo que cuesta y si te queda."
                    >
                        <div className="grid gap-4 md:grid-cols-2">
                            <TextField
                                label="Precio"
                                value={data.price}
                                onChange={(e) => setData('price', e.target.value)}
                                error={errors.price}
                                inputMode="decimal"
                                hint="Por ejemplo 149,90. Déjalo vacío si prefieres que te pregunten."
                            />

                            <TextField
                                label="Precio rebajado"
                                value={data.sale_price}
                                onChange={(e) => setData('sale_price', e.target.value)}
                                error={errors.sale_price}
                                inputMode="decimal"
                                hint="Solo si está de oferta. La web enseñará el precio de arriba tachado."
                            />
                        </div>

                        {onSale && (
                            <p className="mt-2 text-sm text-success-600 dark:text-success-400">
                                En la web se verá <s>{data.price} €</s> {data.sale_price} €, con un{' '}
                                {discount}&nbsp;% de descuento.
                            </p>
                        )}

                        <div className="mt-4">
                            <SelectField
                                label="Disponibilidad"
                                value={data.availability}
                                onChange={(e) => setData('availability', e.target.value)}
                                error={errors.availability}
                                options={availabilityOptions}
                            />
                            <p className="text-secondary-light mt-1 text-sm">
                                Un producto sin stock sigue saliendo en la web, avisado. Para que desaparezca,
                                quítale la marca de visible.
                            </p>
                        </div>
                    </FormSection>

                    <FormSection title="La foto" description="Se elige de la biblioteca y se puede reutilizar.">
                        <MediaSelectField
                            label="Imagen del producto"
                            value={
                                data.image_media_id && data.image_url
                                    ? { id: data.image_media_id, name: data.name, url: data.image_url }
                                    : null
                            }
                            onChange={(media: MediaPickerItem | null) => {
                                setData((current) => ({
                                    ...current,
                                    image_media_id: media?.id ?? null,
                                    image_url: media?.preview_url ?? null,
                                    image_alt: media?.alt_text ?? current.image_alt,
                                }))
                            }}
                            error={errors.image_media_id}
                            hint={imageHint}
                        />

                        <div className="mt-4">
                            <TextField
                                label="Texto alternativo"
                                value={data.image_alt}
                                onChange={(e) => setData('image_alt', e.target.value)}
                                error={errors.image_alt}
                                hint="Describe la foto en pocas palabras. Lo leen quienes no pueden verla y también Google."
                            />
                        </div>
                    </FormSection>

                    <FormSection title="En la web" description="Mientras no esté visible, no lo ve nadie.">
                        <Checkbox
                            label="Enseñar este producto en la web"
                            checked={data.is_visible}
                            onChange={(e) => setData('is_visible', e.target.checked)}
                        />
                    </FormSection>

                    <div className="flex flex-wrap items-center gap-3">
                        <SubmitButton processing={processing} processingLabel="Guardando…">
                            {submitLabel}
                        </SubmitButton>

                        <LinkButton href={cancelUrl} variant="outline">
                            Cancelar
                        </LinkButton>
                    </div>
                </div>
            </Card>
        </form>
    )
}
