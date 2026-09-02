import { useEffect, useRef, type ReactNode } from 'react'
import IconClose from '~icons/radix-icons/cross-1'

interface ModalProps {
    open: boolean
    onClose: () => void
    title: string
    children: ReactNode
    /** Botones de acción del pie. */
    footer?: ReactNode
}

/**
 * Diálogo modal accesible, con el aspecto de los modales de la plantilla.
 * Se implementa con `<dialog>` nativo para heredar el foco atrapado y el
 * cierre con Escape del navegador.
 */
export default function Modal({ open, onClose, title, children, footer }: ModalProps) {
    const dialogRef = useRef<HTMLDialogElement>(null)

    useEffect(() => {
        const dialog = dialogRef.current

        if (!dialog) {
            return
        }

        if (open && !dialog.open) {
            dialog.showModal()
        } else if (!open && dialog.open) {
            dialog.close()
        }
    }, [open])

    return (
        <dialog
            ref={dialogRef}
            onCancel={(event) => {
                event.preventDefault()
                onClose()
            }}
            onClick={(event) => {
                // Cierra al pulsar sobre el fondo, no sobre el contenido.
                if (event.target === dialogRef.current) {
                    onClose()
                }
            }}
            aria-labelledby="modal-title"
            className="w-full max-w-[496px] rounded-2xl bg-white p-0 backdrop:bg-neutral-900/50 dark:bg-neutral-700"
        >
            <div className="relative p-6">
                <button
                    type="button"
                    onClick={onClose}
                    className="absolute end-4 top-4 inline-flex h-8 w-8 items-center justify-center rounded-lg text-neutral-400 hover:bg-neutral-200 hover:text-neutral-900 dark:hover:bg-neutral-600 dark:hover:text-white"
                    aria-label="Cerrar"
                >
                    <IconClose />
                </button>

                <h6 id="modal-title" className="mb-3 pe-8 text-lg font-semibold">
                    {title}
                </h6>

                <div className="text-secondary-light text-sm">{children}</div>

                {footer && <div className="mt-6 flex flex-wrap justify-end gap-3">{footer}</div>}
            </div>
        </dialog>
    )
}
