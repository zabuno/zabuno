import { useId } from 'react';
import { Input, type InputProps } from '../micro/Input';

export type TextFieldProps = InputProps & {
    label: string;
    helpText?: string;
    errorText?: string;
};

/**
 * Compound: composes the Micro/Input with a label, optional help text, and
 * optional error text. Does not reimplement Input's markup or a11y — it
 * only wires label/aria-describedby/aria-invalid around it.
 */
export function TextField({ label, helpText, errorText, id, ...inputProps }: TextFieldProps) {
    const generatedId = useId();
    const fieldId = id ?? generatedId;
    const helpId = helpText ? `${fieldId}-help` : undefined;
    const errorId = errorText ? `${fieldId}-error` : undefined;
    const describedBy = [helpId, errorId].filter(Boolean).join(' ') || undefined;

    return (
        <div className="flex flex-col gap-1">
            <label htmlFor={fieldId} className="text-sm font-medium text-gray-900">
                {label}
            </label>
            <Input
                id={fieldId}
                invalid={Boolean(errorText)}
                aria-describedby={describedBy}
                {...inputProps}
            />
            {helpText ? (
                <p id={helpId} className="text-xs text-gray-500">
                    {helpText}
                </p>
            ) : null}
            {errorText ? (
                <p id={errorId} role="alert" aria-live="polite" className="text-xs text-red-600">
                    {errorText}
                </p>
            ) : null}
        </div>
    );
}
