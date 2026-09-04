import type { ReactNode } from 'react';
import clsx from 'clsx';
import { StatValue, type StatValueTrend } from '../micro/StatValue';
import { Skeleton } from '../micro/Skeleton';

export type StatCardProps = {
    label: string;
    value: ReactNode;
    trend?: StatValueTrend;
    icon?: ReactNode;
    loading?: boolean;
    className?: string;
};

/**
 * Compound: composes Micro/Data Display/StatValue for the value+trend and
 * Micro/Data Display/Skeleton for its loading placeholder. Does not
 * reimplement either micro's markup.
 */
export function StatCard({ label, value, trend, icon, loading = false, className }: StatCardProps) {
    return (
        <div
            className={clsx(
                // Veri-hassas kart (`docs/102`): etiket küçük ve sakin, değer büyük ve
                // tabular; sayı okunurken göz satır kaymaz.
                'flex items-start justify-between gap-3 rounded-[var(--radius-lg)] border border-border bg-surface p-[var(--space-5)]',
                className,
            )}
        >
            <div className="flex flex-col gap-1">
                <span className="text-meta font-semibold text-fg-muted">{label}</span>
                {loading ? (
                    <Skeleton shape="text" width="6rem" height="1.75rem" />
                ) : (
                    <StatValue value={value} trend={trend} />
                )}
            </div>
            {icon ? (
                <span aria-hidden="true" className="text-fg-muted">
                    {icon}
                </span>
            ) : null}
        </div>
    );
}
