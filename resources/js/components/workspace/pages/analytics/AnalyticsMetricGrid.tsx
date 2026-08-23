import { StatCard } from '../../../catalog/data-display/compound/StatCard';
import { t } from '../../../../i18n/workspace';

export type AnalyticsMetricGridProps = {
    qrResolveCount: number;
    menuOpenCount: number;
};

/**
 * Small analytics feature component: renders the two raw ledger counts
 * using the existing StatCard compound. Never fabricates a value — the
 * caller only renders this once a real summary response has arrived.
 */
export function AnalyticsMetricGrid({ qrResolveCount, menuOpenCount }: AnalyticsMetricGridProps) {
    return (
        <div className="flex flex-col gap-3">
            <StatCard label={t('workspace.analytics.metric.qrResolve')} value={qrResolveCount} />
            <StatCard label={t('workspace.analytics.metric.menuOpen')} value={menuOpenCount} />
        </div>
    );
}

export default AnalyticsMetricGrid;
