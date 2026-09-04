import type { ReactNode } from 'react';

type FormSectionProps = {
    title: string;
    children: ReactNode;
};

/**
 * Compound: groups related fields under a heading in a fluid grid that
 * starts as one column at 320px and grows via auto-fit/minmax — no
 * breakpoint classes. Reused by Brand and Location edit/onboarding forms to
 * make field grouping visible instead of one long flat list.
 */
export function FormSection({ title, children }: FormSectionProps) {
    return (
        <fieldset className="flex flex-col gap-3 border-0 p-0 m-0">
            <legend className="mb-1 text-body font-bold text-fg">{title}</legend>
            <div
                className="grid gap-4"
                style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 14rem), 1fr))' }}
            >
                {children}
            </div>
        </fieldset>
    );
}

export default FormSection;
