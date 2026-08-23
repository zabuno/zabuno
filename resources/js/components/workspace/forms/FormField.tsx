import { Label, TextInput } from 'flowbite-react';

type FormFieldProps = {
    id: string;
    label: string;
    name: string;
    value: string;
    onChange?: (value: string) => void;
    disabled?: boolean;
    readOnly?: boolean;
    type?: string;
};

/**
 * Micro: one labeled TextInput. Owns no fetch/route/business logic — value
 * and onChange come from the calling form, so it stays a pure presentation
 * building block reusable across Brand/Location edit and onboarding forms.
 */
export function FormField({
    id,
    label,
    name,
    value,
    onChange,
    disabled = false,
    readOnly = false,
    type = 'text',
}: FormFieldProps) {
    return (
        <div>
            <div className="mb-2 block">
                <Label htmlFor={id}>{label}</Label>
            </div>
            <TextInput
                id={id}
                name={name}
                type={type}
                className="w-full"
                value={value}
                disabled={disabled}
                readOnly={readOnly}
                onChange={onChange ? (event) => onChange(event.target.value) : undefined}
            />
        </div>
    );
}

export default FormField;
