import Modal from './Modal'

interface ConfirmDialogProps {
    open: boolean
    title: string
    message: string
    confirmLabel?: string
    cancelLabel?: string
    /** `danger` para acciones destructivas. */
    tone?: 'danger' | 'primary'
    processing?: boolean
    onConfirm: () => void
    onCancel: () => void
}

/**
 * Confirmación previa a una acción sensible (papelera, restauración…).
 */
export default function ConfirmDialog({
    open,
    title,
    message,
    confirmLabel = 'Confirmar',
    cancelLabel = 'Cancelar',
    tone = 'danger',
    processing = false,
    onConfirm,
    onCancel,
}: ConfirmDialogProps) {
    const confirmClasses =
        tone === 'danger'
            ? 'bg-danger-600 text-white hover:bg-danger-700'
            : 'bg-primary-600 text-white hover:bg-primary-700'

    return (
        <Modal
            open={open}
            onClose={onCancel}
            title={title}
            footer={
                <>
                    <button
                        type="button"
                        onClick={onCancel}
                        disabled={processing}
                        className="btn rounded-lg border border-neutral-300 px-5 py-2.5 text-sm font-medium text-neutral-600 hover:bg-neutral-100 disabled:opacity-60 dark:border-neutral-500 dark:text-neutral-200 dark:hover:bg-neutral-600"
                    >
                        {cancelLabel}
                    </button>

                    <button
                        type="button"
                        onClick={onConfirm}
                        disabled={processing}
                        aria-busy={processing}
                        className={`btn rounded-lg px-5 py-2.5 text-sm font-medium disabled:opacity-60 ${confirmClasses}`}
                    >
                        {processing ? 'Procesando…' : confirmLabel}
                    </button>
                </>
            }
        >
            {message}
        </Modal>
    )
}
