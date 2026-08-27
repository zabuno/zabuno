import type { FormEvent, ReactNode } from 'react';
import { TextField, type TextFieldProps } from '../compound/TextField';

export type DemoFormCardField = Pick<
    TextFieldProps,
    'label' | 'helpText' | 'errorText' | 'name' | 'defaultValue' | 'placeholder'
> & { id: string };

export type DemoFormCardProps = {
    title: string;
    description?: string;
    fields: DemoFormCardField[];
    submitLabel?: string;
    onSubmit?: (event: FormEvent<HTMLFormElement>) => void;
    footer?: ReactNode;
};

/**
 * Macro: composes Compound/Form/TextField instances (each of which composes
 * Micro/Input) into a card-shaped form surface. Renders only the data/
 * callbacks passed in — no fetch, no route knowledge, no business rule.
 */
export function DemoFormCard({
    title,
    description,
    fields,
    submitLabel = 'Save',
    onSubmit,
    footer,
}: DemoFormCardProps) {
    return (
        <div className="w-full max-w-md rounded-lg border border-border p-6 shadow-sm">
            <h2 className="text-base font-semibold text-fg">{title}</h2>
            {description ? <p className="mt-1 text-body text-fg-muted">{description}</p> : null}
            <form
                className="mt-4 flex flex-col gap-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    onSubmit?.(event);
                }}
            >
                {fields.map(({ id, ...field }) => (
                    <TextField key={id} id={id} {...field} />
                ))}
                <button
                    type="submit"
                    className="min-h-[var(--density-hit-area-min)] self-start rounded-md bg-action px-4 py-2 text-body font-medium text-action-fg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-focus"
                >
                    {submitLabel}
                </button>
            </form>
            {footer}
        </div>
    );
}
