import { useEffect, useState, type ReactNode } from 'react';

import { t } from '../../../../i18n/workspace';
import { PanelCard } from '../shared/PanelCard';

/*
    SATIRIN ORTAK RİTMİ (FF-131, teslim paketi "Kart grameri": tek kart,
    içinde İNCE AYRAÇLI satırlar).

    Yükseklik yoğunluk jetonundan gelir — sahip Ayarlar'dan "Sıkı / Standart /
    Ferah" seçtiğinde bu liste de onunla değişmeli. Yatay dolgu kart
    başlığınınkiyle aynı (`--space-5`), yoksa ad sütunu başlığa göre içeri
    kayar ve kart "iki farklı ızgaradan yapılmış" görünür.

    Ayraç ÜSTE konur ve grup başlığının hemen altındaki ilk satırda
    susturulur: başlık zaten ayırıcıdır, üstüne bir de çizgi konduğunda
    başlık kendi listesinden kopmuş görünür.
*/
const ROW =
    'flex min-h-[var(--density-row-height)] flex-wrap items-center gap-x-[var(--space-3)] gap-y-[var(--space-1)] border-t border-border px-[var(--space-5)] py-[var(--space-2)] text-body text-fg-secondary first:border-t-0';

/*
    Grup başlığı: 700 ağırlık, cümle düzeni, soluk ton. AEP yalnız
    400/500/700 yayınlıyor; 600 Roboto'da ayrı kesim olarak yüklenmediği
    için tarayıcı tarafından SENTEZLENİYOR ve harf biçimleri bozuluyordu.
*/
const GROUP_LABEL =
    'px-[var(--space-5)] pt-[var(--space-4)] pb-[var(--space-2)] text-meta font-bold text-fg-muted';

const QUIET = 'px-[var(--space-5)] py-[var(--space-3)] text-body text-fg-muted';

export type MenuEngineeringRow = {
    menuItemId: number;
    productName: string;
    categoryName: string;
    viewers: number;
};

export type MenuEngineeringSearch = { term: string; searches: number };

export type MenuEngineeringReport = {
    state: 'ready' | 'not_enough_data';
    threshold: number;
    observedViewers: number;
    mostViewed: MenuEngineeringRow[];
    neverViewed: MenuEngineeringRow[];
    searchesWithNoResults: MenuEngineeringSearch[];
};

type MenuEngineeringRegionProps = {
    workspaceId: number;
    range: string;
};

/**
 * "Menümde ne işe yarıyor?" — `docs/84` (P1-08).
 *
 * Bugüne kadarki cevap "menün 214 kez açıldı"ydı ve bu, menüyü DEĞİŞTİRMEK
 * için hiçbir şey söylemez: hangi ürünü büyütmeli, hangisini listeden
 * çıkarmalı, hangi talebi karşılamıyorum?
 */
/**
 * Yanıt şekline KÖRÜ KÖRÜNE güvenilmez.
 *
 * Eski bir önbellek sürümü, araya giren bir vekil ya da 200 dönen bir hata
 * sayfası beklenmedik bir gövde verebilir. Bir alanın eksikliği, ANALİTİK
 * SAYFASININ TAMAMINI çökertmemeli — sahibin gördüğü şey boş bir ekran
 * olurdu ve sebebini hiçbir yerde okuyamazdı.
 */
function normalize(body: Partial<MenuEngineeringReport>): MenuEngineeringReport {
    const rows = (value: unknown): MenuEngineeringRow[] =>
        Array.isArray(value) ? (value as MenuEngineeringRow[]) : [];

    return {
        state: body.state === 'ready' ? 'ready' : 'not_enough_data',
        threshold: typeof body.threshold === 'number' ? body.threshold : 0,
        observedViewers: typeof body.observedViewers === 'number' ? body.observedViewers : 0,
        mostViewed: rows(body.mostViewed),
        neverViewed: rows(body.neverViewed),
        searchesWithNoResults: Array.isArray(body.searchesWithNoResults)
            ? body.searchesWithNoResults
            : [],
    };
}

export function MenuEngineeringRegion({ workspaceId, range }: MenuEngineeringRegionProps) {
    const [report, setReport] = useState<MenuEngineeringReport | null>(null);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        let cancelled = false;

        (async () => {
            try {
                const response = await fetch(
                    `/api/workspaces/${workspaceId}/analytics/menu-engineering?range=${range}`,
                    { credentials: 'include', headers: { Accept: 'application/json' } },
                );

                if (cancelled) return;

                if (!response.ok) {
                    setFailed(true);

                    return;
                }

                const body = (await response.json()) as Partial<MenuEngineeringReport>;

                if (cancelled) return;

                setFailed(false);
                setReport(normalize(body));
            } catch {
                if (!cancelled) setFailed(true);
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [workspaceId, range]);

    if (failed) {
        return (
            <p role="alert" className="text-body text-fg-danger">
                {t('workspace.analytics.menuEngineering.error')}
            </p>
        );
    }

    if (report === null) {
        return (
            <p role="status" className="text-body text-fg-muted">
                {t('workspace.analytics.menuEngineering.loading')}
            </p>
        );
    }

    if (report.state === 'not_enough_data') {
        return (
            <Region>
                <PanelCard title={t('workspace.analytics.menuEngineering.title')}>
                    {/*
                        Boş bir tablo, sahibe "ürünüm bozuk" dedirtir.

                        Sebep ve EŞİK açıkça yazılır (`docs/66` disiplini): kaç
                        ziyaretçi gerektiğini bilmeyen biri, ne kadar
                        bekleyeceğini de bilemez.
                    */}
                    <p role="status" className="text-body text-fg-muted">
                        {t('workspace.analytics.menuEngineering.thin', {
                            observed: String(report.observedViewers),
                            threshold: String(report.threshold),
                        })}
                    </p>
                </PanelCard>
            </Region>
        );
    }

    return (
        <Region>
            {/*
                İKİ KARAR, İKİ KART (FF-131, teslim paketi §5).

                "Levrek'e 8 kişi baktı" ile "4 kişi karides güveç arayıp
                bulamadı" aynı listede alt alta duruyordu. Oysa ilki menüde
                VAR OLAN bir ürünle, ikincisi menüde HİÇ OLMAYAN bir taleple
                ilgili — ve sahip ikisini karıştırdığında, ürünü büyütmesi
                gereken yerde yenisini ekliyor.

                `padded={false}`: dolguyu satırlar yönetir; kartın kendi
                dolgusu olsaydı ayraçlar kenarlığa ulaşmaz, çizgiler havada
                asılı kalırdı.
            */}
            <PanelCard
                title={t('workspace.analytics.menuEngineering.title')}
                padded={false}
                className="overflow-hidden"
            >
                <h3 className={GROUP_LABEL}>
                    {t('workspace.analytics.menuEngineering.mostViewed')}
                </h3>
                <ol className="flex flex-col">
                    {report.mostViewed.map((row) => (
                        <li key={row.menuItemId} className={ROW}>
                            <span className="font-medium text-fg">{row.productName}</span>
                            <span className="text-fg-muted">{row.categoryName}</span>
                            {/*
                                Sayı satırın SONUNA yaslanır (`ms-auto`) ve
                                `tabular-nums` taşır: aksi hâlde sayı ürün
                                adının uzunluğuna göre sağa sola kayar, göz
                                her satırda onu yeniden aramak zorunda kalır
                                ve karşılaştırma yapılamaz.
                            */}
                            <span className="ms-auto tabular-nums">
                                {t('workspace.analytics.menuEngineering.viewers', {
                                    count: String(row.viewers),
                                })}
                            </span>
                        </li>
                    ))}
                </ol>

                <h3 className={`${GROUP_LABEL} border-t border-border`}>
                    {t('workspace.analytics.menuEngineering.neverViewed')}
                </h3>
                {report.neverViewed.length === 0 ? (
                    <p className={QUIET}>
                        {t('workspace.analytics.menuEngineering.neverViewed.none')}
                    </p>
                ) : (
                    <ul className="flex flex-col">
                        {report.neverViewed.map((row) => (
                            <li key={row.menuItemId} className={ROW}>
                                <span className="font-medium text-fg">{row.productName}</span>
                                <span className="text-fg-muted">{row.categoryName}</span>
                            </li>
                        ))}
                    </ul>
                )}
            </PanelCard>

            <PanelCard
                title={t('workspace.analytics.menuEngineering.searches')}
                padded={false}
                className="overflow-hidden"
            >
                {report.searchesWithNoResults.length === 0 ? (
                    <p className={QUIET}>
                        {t('workspace.analytics.menuEngineering.searches.none')}
                    </p>
                ) : (
                    <ul className="flex flex-col">
                        {report.searchesWithNoResults.map((row) => (
                            <li key={row.term} className={ROW}>
                                <span className="font-medium text-fg">{row.term}</span>
                                <span className="ms-auto tabular-nums">
                                    {t('workspace.analytics.menuEngineering.searchCount', {
                                        count: String(row.searches),
                                    })}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </PanelCard>
        </Region>
    );
}

/**
 * İki kartı tek bir isimli bölgede toplar.
 *
 * Bölge adı korunur: ekran okuyucu kullanıcısı "menümde ne işe yarıyor"
 * bölgesine landmark listesinden atlayabilmeli; iki kartı gövdeye serbest
 * bırakmak o atlama noktasını yok ederdi.
 */
function Region({ children }: { children: ReactNode }) {
    return (
        <section
            aria-label={t('workspace.analytics.menuEngineering.region')}
            className="flex flex-col gap-[var(--space-fluid-md)]"
        >
            {children}
        </section>
    );
}

export default MenuEngineeringRegion;
