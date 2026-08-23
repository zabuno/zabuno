import { t } from '../../../../i18n/workspace';
import type { DashboardMenuTree } from '../DashboardPage';

type DraftMenuPreviewRegionProps = {
    dashboardMenuTree: DashboardMenuTree | null;
};

function formatPrice(priceMinorAmount: number, currencyCode: string): string {
    return `${(priceMinorAmount / 100).toFixed(2)} ${currencyCode}`;
}

export function DraftMenuPreviewRegion({ dashboardMenuTree }: DraftMenuPreviewRegionProps) {
    return (
        <div role="region" aria-label={t('workspace.publication.draftPreview.region')} className="flex flex-col gap-3">
            <h3 className="text-sm font-semibold text-gray-900 dark:text-white">
                {t('workspace.publication.draftPreview.region')}
            </h3>

            <p role="status" className="text-sm text-gray-500 dark:text-gray-400">
                {t('workspace.publication.draftPreview.notice')}
            </p>

            {dashboardMenuTree === null ? (
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    {t('workspace.publication.draftPreview.empty')}
                </p>
            ) : (
                <div className="flex flex-col gap-4">
                    <h4 className="text-sm font-semibold text-gray-900 dark:text-white">
                        {dashboardMenuTree.name}
                    </h4>

                    {dashboardMenuTree.categories
                        .slice()
                        .sort((a, b) => a.position - b.position)
                        .map((category) => (
                            <div key={category.id} className="flex flex-col gap-2">
                                <p className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    {category.name}
                                </p>
                                <ul className="flex flex-col gap-2">
                                    {category.menuItems
                                        .slice()
                                        .sort((a, b) => a.position - b.position)
                                        .map((item) => (
                                            <li
                                                key={item.id}
                                                className="flex flex-col gap-1 text-sm text-gray-700 dark:text-gray-300"
                                            >
                                                <span>{item.productName ?? `#${item.productId}`}</span>
                                                <span>
                                                    {formatPrice(item.priceMinorAmount, item.currencyCode)}
                                                </span>
                                                <span className="text-xs text-gray-500 dark:text-gray-400">
                                                    {t('workspace.publication.draftPreview.allergens')}:{' '}
                                                    {item.allergens.length > 0 ? item.allergens.join(', ') : '—'}
                                                </span>
                                                <span className="text-xs text-gray-500 dark:text-gray-400">
                                                    {item.isVisible
                                                        ? t('workspace.publication.draftPreview.visible')
                                                        : t('workspace.publication.draftPreview.hidden')}
                                                </span>
                                            </li>
                                        ))}
                                </ul>
                            </div>
                        ))}
                </div>
            )}
        </div>
    );
}

export default DraftMenuPreviewRegion;
