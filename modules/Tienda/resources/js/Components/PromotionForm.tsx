import { useForm } from '@inertiajs/react'
import type { FormEvent } from 'react'
import type { SelectOption } from '@/types'
import Card from '@/Components/UI/Card'
import Checkbox from '@/Components/UI/Checkbox'
import FormSection from '@/Components/UI/FormSection'
import LinkButton from '@/Components/UI/LinkButton'
import MultiSelect from '@/Components/UI/MultiSelect'
import SelectField from '@/Components/UI/SelectField'
import SubmitButton from '@/Components/UI/SubmitButton'
import TextField from '@/Components/UI/TextField'

export interface PromotionFormValues {
    name: string
    description: string
    type: string
    value: string
    scope: string
    category_ids: number[]
    product_ids: number[]
    starts_at: string
    ends_at: string
    is_active: boolean
}

export interface PromotionFormOptions {
    typeOptions: SelectOption[]
    scopeOptions: SelectOption[]
    categoryOptions: SelectOption[]
    productOptions: SelectOption[]
    timezone: string
}

interface Props extends PromotionFormOptions {
    values: PromotionFormValues
    method: 'post' | 'put'
    submitUrl: string
    submitLabel: string
}

/**
 * Formulario de una promoción.
 *
 * Debajo del descuento se enseña un ejemplo con números, porque «20» no dice
 * si son euros o porcentaje hasta que se ve aplicado.
 */
export default function PromotionForm({
    values,
    typeOptions,
    scopeOptions,
    categoryOptions,
    productOptions,
    timezone,
    method,
    submitUrl,
    submitLabel,
}: Props) {
    const { data, setData, submit, processing, errors } = useForm(values)

    const amount = Number(data.value.replace(/\./g, '').replace(',', '.'))
    const example =
        amount > 0
            ? data.type === 'percentage'
                ? `Un artículo de 50,00 se quedará en ${(50 - (50 * Math.min(amount, 100)) / 100).toFixed(2).replace('.', ',')}.`
                : `Un artículo de 50,00 se quedará en ${Math.max(0, 50 - amount)
                      .toFixed(2)
                      .replace('.', ',')}.`
            : null

    function save(event: FormEvent) {
        event.preventDefault()
        submit(method, submitUrl, { preserveScroll: true })
    }

    return (
        <form onSubmit={save}>
            <Card>
                <div className="flex flex-col gap-6">
                    <FormSection title="La promoción" description="El nombre y el texto los verá la gente en la web.">
                        <div className="grid gap-4 md:grid-cols-2">
                            <TextField
                                label="Nombre"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                error={errors.name}
                                placeholder="Rebajas de verano"
                                required
                                autoFocus
                            />
                            <TextField
                                label="Texto para la web"
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                error={errors.description}
                                maxLength={255}
                                placeholder="Un 20 % en toda la ropa hasta el domingo"
                            />
                        </div>
                    </FormSection>

                    <FormSection title="El descuento">
                        <div className="grid gap-4 md:grid-cols-2">
                            <SelectField
                                label="Tipo"
                                value={data.type}
                                onChange={(e) => setData('type', e.target.value)}
                                error={errors.type}
                                options={typeOptions}
                            />
                            <TextField
                                label={data.type === 'percentage' ? 'Porcentaje' : 'Importe'}
                                value={data.value}
                                onChange={(e) => setData('value', e.target.value)}
                                error={errors.value}
                                inputMode="decimal"
                                required
                                hint={example ?? undefined}
                            />
                        </div>
                    </FormSection>

                    <FormSection
                        title="A qué se aplica"
                        description="Una categoría incluye sus subcategorías. Si a un artículo le tocan dos promociones, gana la que más descuenta."
                    >
                        <SelectField
                            label="Alcance"
                            value={data.scope}
                            onChange={(e) => setData('scope', e.target.value)}
                            error={errors.scope}
                            options={scopeOptions}
                        />

                        {data.scope === 'categories' && (
                            <div className="mt-4">
                                <MultiSelect
                                    label="Categorías"
                                    options={categoryOptions}
                                    value={data.category_ids}
                                    onChange={(ids) => setData('category_ids', ids)}
                                    error={errors.category_ids}
                                    emptyMessage="Todavía no hay categorías."
                                />
                            </div>
                        )}

                        {data.scope === 'products' && (
                            <div className="mt-4">
                                <MultiSelect
                                    label="Artículos"
                                    options={productOptions}
                                    value={data.product_ids}
                                    onChange={(ids) => setData('product_ids', ids)}
                                    error={errors.product_ids}
                                    searchThreshold={0}
                                    emptyMessage="Todavía no hay artículos."
                                />
                            </div>
                        )}
                    </FormSection>

                    <FormSection
                        title="Cuándo"
                        description={`Hora de ${timezone}. Sin inicio empieza ya; sin fin dura hasta que la desactives.`}
                    >
                        <div className="grid gap-4 md:grid-cols-2">
                            <TextField
                                label="Empieza"
                                type="datetime-local"
                                value={data.starts_at}
                                onChange={(e) => setData('starts_at', e.target.value)}
                                error={errors.starts_at}
                            />
                            <TextField
                                label="Termina"
                                type="datetime-local"
                                value={data.ends_at}
                                onChange={(e) => setData('ends_at', e.target.value)}
                                error={errors.ends_at}
                            />
                        </div>

                        <div className="mt-4">
                            <Checkbox
                                label="Activa"
                                checked={data.is_active}
                                onChange={(e) => setData('is_active', e.target.checked)}
                            />
                        </div>
                    </FormSection>

                    <div className="flex flex-wrap items-center gap-3">
                        <SubmitButton processing={processing} processingLabel="Guardando…">
                            {submitLabel}
                        </SubmitButton>
                        <LinkButton href={route('tienda.promotions.index')} variant="outline">
                            Cancelar
                        </LinkButton>
                    </div>
                </div>
            </Card>
        </form>
    )
}
