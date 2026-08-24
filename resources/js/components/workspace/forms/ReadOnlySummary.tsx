import type { ReactNode } from 'react';

export type ReadOnlySummaryItem = {
    key: string;
    label: string;
    value: ReactNode;
};

type ReadOnlySummaryProps = {
    title: ReactNode;
    items: ReadOnlySummaryItem[];
    actions?: ReactNode;
};

/**
 * Compound: a title/actions row above a definition-list grid of label/value
 * pairs. Fluid via auto-fit/minmax — one column at 320px, more as space
 * allows. Reused by the Brand and Location read-only (non-editing) views so
 * their already-existing fields render as one recognizable pattern.
 */
export function ReadOnlySummary({ title, items, actions }: ReadOnlySummaryProps) {
    return (
        <div className="flex flex-col gap-2">
            <div className="flex items-center justify-between gap-4">
                <p className="text-base font-medium text-gray-900 dark:text-white">{title}</p>
                {actions}
            </div>
            <dl className="grid grid-cols-[repeat(auto-fit,minmax(min(100%,12rem),1fr))] gap-x-4 gap-y-2 text-sm text-gray-700 dark:text-gray-300">
                {items.map((item) => (
                    <div key={item.key}>
                        <dt className="font-medium">{item.label}</dt>
                        <dd>{item.value}</dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}

export default ReadOnlySummary;
