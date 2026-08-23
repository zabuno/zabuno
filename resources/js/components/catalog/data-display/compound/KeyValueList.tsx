import type { ReactNode } from 'react';
import { Fragment } from 'react';
import clsx from 'clsx';
import { Divider } from '../micro/Divider';

export type KeyValueEntry = {
    key: string;
    label: string;
    value: ReactNode;
};

export type KeyValueListProps = {
    entries: readonly KeyValueEntry[];
    className?: string;
};

/**
 * Compound: composes Micro/Data Display/Divider between rows instead of
 * reimplementing a border-bottom rule.
 */
export function KeyValueList({ entries, className }: KeyValueListProps) {
    return (
        <dl className={clsx('flex flex-col', className)}>
            {entries.map((entry, index) => (
                <Fragment key={entry.key}>
                    {index > 0 ? <Divider /> : null}
                    <div className="flex items-baseline justify-between gap-4 py-2">
                        <dt className="text-sm text-gray-500 dark:text-gray-400">{entry.label}</dt>
                        <dd className="text-sm font-medium text-gray-900 dark:text-gray-100">
                            {entry.value}
                        </dd>
                    </div>
                </Fragment>
            ))}
        </dl>
    );
}
