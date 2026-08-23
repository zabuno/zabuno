import type { ReactElement, ReactNode } from 'react';
import { Tooltip } from 'flowbite-react';

export type TooltipHintProps = {
    /** Hint content announced/shown alongside the trigger element. */
    content: ReactNode;
    /** The single trigger element the hint is attached to. */
    children: ReactElement;
    placement?: 'top' | 'right' | 'bottom' | 'left';
    className?: string;
};

/**
 * Compound: a thin, typed wrapper around Flowbite's Tooltip so overlay
 * consumers reach it through the catalog's own contract instead of
 * importing `flowbite-react` directly. Tooltip already provides its own
 * hover/focus trigger, Escape-to-dismiss and positioning — this wrapper
 * does not reimplement any of that, it only narrows and documents the
 * props this catalog exposes.
 */
export function TooltipHint({ content, children, placement = 'top', className }: TooltipHintProps) {
    return (
        <Tooltip content={content} placement={placement} className={className}>
            {children}
        </Tooltip>
    );
}
