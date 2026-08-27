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

/**
 * Durum tonu bileşenin kararı DEĞİLDİR. Aynı "hata" rengi uygulamanın her
 * yerinde aynı olmalı ve tema değiştiğinde hepsi birlikte değişmelidir; bu
 * yüzden ton token kökünden gelir, buradan değil (`docs/37` §2).
 */
const STATUS_CONTAINER_CLASS: Record<BadgeStatus, string> = {
    info: 'border-border-info bg-surface-info',
    success: 'border-border-success bg-surface-success',
    warning: 'border-border-warning bg-surface-warning',
    error: 'border-border-danger bg-surface-danger',
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
                'flex items-start gap-2 rounded-lg border p-4 text-body',
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
