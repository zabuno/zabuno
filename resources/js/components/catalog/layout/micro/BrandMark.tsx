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
/**
 * Varsayılan marka işareti.
 *
 * Marka sarısı `docs/06` §11 gereği metin ön planı olarak KULLANILAMAZ —
 * kontrastı düşürür. Yapısal bir işaret olarak kullanıldığında ise markayı
 * görünür kılar ve okunabilirliğe hiç dokunmaz. Kabuk şu ana kadar hiç
 * `mark` geçirmediği için arayüzde marka varlığı sıfırdı.
 *
 * `aria-hidden`: ad zaten yanında metin olarak okunur, işaret dekoratiftir.
 */
function DefaultMark() {
    return (
        <span aria-hidden="true" className="inline-block size-4 shrink-0 rounded-[5px] bg-brand" />
    );
}

export function BrandMark({ name, mark, href, hideName = false, className }: BrandMarkProps) {
    const content = (
        <>
            <span aria-hidden="true" className="shrink-0">
                {mark ?? <DefaultMark />}
            </span>
            <span className={hideName ? 'sr-only' : undefined}>{name}</span>
        </>
    );

    const sharedClassName = clsx(
        'inline-flex items-center gap-[var(--space-2)] text-base font-semibold text-fg',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus',
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
