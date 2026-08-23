import type { ReactNode } from 'react';
import clsx from 'clsx';

export type BrandMarkProps = {
    /** Product/tenant name shown next to (or in place of) the mark. */
    name: string;
    /** Optional logo/glyph node; the caller owns the icon implementation. */
    mark?: ReactNode;
    /** Destination for a real link. When omitted, renders a non-interactive span. */
    href?: string;
    /** Hides the visible name text while keeping it for assistive tech. */
    hideName?: boolean;
    className?: string;
};

/**
 * Micro building block: the shell's identity mark (logo + name), optionally
 * linking back to a home/dashboard route. Knows nothing about routing,
 * tenancy, or which persona is active — the caller supplies the name/mark.
 */
export function BrandMark({ name, mark, href, hideName = false, className }: BrandMarkProps) {
    const content = (
        <>
            {mark ? (
                <span aria-hidden="true" className="shrink-0">
                    {mark}
                </span>
            ) : null}
            <span className={hideName ? 'sr-only' : undefined}>{name}</span>
        </>
    );

    const sharedClassName = clsx(
        'inline-flex items-center gap-2 text-base font-semibold text-gray-900',
        'dark:text-white',
        'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600',
        className,
    );

    if (href) {
        return (
            <a href={href} className={sharedClassName}>
                {content}
            </a>
        );
    }

    return <span className={sharedClassName}>{content}</span>;
}
