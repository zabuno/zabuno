import type { ReactNode } from 'react';
import clsx from 'clsx';

export type MenuItemProps = {
    children: ReactNode;
    onSelect: () => void;
    /** Leading icon; the caller owns the icon implementation. */
    icon?: ReactNode;
    disabled?: boolean;
    /** Marks a destructive action (e.g. delete) with a distinct visual treatment. */
    destructive?: boolean;
    className?: string;
};

/**
 * Micro building block: a single actionable row inside a menu (ActionMenu,
 * a future context menu, etc.). Renders `role="menuitem"` so it composes
 * correctly under a parent with `role="menu"` — it does not manage its own
 * open/close state or roving focus, that belongs to the composing menu.
 */
export function MenuItem({
    children,
    onSelect,
    icon,
    disabled = false,
    destructive = false,
    className,
}: MenuItemProps) {
    return (
        <button
            type="button"
            role="menuitem"
            onClick={onSelect}
            disabled={disabled}
            className={clsx(
                // 2026 menü satırı: içeriden yuvarlak vurgu, dokunma yüksekliği,
                // ikon için sabit sütun (`docs/102` §5f).
                'flex min-h-[var(--density-hit-area-min)] w-full items-center gap-[var(--space-3)] rounded-[var(--radius-md)]',
                'px-[var(--space-3)] py-[var(--space-2)] text-start text-body',
                destructive
                    ? 'text-fg-danger hover:bg-surface-danger'
                    : 'text-fg-secondary hover:bg-surface-hover',
                'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-focus',
                'disabled:pointer-events-none disabled:opacity-50',
                className,
            )}
        >
            {icon ? (
                <span aria-hidden="true" className="shrink-0">
                    {icon}
                </span>
            ) : null}
            <span>{children}</span>
        </button>
    );
}
