import { useCallback, useEffect, useState } from 'react';

import { t } from '../../../../i18n/workspace';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../../../lib/csrfHeader';

export type PublicationHistoryRow = {
    id: number;
    version: number;
    state: string;
    publishedAt: string;
    isLive: boolean;
};

type PublicationHistoryRegionProps = {
    workspaceId: number;
    menuId: number;
    /** Yeni bir yayın yapıldığında liste tazelensin diye artan sayaç. */
    refreshToken: number;
    onRestored: () => void;
};

/**
 * Yanlış yayından dönmek — `docs/81` (P1-05).
 *
 * Sahip yanlış fiyat listesini yayınladı ve misafirler şu anda onu okuyor.
 * Taslağı düzeltip yeniden yayınlamak, panik anında en yavaş yol ve ikinci
 * bir hata yapma ihtimali en yüksek olan yol.
 */
export function PublicationHistoryRegion({
    workspaceId,
    menuId,
    refreshToken,
    onRestored,
}: PublicationHistoryRegionProps) {
    const [rows, setRows] = useState<PublicationHistoryRow[]>([]);
    const [restoringId, setRestoringId] = useState<number | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    const historyUrl = `/api/workspaces/${workspaceId}/menu/${menuId}/publications`;
    const [reloadToken, setReloadToken] = useState(0);

    useEffect(() => {
        // Efektin İÇİNDE senkron `setState` yok: istek beklenir, sökülmüş
        // bir bileşene yazmamak için iptal bayrağı taşınır.
        let cancelled = false;

        (async () => {
            try {
                const response = await fetch(historyUrl, {
                    credentials: 'include',
                    headers: { Accept: 'application/json' },
                });

                if (cancelled || !response.ok) return;

                const body = (await response.json()) as { data?: PublicationHistoryRow[] };

                if (cancelled) return;

                setRows(body.data ?? []);
            } catch {
                // Geçmiş okunamadıysa bölüm sessizce boş kalır: yayın
                // akışının kendisi bundan etkilenmemeli.
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [historyUrl, refreshToken, reloadToken]);

    const restore = useCallback(
        async (publicationId: number) => {
            setRestoringId(publicationId);
            setErrorMessage(null);

            try {
                await bootstrapCsrfCookie();

                const response = await fetch(
                    `/api/workspaces/${workspaceId}/menu/${menuId}/publications/${publicationId}/restore`,
                    buildAuthRequestInit({ method: 'POST' }),
                );

                if (!response.ok) {
                    setErrorMessage(t('workspace.publication.history.restoreError'));

                    return;
                }

                setReloadToken((token) => token + 1);
                onRestored();
            } catch {
                setErrorMessage(t('workspace.publication.history.restoreError'));
            } finally {
                setRestoringId(null);
            }
        },
        [workspaceId, menuId, onRestored],
    );

    if (rows.length === 0) {
        return null;
    }

    return (
        <section className="flex flex-col gap-2">
            {/*
                SÜRÜM SATIRI KART DEĞİLDİR (FF-131, kanonik teslim paketi
                `DESIGN_SPEC` §9 "Sürümler" + "Kart grameri").

                Önceki hâlde her sürüm kendi başına duran, aralarında boşluk
                olan bir satırdı ve listenin dış çerçevesi yoktu. Dört sürüm
                dört ayrı duyuru gibi okunuyordu; oysa sahibin buradaki tek
                işi KARŞILAŞTIRMAKTIR — "hangisine döneyim?".

                Paketin düzeni: tek kart, başlık şeridi, içeride 1 piksellik
                ayraçlarla ayrılmış eşit ritimli satırlar, altta kuralın
                kendisini anlatan bir dipnot.
            */}
            <div
                data-publication-history="true"
                className="overflow-hidden rounded-[var(--radius-lg)] border border-border bg-surface"
            >
                <h3 className="border-b border-border px-[var(--density-padding-inline)] py-[var(--space-3)] text-body font-bold text-fg">
                    {t('workspace.publication.history.title')}
                </h3>

                <ul className="flex flex-col">
                    {rows.map((row) => (
                        <li
                            key={row.id}
                            className="flex min-h-[var(--density-row-height)] flex-wrap items-center gap-x-3 gap-y-1 border-t border-border px-[var(--density-padding-inline)] py-[var(--space-2)] first:border-t-0"
                        >
                            <span className="text-body font-bold tabular-nums text-fg">
                                {t('workspace.publication.history.version', {
                                    version: String(row.version),
                                })}
                            </span>
                            {/*
                                Zaman damgası `text-meta`nın MEŞRU kullanımıdır
                                ve `tabular-nums` taşır: tarihler alt alta
                                okunur, orantılı rakamda haneler kayar ve
                                sütun titrer.
                            */}
                            <span className="flex-1 text-meta tabular-nums text-fg-muted">
                                {row.publishedAt}
                            </span>

                            {row.isLive ? (
                                /*
                                    Canlı olan sürüm İŞARETLİ. "Hangi sürüm
                                    yayında" sorusunun cevabı her zaman ekranda
                                    olmalı — yanlış fiyatı gören misafirle
                                    tartışan sahip tam olarak bunu sorar.

                                    Rozet HAP biçimlidir (`rounded-pill`;
                                    tam yuvarlak sınıfı bu depoda jeton çarpmıyordu)
                                    ve yalnız renge yaslanmaz: kelime metin
                                    olarak orada durur, dolgu ikinci kanaldır.
                                */
                                <span className="rounded-pill bg-surface-success px-[var(--space-2)] py-[var(--space-1)] text-meta font-bold text-fg-success">
                                    {t('workspace.publication.history.live')}
                                </span>
                            ) : (
                                <button
                                    type="button"
                                    disabled={restoringId !== null}
                                    onClick={() => void restore(row.id)}
                                    /*
                                        Geri alma bir DÜĞMEDİR, altı çizili bir
                                        bağlantı değil: bir adrese gitmez, yeni
                                        bir yayın yazar. Paket onu kenarlıklı
                                        hayalet düğme olarak çizer.
                                    */
                                    className="rounded-[var(--radius-md)] border border-border px-[var(--space-3)] py-[var(--space-1)] text-body font-medium text-fg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                                    aria-label={t('workspace.publication.history.restoreLabel', {
                                        version: String(row.version),
                                    })}
                                >
                                    {t('workspace.publication.history.restore')}
                                </button>
                            )}
                        </li>
                    ))}
                </ul>

                {/*
                    Dipnot LİSTENİN ALTINDA. Başlığın altındayken kural,
                    henüz görmediği bir listeyi açıklıyordu; şimdi sahip
                    sürümleri okuduktan sonra "peki geri alırsam ne olur?"
                    diye sorduğu anda cevabı tam orada bulur.
                */}
                <p className="border-t border-border px-[var(--density-padding-inline)] py-[var(--space-3)] text-body text-fg-muted">
                    {t('workspace.publication.history.help')}
                </p>
            </div>

            {errorMessage ? (
                <p role="alert" className="text-body text-fg-danger">
                    {errorMessage}
                </p>
            ) : null}
        </section>
    );
}

export default PublicationHistoryRegion;
