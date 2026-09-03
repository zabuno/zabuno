import { useCallback, useEffect, useState } from 'react';

import { t } from '../../../../i18n/workspace';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../../../lib/csrfHeader';
import { QrDestinationFieldsRegion } from './QrDestinationFieldsRegion';
import { QrPrintExportRegion } from './QrPrintExportRegion';
import {
    QrCodeListItem,
    type QrCodeItem,
    type RetargetLocation,
} from './qr-destination/QrCodeListItem';

type QrDestinationRegionProps = {
    workspaceId: number;
    locationId: number;
    menuId: number;
    hasCurrentPublication: boolean;
};

/**
 * Fetches/creates/disables real QR codes for the current published menu.
 * Never invents a token or resolverUrl client-side — every one shown comes
 * from a real server response.
 */
export function QrDestinationRegion(props: QrDestinationRegionProps) {
    const { workspaceId, locationId, menuId, hasCurrentPublication } = props;

    const [items, setItems] = useState<QrCodeItem[]>([]);
    const [loaded, setLoaded] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [creating, setCreating] = useState(false);
    /*
        ŞUBE LİSTESİ — kodu başka şubeye taşımak için (`docs/98` FF-64).
        "Taşı" istenene kadar YÜKLENMEZ: kodların çoğu hiç taşınmaz ve her
        açılışta bir istek daha atmak, taşımayan sahibe bedel ödetirdi.
        Bir kez gelince saklanır.
    */
    const [locations, setLocations] = useState<RetargetLocation[] | null>(null);
    const [movingId, setMovingId] = useState<number | null>(null);

    const handleStartMove = useCallback(
        async (qrCodeId: number) => {
            setMovingId(qrCodeId);
            if (locations !== null) return;

            try {
                const response = await fetch(`/api/workspaces/${workspaceId}/brand/locations`, {
                    credentials: 'include',
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok) {
                    setLocations([]);
                    return;
                }
                const body = (await response.json()) as unknown;
                setLocations(
                    Array.isArray(body)
                        ? body.map((row) => ({
                              id: Number((row as { id: number }).id),
                              displayName: String(
                                  (row as { display_name?: string }).display_name ?? '',
                              ),
                          }))
                        : [],
                );
            } catch {
                setLocations([]);
            }
        },
        [workspaceId, locations],
    );

    const handleRetarget = useCallback(
        async (qrCodeId: number, locationId: number) => {
            try {
                await bootstrapCsrfCookie();

                const response = await fetch(
                    `/api/workspaces/${workspaceId}/qr-codes/${qrCodeId}/destination`,
                    buildAuthRequestInit({
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ locationId }),
                    }),
                );

                if (response.ok) {
                    const updated = (await response.json()) as Partial<QrCodeItem>;
                    setItems((prev) =>
                        prev.map((item) =>
                            item.id === qrCodeId
                                ? {
                                      ...item,
                                      locationId: updated.locationId ?? locationId,
                                      menuId: updated.menuId ?? item.menuId,
                                  }
                                : item,
                        ),
                    );
                    setErrorMessage(null);
                    setMovingId(null);
                } else {
                    setErrorMessage(t('workspace.publication.qrDestination.move.error'));
                }
            } catch {
                setErrorMessage(t('workspace.publication.qrDestination.move.error'));
            }
        },
        [workspaceId],
    );

    const listUrl = `/api/workspaces/${workspaceId}/brand/locations/${locationId}/qr-codes`;

    useEffect(() => {
        if (!hasCurrentPublication) {
            return;
        }

        let cancelled = false;

        (async () => {
            try {
                const response = await fetch(listUrl, {
                    credentials: 'include',
                    headers: { Accept: 'application/json' },
                });

                if (cancelled) return;

                if (response.ok) {
                    const body = (await response.json()) as unknown;

                    if (Array.isArray(body)) {
                        setItems(body as QrCodeItem[]);
                        setErrorMessage(null);
                    } else {
                        setErrorMessage(t('workspace.publication.qrDestination.loadError'));
                    }
                } else {
                    setErrorMessage(t('workspace.publication.qrDestination.loadError'));
                }
            } catch {
                if (!cancelled) setErrorMessage(t('workspace.publication.qrDestination.loadError'));
            } finally {
                if (!cancelled) {
                    setLoaded(true);
                }
            }
        })();

        return () => {
            cancelled = true;
        };
        // Refetch whenever the parent hands us a fresh props object (e.g. once
        // a current publication becomes available), not on internal re-renders.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [props]);

    const handleCreate = useCallback(async () => {
        if (!hasCurrentPublication) return;

        setCreating(true);
        setErrorMessage(null);

        try {
            await bootstrapCsrfCookie();

            const response = await fetch(
                listUrl,
                buildAuthRequestInit({
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ menuId }),
                }),
            );

            if (response.ok) {
                const created = (await response.json()) as QrCodeItem;
                setItems((prev) => [...prev, created]);
                setErrorMessage(null);
            } else {
                setErrorMessage(t('workspace.publication.qrDestination.createError'));
            }
        } catch {
            setErrorMessage(t('workspace.publication.qrDestination.createError'));
        } finally {
            setCreating(false);
        }
    }, [hasCurrentPublication, listUrl, menuId]);

    const handleDisable = useCallback(
        async (qrCodeId: number) => {
            try {
                await bootstrapCsrfCookie();

                const response = await fetch(
                    `/api/workspaces/${workspaceId}/qr-codes/${qrCodeId}/disable`,
                    buildAuthRequestInit({ method: 'PUT' }),
                );

                if (response.ok) {
                    setItems((prev) =>
                        prev.map((item) =>
                            item.id === qrCodeId ? { ...item, state: 'disabled' } : item,
                        ),
                    );
                    setErrorMessage(null);
                } else {
                    setErrorMessage(t('workspace.publication.qrDestination.disableError'));
                }
            } catch {
                setErrorMessage(t('workspace.publication.qrDestination.disableError'));
            }
        },
        [workspaceId],
    );

    const handleEnable = useCallback(
        async (qrCodeId: number) => {
            try {
                await bootstrapCsrfCookie();

                const response = await fetch(
                    `/api/workspaces/${workspaceId}/qr-codes/${qrCodeId}/enable`,
                    buildAuthRequestInit({ method: 'PUT' }),
                );

                if (response.ok) {
                    setItems((prev) =>
                        prev.map((item) =>
                            item.id === qrCodeId ? { ...item, state: 'active' } : item,
                        ),
                    );
                    setErrorMessage(null);
                } else {
                    setErrorMessage(t('workspace.publication.qrDestination.enableError'));
                }
            } catch {
                setErrorMessage(t('workspace.publication.qrDestination.enableError'));
            }
        },
        [workspaceId],
    );

    const handleBulkCreated = useCallback((created: QrCodeItem[]) => {
        setItems((prev) => {
            const seenIds = new Set(prev.map((item) => item.id));
            const additions: QrCodeItem[] = [];

            for (const item of created) {
                if (seenIds.has(item.id)) continue;
                seenIds.add(item.id);
                additions.push(item);
            }

            return additions.length === 0 ? prev : [...prev, ...additions];
        });
    }, []);

    const showEmpty = hasCurrentPublication && loaded && items.length === 0;
    const showLoading = hasCurrentPublication && !loaded;

    return (
        <>
            <div
                role="region"
                aria-label={t('workspace.publication.qrDestination.region')}
                className="flex flex-col gap-3"
            >
                <h3 className="text-body font-semibold text-fg">
                    {t('workspace.publication.qrDestination.region')}
                </h3>

                <p className="text-body text-fg-secondary">
                    {t('workspace.publication.qrDestination.explanation')}
                </p>

                {errorMessage !== null ? (
                    <p role="alert" className="text-body text-fg-danger">
                        {errorMessage}
                    </p>
                ) : null}

                {showLoading ? (
                    <p role="status" className="text-body text-fg-muted">
                        {t('workspace.publication.qrDestination.loading')}
                    </p>
                ) : null}

                {showEmpty ? (
                    <p className="text-body text-fg-muted">
                        {t('workspace.publication.qrDestination.empty')}
                    </p>
                ) : null}

                <ul className="flex flex-col gap-2">
                    {items.map((item) => (
                        <QrCodeListItem
                            key={item.id}
                            item={item}
                            onDisable={handleDisable}
                            onEnable={handleEnable}
                            moving={movingId === item.id}
                            otherLocations={
                                locations === null
                                    ? null
                                    : locations.filter(
                                          (location) => location.id !== item.locationId,
                                      )
                            }
                            onStartMove={(id) => void handleStartMove(id)}
                            onCancelMove={() => setMovingId(null)}
                            onRetarget={handleRetarget}
                        />
                    ))}
                </ul>

                <QrDestinationFieldsRegion
                    disabled={!hasCurrentPublication || creating}
                    onCreate={handleCreate}
                />
            </div>

            <QrPrintExportRegion
                items={items}
                workspaceId={workspaceId}
                locationId={locationId}
                menuId={menuId}
                hasCurrentPublication={hasCurrentPublication}
                onBulkCreated={handleBulkCreated}
            />
        </>
    );
}

export default QrDestinationRegion;
