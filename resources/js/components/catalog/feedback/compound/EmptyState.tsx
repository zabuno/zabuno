import type { ReactNode } from 'react';
import clsx from 'clsx';
import { Spinner } from '../micro/Spinner';

export type EmptyStateProps = {
    title: string;
    description?: string;
    /** Renders the Micro/Feedback/Spinner instead of the empty glyph, for a "checking" state before we know if the list is truly empty. */
    loading?: boolean;
    action?: ReactNode;
    className?: string;
};

/**
 * Compound: composes Micro/Feedback/Spinner for its loading variant.
 * Does not reimplement Spinner's markup or its live-region wiring.
 */
export function EmptyState({
    title,
    description,
    loading = false,
    action,
    className,
}: EmptyStateProps) {
    return (
        <div
            className={clsx(
                'flex flex-col items-center gap-3 rounded-lg border border-dashed border-gray-300 p-8 text-center',
                'dark:border-gray-700',
                className,
            )}
        >
            {loading ? (
                <Spinner label={`${title} — loading…`} />
            ) : (
                <div
                    aria-hidden="true"
                    className="h-8 w-8 rounded-full bg-gray-200 dark:bg-gray-700"
                />
            )}
            <p className="text-sm font-medium text-gray-900 dark:text-gray-100">{title}</p>
            {description ? (
                <p className="text-sm text-gray-500 dark:text-gray-400">{description}</p>
            ) : null}
            {action ? <div className="mt-1">{action}</div> : null}
        </div>
    );
}
