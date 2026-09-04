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
                    {/*
                        Sayfa başlığı 700 ve SIKI harf aralığı (FF-131):
                        teslim paketi başlığı `letter-spacing:-.02em` ile
                        çiziyor. 600 ağırlık AEP ölçeğinde yok ve Roboto'da
                        ayrı kesim olarak yüklenmediği için sentezleniyordu.
                    */}
                    <h1 className="text-title font-bold tracking-[-0.02em] text-fg">{title}</h1>
                    {description ? (
                        <p className="max-w-[60ch] text-body text-fg-secondary">{description}</p>
                    ) : null}
                </div>
                {actions ? <div className="flex items-center gap-2">{actions}</div> : null}
            </div>
        </div>
    );
}
