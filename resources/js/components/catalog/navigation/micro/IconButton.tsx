import type { MouseEvent, ReactNode } from 'react';
import clsx from 'clsx';
import { VisuallyHidden } from './VisuallyHidden';

export type IconButtonProps = {
    /** Icon node; the caller owns the icon implementation. */
    icon: ReactNode;
    /** Required accessible name — the button has no visible text. */
    label: string;
    onClick?: (event: MouseEvent<HTMLButtonElement>) => void;
    disabled?: boolean;
    className?: string;
};

/**
 * Micro building block: an icon-only, square-ish button used for nav
 * affordances such as a mobile menu toggle or a sidebar collapse control.
 * Renders VisuallyHidden text for its accessible name rather than relying
 * on `aria-label` alone, so a browser/OS tooltip or find-in-page still
 * surfaces meaningful text. Knows nothing about routes or fetches.
 */
export function IconButton({ icon, label, onClick, disabled = false, className }: IconButtonProps) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            className={clsx(
                'inline-flex h-9 w-9 items-center justify-center rounded-md',
                'text-gray-700 hover:bg-gray-100 hover:text-gray-900',
                'dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white',
                'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600',
                'disabled:pointer-events-none disabled:opacity-50',
                className,
            )}
        >
            <span aria-hidden="true">{icon}</span>
            <VisuallyHidden>{label}</VisuallyHidden>
        </button>
    );
}
