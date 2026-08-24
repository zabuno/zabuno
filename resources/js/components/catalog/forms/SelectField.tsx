import { useId } from 'react';
import { Label } from './Label';
import { Select, type SelectProps } from './Select';
import { HelperText } from 'flowbite-react';

export type SelectFieldProps = SelectProps & {
    label: string;
    helpText?: string;
    errorText?: string;
    required?: boolean;
};

/**
 * Compound: composes Micro/Forms/Label + Micro/Forms/Select + help/error
 * text. Does not reimplement either micro's markup or accessibility
 * behaviour — it only wires label/aria-describedby/aria-invalid around them.
 */
export function SelectField({
    label,
    helpText,
    errorText,
    required = false,
    id,
    ...selectProps
}: SelectFieldProps) {
    const generatedId = useId();
    const fieldId = id ?? generatedId;
    const helpId = helpText ? `${fieldId}-help` : undefined;
    const errorId = errorText ? `${fieldId}-error` : undefined;
    const describedBy = [helpId, errorId].filter(Boolean).join(' ') || undefined;

    return (
        <div className="flex flex-col gap-1">
            <Label htmlFor={fieldId} required={required}>
                {label}
            </Label>
            <Select
                id={fieldId}
                invalid={Boolean(errorText)}
                aria-required={required || undefined}
                aria-describedby={describedBy}
                {...selectProps}
            />
            {helpText ? (
                <HelperText id={helpId} color="gray">
                    {helpText}
                </HelperText>
            ) : null}
            {errorText ? (
                <HelperText id={errorId} color="failure" role="alert" aria-live="polite">
                    {errorText}
                </HelperText>
            ) : null}
        </div>
    );
}
