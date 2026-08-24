import type { ReactNode } from 'react';
import { Badge as FlowbiteBadge } from 'flowbite-react';

export type BadgeStatus = 'info' | 'success' | 'warning' | 'error';

export type BadgeProps = {
    status: BadgeStatus;
    children: ReactNode;
    className?: string;
};

const STATUS_COLOR: Record<BadgeStatus, string> = {
    info: 'info',
    success: 'success',
    warning: 'warning',
    error: 'failure',
};

/**
 * Micro building block: a status badge. Knows nothing about forms, routes,
 * or fetches — only how to render one of the four feedback statuses.
 */
export function Badge({ status, children, className }: BadgeProps) {
    return (
        <FlowbiteBadge color={STATUS_COLOR[status]} className={className}>
            {children}
        </FlowbiteBadge>
    );
}
