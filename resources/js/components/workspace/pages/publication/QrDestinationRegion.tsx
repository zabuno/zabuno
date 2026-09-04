import { useCallback, useEffect, useState } from 'react';

import { t } from '../../../../i18n/workspace';
import { Button } from '../../../catalog/forms/micro/Button';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../../../lib/csrfHeader';
import { QrDestinationFieldsRegion, type QrCreateReasonKind } from './QrDestinationFieldsRegion';
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
    /**
     * Yayın bilgisi HENÜZ gelmedi mi, yoksa SORULAMADI mı (FF-108)?
     *
     * İkisi de `hasCurrentPublication: false` üretir ama anlamları zıttır:
     * biri "bekle", diğeri "sunucuya ulaşamadık". Ayırmayınca ekran, yayında
     * bir menüsü ve masalarında çalışan kartları olan sahibe "önce
     * yayınlayın" diyordu.
     */
    publicationLoading?: boolean;
    publicationLoadFailed?: boolean;
    /** Plan kısıtının çıkış yolu: faturalama ekranı. */
    onUpgrade?: () => void;
    /** Markanın ana rengi — "markalı" tema bunu kullanır (FF-112). */
    brandPrimaryColor?: string | null;
    /** Marka rengini düzeltmenin yolu: marka ekranı. */
    onEditBrand?: () => void;
};

/**
 * Fetches/creates/disables real QR codes for the current published menu.
 * Never invents a token or resolverUrl client-side — every one shown comes
 * from a real server response.
 */
export function QrDestinationRegion(props: QrDestinationRegionProps) {
    const {
        workspaceId,
        locationId,
        menuId,
        hasCurrentPublication,
        publicationLoading = false,
        publicationLoadFailed = false,
        onUpgrade,
        brandPrimaryColor = null,
        onEditBrand,
    } = props;

    const listUrl = `/api/workspaces/${workspaceId}/brand/locations/${locationId}/qr-codes`;

    /*
        LİSTE, ÇEKİLDİĞİ ADRESLE BİRLİKTE SAKLANIR (FF-108).

        Önceden kodlar çıplak bir dizideydi ve adres değişince — sahip başka
        bir şubeye geçtiğinde — eski şubenin kodları ekranda kalıyordu; yeni
        istek başarısız olursa KALICI olarak kalıyordu. Sahip, Kadıköy
        ekranında Beşiktaş'ın kartlarını görüyordu.

        Anahtarı durumun İÇİNE koymak bunu imkânsız kılar: elimizdeki cevap şu
        anki adrese ait değilse liste "henüz yüklenmedi" sayılır. Aynı desen
        `useCurrentPublication`'da da var — efektin başında `setState`
        çağırmadan, fazladan render turu üretmeden.

        Anahtara `reloadToken` de girer: "tekrar dene" yeni bir istektir ve
        ekran o an dürüstçe yeniden "yükleniyor" olur.
    */
    const [reloadToken, setReloadToken] = useState(0);
    const requestKey = `${listUrl}#${String(reloadToken)}`;
    const [list, setList] = useState<{
        key: string;
        items: QrCodeItem[];
        failed: boolean;
    } | null>(null);

    const currentList = list?.key === requestKey ? list : null;
    const items = currentList?.items ?? [];
    const loaded = currentList !== null;
    const listFailed = currentList?.failed ?? false;

    /** Sunucu cevabı olmadan listeyi değiştirmenin tek yolu (create/disable/move). */
    const updateItems = useCallback(
        (updater: (previous: QrCodeItem[]) => QrCodeItem[]) => {
            setList((previous) =>
                previous === null || previous.key !== requestKey
                    ? previous
                    : { ...previous, items: updater(previous.items) },
            );
        },
        [requestKey],
    );

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
                    updateItems((prev) =>
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
        [workspaceId, updateItems],
    );

    /*
        KODLAR HER ZAMAN OKUNUR (FF-108).

        Eskiden bu istek `hasCurrentPublication` false iken hiç atılmıyordu.
        Oysa QR kodları yayından AYRI kayıtlardır: sahip menüsünü geri çekmiş
        ya da yayın sorgusu 500 dönmüş olabilir — masalardaki kartlar yine
        basılıdır ve yine bu hesaba aittir. Liste "yükleniyor" bile demeden
        boş kalıyordu: ekranda hiçbir şey ve hiçbir açıklama yoktu. Yayın
        durumu artık yalnız YENİ KOD ÜRETMEYİ kısıtlar, var olanı görmeyi
        değil.
    */
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

                if (Array.isArray(body)) {
                    setList({ key: requestKey, items: body as QrCodeItem[], failed: false });
                } else {
                    setList({ key: requestKey, items: [], failed: true });
                }
            } catch {
                if (!cancelled) setList({ key: requestKey, items: [], failed: true });
            }
        })();

        return () => {
            cancelled = true;
        };
        /*
            Bağımlılık ADRESTİR, props NESNESİ değil. Önceki hâl `[props]` idi
            ve üst bileşenin her render'ı yeni bir nesne ürettiği için liste
            durup dururken yeniden çekiliyordu.
        */
    }, [listUrl, requestKey]);

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
                updateItems((prev) => [...prev, created]);
                setErrorMessage(null);
            } else {
                setErrorMessage(t('workspace.publication.qrDestination.createError'));
            }
        } catch {
            setErrorMessage(t('workspace.publication.qrDestination.createError'));
        } finally {
            setCreating(false);
        }
    }, [hasCurrentPublication, listUrl, menuId, updateItems]);

    const handleDisable = useCallback(
        async (qrCodeId: number) => {
            try {
                await bootstrapCsrfCookie();

                const response = await fetch(
                    `/api/workspaces/${workspaceId}/qr-codes/${qrCodeId}/disable`,
                    buildAuthRequestInit({ method: 'PUT' }),
                );

                if (response.ok) {
                    updateItems((prev) =>
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
        [workspaceId, updateItems],
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
                    updateItems((prev) =>
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
        [workspaceId, updateItems],
    );

    const handleBulkCreated = useCallback(
        (created: QrCodeItem[]) => {
            updateItems((prev) => {
                const seenIds = new Set(prev.map((item) => item.id));
                const additions: QrCodeItem[] = [];

                for (const item of created) {
                    if (seenIds.has(item.id)) continue;
                    seenIds.add(item.id);
                    additions.push(item);
                }

                return additions.length === 0 ? prev : [...prev, ...additions];
            });
        },
        [updateItems],
    );

    const showEmpty = loaded && !listFailed && items.length === 0;
    const showLoading = !loaded;

    /*
        SEBEP TEK YERDE TÜRETİLİR (FF-108): "Oluştur" düğmesi ile toplu
        sihirbaz aynı cümleyi söyler. İki ayrı yerde hesaplamak, ikisinin bir
        gün ayrışacağı anlamına gelirdi.
    */
    const reasonKind: QrCreateReasonKind = publicationLoading
        ? 'loading'
        : publicationLoadFailed
          ? 'unknown'
          : 'notPublished';

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

                {/*
                    ÜRÜNÜN EN GÜÇLÜ ARGÜMANI EKRANDA YAZMIYORDU (FF-112,
                    `docs/104` Döngü 11).

                    Bu sektördeki en pahalı arıza, üçüncü taraf bir kısaltıcıya
                    bağlanmış kodların bir gün ölmesidir: masadaki kırk kart
                    aynı anda çöp olur ve restoran bunu misafirden öğrenir.
                    Zabuno'nun kodları kalıcıdır ve hedefleri sonradan
                    değiştirilebilir — sahip bunu bilmeden bastırıyordu, yani
                    ödediği şeyin ne olduğunu bilmiyordu.
                */}
                <p className="text-meta text-fg-muted">
                    {t('workspace.publication.qrDestination.permanence')}
                </p>

                {errorMessage !== null ? (
                    <p role="alert" className="text-body text-fg-danger">
                        {errorMessage}
                    </p>
                ) : null}

                {/*
                    HATANIN BİR ÇIKIŞI OLMALI (FF-108). Liste çekilemediğinde
                    ekranda yalnız bir cümle kalıyordu; kullanıcının
                    yapabileceği tek şey sayfayı yenilemekti ve bunu ona kimse
                    söylemiyordu. Çıkışı olmayan bir hata, hatanın kendisinden
                    pahalıdır.
                */}
                {listFailed ? (
                    <div className="flex flex-col items-start gap-[var(--space-2)]">
                        <p role="alert" className="text-body text-fg-danger">
                            {t('workspace.publication.qrDestination.loadError')}
                        </p>
                        <Button
                            type="button"
                            color="light"
                            onClick={() => setReloadToken((token) => token + 1)}
                        >
                            {t('workspace.publication.qrDestination.retry')}
                        </Button>
                    </div>
                ) : null}

                {/*
                    ÜÇ HÂL AYRI (FF-108): biliniyor, henüz bilinmiyor,
                    sorulamadı. "Sorulamadı", kodların yok olduğu anlamına
                    GELMEZ — masadaki basılı kartlar çalışmaya devam ediyor
                    olabilir ve sahibe aksini söylemek en pahalı yalandır.
                */}
                {showLoading ? (
                    <p role="status" className="text-body text-fg-muted">
                        {t('workspace.publication.qrDestination.loading')}
                    </p>
                ) : null}

                {publicationLoadFailed ? (
                    <p role="alert" className="text-body text-fg-danger">
                        {t('workspace.publication.qrDestination.statusUnknown')}
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
                    reasonKind={reasonKind}
                    onCreate={handleCreate}
                />
            </div>

            <QrPrintExportRegion
                items={items}
                workspaceId={workspaceId}
                locationId={locationId}
                menuId={menuId}
                hasCurrentPublication={hasCurrentPublication}
                bulkUnavailableReason={reasonKind}
                onBulkCreated={handleBulkCreated}
                onUpgrade={onUpgrade}
                brandPrimaryColor={brandPrimaryColor}
                onEditBrand={onEditBrand}
            />
        </>
    );
}

export default QrDestinationRegion;
