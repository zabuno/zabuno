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
            <h3 className="text-body font-semibold text-fg">
                {t('workspace.publication.history.title')}
            </h3>
            <p className="text-meta text-fg-muted">{t('workspace.publication.history.help')}</p>

            <ul className="flex flex-col gap-2">
                {rows.map((row) => (
                    <li key={row.id} className="flex flex-wrap items-baseline gap-2">
                        <span className="text-body font-medium text-fg">
                            {t('workspace.publication.history.version', {
                                version: String(row.version),
                            })}
                        </span>
                        <span className="text-meta text-fg-muted">{row.publishedAt}</span>

                        {row.isLive ? (
                            /*
                                Canlı olan sürüm İŞARETLİ. "Hangi sürüm
                                yayında" sorusunun cevabı her zaman ekranda
                                olmalı — yanlış fiyatı gören misafirle
                                tartışan sahip tam olarak bunu sorar.
                            */
                            <span className="text-meta font-medium text-fg-success">
                                {t('workspace.publication.history.live')}
                            </span>
                        ) : (
                            <button
                                type="button"
                                disabled={restoringId !== null}
                                onClick={() => void restore(row.id)}
                                className="text-body text-fg-link underline underline-offset-2"
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

            {errorMessage ? (
                <p role="alert" className="text-body text-fg-danger">
                    {errorMessage}
                </p>
            ) : null}
        </section>
    );
}

export default PublicationHistoryRegion;
