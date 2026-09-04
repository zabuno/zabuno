import { formatMoneyOr } from '../../../../money/format';
import { t } from '../../../../i18n/workspace';
import type { DashboardMenuTree } from '../DashboardPage';

type DraftMenuPreviewRegionProps = {
    dashboardMenuTree: DashboardMenuTree | null;
};

function formatPrice(priceMinorAmount: number, currencyCode: string): string {
    // Biçimlendirme CORE-12'ye aittir; burada tekrar edilmez (docs/13 §4).
    return formatMoneyOr(priceMinorAmount, currencyCode, `${priceMinorAmount} ${currencyCode}`);
}

export function DraftMenuPreviewRegion({ dashboardMenuTree }: DraftMenuPreviewRegionProps) {
    return (
        <div
            role="region"
            aria-label={t('workspace.publication.draftPreview.region')}
            className="flex flex-col gap-3"
        >
            <h3 className="text-body font-bold text-fg">
                {t('workspace.publication.draftPreview.region')}
            </h3>

            <p role="status" className="text-body text-fg-muted">
                {t('workspace.publication.draftPreview.notice')}
            </p>

            {dashboardMenuTree === null ? (
                <p className="text-body text-fg-muted">
                    {t('workspace.publication.draftPreview.empty')}
                </p>
            ) : (
                <div className="flex flex-col gap-4">
                    <h4 className="text-body font-bold text-fg">{dashboardMenuTree.name}</h4>

                    {dashboardMenuTree.categories
                        .slice()
                        .sort((a, b) => a.position - b.position)
                        .map((category) => (
                            <div key={category.id} className="flex flex-col gap-2">
                                <p className="text-meta font-bold text-fg-muted">{category.name}</p>
                                <ul className="flex flex-col gap-2">
                                    {category.menuItems
                                        .slice()
                                        .sort((a, b) => a.position - b.position)
                                        .map((item) => (
                                            <li
                                                key={item.id}
                                                className="flex flex-col gap-1 text-body text-fg-secondary"
                                            >
                                                <span>
                                                    {item.productName ?? `#${item.productId}`}
                                                </span>
                                                <span>
                                                    {formatPrice(
                                                        item.priceMinorAmount,
                                                        item.currencyCode,
                                                    )}
                                                </span>
                                                <span className="text-meta text-fg-muted">
                                                    {t(
                                                        'workspace.publication.draftPreview.allergens',
                                                    )}
                                                    :{' '}
                                                    {item.allergens.length > 0
                                                        ? item.allergens.join(', ')
                                                        : '—'}
                                                </span>
                                                <span className="text-meta text-fg-muted">
                                                    {item.isVisible
                                                        ? t(
                                                              'workspace.publication.draftPreview.visible',
                                                          )
                                                        : t(
                                                              'workspace.publication.draftPreview.hidden',
                                                          )}
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
