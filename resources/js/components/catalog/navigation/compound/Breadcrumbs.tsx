import type { MouseEvent } from 'react';
import clsx from 'clsx';
import { NavLink } from '../micro/NavLink';
import { VisuallyHidden } from '../micro/VisuallyHidden';

export type BreadcrumbItem = {
    key: string;
    label: string;
    href?: string;
    onSelect?: (event: MouseEvent<HTMLAnchorElement | HTMLButtonElement>) => void;
};

export type BreadcrumbsProps = {
    items: BreadcrumbItem[];
    /** Accessible name for the `<nav>` landmark; defaults to "Breadcrumb". */
    label?: string;
    className?: string;
};

/**
 * Compound: composes Micro/Navigation/NavLink for every non-terminal
 * crumb. Does not reimplement NavLink's markup or focus/current-page
 * handling — the final item is rendered as plain text with
 * `aria-current="page"` per WAI-ARIA breadcrumb pattern, since the current
 * page is not itself a navigable link.
 */
export function Breadcrumbs({ items, label = 'Breadcrumb', className }: BreadcrumbsProps) {
    return (
        <nav aria-label={label} className={className}>
            <ol className="flex flex-wrap items-center gap-1 text-sm">
                {items.map((item, index) => {
                    const isLast = index === items.length - 1;

                    return (
                        <li key={item.key} className="flex items-center gap-1">
                            {index > 0 ? (
                                <span aria-hidden="true" className="text-fg-muted rtl:-scale-x-100">
                                    /
                                </span>
                            ) : null}
                            {isLast ? (
                                <span
                                    aria-current="page"
                                    className={clsx('px-3 py-2 font-semibold text-fg')}
                                >
                                    {item.label}
                                </span>
                            ) : (
                                <NavLink href={item.href} onSelect={item.onSelect}>
                                    {item.label}
                                </NavLink>
                            )}
                        </li>
                    );
                })}
            </ol>
            {items.length === 0 ? <VisuallyHidden>Empty breadcrumb trail</VisuallyHidden> : null}
        </nav>
    );
}
