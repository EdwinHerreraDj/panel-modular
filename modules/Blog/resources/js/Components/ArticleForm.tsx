import axios from 'axios'
import { Link, router, useForm } from '@inertiajs/react'
import { useState, type FormEvent } from 'react'
import { useAutosave } from '@/hooks/useAutosave'
import AutosaveIndicator from '@/Components/UI/AutosaveIndicator'
import Card from '@/Components/UI/Card'
import Alert from '@/Components/UI/Alert'
import FormSection from '@/Components/UI/FormSection'
import TextField from '@/Components/UI/TextField'
import TextareaField from '@/Components/UI/TextareaField'
import SelectField from '@/Components/UI/SelectField'
import MultiSelect from '@/Components/UI/MultiSelect'
import MediaSelectField from '@/Components/Media/MediaSelectField'
import SlugDisplay from '@/Components/UI/SlugDisplay'
import RichTextEditor from '@/Components/Editor/RichTextEditor'
import type { SelectOption } from '@/types'

export interface ArticleFormValues {
    title: string
    excerpt: string
    content: string
    status: string
    visibility: string
    published_at: string
    scheduled_for: string
    position: number
    category_ids: number[]
    tag_ids: number[]
    related_ids: number[]
    featured_media_id: number | null
    featured_image_alt: string
    meta_title: string
    meta_description: string
    canonical_url: string
    og_title: string
    og_description: string
    is_indexable: boolean
}

/** Copia automática más reciente que el último guardado. */
export interface PendingAutosave {
    saved_at: string | null
    title: string | null
    excerpt: string | null
    content: string | null
}

interface ArticleFormProps {
    initialValues: ArticleFormValues
    statusOptions: SelectOption[]
    visibilityOptions: SelectOption[]
    categoryOptions: SelectOption[]
    tagOptions: SelectOption[]
    relatedOptions: SelectOption[]
    submitUrl: string
    submitLabel: string
    /** Solo al editar. */
    slug?: { value: string; locked: boolean; regenerateUrl: string }
    /** Imagen destacada ya asignada. */
    initialFeaturedMedia?: { id: number; name: string; url: string } | null
    readingMinutes?: number
    /** Identificador del artículo; sin él no hay autoguardado ni historial. */
    articleId?: number
    autosaveSeconds?: number
    pendingAutosave?: PendingAutosave | null
    revisionsCount?: number
}

/**
 * Formulario compartido por el alta y la edición de artículos.
 *
 * El envío siempre es POST porque lleva la imagen destacada y viaja como
 * multipart, donde el navegador no admite PUT.
 */
export default function ArticleForm({
    initialValues,
    statusOptions,
    visibilityOptions,
    categoryOptions,
    tagOptions,
    relatedOptions,
    submitUrl,
    submitLabel,
    slug,
    initialFeaturedMedia = null,
    readingMinutes = 0,
    articleId,
    autosaveSeconds = 30,
    pendingAutosave = null,
    revisionsCount = 0,
}: ArticleFormProps) {
    const { data, setData, post, processing, errors, isDirty } = useForm(initialValues)

    const [featuredMedia, setFeaturedMedia] = useState(initialFeaturedMedia)
    const [regenerating, setRegenerating] = useState(false)
    const [recoveryDismissed, setRecoveryDismissed] = useState(false)

    const autosave = useAutosave({
        url: articleId ? route('articles.autosave', articleId) : null,
        data: { title: data.title, excerpt: data.excerpt, content: data.content },
        intervalSeconds: autosaveSeconds,
        paused: processing,
    })

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault()
        post(submitUrl, { preserveScroll: true })
    }

    /** Vuelca la copia automática sobre el formulario. */
    function recoverAutosave() {
        if (!pendingAutosave) {
            return
        }

        setData((current) => ({
            ...current,
            title: pendingAutosave.title ?? current.title,
            excerpt: pendingAutosave.excerpt ?? current.excerpt,
            content: pendingAutosave.content ?? current.content,
        }))

        setRecoveryDismissed(true)
    }

    /** Descarta la copia y se queda con lo último guardado. */
    function discardAutosave() {
        if (articleId) {
            void axios.delete(route('articles.autosave.discard', articleId))
        }

        setRecoveryDismissed(true)
    }

    async function openPreview() {
        if (!articleId) {
            return
        }

        // Se guarda una copia antes de abrir, para que la vista previa muestre
        // lo que se tiene delante y no lo último guardado.
        await autosave.saveNow()

        window.open(route('articles.preview', articleId), '_blank', 'noopener')
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

    const isScheduled = data.status === 'scheduled'
    const isPublished = data.status === 'published'

    return (
        <form onSubmit={submit} noValidate>
            {Object.keys(errors).length > 0 && (
                <Alert variant="danger">Revisa los campos marcados: hay datos que no son correctos.</Alert>
            )}

            {pendingAutosave && !recoveryDismissed && (
                <div className="form-alert form-alert-info flex-col items-start gap-3 sm:flex-row sm:items-center">
                    <i className="ri-history-line mt-px shrink-0 text-lg" aria-hidden="true" />

                    <span className="flex-1">
                        Hay una copia automática más reciente que lo último guardado
                        {pendingAutosave.saved_at &&
                            ` (${new Intl.DateTimeFormat('es-ES', {
                                dateStyle: 'short',
                                timeStyle: 'short',
                            }).format(new Date(pendingAutosave.saved_at))})`}
                        . ¿Quieres recuperarla?
                    </span>

                    <span className="flex shrink-0 gap-3">
                        <button
                            type="button"
                            onClick={recoverAutosave}
                            className="font-medium text-primary-600 hover:underline"
                        >
                            Recuperar
                        </button>
                        <button
                            type="button"
                            onClick={discardAutosave}
                            className="font-medium text-neutral-600 hover:underline dark:text-neutral-300"
                        >
                            Descartar
                        </button>
                    </span>
                </div>
            )}

            <div className="grid gap-6 xl:grid-cols-[1fr_360px]">
                {/* ------------------------- Columna principal ------------------------ */}
                <div className="flex flex-col gap-6">
                    <Card>
                        <div className="flex flex-col gap-5">
                            <TextField
                                label="Título"
                                name="title"
                                value={data.title}
                                onChange={(event) => setData('title', event.target.value)}
                                error={errors.title}
                                maxLength={200}
                                autoFocus
                                required
                            />

                            <TextareaField
                                label="Extracto"
                                name="excerpt"
                                rows={3}
                                value={data.excerpt}
                                onChange={(event) => setData('excerpt', event.target.value)}
                                error={errors.excerpt}
                                maxLength={500}
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
                    </Card>

                    <Card padded={false}>
                        <div className="px-6 py-5">
                            <RichTextEditor
                                label="Contenido"
                                value={data.content}
                                onChange={(html) => setData('content', html)}
                                error={errors.content}
                            />

                            {readingMinutes > 0 && (
                                <p className="text-secondary-light mt-3 mb-0 text-sm">
                                    Tiempo estimado de lectura: {readingMinutes} min. Se recalcula al
                                    guardar.
                                </p>
                            )}
                        </div>
                    </Card>

                    <Card header={<h6 className="mb-0 text-base font-semibold">SEO y redes sociales</h6>}>
                        <div className="flex flex-col gap-6">
                            <FormSection
                                title="Buscadores"
                                description="Si los dejas vacíos se usan el título y el extracto del artículo."
                            >
                                <div className="flex flex-col gap-5">
                                    <TextField
                                        label="Meta título"
                                        name="meta_title"
                                        value={data.meta_title}
                                        onChange={(event) => setData('meta_title', event.target.value)}
                                        error={errors.meta_title}
                                        maxLength={70}
                                        hint={`${data.meta_title.length}/70 caracteres`}
                                    />

                                    <TextareaField
                                        label="Meta descripción"
                                        name="meta_description"
                                        rows={3}
                                        value={data.meta_description}
                                        onChange={(event) =>
                                            setData('meta_description', event.target.value)
                                        }
                                        error={errors.meta_description}
                                        maxLength={160}
                                    />

                                    <TextField
                                        label="URL canónica"
                                        name="canonical_url"
                                        value={data.canonical_url}
                                        onChange={(event) => setData('canonical_url', event.target.value)}
                                        error={errors.canonical_url}
                                        maxLength={255}
                                        placeholder="https://www.miempresa.es/blog/articulo"
                                        hint="Solo si este contenido ya existe en otra dirección."
                                    />

                                    <label className="flex items-start gap-3 rounded-xl border border-neutral-300 p-4 dark:border-neutral-600">
                                        <input
                                            type="checkbox"
                                            checked={data.is_indexable}
                                            onChange={(event) =>
                                                setData('is_indexable', event.target.checked)
                                            }
                                            className="form-check-input mt-1 border border-neutral-300"
                                        />
                                        <span>
                                            <span className="block font-semibold text-neutral-900 dark:text-white">
                                                Permitir la indexación en buscadores
                                            </span>
                                            <span className="text-secondary-light block text-sm">
                                                Desactívalo para contenido que no quieras que aparezca en
                                                Google.
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            </FormSection>

                            <FormSection
                                title="Al compartir"
                                description="Cómo se ve el artículo cuando alguien pega el enlace en una red social."
                            >
                                <div className="flex flex-col gap-5">
                                    <TextField
                                        label="Título para redes"
                                        name="og_title"
                                        value={data.og_title}
                                        onChange={(event) => setData('og_title', event.target.value)}
                                        error={errors.og_title}
                                        maxLength={100}
                                    />

                                    <TextareaField
                                        label="Descripción para redes"
                                        name="og_description"
                                        rows={2}
                                        value={data.og_description}
                                        onChange={(event) => setData('og_description', event.target.value)}
                                        error={errors.og_description}
                                        maxLength={200}
                                    />
                                </div>
                            </FormSection>
                        </div>
                    </Card>
                </div>

                {/* ------------------------------ Barra lateral ----------------------- */}
                <div className="flex flex-col gap-6">
                    <Card header={<h6 className="mb-0 text-base font-semibold">Publicación</h6>}>
                        <div className="flex flex-col gap-5">
                            <SelectField
                                label="Estado"
                                name="status"
                                value={data.status}
                                onChange={(event) => setData('status', event.target.value)}
                                options={statusOptions}
                                error={errors.status}
                                required
                            />

                            {isScheduled && (
                                <TextField
                                    label="Publicar el"
                                    name="scheduled_for"
                                    type="datetime-local"
                                    value={data.scheduled_for}
                                    onChange={(event) => setData('scheduled_for', event.target.value)}
                                    error={errors.scheduled_for}
                                    hint="Se publicará solo a esa hora."
                                    required
                                />
                            )}

                            {isPublished && (
                                <TextField
                                    label="Fecha de publicación"
                                    name="published_at"
                                    type="datetime-local"
                                    value={data.published_at}
                                    onChange={(event) => setData('published_at', event.target.value)}
                                    error={errors.published_at}
                                    hint="Si la dejas vacía se usa el momento de guardar."
                                />
                            )}

                            <SelectField
                                label="Visibilidad"
                                name="visibility"
                                value={data.visibility}
                                onChange={(event) => setData('visibility', event.target.value)}
                                options={visibilityOptions}
                                error={errors.visibility}
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
                                hint="Menor, antes en los listados destacados."
                                required
                            />
                        </div>
                    </Card>

                    <Card header={<h6 className="mb-0 text-base font-semibold">Imagen destacada</h6>}>
                        <div className="flex flex-col gap-5">
                            <MediaSelectField
                                label="Imagen"
                                value={featuredMedia}
                                onChange={(media) => {
                                    setFeaturedMedia(
                                        media === null
                                            ? null
                                            : { id: media.id, name: media.name, url: media.preview_url },
                                    )
                                    setData('featured_media_id', media?.id ?? null)
                                }}
                                error={errors.featured_media_id}
                                hint="Se elige de la biblioteca y se puede reutilizar en varios artículos."
                            />

                            <TextField
                                label="Texto alternativo"
                                name="featured_image_alt"
                                value={data.featured_image_alt}
                                onChange={(event) => setData('featured_image_alt', event.target.value)}
                                error={errors.featured_image_alt}
                                maxLength={255}
                                hint="Describe la imagen para quien no puede verla."
                            />
                        </div>
                    </Card>

                    <Card header={<h6 className="mb-0 text-base font-semibold">Clasificación</h6>}>
                        <div className="flex flex-col gap-5">
                            <MultiSelect
                                label="Categorías"
                                options={categoryOptions}
                                value={data.category_ids}
                                onChange={(ids) => setData('category_ids', ids)}
                                error={errors.category_ids}
                                emptyMessage="Todavía no hay categorías creadas."
                            />

                            <MultiSelect
                                label="Etiquetas"
                                options={tagOptions}
                                value={data.tag_ids}
                                onChange={(ids) => setData('tag_ids', ids)}
                                error={errors.tag_ids}
                                emptyMessage="Todavía no hay etiquetas creadas."
                            />
                        </div>
                    </Card>

                    <Card header={<h6 className="mb-0 text-base font-semibold">Artículos relacionados</h6>}>
                        <MultiSelect
                            label="Se recomendarán al final de este artículo"
                            options={relatedOptions}
                            value={data.related_ids}
                            onChange={(ids) => setData('related_ids', ids)}
                            error={errors.related_ids}
                            emptyMessage="Todavía no hay otros artículos con los que relacionarlo."
                        />
                    </Card>

                    {articleId && (
                        <Card header={<h6 className="mb-0 text-base font-semibold">Herramientas</h6>}>
                            <div className="flex flex-col gap-2">
                                <button
                                    type="button"
                                    onClick={() => void openPreview()}
                                    className="flex items-center gap-3 rounded-lg px-3 py-2 text-start text-sm text-neutral-600 hover:bg-neutral-100 hover:text-primary-600 dark:text-neutral-200 dark:hover:bg-neutral-600"
                                >
                                    <i className="ri-eye-line text-lg" aria-hidden="true" />
                                    Vista previa
                                </button>

                                <Link
                                    href={route('articles.revisions.index', articleId)}
                                    className="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-neutral-600 hover:bg-neutral-100 hover:text-primary-600 dark:text-neutral-200 dark:hover:bg-neutral-600"
                                >
                                    <i className="ri-history-line text-lg" aria-hidden="true" />
                                    Historial de versiones
                                    {revisionsCount > 0 && (
                                        <span className="text-secondary-light ms-auto text-xs">
                                            {revisionsCount}
                                        </span>
                                    )}
                                </Link>

                                <button
                                    type="button"
                                    onClick={() =>
                                        router.post(route('articles.duplicate', articleId), {}, {})
                                    }
                                    className="flex items-center gap-3 rounded-lg px-3 py-2 text-start text-sm text-neutral-600 hover:bg-neutral-100 hover:text-primary-600 dark:text-neutral-200 dark:hover:bg-neutral-600"
                                >
                                    <i className="ri-file-copy-line text-lg" aria-hidden="true" />
                                    Duplicar artículo
                                </button>
                            </div>
                        </Card>
                    )}
                </div>
            </div>

            <div className="sticky bottom-0 mt-6 flex flex-wrap items-center justify-end gap-3 rounded-xl border border-neutral-200 bg-white px-6 py-4 dark:border-neutral-600 dark:bg-dark-2">
                <div className="me-auto flex flex-wrap items-center gap-4">
                    {isDirty && (
                        <p className="text-secondary-light mb-0 text-sm">Tienes cambios sin guardar.</p>
                    )}

                    <AutosaveIndicator state={autosave.state} savedAt={autosave.savedAt} />
                </div>

                <Link
                    href={route('articles.index')}
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
