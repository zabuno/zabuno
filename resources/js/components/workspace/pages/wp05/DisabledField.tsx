import { Label, TextInput } from 'flowbite-react';

type DisabledFieldProps = {
    id: string;
    label: string;
};

/**
 * Micro: one disabled, empty labeled field. Used for the canonical Stage 1
 * manual-payment fields (plan assignment, end date, payment note, document
 * reference) before a real billing API exists — never pre-filled with an
 * invented value.
 */
export function DisabledField({ id, label }: DisabledFieldProps) {
    return (
        <div>
            <div className="mb-2 block">
                <Label htmlFor={id}>{label}</Label>
            </div>
            <TextInput id={id} value="" disabled readOnly />
        </div>
    );
}

export default DisabledField;
