import type { ElementType, ReactNode } from 'react';

export type VisuallyHiddenProps = {
    children: ReactNode;
    as?: ElementType;
    className?: string;
};

/**
 * Micro building block: hides content visually while keeping it in the
 * accessibility tree (screen-reader-only text). Knows nothing about forms,
 * routes, or fetches — only how to render its children off-screen.
 */
export function VisuallyHidden({ children, as: Tag = 'span', className }: VisuallyHiddenProps) {
    return <Tag className={['sr-only', className].filter(Boolean).join(' ')}>{children}</Tag>;
}
