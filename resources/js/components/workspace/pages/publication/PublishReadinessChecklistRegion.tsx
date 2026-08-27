import { t } from '../../../../i18n/workspace';
import type { DashboardMenuTree } from '../DashboardPage';

type PublishReadinessChecklistRegionProps = {
    dashboardMenuTree: DashboardMenuTree | null;
};

type ReadinessCheck = {
    key: string;
    label: string;
    ready: boolean;
};

function buildChecks(tree: DashboardMenuTree): ReadinessCheck[] {
    const categories = tree.categories;
    const items = categories.flatMap((category) => category.menuItems);
    const visibleItems = items.filter((item) => item.isVisible);

    const hasCategory = categories.length > 0;
    const hasVisibleItem = visibleItems.length > 0;
    const visibleNamesReady = visibleItems.every(
        (item) => (item.productName ?? '').trim().length > 0,
    );
    const visiblePriceCurrencyReady = visibleItems.every(
        (item) => item.priceMinorAmount > 0 && item.currencyCode.trim().length > 0,
    );
    const categoryNamesReady = categories.every((category) => category.name.trim().length > 0);

    return [
        {
            key: 'hasCategory',
            label: t('workspace.publication.readiness.hasCategory'),
            ready: hasCategory,
        },
        {
            key: 'hasVisibleItem',
            label: t('workspace.publication.readiness.hasVisibleItem'),
            ready: hasVisibleItem,
        },
        {
            key: 'visibleProductNames',
            label: t('workspace.publication.readiness.visibleProductNames'),
            ready: visibleNamesReady,
        },
        {
            key: 'visiblePriceAndCurrency',
            label: t('workspace.publication.readiness.visiblePriceAndCurrency'),
            ready: visiblePriceCurrencyReady,
        },
        {
            key: 'categoryNames',
            label: t('workspace.publication.readiness.categoryNames'),
            ready: categoryNamesReady,
        },
    ];
}

export function isDraftReady(dashboardMenuTree: DashboardMenuTree | null): boolean {
    if (dashboardMenuTree === null) {
        return false;
    }

    return buildChecks(dashboardMenuTree).every((check) => check.ready);
}

export function PublishReadinessChecklistRegion({
    dashboardMenuTree,
}: PublishReadinessChecklistRegionProps) {
    return (
        <div
            role="region"
            aria-label={t('workspace.publication.readiness.region')}
            className="flex flex-col gap-3"
        >
            <h3 className="text-body font-semibold text-fg">
                {t('workspace.publication.readiness.region')}
            </h3>

            {dashboardMenuTree === null ? (
                <p role="status" className="text-body text-fg-muted">
                    {t('workspace.publication.readiness.notLoaded')}
                </p>
            ) : (
                <ul className="flex flex-col gap-1">
                    {buildChecks(dashboardMenuTree).map((check) => (
                        <li key={check.key} className="text-body text-fg-secondary">
                            {check.label}:{' '}
                            {check.ready
                                ? t('workspace.publication.readiness.ready')
                                : t('workspace.publication.readiness.needsAttention')}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export default PublishReadinessChecklistRegion;
