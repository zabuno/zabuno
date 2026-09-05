import { ArrowRight } from '@phosphor-icons/react';
import { t } from '../../../../i18n/dashboard';
import { formatMoneyOr } from '../../../../money/format';
import type { DashboardMenuTree } from '../DashboardPage';
import type { MenuInsights } from './useMenuInsights';

/**
 * "En çok bakılanlar" — kaynak `topItems`, `docs/109` §1.
 *
 * Home'daki sayaçlar ("menünde 46 ürün var") sahibin ZATEN bildiği şeyi
 * söyler. Bu tablo bilmediğini söyler: misafirin gözü menüde nereye gidiyor.
 * Kaynağın Home'unda bu tablo tam da bu yüzden var — ekranın tek "dışarıdan
 * gelen haber"i odur.
 *
 * SAYI NEDİR? Uç FARKLI ZİYARETÇİ sayar, açılış değil. Aynı masadaki bir
 * müşterinin ürüne altı kez bakması altı ilgi demek değildir; ham sayaç bu
 * iki durumu ayırt edemez ve ayırt edemeyen bir sayı, sahibi en gürültülü
 * ürüne yatırım yaptırır.
 */

type DashboardTopViewedProps = {
    insights: MenuInsights | null;
    dashboardMenuTree: DashboardMenuTree | null;
    onNavigateToSection?: (section: string) => void;
};

/** Satır ızgarası tek yerde tanımlı: başlık ile gövde ASLA ayrışmasın. */
const GRID = 'grid grid-cols-[2rem_1fr_auto] items-center gap-x-[var(--space-3)]';

function priceOf(dashboardMenuTree: DashboardMenuTree | null, menuItemId: number): string {
    const item = dashboardMenuTree?.categories
        .flatMap((category) => category.menuItems)
        .find((candidate) => candidate.id === menuItemId);

    /*
        FİYAT UYDURULMAZ. Ölçüm, menüden bu arada silinmiş bir ürüne ait
        olabilir; o satıra sıfır ya da hatırlanan eski bir fiyat yazmak,
        sahibin bugün geçerli sandığı bir rakam üretirdi.
    */
    if (!item) {
        return t('dashboard.topViewed.noPrice');
    }

    return formatMoneyOr(
        item.priceMinorAmount,
        item.currencyCode,
        t('dashboard.topViewed.noPrice'),
    );
}

export function DashboardTopViewed({
    insights,
    dashboardMenuTree,
    onNavigateToSection,
}: DashboardTopViewedProps) {
    const rows = insights?.state === 'ready' ? insights.mostViewed : [];

    /*
        Ölçüm yoksa, eşiğin altındaysa ya da hiç görüntülenme yoksa bölüm
        ÇİZİLMEZ. Boş bir "en çok bakılanlar" tablosu sahibe "kimse menüne
        bakmıyor" der; oysa doğrusu çoğu zaman "henüz yeterince ölçmedim"dir.
    */
    if (rows.length === 0) {
        return null;
    }

    const mostViewers = Math.max(...rows.map((row) => row.viewers));

    return (
        <section
            aria-label={t('dashboard.topViewed.heading')}
            className="flex flex-col rounded-[var(--radius-lg)] border border-border bg-surface"
        >
            <div className="flex flex-wrap items-center justify-between gap-[var(--space-2)] border-b border-border px-[var(--space-5)] py-[var(--space-4)]">
                {/*
                    Başlık ARALIĞI söyler. "En çok bakılanlar" tek başına
                    okuyanın "bugün" sandığı bir liste üretir; ölçülen aralığı
                    gizleyen bir tablo, yanlış bir bugüne karar verdirir.
                */}
                <h2 className="text-body font-bold tracking-tight text-fg">
                    {t('dashboard.topViewed.heading')}
                </h2>
                {onNavigateToSection ? (
                    <button
                        type="button"
                        onClick={() => onNavigateToSection('analytics')}
                        className="inline-flex min-h-[var(--control-height)] items-center gap-[var(--space-1)] rounded-[var(--radius-md)] px-[var(--space-2)] text-meta font-medium text-fg-secondary transition-colors duration-[var(--duration-fast)] ease-[var(--easing-inout)] hover:bg-surface-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                    >
                        {t('dashboard.topViewed.all')}
                        <ArrowRight aria-hidden="true" size={16} weight="bold" />
                    </button>
                ) : null}
            </div>

            {/*
                Sütun başlıkları `aria-hidden`: her satır zaten kendi
                etiketlerini taşıyor ve ekran okuyucuya "# Ürün Bakan Fiyat"
                dizisini bir kez daha okutmak gürültü olurdu.
            */}
            <div
                aria-hidden="true"
                className={`${GRID} bg-surface-subtle px-[var(--space-5)] py-[var(--space-2)] text-meta font-bold text-fg-muted`}
            >
                <span>{t('dashboard.topViewed.column.rank')}</span>
                <span>{t('dashboard.topViewed.column.item')}</span>
                <span className="text-end">{t('dashboard.topViewed.column.price')}</span>
            </div>

            <ul className="flex flex-col">
                {rows.map((row, index) => (
                    <li
                        key={row.menuItemId}
                        className={`${GRID} border-t border-border px-[var(--space-5)] py-[var(--space-2)]`}
                    >
                        <span className="text-meta tabular-nums text-fg-muted">{index + 1}</span>

                        <span className="flex min-w-0 flex-wrap items-center gap-x-[var(--space-3)] gap-y-[var(--space-1)] text-start">
                            <span className="min-w-0 truncate text-body font-medium text-fg">
                                {row.productName}
                            </span>
                            <span className="flex items-center gap-[var(--space-2)]">
                                {/*
                                    Çubuk, en çok bakılana ORANLA çizilir: göz
                                    "61 mi 44 mü daha çok" sorusunu okumadan
                                    cevaplasın. Sayıyı ikinci kez söylediği
                                    için ekran okuyucuya gizlenir.
                                */}
                                <span
                                    aria-hidden="true"
                                    className="block h-[0.375rem] w-[5rem] overflow-hidden rounded-pill bg-[var(--color-surface-active)]"
                                >
                                    <span
                                        data-viewer-bar=""
                                        className="block h-full rounded-pill bg-action"
                                        style={{
                                            width: `${(row.viewers / mostViewers) * 100}%`,
                                        }}
                                    />
                                </span>
                                <span className="text-meta tabular-nums text-fg-secondary">
                                    {row.viewers}
                                </span>
                                <span className="sr-only">
                                    {t('dashboard.topViewed.column.viewers')}
                                </span>
                            </span>
                        </span>

                        <span className="text-end text-body font-medium tabular-nums text-fg">
                            {priceOf(dashboardMenuTree, row.menuItemId)}
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}

export default DashboardTopViewed;
