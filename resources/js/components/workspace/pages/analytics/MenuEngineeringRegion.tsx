import { useEffect, useState } from 'react';

import { t } from '../../../../i18n/workspace';

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

    return (
        <section
            aria-label={t('workspace.analytics.menuEngineering.region')}
            className="flex flex-col gap-4"
        >
            <h3 className="text-body font-semibold text-fg">
                {t('workspace.analytics.menuEngineering.title')}
            </h3>

            {report.state === 'not_enough_data' ? (
                /*
                    Boş bir tablo, sahibe "ürünüm bozuk" dedirtir.

                    Sebep ve EŞİK açıkça yazılır (`docs/66` disiplini): kaç
                    ziyaretçi gerektiğini bilmeyen biri, ne kadar bekleyeceğini
                    de bilemez.
                */
                <p role="status" className="text-body text-fg-muted">
                    {t('workspace.analytics.menuEngineering.thin', {
                        observed: String(report.observedViewers),
                        threshold: String(report.threshold),
                    })}
                </p>
            ) : (
                <>
                    <div className="flex flex-col gap-2">
                        <h4 className="text-body font-medium text-fg-secondary">
                            {t('workspace.analytics.menuEngineering.mostViewed')}
                        </h4>
                        <ol className="flex flex-col gap-1">
                            {report.mostViewed.map((row) => (
                                <li
                                    key={row.menuItemId}
                                    className="flex flex-wrap items-baseline gap-x-3 text-body text-fg-secondary"
                                >
                                    <span className="font-medium text-fg">{row.productName}</span>
                                    <span className="text-fg-muted">{row.categoryName}</span>
                                    {/* `tabular-nums`: rakamlar eşit genişlikte
                                        olmazsa sayılar hizalanmaz ve
                                        karşılaştırma gözle yapılamaz. */}
                                    <span className="tabular-nums">
                                        {t('workspace.analytics.menuEngineering.viewers', {
                                            count: String(row.viewers),
                                        })}
                                    </span>
                                </li>
                            ))}
                        </ol>
                    </div>

                    <div className="flex flex-col gap-2">
                        <h4 className="text-body font-medium text-fg-secondary">
                            {t('workspace.analytics.menuEngineering.neverViewed')}
                        </h4>
                        {report.neverViewed.length === 0 ? (
                            <p className="text-body text-fg-muted">
                                {t('workspace.analytics.menuEngineering.neverViewed.none')}
                            </p>
                        ) : (
                            <ul className="flex flex-col gap-1">
                                {report.neverViewed.map((row) => (
                                    <li
                                        key={row.menuItemId}
                                        className="flex flex-wrap items-baseline gap-x-3 text-body text-fg-secondary"
                                    >
                                        <span className="font-medium text-fg">
                                            {row.productName}
                                        </span>
                                        <span className="text-fg-muted">{row.categoryName}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <div className="flex flex-col gap-2">
                        <h4 className="text-body font-medium text-fg-secondary">
                            {t('workspace.analytics.menuEngineering.searches')}
                        </h4>
                        {report.searchesWithNoResults.length === 0 ? (
                            <p className="text-body text-fg-muted">
                                {t('workspace.analytics.menuEngineering.searches.none')}
                            </p>
                        ) : (
                            <ul className="flex flex-col gap-1">
                                {report.searchesWithNoResults.map((row) => (
                                    <li
                                        key={row.term}
                                        className="flex flex-wrap items-baseline gap-x-3 text-body text-fg-secondary"
                                    >
                                        <span className="font-medium text-fg">{row.term}</span>
                                        <span className="tabular-nums">
                                            {t('workspace.analytics.menuEngineering.searchCount', {
                                                count: String(row.searches),
                                            })}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </>
            )}
        </section>
    );
}

export default MenuEngineeringRegion;
