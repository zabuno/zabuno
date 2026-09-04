import { useCallback, useEffect, useState } from 'react';

import { t } from '../../../../i18n/workspace';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../../../lib/csrfHeader';
import { InlineRename } from '../../../catalog/menu/micro/InlineRename';

/**
 * Salonun bölümleri — FF-123, sahibin cümlesi (2026-09-04): "salon üst kat,
 * salon içerisi, salon bahçe".
 *
 * Toplu üretim bölümleri "Area 1", "Area 2" diye açıyor ve bu bir YER
 * TUTUCUDUR: hiçbir restoran sahibi salonunu böyle adlandırmaz. Kart basarken
 * alanı seçen kişi kendi kullandığı adı görmeli; yoksa hangi "Area"nın bahçe
 * olduğunu hatırlamak zorunda kalır ve yanlış kartları bastırır.
 *
 * Ad DEĞİŞİR ama kimlik değişmez: basılı kartlar alanın adına değil kendi
 * token'ına bağlıdır, dolayısıyla yeniden adlandırmak masadaki hiçbir kartı
 * bozmaz.
 */
type DiningArea = { id: number; label: string; tableCount: number };

type DiningAreasRegionProps = {
    workspaceId: number;
    locationId: number;
    /** Ad değiştiğinde kod listesi de tazelenmeli: etiket orada da yazıyor. */
    onRenamed?: () => void;
};

export function DiningAreasRegion({ workspaceId, locationId, onRenamed }: DiningAreasRegionProps) {
    const listUrl = `/api/workspaces/${String(workspaceId)}/brand/locations/${String(locationId)}/dining-areas`;

    const [areas, setAreas] = useState<DiningArea[] | null>(null);

    useEffect(() => {
        let cancelled = false;

        (async () => {
            try {
                const response = await fetch(listUrl, {
                    credentials: 'include',
                    headers: { Accept: 'application/json' },
                });

                if (cancelled) return;

                const body: unknown = response.ok ? await response.json() : null;

                if (cancelled) return;

                setAreas(Array.isArray(body) ? (body as DiningArea[]) : []);
            } catch {
                // Bölüm listesi yardımcı bir bilgidir; alınamazsa kart basma
                // işi durmaz ve bölüm hiç çizilmez.
                if (!cancelled) setAreas([]);
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [listUrl]);

    const rename = useCallback(
        async (areaId: number, label: string): Promise<string | null> => {
            try {
                await bootstrapCsrfCookie();

                const response = await fetch(
                    `${listUrl}/${String(areaId)}`,
                    buildAuthRequestInit({
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ label }),
                    }),
                );

                if (!response.ok) {
                    return t('workspace.publication.diningAreas.renameError');
                }

                setAreas(
                    (previous) =>
                        previous?.map((area) => (area.id === areaId ? { ...area, label } : area)) ??
                        previous,
                );
                onRenamed?.();

                return null;
            } catch {
                return t('workspace.publication.diningAreas.renameError');
            }
        },
        [listUrl, onRenamed],
    );

    // Hiç bölüm yoksa bölüm HİÇ çizilmez: boş bir başlık, olmayan bir işi
    // varmış gibi gösterir.
    if (areas === null || areas.length === 0) {
        return null;
    }

    return (
        <section
            aria-label={t('workspace.publication.diningAreas.heading')}
            className="flex flex-col gap-[var(--space-2)]"
        >
            <h4 className="text-body font-bold text-fg">
                {t('workspace.publication.diningAreas.heading')}
            </h4>
            <p className="text-meta text-fg-muted">{t('workspace.publication.diningAreas.help')}</p>

            {/*
                SATIR KART DEĞİLDİR (FF-131, kanonik teslim paketinin düzeni).

                Ayraç ÜSTE konur, alta değil. Alt ayraçlı bir listede son
                satırın ayracını ayrıca susturmak gerekir; o susturma
                unutulduğunda listenin altında, kartın kendi kenarlığıyla
                çakışan ikinci bir çizgi belirir. Üstten ayraç listeye eklenen
                her yeni satırı kendiliğinden doğru çizer.

                Yükseklik ve yatay dolgu YOĞUNLUK jetonlarından gelir: sahip
                Ayarlar'dan "Sıkı / Standart / Ferah" seçtiğinde bu liste de
                onunla değişir. Elle yazılmış bir `py-1` o anahtarı sağır
                bırakırdı — ekranın yarısı değişir, yarısı olduğu yerde kalır.
            */}
            <ul className="flex flex-col">
                {areas.map((area) => (
                    <li
                        key={area.id}
                        className="flex min-h-[var(--density-row-height)] flex-wrap items-center gap-[var(--space-2)] border-t border-border px-[var(--density-padding-inline)] py-[var(--space-1)] first:border-t-0"
                    >
                        <InlineRename
                            value={area.label}
                            label={t('workspace.publication.diningAreas.rename', {
                                name: area.label,
                            })}
                            emptyMessage={t('workspace.publication.diningAreas.empty')}
                            saveLabel={t('workspace.publication.diningAreas.save')}
                            cancelLabel={t('workspace.publication.diningAreas.cancel')}
                            textClassName="text-body font-medium text-fg"
                            onSubmit={(next) => rename(area.id, next)}
                        />
                        {/*
                            MASA SAYISI, yeniden adlandırırken hangi alan
                            olduğunu hatırlatır: "Area 2 (12 masa)" ile
                            "Area 3 (4 masa)" arasında seçim yapan sahip,
                            hangisinin bahçe olduğunu sayıdan çıkarır.
                        */}
                        <span className="text-meta text-fg-muted">
                            {t('workspace.publication.diningAreas.tableCount', {
                                count: String(area.tableCount),
                            })}
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}

export default DiningAreasRegion;
