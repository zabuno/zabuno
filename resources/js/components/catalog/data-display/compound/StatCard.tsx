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
                'flex items-start justify-between gap-3 rounded-lg border border-gray-200 bg-white p-4',
                'dark:border-gray-700 dark:bg-gray-900',
                className,
            )}
        >
            <div className="flex flex-col gap-1">
                <span className="text-sm font-medium text-gray-500 dark:text-gray-400">
                    {label}
                </span>
                {loading ? (
                    <Skeleton shape="text" width="6rem" height="1.75rem" />
                ) : (
                    <StatValue value={value} trend={trend} />
                )}
            </div>
            {icon ? (
                <span aria-hidden="true" className="text-gray-400 dark:text-gray-500">
                    {icon}
                </span>
            ) : null}
        </div>
    );
}
