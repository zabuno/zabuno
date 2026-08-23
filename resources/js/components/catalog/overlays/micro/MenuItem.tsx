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
                'flex w-full items-center gap-2 px-4 py-2 text-left text-sm',
                destructive
                    ? 'text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950'
                    : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700',
                'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-blue-600',
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
