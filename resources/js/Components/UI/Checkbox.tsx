import { useId, type InputHTMLAttributes } from 'react'

interface CheckboxProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'className' | 'id' | 'type'> {
    label: string
}

export default function Checkbox({ label, ...props }: CheckboxProps) {
    const id = useId()

    return (
        <div className="flex items-center">
            <input {...props} id={id} type="checkbox" className="form-check-input border border-neutral-300" />
            <label className="ps-2" htmlFor={id}>
                {label}
            </label>
        </div>
    )
}
