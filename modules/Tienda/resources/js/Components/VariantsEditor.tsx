import { useState } from 'react'
import FieldError from '@/Components/UI/FieldError'
import type { GalleryImage, ProductOptionValues, VariantRow } from '../types'

interface Props {
    options: ProductOptionValues[]
    variants: VariantRow[]
    onChange: (options: ProductOptionValues[], variants: VariantRow[]) => void
    /** Fotos de la galería, para elegir la de cada variante. */
    images: GalleryImage[]
    errors: Record<string, string | undefined>
    /** Referencia del artículo, para proponer la de cada variante. */
    baseSku: string
    currency: string
    canManageStock: boolean
}

const MAX_OPTIONS = 3

/**
 * Opciones (talla, color…) y la tabla de combinaciones.
 *
 * Se escriben las opciones y se pulsa «Generar combinaciones». Las que ya
 * existían se conservan con su stock, su precio y su referencia: añadir una
 * talla nueva no obliga a volver a rellenar las demás.
 */
export default function VariantsEditor({
    options,
    variants,
    onChange,
    images,
    errors,
    baseSku,
    currency,
    canManageStock,
}: Props) {
    // Los valores se editan como texto con comas; se convierten en lista al
    // generar. Así se puede escribir «S, M,» sin que la coma desaparezca.
    const [texts, setTexts] = useState<string[]>(options.map((option) => option.values.join(', ')))
    const [bulkPrice, setBulkPrice] = useState('')
    const [bulkStock, setBulkStock] = useState('')

    const parsed: ProductOptionValues[] = options.map((option, index) => ({
        name: option.name,
        values: split(texts[index] ?? ''),
    }))

    const combinations = parsed.reduce((total, option) => total * Math.max(option.values.length, 1), 1)
    const outOfSync = options.length > 0 && !sameShape(parsed, variants)

    function setOptionName(index: number, name: string) {
        onChange(
            options.map((option, current) => (current === index ? { ...option, name } : option)),
            variants,
        )
    }

    function setOptionText(index: number, text: string) {
        const nextTexts = texts.map((current, position) => (position === index ? text : current))
        setTexts(nextTexts)
        onChange(
            options.map((option, current) => (current === index ? { ...option, values: split(text) } : option)),
            variants,
        )
    }

    function addOption() {
        const suggestions = ['Talla', 'Color', 'Material']
        const name = suggestions.find((s) => !options.some((o) => o.name === s)) ?? ''

        setTexts([...texts, ''])
        onChange([...options, { name, values: [] }], variants)
    }

    function removeOption(index: number) {
        const nextOptions = options.filter((_, current) => current !== index)
        setTexts(texts.filter((_, current) => current !== index))
        // Sin opciones no hay variantes; con menos, hay que regenerarlas.
        onChange(nextOptions, nextOptions.length === 0 ? [] : variants)
    }

    function generate() {
        const lists = parsed.map((option) => option.values).filter((values) => values.length > 0)

        if (lists.length !== parsed.length) {
            return
        }

        const existing = new Map(variants.map((variant) => [key(variant.values), variant]))

        const rows = cartesian(lists).map((values): VariantRow => {
            const previous = existing.get(key(values))

            return (
                previous ?? {
                    id: null,
                    values,
                    sku: baseSku ? [baseSku, ...values].join('-').toUpperCase().replace(/\s+/g, '') : '',
                    price: '',
                    stock: 0,
                    stock_loaded: null,
                    is_active: true,
                    media_id: null,
                }
            )
        })

        onChange(parsed, rows)
    }

    function updateVariant(index: number, changes: Partial<VariantRow>) {
        onChange(
            options,
            variants.map((variant, current) => (current === index ? { ...variant, ...changes } : variant)),
        )
    }

    function applyToAll() {
        onChange(
            options,
            variants.map((variant) => ({
                ...variant,
                ...(bulkPrice !== '' ? { price: bulkPrice } : {}),
                ...(bulkStock !== '' && canManageStock ? { stock: Math.max(0, Number(bulkStock)) } : {}),
            })),
        )
        setBulkPrice('')
        setBulkStock('')
    }

    return (
        <div className="flex flex-col gap-6">
            {options.length === 0 ? (
                <p className="text-secondary-light mb-0 text-sm">
                    Este artículo no tiene variantes: se vende tal cual y su stock es uno solo. Si viene en varias
                    tallas, colores o materiales, añade una opción.
                </p>
            ) : (
                <div className="flex flex-col gap-3">
                    {options.map((option, index) => (
                        <div key={index} className="grid items-start gap-3 md:grid-cols-[200px_1fr_auto]">
                            <div>
                                <label className="form-label" htmlFor={`option-name-${index}`}>
                                    Opción {index + 1}
                                </label>
                                <input
                                    id={`option-name-${index}`}
                                    className="form-control h-[52px] rounded-lg"
                                    value={option.name}
                                    placeholder="Talla"
                                    maxLength={60}
                                    onChange={(e) => setOptionName(index, e.target.value)}
                                />
                                <FieldError
                                    id={`option-name-error-${index}`}
                                    message={errors[`options.${index}.name`]}
                                />
                            </div>

                            <div>
                                <label className="form-label" htmlFor={`option-values-${index}`}>
                                    Valores, separados por comas
                                </label>
                                <input
                                    id={`option-values-${index}`}
                                    className="form-control h-[52px] rounded-lg"
                                    value={texts[index] ?? ''}
                                    placeholder="S, M, L, XL"
                                    onChange={(e) => setOptionText(index, e.target.value)}
                                />
                                <FieldError
                                    id={`option-values-error-${index}`}
                                    message={errors[`options.${index}.values`]}
                                />
                            </div>

                            <button
                                type="button"
                                className="mt-7 inline-flex h-[52px] w-[52px] items-center justify-center rounded-lg text-neutral-600 hover:bg-danger-50 hover:text-danger-600 dark:text-neutral-200"
                                onClick={() => removeOption(index)}
                                aria-label={`Quitar la opción ${option.name || index + 1}`}
                            >
                                <i className="ri-delete-bin-line" aria-hidden="true" />
                            </button>
                        </div>
                    ))}
                </div>
            )}

            <FieldError id="options-error" message={errors.options} />

            <div className="flex flex-wrap items-center gap-3">
                {options.length < MAX_OPTIONS && (
                    <button
                        type="button"
                        className="btn btn-outline-primary rounded-lg px-4 py-2 text-sm"
                        onClick={addOption}
                    >
                        <i className="ri-add-line me-1" aria-hidden="true" />
                        Añadir opción
                    </button>
                )}

                {options.length > 0 && (
                    <button
                        type="button"
                        className="btn btn-primary rounded-lg px-4 py-2 text-sm"
                        onClick={generate}
                        disabled={parsed.some((option) => option.values.length === 0)}
                    >
                        <i className="ri-shuffle-line me-1" aria-hidden="true" />
                        Generar combinaciones ({combinations})
                    </button>
                )}
            </div>

            {outOfSync && (
                <p className="mb-0 rounded-lg bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:bg-warning-600/25 dark:text-warning-400">
                    Las opciones han cambiado. Pulsa «Generar combinaciones» para poner la tabla al día; las variantes
                    que ya existían conservan su stock y su precio.
                </p>
            )}

            <FieldError id="variants-error" message={errors.variants} />

            {variants.length > 0 && (
                <>
                    <div className="flex flex-wrap items-end gap-3 rounded-lg bg-neutral-50 p-3 dark:bg-neutral-700">
                        <span className="text-secondary-light w-full text-sm">Aplicar a todas las variantes:</span>
                        <div className="w-36">
                            <label className="form-label text-xs" htmlFor="bulk-price">
                                Precio ({currency})
                            </label>
                            <input
                                id="bulk-price"
                                className="form-control h-10 rounded-lg"
                                inputMode="decimal"
                                value={bulkPrice}
                                onChange={(e) => setBulkPrice(e.target.value)}
                            />
                        </div>
                        {canManageStock && (
                            <div className="w-28">
                                <label className="form-label text-xs" htmlFor="bulk-stock">
                                    Unidades
                                </label>
                                <input
                                    id="bulk-stock"
                                    type="number"
                                    min={0}
                                    className="form-control h-10 rounded-lg"
                                    value={bulkStock}
                                    onChange={(e) => setBulkStock(e.target.value)}
                                />
                            </div>
                        )}
                        <button
                            type="button"
                            className="btn btn-outline-primary h-10 rounded-lg px-4 text-sm"
                            onClick={applyToAll}
                            disabled={bulkPrice === '' && bulkStock === ''}
                        >
                            Aplicar
                        </button>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="style-three table w-full text-sm">
                            <thead>
                                <tr>
                                    <th className="text-start">Variante</th>
                                    <th className="text-start">Referencia</th>
                                    <th className="text-start">Precio</th>
                                    <th className="text-start">Unidades</th>
                                    {images.length > 0 && <th className="text-start">Foto</th>}
                                    <th className="text-start">Activa</th>
                                </tr>
                            </thead>
                            <tbody>
                                {variants.map((variant, index) => {
                                    const rowError =
                                        errors[`variants.${index}.values`] ??
                                        errors[`variants.${index}.sku`] ??
                                        errors[`variants.${index}.price`] ??
                                        errors[`variants.${index}.stock`]
                                    const label = variant.values.join(' / ')
                                    const image = images.find((item) => item.media_id === variant.media_id)

                                    return (
                                        <tr key={key(variant.values)} className={variant.is_active ? '' : 'opacity-50'}>
                                            <td className="whitespace-nowrap font-medium">
                                                {label}
                                                <FieldError id={`variant-error-${index}`} message={rowError} />
                                            </td>
                                            <td>
                                                <input
                                                    aria-label={`Referencia de ${label}`}
                                                    className="form-control h-10 min-w-32 rounded-lg"
                                                    value={variant.sku}
                                                    maxLength={60}
                                                    onChange={(e) =>
                                                        updateVariant(index, {
                                                            sku: e.target.value,
                                                        })
                                                    }
                                                />
                                            </td>
                                            <td>
                                                <input
                                                    aria-label={`Precio de ${label}`}
                                                    className="form-control h-10 w-28 rounded-lg"
                                                    inputMode="decimal"
                                                    placeholder="El del artículo"
                                                    value={variant.price}
                                                    onChange={(e) =>
                                                        updateVariant(index, {
                                                            price: e.target.value,
                                                        })
                                                    }
                                                />
                                            </td>
                                            <td>
                                                <input
                                                    aria-label={`Unidades de ${label}`}
                                                    type="number"
                                                    min={0}
                                                    className="form-control h-10 w-24 rounded-lg"
                                                    value={variant.stock}
                                                    disabled={!canManageStock}
                                                    onChange={(e) =>
                                                        updateVariant(index, {
                                                            stock: Math.max(0, Number(e.target.value)),
                                                        })
                                                    }
                                                />
                                            </td>
                                            {images.length > 0 && (
                                                <td>
                                                    <div className="flex items-center gap-2">
                                                        <span className="h-10 w-10 shrink-0 overflow-hidden rounded-lg bg-neutral-100 dark:bg-neutral-700">
                                                            {image?.url && (
                                                                <img
                                                                    src={image.url}
                                                                    alt=""
                                                                    className="h-full w-full object-cover"
                                                                />
                                                            )}
                                                        </span>
                                                        <select
                                                            aria-label={`Foto de ${label}`}
                                                            className="form-select h-10 rounded-lg"
                                                            value={variant.media_id ?? ''}
                                                            onChange={(e) =>
                                                                updateVariant(index, {
                                                                    media_id:
                                                                        e.target.value === ''
                                                                            ? null
                                                                            : Number(e.target.value),
                                                                })
                                                            }
                                                        >
                                                            <option value="">La del artículo</option>
                                                            {images.map((item, position) => (
                                                                <option key={item.media_id} value={item.media_id}>
                                                                    Foto {position + 1}
                                                                </option>
                                                            ))}
                                                        </select>
                                                    </div>
                                                </td>
                                            )}
                                            <td>
                                                <input
                                                    type="checkbox"
                                                    aria-label={`${label} a la venta`}
                                                    className="form-check-input border border-neutral-300"
                                                    checked={variant.is_active}
                                                    onChange={(e) =>
                                                        updateVariant(index, {
                                                            is_active: e.target.checked,
                                                        })
                                                    }
                                                />
                                            </td>
                                        </tr>
                                    )
                                })}
                            </tbody>
                        </table>
                    </div>

                    <p className="text-secondary-light mb-0 text-sm">
                        Deja el precio vacío para usar el del artículo. Una variante desactivada no se ofrece en la web
                        pero conserva su historial.
                        {!canManageStock && ' Las unidades solo las cambia quien gestiona el inventario.'}
                    </p>
                </>
            )}
        </div>
    )
}

function split(text: string): string[] {
    const values = text
        .split(',')
        .map((value) => value.trim())
        .filter((value) => value !== '')

    return Array.from(new Set(values))
}

function key(values: string[]): string {
    return values.map((value) => value.trim().toLowerCase()).join('\u001f')
}

function cartesian(lists: string[][]): string[][] {
    return lists.reduce<string[][]>(
        (rows, values) => rows.flatMap((row) => values.map((value) => [...row, value])),
        [[]],
    )
}

/**
 * ¿La tabla corresponde a las opciones escritas? Si alguien ha añadido una
 * talla y no ha regenerado, se avisa antes de que el servidor lo rechace.
 */
function sameShape(options: ProductOptionValues[], variants: VariantRow[]): boolean {
    if (variants.length === 0) {
        return false
    }

    const expected = new Set(cartesian(options.map((option) => option.values)).map(key))
    const actual = new Set(variants.map((variant) => key(variant.values)))

    return expected.size === actual.size && [...expected].every((value) => actual.has(value))
}
