import type { MouseEvent, ReactNode } from 'react';
import clsx from 'clsx';

export type NavLinkProps = {
    /** Visible/accessible label text. */
    children: ReactNode;
    /**
     * Destination for a real link. When omitted, the component renders a
     * `<button>` and relies on `onSelect` instead — no router dependency
     * either way, callers own navigation.
     */
    href?: string;
    /** Invoked on activation (click or Enter/Space). Router-agnostic. */
    onSelect?: (event: MouseEvent<HTMLAnchorElement | HTMLButtonElement>) => void;
    /** Marks this as the current page/section per WAI-ARIA `aria-current`. */
    current?: boolean;
    /** Optional leading icon node; the caller owns the icon implementation. */
    icon?: ReactNode;
    disabled?: boolean;
    className?: string;
};

/**
 * Micro building block: a single navigation item. Renders an anchor when
 * `href` is given (so browsers get real link semantics — open-in-new-tab,
 * status bar preview, etc.) or a button otherwise. Knows nothing about
 * routing, fetches, or business rules.
 */
export function NavLink({
    children,
    href,
    onSelect,
    current = false,
    icon,
    disabled = false,
    className,
}: NavLinkProps) {
    // Kimlik: Precision Flat 2.0 + tonal kabuk (docs/06 §11, docs/37 §1).
    // Gölge yok; katman farkı TONLA kurulur. Aktif öğe bir MARKA RAYI taşır:
    // marka sarısı metin zemini olarak kullanılamaz (kontrast düşer), yapısal
    // vurgu olarak kullanılır — böylece marka görünür olur ve okunabilirlik
    // hiçbir temada bozulmaz. Ray logical kenarlıktır, RTL'de kendiliğinden
    // sağa geçer.
    const sharedClassName = clsx(
        'inline-flex w-full items-center gap-[var(--space-3)] rounded-[var(--radius-md)] text-body font-medium',
        'px-[var(--space-3)] py-[var(--space-2)]',
        'min-h-[var(--density-hit-area-min)]',
        'border-s-2 border-transparent',
        'transition-colors duration-[var(--duration-fast)] ease-[var(--easing-standard)]',
        'text-fg-secondary hover:bg-surface-hover hover:text-fg',
        /*
            HALKA DEĞİL ANA HAT — `docs/71`.

            `ring-*` Tailwind'in kutu gölgesiyle çizdiği halkadır: öğenin
            dışına taşar, komşu kontrollerin üstüne biner ve `outline-none`
            ile birlikte kullanıldığında tarayıcının kendi göstergesini de
            kapatır. Uygulamanın geri kalanı `outline` kullanıyor; iki ayrı
            dil, iki ayrı görünüm demekti.
        */
        'focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
        'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
        current && 'border-s-brand bg-surface-active font-bold text-fg',
        disabled && 'pointer-events-none opacity-50',
        className,
    );

    const content = (
        <>
            {icon ? (
                <span aria-hidden="true" className="shrink-0">
                    {icon}
                </span>
            ) : null}
            <span>{children}</span>
        </>
    );

    if (href) {
        return (
            <a
                href={disabled ? undefined : href}
                aria-current={current ? 'page' : undefined}
                aria-disabled={disabled || undefined}
                tabIndex={disabled ? -1 : undefined}
                onClick={disabled ? undefined : onSelect}
                className={sharedClassName}
            >
                {content}
            </a>
        );
    }

    return (
        <button
            type="button"
            aria-current={current ? 'page' : undefined}
            disabled={disabled}
            onClick={onSelect}
            className={sharedClassName}
        >
            {content}
        </button>
    );
}
