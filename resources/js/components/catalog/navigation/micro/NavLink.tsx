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
    const sharedClassName = clsx(
        'inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium',
        'text-gray-700 hover:bg-gray-100 hover:text-gray-900',
        'dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white',
        'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600',
        current && 'bg-gray-100 font-semibold text-gray-900 dark:bg-gray-800 dark:text-white',
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
