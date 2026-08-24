import { useId } from 'react';
import { Label } from './Label';
import { Checkbox, type CheckboxProps } from './Checkbox';
import { HelperText } from 'flowbite-react';

export type CheckboxFieldProps = CheckboxProps & {
    label: string;
    helpText?: string;
    errorText?: string;
};

/**
 * Compound: composes Micro/Forms/Checkbox + Micro/Forms/Label + optional
 * help/error text. Does not reimplement either micro's markup or
 * accessibility behaviour — it only wires label/aria-describedby/
 * aria-invalid around them.
 */
export function CheckboxField({
    label,
    helpText,
    errorText,
    id,
    ...checkboxProps
}: CheckboxFieldProps) {
    const generatedId = useId();
    const fieldId = id ?? generatedId;
    const helpId = helpText ? `${fieldId}-help` : undefined;
    const errorId = errorText ? `${fieldId}-error` : undefined;
    const describedBy = [helpId, errorId].filter(Boolean).join(' ') || undefined;

    return (
        <div className="flex flex-col gap-1">
            <div className="flex items-center gap-2">
                <Checkbox
                    id={fieldId}
                    invalid={Boolean(errorText)}
                    aria-describedby={describedBy}
                    {...checkboxProps}
                />
                <Label htmlFor={fieldId}>{label}</Label>
            </div>
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
