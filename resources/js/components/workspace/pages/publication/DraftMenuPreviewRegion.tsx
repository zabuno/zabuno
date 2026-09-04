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
                            <div key={category.id} className="flex flex-col">
                                {/*
                                    Kategori adı GÖVDE tabanındadır.
                                    `--text-meta` yalnız zaman damgası ve
                                    sayaç içindir; alerjen listesi, fiyat ve
                                    görünürlük durumu okunacak metindir ve
                                    ikisi de sahibin menüsünü kontrol ederken
                                    tam olarak baktığı şeydir.
                                */}
                                <p className="text-body font-bold text-fg">{category.name}</p>
                                {/*
                                    Ürün satırı KART DEĞİL: tek kartın içinde
                                    ince ayraçlı satırlar, tek ritim
                                    (`DESIGN_SPEC` "Kart grameri").
                                */}
                                <ul className="flex flex-col">
                                    {category.menuItems
                                        .slice()
                                        .sort((a, b) => a.position - b.position)
                                        .map((item) => (
                                            <li
                                                key={item.id}
                                                className="flex min-h-[var(--density-row-height)] flex-wrap items-center gap-x-3 gap-y-1 border-t border-border py-[var(--space-2)] text-body text-fg-secondary first:border-t-0"
                                            >
                                                <span className="font-medium text-fg">
                                                    {item.productName ?? `#${item.productId}`}
                                                </span>
                                                {/*
                                                    Fiyat `tabular-nums`:
                                                    fiyatlar alt alta okunur ve
                                                    karşılaştırılır; orantılı
                                                    rakamda göz sütunu kaybeder.
                                                */}
                                                <span className="tabular-nums">
                                                    {formatPrice(
                                                        item.priceMinorAmount,
                                                        item.currencyCode,
                                                    )}
                                                </span>
                                                <span className="text-body text-fg-muted">
                                                    {t(
                                                        'workspace.publication.draftPreview.allergens',
                                                    )}
                                                    :{' '}
                                                    {item.allergens.length > 0
                                                        ? item.allergens.join(', ')
                                                        : '—'}
                                                </span>
                                                <span className="text-body text-fg-muted">
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
