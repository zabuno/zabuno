import clsx from 'clsx';
import { PageHeader, type PageHeaderProps } from './PageHeader';
import { StatCard, type StatCardProps } from '../../data-display/compound/StatCard';
import {
    ResponsiveDataTable,
    type ResponsiveDataTableProps,
} from '../../data-display/compound/ResponsiveDataTable';

export type DashboardStatCardProps = StatCardProps & { key: string };

export type DashboardOverviewProps<Row> = {
    header: PageHeaderProps;
    stats: DashboardStatCardProps[];
    table: ResponsiveDataTableProps<Row>;
    className?: string;
};

/**
 * Macro: composes Compound/Layout/PageHeader, a grid of
 * Compound/Data Display/StatCard, and Compound/Data Display/
 * ResponsiveDataTable. Route/fetch/business-rule agnostic — header text,
 * stat values, and table rows/columns are all props; a surface supplies
 * live or mock data via its own use-case hook (docs/35 §6).
 */
export function DashboardOverview<Row>({
    header,
    stats,
    table,
    className,
}: DashboardOverviewProps<Row>) {
    return (
        <div className={clsx('flex flex-col gap-6', className)}>
            <PageHeader {...header} />
            {stats.length > 0 ? (
                <div className="grid grid-cols-[repeat(auto-fit,minmax(min(100%,12rem),1fr))] gap-4">
                    {stats.map(({ key, ...statCardProps }) => (
                        <StatCard key={key} {...statCardProps} />
                    ))}
                </div>
            ) : null}
            <ResponsiveDataTable {...table} />
        </div>
    );
}
