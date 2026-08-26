import type { ReactNode } from 'react';
import clsx from 'clsx';
import { Badge, type BadgeStatus } from '../micro/Badge';

export type AlertMessageProps = {
    status: BadgeStatus;
    title: string;
    children?: ReactNode;
    className?: string;
};

const STATUS_LABEL: Record<BadgeStatus, string> = {
    info: 'Info',
    success: 'Success',
    warning: 'Warning',
    error: 'Error',
};

const STATUS_CONTAINER_CLASS: Record<BadgeStatus, string> = {
    info: 'border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950',
    success: 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950',
    warning: 'border-yellow-200 bg-yellow-50 dark:border-yellow-800 dark:bg-yellow-950',
    error: 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950',
};

/**
 * Compound: composes Micro/Feedback/Badge with a message body. Error and
 * warning statuses use role="alert" (assertive) since they demand
 * attention; info/success use a polite status region so they do not
 * interrupt. Does not reimplement Badge's markup.
 */
export function AlertMessage({ status, title, children, className }: AlertMessageProps) {
    const isUrgent = status === 'error' || status === 'warning';

    return (
        <div
            role={isUrgent ? 'alert' : 'status'}
            aria-live={isUrgent ? 'assertive' : 'polite'}
            className={clsx(
                'flex items-start gap-2 rounded-lg border p-4 text-sm',
                'text-fg',
                STATUS_CONTAINER_CLASS[status],
                className,
            )}
        >
            <Badge status={status}>{STATUS_LABEL[status]}</Badge>
            <div className="flex flex-col gap-1">
                <p className="font-medium">{title}</p>
                {children ? <div className="text-fg-secondary">{children}</div> : null}
            </div>
        </div>
    );
}
