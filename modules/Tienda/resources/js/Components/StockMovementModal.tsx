import { useForm } from '@inertiajs/react'
import type { FormEvent } from 'react'
import type { SelectOption } from '@/types'
import Modal from '@/Components/UI/Modal'
import SelectField from '@/Components/UI/SelectField'
import SubmitButton from '@/Components/UI/SubmitButton'
import TextField from '@/Components/UI/TextField'

export interface StockTarget {
    productId: number
    variantId: number | null
    /** «Camiseta (M / Rojo)», para el título. */
    label: string
    stock: number
}

interface Props {
    target: StockTarget | null
    typeOptions: SelectOption[]
    onClose: () => void
}

/**
 * Registrar una entrada, una venta, una devolución, una merma o un recuento.
 *
 * Enseña cuántas quedarán antes de guardar: la mayoría de errores de stock son
 * un número tecleado de más, y verlo aplicado los delata.
 */
export default function StockMovementModal({ target, typeOptions, onClose }: Props) {
    const { data, setData, post, transform, processing, errors, reset, clearErrors } = useForm({
        type: 'sale',
        quantity: '',
        note: '',
    })

    const quantity = Math.max(0, Number(data.quantity) || 0)
    const direction = data.type === 'entry' || data.type === 'return' ? 1 : data.type === 'count' ? 0 : -1
    const after = target === null ? 0 : direction === 0 ? quantity : target.stock + direction * quantity

    function close() {
        reset()
        clearErrors()
        onClose()
    }

    function save(event: FormEvent) {
        event.preventDefault()

        if (!target) {
            return
        }

        transform((current) => ({ ...current, variant_id: target.variantId }))
        post(route('tienda.stock.store', target.productId), {
            preserveScroll: true,
            onSuccess: close,
        })
    }

    return (
        <Modal open={target !== null} onClose={close} title={target ? `Existencias de ${target.label}` : ''}>
            <form onSubmit={save} className="flex flex-col gap-4 text-start">
                <p className="mb-0">
                    Ahora hay <strong className="text-neutral-900 dark:text-white">{target?.stock ?? 0}</strong>{' '}
                    unidades.
                </p>

                <SelectField
                    label="Qué ha pasado"
                    value={data.type}
                    onChange={(e) => setData('type', e.target.value)}
                    options={typeOptions}
                    error={errors.type}
                />

                <TextField
                    label={data.type === 'count' ? 'Unidades que hay de verdad' : 'Unidades'}
                    type="number"
                    min={data.type === 'count' ? 0 : 1}
                    value={data.quantity}
                    onChange={(e) => setData('quantity', e.target.value)}
                    error={errors.quantity ?? (errors as Record<string, string | undefined>).variant_id}
                    required
                    autoFocus
                    hint={
                        data.quantity === '' ? undefined : (
                            <span className={after < 0 ? 'text-danger-600' : ''}>Quedarán {after} unidades.</span>
                        )
                    }
                />

                <TextField
                    label="Nota"
                    value={data.note}
                    onChange={(e) => setData('note', e.target.value)}
                    error={errors.note}
                    maxLength={255}
                    placeholder="Pedido de Laura por WhatsApp, albarán 1234…"
                />

                <div className="flex flex-wrap justify-end gap-3">
                    <button type="button" className="btn btn-outline-primary" onClick={close}>
                        Cancelar
                    </button>
                    <SubmitButton processing={processing} processingLabel="Guardando…" disabled={after < 0}>
                        Registrar
                    </SubmitButton>
                </div>
            </form>
        </Modal>
    )
}
