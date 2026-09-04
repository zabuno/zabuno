import { CheckCircle, Circle } from '@phosphor-icons/react';

import { cn } from '../../../../lib/utils';
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
            <h3 className="text-body font-bold text-fg">
                {t('workspace.publication.readiness.region')}
            </h3>

            {dashboardMenuTree === null ? (
                <p role="status" className="text-body text-fg-muted">
                    {t('workspace.publication.readiness.notLoaded')}
                </p>
            ) : (
                /*
                    DURUM İKİ GÖRSEL KANALLA (WCAG 1.4.1) — AEP `DESIGN_SPEC`
                    §9 "Hazırlık kontrolü" + §12 "Erişilebilirlik".

                    Önceki hâl beş satırı da aynı çiziyordu: "Has category:
                    Ready" ile "Visible product names ready: Needs attention"
                    arasındaki tek fark satırın SONUNDAKİ kelimeydi. Sahip
                    "yayınlayabilir miyim?" sorusunun cevabını, beş satırı da
                    sonuna kadar okumadan alamıyordu.

                    Şimdi: biten madde DOLU onay dairesi + üstü çizili etiket,
                    eksik madde BOŞ daire + koyu etiket. Renk üçüncü kanaldır,
                    tek kanal değil — renk körü bir kullanıcı da, yüksek
                    kontrast modundaki biri de ikisini ayırt eder.
                    Uygulanmış örnek: `dashboard/DashboardSetupJourney.tsx`.
                */
                <ul className="flex flex-col">
                    {buildChecks(dashboardMenuTree).map((check) => (
                        <li
                            key={check.key}
                            /*
                                Satır KART DEĞİL: ince üst ayraç, tek ritim.
                                Yükseklik yoğunluk jetonundan gelir — sahip
                                "Sıkı / Standart / Ferah" seçtiğinde bu liste
                                de onunla değişir.
                            */
                            className="flex min-h-[var(--density-row-height)] items-center gap-[var(--space-2)] border-t border-border py-[var(--space-1)] first:border-t-0"
                        >
                            {check.ready ? (
                                <CheckCircle
                                    aria-hidden="true"
                                    size={22}
                                    weight="fill"
                                    className="shrink-0 text-fg-success"
                                />
                            ) : (
                                <Circle
                                    aria-hidden="true"
                                    size={22}
                                    weight="bold"
                                    className="shrink-0 text-fg-muted"
                                />
                            )}

                            <span
                                className={cn(
                                    // Etiket GÖVDE tabanındadır: okunacak bir
                                    // cümledir, `text-meta` zaman damgası ve
                                    // sayaç içindir.
                                    'text-body',
                                    check.ready
                                        ? 'font-medium text-fg-secondary line-through'
                                        : 'font-medium text-fg',
                                )}
                            >
                                {check.label}
                            </span>

                            {/*
                                Durum METİNLE de söylenir. İşaret ile üstü
                                çizgi görene yeter; ekran okuyucu kullanan biri
                                için dolu daire ile boş daire arasında hiçbir
                                fark yoktur.
                            */}
                            <span className="sr-only">
                                {check.ready
                                    ? t('workspace.publication.readiness.ready')
                                    : t('workspace.publication.readiness.needsAttention')}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export default PublishReadinessChecklistRegion;
