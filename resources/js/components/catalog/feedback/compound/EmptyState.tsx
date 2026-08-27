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
                'flex flex-col items-center gap-3 rounded-lg border border-dashed border-border p-8 text-center',
                className,
            )}
        >
            {loading ? (
                <Spinner label={`${title} — loading…`} />
            ) : (
                <div aria-hidden="true" className="h-8 w-8 rounded-pill bg-surface-active" />
            )}
            <p className="text-body font-medium text-fg">{title}</p>
            {description ? <p className="text-body text-fg-muted">{description}</p> : null}
            {action ? <div className="mt-1">{action}</div> : null}
        </div>
    );
}
