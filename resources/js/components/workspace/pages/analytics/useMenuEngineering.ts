import { useEffect, useState } from 'react';

import type { MenuEngineeringReport, MenuEngineeringRow } from './MenuEngineeringRegion';

/**
 * Yanıt şekline KÖRÜ KÖRÜNE güvenilmez.
 *
 * Eski bir önbellek sürümü, araya giren bir vekil ya da 200 dönen bir hata
 * sayfası beklenmedik bir gövde verebilir. Bir alanın eksikliği ANALİTİK
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

export type MenuEngineeringSource = {
    report: MenuEngineeringReport | null;
    failed: boolean;
};

/**
 * "Menümde ne işe yarıyor?" raporu — `docs/84` (P1-08).
 *
 * Çekme işi bileşenden AYRILDI çünkü aynı rapor iki yerde okunuyor:
 * ekranın üstündeki "bu aralıkta ne oldu?" kartı ile ürün listeleri. İki
 * ayrı istek atmak aynı sayıyı iki kez indirmek ve iki listenin
 * ayrışabilmesi demekti.
 *
 * `workspaceId` tanımsızsa hiçbir istek atılmaz: veri kaynağını dışarıdan
 * alan bir çağıran, ikinci bir isteği tetiklemeden aynı bileşeni kullanabilsin.
 */
export function useMenuEngineering(
    workspaceId: number | undefined,
    range: string,
): MenuEngineeringSource {
    const [report, setReport] = useState<MenuEngineeringReport | null>(null);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        if (workspaceId === undefined) {
            return;
        }

        let cancelled = false;

        void (async () => {
            try {
                const response = await fetch(
                    `/api/workspaces/${String(workspaceId)}/analytics/menu-engineering?range=${range}`,
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

    return { report, failed };
}
