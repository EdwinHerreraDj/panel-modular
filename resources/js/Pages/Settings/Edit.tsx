import { useForm } from '@inertiajs/react'
import { useState, type FormEvent } from 'react'
import DashboardLayout from '@/Layouts/DashboardLayout'
import Card from '@/Components/UI/Card'
import Alert from '@/Components/UI/Alert'
import Tabs, { TabPanel, type TabDefinition } from '@/Components/UI/Tabs'
import FormSection from '@/Components/UI/FormSection'
import TextField from '@/Components/UI/TextField'
import TextareaField from '@/Components/UI/TextareaField'
import SelectField from '@/Components/UI/SelectField'
import Checkbox from '@/Components/UI/Checkbox'
import ImageField from '@/Components/UI/ImageField'
import type { ImageConstraint, SelectOption, SocialProfile } from '@/types'

interface SettingsValues {
    site_name: string
    tagline: string | null
    public_url: string | null
    locale: string
    timezone: string
    meta_title: string | null
    meta_description: string | null
    title_template: string
    is_indexable: boolean
    twitter_card: string
    organization_name: string | null
    social_profiles: SocialProfile[]
}

/** Un ajuste que aporta un módulo activo. */
interface ModuleField {
    key: string
    label: string
    type: 'number' | 'text' | 'boolean'
    hint: string | null
    value: string | number | boolean | null
}

interface ModuleSection {
    module: string
    title: string
    fields: ModuleField[]
}

interface SettingsEditProps {
    settings: SettingsValues
    moduleSettings: ModuleSection[]
    images: Record<'logo' | 'logo_dark' | 'favicon' | 'og_image', string | null>
    imageConstraints: Record<'logo' | 'logo_dark' | 'favicon' | 'og_image', ImageConstraint>
    localeOptions: SelectOption[]
    timezoneOptions: SelectOption[]
    networkOptions: SelectOption[]
}

type ImageKey = 'logo' | 'logo_dark' | 'favicon' | 'og_image'

/** Campos que pertenecen a cada pestaña, para contar sus errores. */
const TAB_FIELDS: Record<string, string[]> = {
    general: [
        'site_name',
        'tagline',
        'public_url',
        'locale',
        'timezone',
        'logo',
        'logo_dark',
        'favicon',
    ],
    seo: ['meta_title', 'meta_description', 'title_template', 'is_indexable', 'twitter_card', 'og_image'],
    social: ['organization_name', 'social_profiles'],
}

export default function SettingsEdit({
    settings,
    moduleSettings,
    images,
    imageConstraints,
    localeOptions,
    timezoneOptions,
    networkOptions,
}: SettingsEditProps) {
    const [tab, setTab] = useState('general')

    // Lo que aporta cada módulo llega como datos, así que el estado se arma
    // desde la respuesta y esta pantalla no sabe qué está configurando.
    const initialModules: Record<string, Record<string, string | number | boolean | null>> = {}

    for (const section of moduleSettings) {
        initialModules[section.module] = Object.fromEntries(
            section.fields.map((field) => [field.key, field.value]),
        )
    }

    const { data, setData, post, processing, errors, isDirty } = useForm({
        modules: initialModules,
        site_name: settings.site_name,
        tagline: settings.tagline ?? '',
        public_url: settings.public_url ?? '',
        locale: settings.locale,
        timezone: settings.timezone,
        meta_title: settings.meta_title ?? '',
        meta_description: settings.meta_description ?? '',
        title_template: settings.title_template,
        is_indexable: settings.is_indexable,
        twitter_card: settings.twitter_card,
        organization_name: settings.organization_name ?? '',
        social_profiles: settings.social_profiles,
        logo: null as File | null,
        logo_dark: null as File | null,
        favicon: null as File | null,
        og_image: null as File | null,
        remove_logo: false,
        remove_logo_dark: false,
        remove_favicon: false,
        remove_og_image: false,
    })

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault()

        // Los archivos obligan a enviar el formulario como multipart, y ahí no
        // viaja PUT: se usa POST y Laravel resuelve la ruta igualmente.
        post(route('settings.update'), { forceFormData: true, preserveScroll: true })
    }

    function countErrors(fields: string[]): number {
        return Object.keys(errors).filter((key) =>
            fields.some((field) => key === field || key.startsWith(`${field}.`)),
        ).length
    }

    const tabs: TabDefinition[] = [
        { id: 'general', label: 'General', icon: 'ri-settings-3-line', errorCount: countErrors(TAB_FIELDS.general ?? []) },
        { id: 'seo', label: 'SEO', icon: 'ri-search-line', errorCount: countErrors(TAB_FIELDS.seo ?? []) },
        { id: 'social', label: 'Redes sociales', icon: 'ri-share-line', errorCount: countErrors(TAB_FIELDS.social ?? []) },
        ...(moduleSettings.length > 0
            ? [
                  {
                      id: 'modules',
                      label: 'Módulos',
                      icon: 'ri-puzzle-line',
                      errorCount: Object.keys(errors).filter((key) => key.startsWith('modules.')).length,
                  },
              ]
            : []),
    ]

    function setModuleValue(module: string, key: string, value: string | number | boolean) {
        setData('modules', {
            ...data.modules,
            [module]: { ...data.modules[module], [key]: value },
        })
    }

    function imageProps(key: ImageKey) {
        const removeKey = `remove_${key}` as const

        return {
            currentUrl: images[key],
            file: data[key],
            onSelect: (file: File | null) => setData(key, file),
            removed: data[removeKey],
            onRemovedChange: (removed: boolean) => setData(removeKey, removed),
            constraints: imageConstraints[key],
        }
    }

    function updateProfile(index: number, changes: Partial<SocialProfile>) {
        setData(
            'social_profiles',
            data.social_profiles.map((profile, current) =>
                current === index ? { ...profile, ...changes } : profile,
            ),
        )
    }

    function addProfile() {
        setData('social_profiles', [
            ...data.social_profiles,
            { network: networkOptions[0]?.value ?? 'facebook', url: '' },
        ])
    }

    function removeProfile(index: number) {
        setData(
            'social_profiles',
            data.social_profiles.filter((_, current) => current !== index),
        )
    }

    return (
        <DashboardLayout title="Configuración" breadcrumb="Administración">
            <div className="mx-auto w-full max-w-[960px]">
                <Card>
                    <form onSubmit={submit} noValidate encType="multipart/form-data">
                        {Object.keys(errors).length > 0 && (
                            <Alert variant="danger">
                                Revisa los campos marcados: hay datos que no son correctos. Las pestañas
                                con errores aparecen señaladas.
                            </Alert>
                        )}

                        <Tabs tabs={tabs} active={tab} onChange={setTab}>
                            {/* ------------------------------- General ---------------------------- */}
                            <TabPanel id="general" active={tab}>
                                <FormSection
                                    title="El sitio"
                                    description="Cómo se llama y dónde se publica."
                                >
                                    <div className="grid gap-5 md:grid-cols-2">
                                        <TextField
                                            label="Nombre del sitio"
                                            name="site_name"
                                            value={data.site_name}
                                            onChange={(event) => setData('site_name', event.target.value)}
                                            error={errors.site_name}
                                            maxLength={150}
                                            required
                                        />

                                        <TextField
                                            label="URL pública"
                                            name="public_url"
                                            value={data.public_url}
                                            onChange={(event) => setData('public_url', event.target.value)}
                                            error={errors.public_url}
                                            placeholder="https://www.miempresa.es"
                                            hint="La dirección de la web que consume esta información."
                                            maxLength={255}
                                        />
                                    </div>

                                    <div className="mt-5">
                                        <TextField
                                            label="Descripción breve"
                                            name="tagline"
                                            value={data.tagline}
                                            onChange={(event) => setData('tagline', event.target.value)}
                                            error={errors.tagline}
                                            maxLength={255}
                                            hint="Una línea que resuma de qué va. Sale en la cabecera de la web."
                                        />
                                    </div>
                                </FormSection>

                                <FormSection
                                    title="Idioma y hora"
                                    description="Afectan a las fechas y al orden en toda la instalación."
                                >
                                    <div className="grid gap-5 md:grid-cols-2">
                                        <SelectField
                                            label="Idioma"
                                            name="locale"
                                            value={data.locale}
                                            onChange={(event) => setData('locale', event.target.value)}
                                            options={localeOptions}
                                            error={errors.locale}
                                            required
                                        />

                                        <SelectField
                                            label="Zona horaria"
                                            name="timezone"
                                            value={data.timezone}
                                            onChange={(event) => setData('timezone', event.target.value)}
                                            options={timezoneOptions}
                                            error={errors.timezone}
                                            required
                                        />
                                    </div>
                                </FormSection>

                                <FormSection
                                    title="Marca"
                                    description="Se usan en el panel y en las páginas públicas."
                                >
                                    <div className="flex flex-col gap-6">
                                        <ImageField
                                            label="Logotipo"
                                            {...imageProps('logo')}
                                            error={errors.logo}
                                        />

                                        <ImageField
                                            label="Logotipo para fondo oscuro"
                                            {...imageProps('logo_dark')}
                                            error={errors.logo_dark}
                                            darkPreview
                                        />

                                        <ImageField
                                            label="Favicon"
                                            {...imageProps('favicon')}
                                            error={errors.favicon}
                                        />
                                    </div>
                                </FormSection>
                            </TabPanel>

                            {/* --------------------------------- SEO ------------------------------ */}
                            <TabPanel id="seo" active={tab}>
                                <FormSection
                                    title="Metadatos por defecto"
                                    description="Se aplican a cualquier página que no traiga los suyos propios."
                                >
                                    <div className="grid gap-5">
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
                                            label="Plantilla del título"
                                            name="title_template"
                                            value={data.title_template}
                                            onChange={(event) =>
                                                setData('title_template', event.target.value)
                                            }
                                            error={errors.title_template}
                                            maxLength={120}
                                            hint="Usa :title para el título de la página y :blog para el nombre del sitio."
                                            required
                                        />
                                    </div>
                                </FormSection>

                                <FormSection
                                    title="Indexación"
                                    description="Controla si los buscadores pueden mostrar el blog en sus resultados."
                                >
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
                                                Desactívalo mientras el blog esté en preparación. La API
                                                devolverá <code>noindex</code> en todas las páginas.
                                            </span>
                                        </span>
                                    </label>
                                </FormSection>

                                <FormSection
                                    title="Compartir en redes"
                                    description="Imagen y formato que se usan cuando alguien comparte un enlace."
                                >
                                    <div className="flex flex-col gap-5">
                                        <ImageField
                                            label="Imagen por defecto (Open Graph)"
                                            {...imageProps('og_image')}
                                            error={errors.og_image}
                                        />

                                        <div className="md:max-w-[320px]">
                                            <SelectField
                                                label="Formato de tarjeta"
                                                name="twitter_card"
                                                value={data.twitter_card}
                                                onChange={(event) =>
                                                    setData('twitter_card', event.target.value)
                                                }
                                                options={[
                                                    {
                                                        value: 'summary_large_image',
                                                        label: 'Imagen grande',
                                                    },
                                                    { value: 'summary', label: 'Resumen con miniatura' },
                                                ]}
                                                error={errors.twitter_card}
                                                required
                                            />
                                        </div>
                                    </div>
                                </FormSection>
                            </TabPanel>

                            {/* ---------------------------- Redes sociales ------------------------ */}
                            {moduleSettings.length > 0 && (
                                <TabPanel id="modules" active={tab}>
                                    {moduleSettings.map((section) => (
                                        <FormSection
                                            key={section.module}
                                            title={section.title}
                                            description="Lo que solo entiende este módulo."
                                        >
                                            <div className="grid gap-5 md:grid-cols-2">
                                                {section.fields.map((field) => {
                                                    const error = errors[
                                                        `modules.${section.module}.${field.key}` as keyof typeof errors
                                                    ] as string | undefined
                                                    const current = data.modules[section.module]?.[field.key]

                                                    if (field.type === 'boolean') {
                                                        return (
                                                            <div key={field.key}>
                                                                <Checkbox
                                                                    label={field.label}
                                                                    checked={Boolean(current)}
                                                                    onChange={(event) =>
                                                                        setModuleValue(
                                                                            section.module,
                                                                            field.key,
                                                                            event.target.checked,
                                                                        )
                                                                    }
                                                                />
                                                                {field.hint && (
                                                                    <p className="text-secondary-light mt-1 text-sm">
                                                                        {field.hint}
                                                                    </p>
                                                                )}
                                                            </div>
                                                        )
                                                    }

                                                    return (
                                                        <TextField
                                                            key={field.key}
                                                            label={field.label}
                                                            type={field.type === 'number' ? 'number' : 'text'}
                                                            value={current === null ? '' : String(current)}
                                                            onChange={(event) =>
                                                                setModuleValue(
                                                                    section.module,
                                                                    field.key,
                                                                    field.type === 'number'
                                                                        ? Number(event.target.value)
                                                                        : event.target.value,
                                                                )
                                                            }
                                                            error={error}
                                                            {...(field.hint ? { hint: field.hint } : {})}
                                                        />
                                                    )
                                                })}
                                            </div>
                                        </FormSection>
                                    ))}
                                </TabPanel>
                            )}

                            <TabPanel id="social" active={tab}>
                                <FormSection
                                    title="Organización"
                                    description="Se publica en los datos estructurados para que los buscadores identifiquen quién está detrás."
                                >
                                    <div className="md:max-w-[480px]">
                                        <TextField
                                            label="Nombre de la organización"
                                            name="organization_name"
                                            value={data.organization_name}
                                            onChange={(event) =>
                                                setData('organization_name', event.target.value)
                                            }
                                            error={errors.organization_name}
                                            maxLength={150}
                                            hint="Si lo dejas vacío se usa el nombre del sitio."
                                        />
                                    </div>
                                </FormSection>

                                <FormSection
                                    title="Perfiles"
                                    description="Enlaces oficiales de la organización en cada red."
                                >
                                    {data.social_profiles.length === 0 ? (
                                        <p className="text-secondary-light mb-0 text-sm">
                                            Todavía no has añadido ningún perfil.
                                        </p>
                                    ) : (
                                        <div className="flex flex-col gap-4">
                                            {data.social_profiles.map((profile, index) => (
                                                <div
                                                    key={index}
                                                    className="flex flex-wrap items-start gap-3"
                                                >
                                                    <div className="w-full sm:w-[180px]">
                                                        <SelectField
                                                            label="Red"
                                                            value={profile.network}
                                                            onChange={(event) =>
                                                                updateProfile(index, {
                                                                    network: event.target.value,
                                                                })
                                                            }
                                                            options={networkOptions}
                                                            error={
                                                                errors[
                                                                    `social_profiles.${index}.network` as keyof typeof errors
                                                                ]
                                                            }
                                                        />
                                                    </div>

                                                    <div className="min-w-[220px] flex-1">
                                                        <TextField
                                                            label="URL del perfil"
                                                            value={profile.url}
                                                            onChange={(event) =>
                                                                updateProfile(index, {
                                                                    url: event.target.value,
                                                                })
                                                            }
                                                            placeholder="https://www.instagram.com/miempresa"
                                                            maxLength={255}
                                                            error={
                                                                errors[
                                                                    `social_profiles.${index}.url` as keyof typeof errors
                                                                ]
                                                            }
                                                        />
                                                    </div>

                                                    <button
                                                        type="button"
                                                        onClick={() => removeProfile(index)}
                                                        className="mt-[34px] inline-flex h-[52px] w-[52px] items-center justify-center rounded-lg text-lg text-neutral-600 hover:bg-danger-50 hover:text-danger-600 dark:text-neutral-300"
                                                        aria-label={`Quitar el perfil de ${profile.network}`}
                                                    >
                                                        <i className="ri-delete-bin-line" aria-hidden="true" />
                                                    </button>
                                                </div>
                                            ))}
                                        </div>
                                    )}

                                    {data.social_profiles.length < 10 && (
                                        <button
                                            type="button"
                                            onClick={addProfile}
                                            className="btn btn-outline-primary mt-5 items-center gap-2 rounded-lg px-4 py-2 text-sm"
                                        >
                                            <i className="ri-add-line" aria-hidden="true" />
                                            Añadir perfil
                                        </button>
                                    )}
                                </FormSection>
                            </TabPanel>
                        </Tabs>

                        <div className="mt-8 flex flex-wrap items-center justify-end gap-3 border-t border-neutral-200 pt-6 dark:border-neutral-600">
                            {isDirty && (
                                <p className="text-secondary-light me-auto mb-0 text-sm">
                                    Tienes cambios sin guardar.
                                </p>
                            )}

                            <button
                                type="submit"
                                disabled={processing}
                                aria-busy={processing}
                                className="btn btn-primary rounded-lg px-5 py-2.5 text-sm disabled:cursor-not-allowed disabled:opacity-70"
                            >
                                {processing ? 'Guardando…' : 'Guardar configuración'}
                            </button>
                        </div>
                    </form>
                </Card>
            </div>
        </DashboardLayout>
    )
}
