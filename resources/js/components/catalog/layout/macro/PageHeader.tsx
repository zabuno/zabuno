import type { ReactNode } from 'react';
import clsx from 'clsx';
import { Breadcrumbs, type BreadcrumbItem } from '../../navigation/compound/Breadcrumbs';

export type PageHeaderProps = {
    title: string;
    /** Optional trail rendered above the title; omit to hide. */
    breadcrumbs?: BreadcrumbItem[];
    description?: ReactNode;
    /** Slot for primary/secondary page actions (buttons), rendered end-aligned. */
    actions?: ReactNode;
    className?: string;
};

/**
 * Macro: composes Compound/Navigation/Breadcrumbs above a title/
 * description/actions row. Does not reimplement Breadcrumbs' markup, and
 * takes no position on what `actions` renders — the caller supplies
 * whatever Button/IconButton nodes it needs.
 */
export function PageHeader({
    title,
    breadcrumbs,
    description,
    actions,
    className,
}: PageHeaderProps) {
    return (
        <div className={clsx('flex flex-col gap-3', className)}>
            {breadcrumbs ? <Breadcrumbs items={breadcrumbs} /> : null}
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="flex flex-col gap-1">
                    <h1 className="text-xl font-semibold text-fg">{title}</h1>
                    {description ? <p className="text-sm text-fg-muted">{description}</p> : null}
                </div>
                {actions ? <div className="flex items-center gap-2">{actions}</div> : null}
            </div>
        </div>
    );
}
