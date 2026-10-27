import { router, useForm } from '@inertiajs/react'
import { useState, type FormEvent } from 'react'
import Card from '@/Components/UI/Card'
import Checkbox from '@/Components/UI/Checkbox'
import FormSection from '@/Components/UI/FormSection'
import LinkButton from '@/Components/UI/LinkButton'
import MultiSelect from '@/Components/UI/MultiSelect'
import SelectField from '@/Components/UI/SelectField'
import SlugDisplay from '@/Components/UI/SlugDisplay'
import SubmitButton from '@/Components/UI/SubmitButton'
import Tabs, { TabPanel } from '@/Components/UI/Tabs'
import TextField from '@/Components/UI/TextField'
import TextareaField from '@/Components/UI/TextareaField'
import RichTextEditor from '@/Components/Editor/RichTextEditor'
import GalleryField from './GalleryField'
import VariantsEditor from './VariantsEditor'
import type { ProductFormOptions, ProductFormValues } from '../types'

interface Props extends ProductFormOptions {
    values: ProductFormValues
    method: 'post' | 'put'
    submitUrl: string
    submitLabel: string
    /** Dirección pública, solo al editar. */
    slug?: string
    slugLocked?: boolean
    slugUrl?: string
    /** Enlace de WhatsApp tal y como lo verá el cliente, solo al editar. */
    whatsappUrl?: string | null
}

type TabId = 'general' | 'price' | 'variants' | 'photos' | 'web'

/** A qué pestaña pertenece cada campo, para contar sus errores. */
const FIELD_TABS: Record<string, TabId> = {
    name: 'general',
    sku: 'general',
    brand: 'general',
    summary: 'general',
    description: 'general',
    category_id: 'general',
    category_ids: 'general',
    price: 'price',
    stock: 'price',
    low_stock_threshold: 'price',
    options: 'variants',
    variants: 'variants',
    images: 'photos',
    position: 'web',
    meta_title: 'web',
    meta_description: 'web',
}

/**
 * Formulario de un artículo de la tienda.
 *
 * Va en pestañas porque un artículo con variantes y galería no cabe en una
 * pantalla sin convertirse en un muro. Las pestañas siguen montadas al
 * cambiar, así que no se pierde nada de lo escrito, y cada una avisa de cuántos
 * errores tiene.
 */
export default function ProductForm({
    values,
    method,
    submitUrl,
    submitLabel,
    slug,
    slugLocked = false,
    slugUrl,
    whatsappUrl,
    categoryOptions,
    imageHint,
    canManageStock,
    defaultThreshold,
    currency,
}: Props) {
    const { data, setData, submit, processing, errors } = useForm<ProductFormValues>(values)
    const [tab, setTab] = useState<TabId>('general')
    const errorMap = errors as Record<string, string | undefined>
    const hasVariants = data.options.length > 0

    function errorCount(id: TabId): number {
        return Object.keys(errorMap).filter((field) => FIELD_TABS[field.split('.')[0] ?? ''] === id).length
    }

    function save(event: FormEvent) {
        event.preventDefault()
        submit(method, submitUrl, {
            preserveScroll: true,
            onError: (failed) => {
                // Lleva a la primera pestaña con errores: si no, el botón no
                // hace nada visible y parece que el formulario no funciona.
                const first = Object.keys(failed)
                    .map((field) => FIELD_TABS[field.split('.')[0] ?? ''])
                    .find((id) => id !== undefined)

                if (first) {
                    setTab(first)
                }
            },
        })
    }

    return (
        <form onSubmit={save}>
            <Card>
                <Tabs
                    tabs={[
                        {
                            id: 'general',
                            label: 'General',
                            icon: 'ri-file-text-line',
                            errorCount: errorCount('general'),
                        },
                        {
                            id: 'price',
                            label: 'Precio y stock',
                            icon: 'ri-price-tag-3-line',
                            errorCount: errorCount('price'),
                        },
                        {
                            id: 'variants',
                            label: 'Variantes',
                            icon: 'ri-stack-line',
                            errorCount: errorCount('variants'),
                        },
                        {
                            id: 'photos',
                            label: 'Fotos',
                            icon: 'ri-image-line',
                            errorCount: errorCount('photos'),
                        },
                        {
                            id: 'web',
                            label: 'En la web',
                            icon: 'ri-global-line',
                            errorCount: errorCount('web'),
                        },
                    ]}
                    active={tab}
                    onChange={(id) => setTab(id as TabId)}
                >
                    <TabPanel id="general" active={tab}>
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
                                value={data.sku}
                                onChange={(e) => setData('sku', e.target.value)}
                                error={errors.sku}
                                hint="Tu código interno. Puedes dejarlo vacío."
                            />
                            <TextField
                                label="Marca"
                                value={data.brand}
                                onChange={(e) => setData('brand', e.target.value)}
                                error={errors.brand}
                            />
                            <SelectField
                                label="Categoría principal"
                                value={data.category_id}
                                onChange={(e) => setData('category_id', e.target.value)}
                                error={errors.category_id}
                                options={[{ value: '', label: 'Sin categoría' }, ...categoryOptions]}
                                hint="La de la ruta de migas en la web."
                            />
                        </div>

                        {slug && (
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
                        )}

                        <MultiSelect
                            label="También en estas categorías"
                            options={categoryOptions.filter((option) => option.value !== data.category_id)}
                            value={data.category_ids}
                            onChange={(ids) => setData('category_ids', ids)}
                            error={errors.category_ids}
                            emptyMessage="Todavía no hay categorías. Créalas en Tienda › Categorías."
                        />

                        <div>
                            <TextareaField
                                label="Resumen"
                                value={data.summary}
                                onChange={(e) => setData('summary', e.target.value)}
                                error={errors.summary}
                                rows={2}
                                maxLength={255}
                            />
                            <p className="text-secondary-light mt-1 text-sm">
                                Una línea que acompaña a la foto en el listado de la tienda.
                            </p>
                        </div>

                        <RichTextEditor
                            label="Descripción"
                            value={data.description}
                            onChange={(html) => setData('description', html)}
                            error={errors.description}
                            placeholder="Materiales, medidas, cuidados, para qué sirve…"
                        />
                    </TabPanel>

                    <TabPanel id="price" active={tab}>
                        <FormSection title="Precio" description="Lo que cuesta sin promociones.">
                            <div className="max-w-xs">
                                <TextField
                                    label={`Precio (${currency})`}
                                    value={data.price}
                                    onChange={(e) => setData('price', e.target.value)}
                                    error={errors.price}
                                    inputMode="decimal"
                                    hint="Por ejemplo 19,90. Vacío: la web enseña «consultar precio»."
                                />
                            </div>
                            <p className="text-secondary-light mb-0 mt-2 text-sm">
                                Para rebajarlo no cambies este precio: crea una promoción. La web tachará este y
                                enseñará el rebajado, y cuando acabe volverá solo.
                            </p>
                        </FormSection>

                        <FormSection title="Existencias" description="Cuántas unidades tienes para vender.">
                            <Checkbox
                                label="Controlar existencias"
                                checked={data.track_stock}
                                onChange={(e) => setData('track_stock', e.target.checked)}
                            />
                            <p className="text-secondary-light mb-4 mt-1 text-sm">
                                Desmárcalo para lo que se hace por encargo o nunca se acaba: siempre saldrá disponible.
                            </p>

                            {data.track_stock && (
                                <div className="grid gap-4 md:grid-cols-2">
                                    {hasVariants ? (
                                        <p className="text-secondary-light mb-0 text-sm md:col-span-2">
                                            Este artículo tiene variantes: las unidades se llevan por variante, en la
                                            pestaña «Variantes».
                                        </p>
                                    ) : (
                                        <TextField
                                            label="Unidades"
                                            type="number"
                                            min={0}
                                            value={data.stock}
                                            onChange={(e) => setData('stock', Math.max(0, Number(e.target.value)))}
                                            error={errors.stock}
                                            disabled={!canManageStock}
                                            hint={
                                                canManageStock
                                                    ? 'Si lo cambias, queda apuntado como recuento en el historial.'
                                                    : 'Solo lo cambia quien gestiona el inventario.'
                                            }
                                        />
                                    )}

                                    <TextField
                                        label="Avisar cuando queden"
                                        type="number"
                                        min={0}
                                        value={data.low_stock_threshold}
                                        placeholder={String(defaultThreshold)}
                                        onChange={(e) => setData('low_stock_threshold', e.target.value)}
                                        error={errors.low_stock_threshold}
                                        hint={`Vacío: el general de Configuración (${defaultThreshold}).`}
                                    />
                                </div>
                            )}
                        </FormSection>
                    </TabPanel>

                    <TabPanel id="variants" active={tab}>
                        <VariantsEditor
                            options={data.options}
                            variants={data.variants}
                            onChange={(options, variants) =>
                                setData((current) => ({
                                    ...current,
                                    options,
                                    variants,
                                }))
                            }
                            images={data.images}
                            errors={errorMap}
                            baseSku={data.sku}
                            currency={currency}
                            canManageStock={canManageStock}
                        />
                    </TabPanel>

                    <TabPanel id="photos" active={tab}>
                        <GalleryField
                            images={data.images}
                            onChange={(images) =>
                                setData((current) => ({
                                    ...current,
                                    images,
                                    // Una variante no puede apuntar a una foto que ya no está.
                                    variants: current.variants.map((variant) =>
                                        images.some((image) => image.media_id === variant.media_id)
                                            ? variant
                                            : { ...variant, media_id: null },
                                    ),
                                }))
                            }
                            errors={errorMap}
                            hint={imageHint}
                            productName={data.name}
                        />
                    </TabPanel>

                    <TabPanel id="web" active={tab}>
                        <FormSection title="Visibilidad" description="Mientras no esté visible, no lo ve nadie.">
                            <div className="flex flex-col gap-3">
                                <Checkbox
                                    label="Enseñar este artículo en la tienda"
                                    checked={data.is_visible}
                                    onChange={(e) => setData('is_visible', e.target.checked)}
                                />
                                <Checkbox
                                    label="Destacado: sale de los primeros"
                                    checked={data.is_featured}
                                    onChange={(e) => setData('is_featured', e.target.checked)}
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
                                    hint="Los números más bajos salen primero."
                                />
                            </div>
                        </FormSection>

                        <FormSection
                            title="Buscadores"
                            description="Opcional. Si lo dejas vacío se usan el nombre y el resumen."
                        >
                            <div className="flex flex-col gap-4">
                                <TextField
                                    label="Título para Google"
                                    value={data.meta_title}
                                    maxLength={70}
                                    onChange={(e) => setData('meta_title', e.target.value)}
                                    error={errors.meta_title}
                                />
                                <TextareaField
                                    label="Descripción para Google"
                                    value={data.meta_description}
                                    maxLength={160}
                                    rows={2}
                                    onChange={(e) => setData('meta_description', e.target.value)}
                                    error={errors.meta_description}
                                />
                            </div>
                        </FormSection>

                        {whatsappUrl !== undefined && (
                            <FormSection title="WhatsApp" description="Lo que verá quien pulse el botón en la web.">
                                {whatsappUrl ? (
                                    <a
                                        href={whatsappUrl}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="btn btn-outline-primary inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm"
                                    >
                                        <i className="ri-whatsapp-line" aria-hidden="true" />
                                        Probar el mensaje
                                    </a>
                                ) : (
                                    <p className="text-secondary-light mb-0 text-sm">
                                        No hay número de WhatsApp configurado. Ponlo en Configuración › Tienda y la web
                                        enseñará el botón.
                                    </p>
                                )}
                            </FormSection>
                        )}
                    </TabPanel>
                </Tabs>

                <div className="mt-6 flex flex-wrap items-center gap-3 border-t border-neutral-200 pt-6 dark:border-neutral-600">
                    <SubmitButton processing={processing} processingLabel="Guardando…">
                        {submitLabel}
                    </SubmitButton>

                    <LinkButton href={route('tienda.products.index')} variant="outline">
                        Volver al listado
                    </LinkButton>
                </div>
            </Card>
        </form>
    )
}
