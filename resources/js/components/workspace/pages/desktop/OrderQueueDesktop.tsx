import { useCallback, useEffect, useId, useRef, useState } from 'react';
import { Check, CursorClick, Keyboard, X } from '@phosphor-icons/react';

/*
    MASAÜSTÜ PAKETİNİN ÇEVİRMENİ (`docs/151`).

    Yol tek kelime farklı ve fark önemli: bu modülün kataloğu yalnız
    masaüstü paketinde bulunur. Aşağıdaki `desktop.*` anahtarları ortak
    katalogdan çıkarıldı, çünkü telefonda çizilmiyorlardı ama iniyorlardı.
    `t` ikisini de okur — önce masaüstü tablosu, sonra ortak tablo.
*/
import { t } from '../../../../i18n/workspace-desktop';
import { PageState } from '../shared/PageState';
import { changeOrderStatus } from '../orders/changeOrderStatus';
import { FeedStatusLine } from '../orders/FeedStatusLine';
import { planGateVisible, queueEmptyReason } from '../orders/queueEmptyState';
import {
    lineTotal,
    orderAllergens,
    orderStatusLabel,
    waitingLabel,
    waitingMinutes,
} from '../orders/orderPresentation';
import { useOrderFeed, type OrderFeedRow } from '../orders/useOrderFeed';
import type { OrderQueueSurfaceContext } from '../orders/queueSurface';

/**
 * GARSON KUYRUĞU — İŞARETLEYİCİ SÜRÜMÜ (`docs/153` §6).
 *
 * ## Bu neden mobil kuyruğun geniş hâli DEĞİL
 *
 * Aynı veriye bakan iki farklı İŞ var, ve fark ekran genişliği değil giriş
 * kipidir (`docs/153` §2):
 *
 * - **Telefondaki garson** salonda yürür, elinde tek bir sipariş vardır ve
 *   ekrana bakmadan basar. Onun ekranı bir kart listesidir: tek sütun, 44
 *   pikselden büyük hedefler, sırayla.
 * - **Kasadaki/ofisteki kişi** aynı anda ON siparişe bakar, klavyesi vardır
 *   ve aynı işi arka arkaya yapar. Onun işi TARAMA ve TOPLU İŞLEMdir.
 *
 * İkincisi birincinin büyütülmüş hâli değildir. Kart listesini 1280 piksele
 * yaymak, tek seferde üç sipariş gösteren ve her biri için fareyi aşağı
 * taşıtan bir ekran verir — yani geniş ekranın tek kazancını (aynı anda
 * çok şey görmek) harcar.
 *
 * ## Burada olan, telefonda OLMAYAN şeyler
 *
 * 1. **Klavyeyle gezinen liste.** Yukarı/aşağı ile satır değişir, Enter
 *    onaylar, R reddeder. Dokunmada karşılığı yoktur.
 * 2. **Çoklu seçim ve toplu işlem.** Shift+ok aralık seçer, Ctrl/Cmd+A
 *    hepsini. Sekiz siparişi tek tek onaylamak, kasadaki kişinin akşamı
 *    demektir.
 * 3. **Sağ tık bağlam menüsü** — ve klavye karşılığı (Shift+F10 / Menü
 *    tuşu), çünkü sağ tıkın klavyesi yoksa iş klavyeyle yapılamaz olur
 *    (WCAG 2.2 AA, `docs/153` §7).
 * 4. **Kalıcı ayrıntı bölmesi.** Seçili siparişin satırları ve alerjenleri
 *    sağda DURUR; telefonda aynı bilgi kartın içindedir çünkü orada ikinci
 *    bir sütun yoktur.
 * 5. **`hover`** satırın eylemlerini gösterir. Dokunmada `hover` yoktur;
 *    bu yüzden telefon kuyruğunda eylemler HER ZAMAN görünürdür ve o
 *    tasarım değişmedi.
 *
 * ## Ortak kalan
 *
 * Veri (`useOrderFeed`), durum değişikliği (`changeOrderStatus`), cümleler
 * (`orderPresentation`) ve boş kuyruğun sebebi (`queueEmptyState`) İKİ
 * yüzeyde de aynıdır. Bu dosyada tek bir `fetch` yoktur ve tek bir ürün
 * kararı verilmez — ayrışan yalnız SUNUM ve ETKİLEŞİMdir (`docs/153` §4).
 *
 * ## Hareket
 *
 * Hiç geçiş/animasyon yok. `prefers-reduced-motion` MUTLAKtır ve bu ekranda
 * uyulmasının en ucuz yolu, uyulacak bir hareket hiç üretmemektir.
 */

type Busy = Record<number, boolean>;

type ActionOutcome = { ok: number; conflict: number; failed: number; lastStatus: string | null };

export function OrderQueueDesktop({
    workspaceId,
    locationId,
    acceptsOrders,
    planIncludesOrdering,
    onNavigateToSettings,
    onNavigateToPlan,
}: OrderQueueSurfaceContext) {
    const feed = useOrderFeed(
        `/api/workspaces/${String(workspaceId)}/locations/${String(locationId)}/orders/pending`,
    );

    const listLabelId = useId();
    const reasonFieldId = useId();

    /** Klavye odağının ÜZERİNDE olduğu satır (roving tabindex). */
    const [activeIdRaw, setActiveId] = useState<number | null>(null);
    const [selectedIdsRaw, setSelectedIds] = useState<number[]>([]);
    /** Shift ile aralık seçerken sabit kalan uç. */
    const [anchorId, setAnchorId] = useState<number | null>(null);
    const [menu, setMenu] = useState<{ orderId: number; x: number; y: number } | null>(null);
    /** Ret sebebi istenen siparişler; boş dizi = ret akışı kapalı. */
    const [rejecting, setRejecting] = useState<number[] | null>(null);
    const [reason, setReason] = useState('');
    const [reasonMissing, setReasonMissing] = useState(false);
    const [busy, setBusy] = useState<Busy>({});
    const [outcome, setOutcome] = useState<ActionOutcome | null>(null);

    const rowRefs = useRef(new Map<number, HTMLLIElement>());
    const menuRef = useRef<HTMLDivElement | null>(null);
    const reasonRef = useRef<HTMLTextAreaElement | null>(null);

    const orders = feed.status === 'ready' ? feed.orders : [];
    const orderIds = orders.map((order) => order.id);

    /*
        LİSTE DEĞİŞİNCE SEÇİM DE DEĞİŞİR — ama bir ETKİYLE değil, TÜRETEREK.

        Sipariş onaylanınca beslemeden düşer. Seçim elde kalsaydı, ekranda
        olmayan bir siparişe "toplu onayla" denebilirdi — o istek sunucuda
        409 döner ve kullanıcı nedenini anlamazdı.

        Bu eşitleme önce `useEffect` içinde `setState` ile yapılıyordu ve
        `react-hooks` kuralı onu haklı olarak reddetti: etkiyle kurulan bir
        eşitleme, listenin değiştiği kare ile seçimin düzeldiği kare
        arasında BİR KARE boyunca yanlış durumu çizer. Türetilmiş değerde
        böyle bir ara kare yoktur — ham durum saklanır, ekrana giden değer
        her çizimde listeden süzülür.
    */
    const selectedIds = selectedIdsRaw.filter((id) => orderIds.includes(id));
    const activeId =
        activeIdRaw !== null && orderIds.includes(activeIdRaw)
            ? activeIdRaw
            : (orderIds[0] ?? null);

    /* Menü açıkken dışarı tıklamak ve Escape kapatır. */
    useEffect(() => {
        if (menu === null) {
            return;
        }

        const close = (event: MouseEvent) => {
            if (menuRef.current !== null && !menuRef.current.contains(event.target as Node)) {
                setMenu(null);
            }
        };

        document.addEventListener('mousedown', close);

        return () => {
            document.removeEventListener('mousedown', close);
        };
    }, [menu]);

    /* Ret bölmesi açıldığında odak sebebe gider; yoksa klavye kullanıcısı
       açtığı alanı arar. */
    useEffect(() => {
        if (rejecting !== null) {
            reasonRef.current?.focus();
        }
    }, [rejecting]);

    const focusRow = useCallback((orderId: number) => {
        rowRefs.current.get(orderId)?.focus();
    }, []);

    /**
     * Eylemin HEDEFİ: seçim varsa ve etkin satır onun içindeyse seçim,
     * değilse yalnız etkin satır.
     *
     * Kural tek cümleyle söylenebilir olmalı, çünkü kullanıcı Enter'a
     * basmadan önce neyin olacağını bilmek zorundadır. "Seçiliyse seçim,
     * değilse üzerindeki" — bağlam menüsü ve toplu şerit de aynı cümleyi
     * kullanır.
     */
    const targetsFor = useCallback(
        (orderId: number | null): number[] => {
            if (orderId === null) {
                return [];
            }

            return selectedIds.length > 1 && selectedIds.includes(orderId)
                ? selectedIds
                : [orderId];
        },
        [selectedIds],
    );

    const runAction = useCallback(
        async (
            targets: number[],
            status: 'confirmed' | 'rejected',
            rejectionReason?: string,
        ): Promise<void> => {
            if (targets.length === 0) {
                return;
            }

            setBusy((current) => {
                const next = { ...current };

                for (const id of targets) {
                    next[id] = true;
                }

                return next;
            });

            const summary: ActionOutcome = { ok: 0, conflict: 0, failed: 0, lastStatus: null };

            /*
                SIRAYLA, paralel DEĞİL.

                Sekiz isteği aynı anda göndermek servis saatinde sunucuya
                ani bir yük bindirir ve bir çakışma olduğunda hangi siparişte
                olduğunu ayırmayı zorlaştırır. Kuyruk ekranında sekiz istek
                sıralı gitse bile bir saniyenin altındadır.
            */
            for (const id of targets) {
                const result = await changeOrderStatus(
                    workspaceId,
                    locationId,
                    id,
                    status,
                    rejectionReason,
                );

                if (result.outcome === 'ok') {
                    summary.ok += 1;
                } else if (result.outcome === 'conflict') {
                    summary.conflict += 1;
                    summary.lastStatus = result.status;
                } else {
                    summary.failed += 1;
                }
            }

            setBusy((current) => {
                const next = { ...current };

                for (const id of targets) {
                    delete next[id];
                }

                return next;
            });

            setOutcome(summary);
            setSelectedIds([]);
            /*
                Satırlar ELLE silinmez; besleme yeniden okunur. İyimser bir
                silme, sunucuda tutmayan bir onaydan sonra ekranı gerçeğe
                aykırı bırakırdı — ve o sipariş kimsenin listesinde
                görünmezdi (mobil kuyrukla aynı kural).
            */
            feed.refresh();
        },
        [feed, locationId, workspaceId],
    );

    const startRejection = useCallback(
        (targets: number[]) => {
            if (targets.length === 0) {
                return;
            }

            setMenu(null);
            setReason('');
            setReasonMissing(false);
            setRejecting(targets);
        },
        [setRejecting],
    );

    const submitRejection = useCallback(() => {
        const trimmed = reason.trim();

        if (trimmed === '') {
            /*
                Sebep zorunlu; sunucu da reddediyor. Ekranda durdurmanın
                sebebi ağdan tasarruf değil: kullanıcı ne yazacağını burada
                öğrenmeli — ve bu cümle misafirin ekranında görünür.
            */
            setReasonMissing(true);

            return;
        }

        const targets = rejecting ?? [];

        setRejecting(null);
        void runAction(targets, 'rejected', trimmed);
    }, [reason, rejecting, runAction]);

    const moveActive = useCallback(
        (delta: number, extend: boolean) => {
            if (orderIds.length === 0) {
                return;
            }

            const currentIndex = activeId === null ? -1 : orderIds.indexOf(activeId);
            const nextIndex = Math.min(
                orderIds.length - 1,
                Math.max(0, (currentIndex === -1 ? 0 : currentIndex) + delta),
            );
            const nextId = orderIds[nextIndex];

            if (nextId === undefined) {
                return;
            }

            setActiveId(nextId);
            focusRow(nextId);

            if (extend) {
                /*
                    Shift ile gezinmek SEÇİMİ BÜYÜTÜR: çıpa sabit kalır ve
                    aradaki her satır seçilir. Çıpa yoksa gezinti başladığı
                    yer çıpa olur.
                */
                const anchor = anchorId ?? activeId ?? nextId;
                const anchorIndex = orderIds.indexOf(anchor);

                if (anchorIndex !== -1) {
                    const from = Math.min(anchorIndex, nextIndex);
                    const to = Math.max(anchorIndex, nextIndex);

                    setAnchorId(anchor);
                    setSelectedIds(orderIds.slice(from, to + 1));
                }

                return;
            }

            setAnchorId(nextId);
        },
        [activeId, anchorId, focusRow, orderIds],
    );

    const toggleSelected = useCallback((orderId: number) => {
        setSelectedIds((current) =>
            current.includes(orderId)
                ? current.filter((id) => id !== orderId)
                : [...current, orderId],
        );
        setAnchorId(orderId);
    }, []);

    const selectRange = useCallback(
        (orderId: number) => {
            const anchor = anchorId ?? orderId;
            const from = orderIds.indexOf(anchor);
            const to = orderIds.indexOf(orderId);

            if (from === -1 || to === -1) {
                return;
            }

            setSelectedIds(orderIds.slice(Math.min(from, to), Math.max(from, to) + 1));
        },
        [anchorId, orderIds],
    );

    if (feed.status === 'loading') {
        return (
            <PageState
                kind="loading"
                screen="orders_queue"
                title={t('workspace.orders.queue.loading')}
            />
        );
    }

    if (feed.status === 'error') {
        return (
            <PageState
                kind="error"
                screen="orders_queue"
                title={t('workspace.orders.queue.error.title')}
                description={t('workspace.orders.queue.error.description')}
                action={
                    <button type="button" className="underline" onClick={() => feed.refresh()}>
                        {t('workspace.orders.refresh')}
                    </button>
                }
            />
        );
    }

    const timeZone = orders[0]?.timeZone ?? null;
    const emptyReason = queueEmptyReason(orders.length, acceptsOrders, planIncludesOrdering);
    const activeOrder = orders.find((order) => order.id === activeId) ?? null;
    const menuTargets = menu === null ? [] : targetsFor(menu.orderId);

    return (
        <section
            aria-label={t('workspace.orders.queue.region')}
            className="flex flex-col gap-[var(--space-4)]"
        >
            <FeedStatusLine feed={feed} timeZone={timeZone} />

            {planGateVisible(planIncludesOrdering) ? (
                <PageState
                    kind="planRestricted"
                    screen="orders_queue"
                    title={t('workspace.orders.queue.empty.plan.title')}
                    description={t('workspace.orders.queue.empty.plan.description', {
                        name: t('workspace.orders.plan.name'),
                    })}
                    {...(onNavigateToPlan
                        ? {
                              action: (
                                  <button
                                      type="button"
                                      className="underline"
                                      onClick={onNavigateToPlan}
                                  >
                                      {t('workspace.orders.plan.action')}
                                  </button>
                              ),
                          }
                        : {
                              whyNoAction: t('workspace.orders.queue.empty.plan.description', {
                                  name: t('workspace.orders.plan.name'),
                              }),
                          })}
                />
            ) : null}

            {emptyReason === 'closed' ? (
                <PageState
                    kind="prerequisite"
                    screen="orders_queue"
                    title={t('workspace.orders.queue.empty.closed.title')}
                    description={t('workspace.orders.queue.empty.closed.description')}
                    action={
                        <button type="button" className="underline" onClick={onNavigateToSettings}>
                            {t('workspace.orders.tab.settings')}
                        </button>
                    }
                />
            ) : null}

            {emptyReason === 'quiet' ? (
                <PageState
                    kind="empty"
                    screen="orders_queue"
                    title={t('workspace.orders.queue.empty.title')}
                    description={t('workspace.orders.queue.empty.description')}
                    whyNoAction={t('workspace.orders.queue.empty.description')}
                />
            ) : null}

            {orders.length > 0 ? (
                <>
                    <div className="flex flex-wrap items-center justify-between gap-[var(--space-3)]">
                        <p className="text-meta text-fg-secondary" id={listLabelId}>
                            {t('workspace.orders.queue.count', { count: String(orders.length) })}
                        </p>
                        {/*
                            KISAYOLLAR YAZILI DURUR.

                            Klavyeyle çalışan bir liste, varlığı SÖYLENMEDİKÇE
                            yoktur: kimse Enter'a basmayı denemez. Bu satır
                            telefonda hiç çizilmez, çünkü orada bu tuşlar yok
                            (`docs/153` §2).
                        */}
                        <p className="flex items-center gap-[var(--space-2)] text-meta text-fg-muted">
                            <Keyboard size={16} weight="regular" aria-hidden="true" />
                            {t('workspace.orders.queue.desktop.shortcuts')}
                        </p>
                    </div>

                    {selectedIds.length > 0 ? (
                        <div
                            role="group"
                            aria-label={t('workspace.orders.queue.desktop.bulkRegion')}
                            className="flex flex-wrap items-center gap-[var(--space-3)] rounded-[var(--radius-md)] border border-border bg-surface-accent px-[var(--space-3)] py-[var(--space-2)]"
                        >
                            <p className="text-body font-medium text-fg">
                                {t('workspace.orders.queue.desktop.selected', {
                                    count: String(selectedIds.length),
                                })}
                            </p>
                            <button
                                type="button"
                                onClick={() => void runAction(selectedIds, 'confirmed')}
                                className="flex min-h-[32px] items-center gap-1 rounded-[var(--radius-md)] border border-border bg-surface px-[var(--space-3)] text-body font-medium text-fg"
                            >
                                <Check size={16} weight="bold" aria-hidden="true" />
                                {t('workspace.orders.queue.desktop.bulkConfirm', {
                                    count: String(selectedIds.length),
                                })}
                            </button>
                            <button
                                type="button"
                                onClick={() => startRejection(selectedIds)}
                                className="flex min-h-[32px] items-center gap-1 rounded-[var(--radius-md)] border border-border-danger px-[var(--space-3)] text-body font-medium text-fg-danger"
                            >
                                <X size={16} weight="bold" aria-hidden="true" />
                                {t('workspace.orders.queue.desktop.bulkReject', {
                                    count: String(selectedIds.length),
                                })}
                            </button>
                            <button
                                type="button"
                                onClick={() => setSelectedIds([])}
                                className="min-h-[32px] px-[var(--space-2)] text-meta underline"
                            >
                                {t('workspace.orders.queue.desktop.clearSelection')}
                            </button>
                        </div>
                    ) : null}

                    {outcome !== null ? (
                        <p role="status" className="text-body text-fg-secondary">
                            {outcome.conflict > 0 && outcome.lastStatus !== null
                                ? t('workspace.orders.conflict', {
                                      status: orderStatusLabel(outcome.lastStatus),
                                  })
                                : t('workspace.orders.queue.desktop.outcome', {
                                      ok: String(outcome.ok),
                                      failed: String(outcome.failed),
                                  })}
                        </p>
                    ) : null}

                    {rejecting !== null ? (
                        <div className="flex flex-col gap-[var(--space-2)] rounded-[var(--radius-md)] border border-border-danger p-[var(--space-3)]">
                            <label
                                htmlFor={reasonFieldId}
                                className="text-body font-medium text-fg"
                            >
                                {rejecting.length > 1
                                    ? t('workspace.orders.queue.desktop.bulkReason', {
                                          count: String(rejecting.length),
                                      })
                                    : t('workspace.orders.reject.heading')}
                            </label>
                            <p className="text-meta text-fg-secondary">
                                {t('workspace.orders.reject.help')}
                            </p>
                            <textarea
                                id={reasonFieldId}
                                ref={reasonRef}
                                value={reason}
                                maxLength={280}
                                rows={2}
                                aria-label={t('workspace.orders.reject.label')}
                                aria-invalid={reasonMissing || undefined}
                                onChange={(event) => setReason(event.target.value)}
                                onKeyDown={(event) => {
                                    if (event.key === 'Escape') {
                                        setRejecting(null);
                                    }
                                }}
                                className="w-full rounded-[var(--radius-md)] border border-border bg-surface p-[var(--space-2)] text-body text-fg"
                            />
                            {reasonMissing ? (
                                <p role="alert" className="text-meta text-fg-danger">
                                    {t('workspace.orders.reject.required')}
                                </p>
                            ) : null}
                            <div className="flex gap-[var(--space-2)]">
                                <button
                                    type="button"
                                    onClick={submitRejection}
                                    className="min-h-[32px] rounded-[var(--radius-md)] border border-border-danger px-[var(--space-3)] text-body font-medium text-fg-danger"
                                >
                                    {t('workspace.orders.reject.submit')}
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setRejecting(null)}
                                    className="min-h-[32px] px-[var(--space-2)] text-body underline"
                                >
                                    {t('workspace.orders.reject.cancel')}
                                </button>
                            </div>
                        </div>
                    ) : null}

                    {/*
                        İKİ BÖLME: liste ve kalıcı ayrıntı.

                        Telefonda ikinci sütun YOKTUR ve aynı bilgi kartın
                        içinde durur. Burada ayrılmasının sebebi süs değil:
                        tarama yaparken satırların KISA kalması gerekir,
                        ayrıntı ise tek bir yerde ve hep aynı yerde okunur.
                    */}
                    <div className="grid grid-cols-[minmax(0,1fr)_20rem] gap-[var(--space-4)]">
                        <ul
                            role="listbox"
                            aria-multiselectable="true"
                            aria-labelledby={listLabelId}
                            className="flex flex-col rounded-[var(--radius-md)] border border-border"
                        >
                            {orders.map((order) => (
                                <QueueRowDesktop
                                    key={order.id}
                                    order={order}
                                    clockOffsetMs={feed.clockOffsetMs}
                                    active={order.id === activeId}
                                    selected={selectedIds.includes(order.id)}
                                    busy={busy[order.id] === true}
                                    registerRef={(node) => {
                                        if (node === null) {
                                            rowRefs.current.delete(order.id);
                                        } else {
                                            rowRefs.current.set(order.id, node);
                                        }
                                    }}
                                    onActivate={(additive, range) => {
                                        setActiveId(order.id);

                                        if (range) {
                                            selectRange(order.id);

                                            return;
                                        }

                                        if (additive) {
                                            toggleSelected(order.id);

                                            return;
                                        }

                                        setAnchorId(order.id);
                                        setSelectedIds([]);
                                    }}
                                    onConfirm={() =>
                                        void runAction(targetsFor(order.id), 'confirmed')
                                    }
                                    onReject={() => startRejection(targetsFor(order.id))}
                                    onContextMenu={(x, y) => {
                                        setActiveId(order.id);
                                        setMenu({ orderId: order.id, x, y });
                                    }}
                                    onKeyDown={(event) => {
                                        if (event.key === 'ArrowDown') {
                                            event.preventDefault();
                                            moveActive(1, event.shiftKey);

                                            return;
                                        }

                                        if (event.key === 'ArrowUp') {
                                            event.preventDefault();
                                            moveActive(-1, event.shiftKey);

                                            return;
                                        }

                                        if (event.key === 'Home') {
                                            event.preventDefault();
                                            moveActive(-orders.length, event.shiftKey);

                                            return;
                                        }

                                        if (event.key === 'End') {
                                            event.preventDefault();
                                            moveActive(orders.length, event.shiftKey);

                                            return;
                                        }

                                        if (event.key === ' ') {
                                            event.preventDefault();
                                            toggleSelected(order.id);

                                            return;
                                        }

                                        if (
                                            (event.ctrlKey || event.metaKey) &&
                                            event.key.toLowerCase() === 'a'
                                        ) {
                                            event.preventDefault();
                                            setSelectedIds(orderIds);

                                            return;
                                        }

                                        if (event.key === 'Enter') {
                                            event.preventDefault();
                                            void runAction(targetsFor(order.id), 'confirmed');

                                            return;
                                        }

                                        if (event.key.toLowerCase() === 'r') {
                                            event.preventDefault();
                                            startRejection(targetsFor(order.id));

                                            return;
                                        }

                                        /*
                                            SAĞ TIKIN KLAVYE KARŞILIĞI —
                                            WCAG 2.2 AA (`docs/153` §7).

                                            Shift+F10 her masaüstü
                                            tarayıcısında, `ContextMenu` ise
                                            o tuşu taşıyan klavyelerde
                                            çalışır. Menü yalnız fareyle
                                            açılabilseydi, klavye kullanıcısı
                                            oradaki eylemlere hiç
                                            ulaşamazdı.
                                        */
                                        if (
                                            event.key === 'ContextMenu' ||
                                            (event.shiftKey && event.key === 'F10')
                                        ) {
                                            event.preventDefault();

                                            const rect =
                                                rowRefs.current
                                                    .get(order.id)
                                                    ?.getBoundingClientRect() ?? null;

                                            setMenu({
                                                orderId: order.id,
                                                x: rect?.left ?? 0,
                                                y: rect?.bottom ?? 0,
                                            });

                                            return;
                                        }

                                        if (event.key === 'Escape') {
                                            setSelectedIds([]);
                                        }
                                    }}
                                />
                            ))}
                        </ul>

                        <aside
                            aria-label={t('workspace.orders.queue.desktop.detail.region')}
                            className="rounded-[var(--radius-md)] border border-border p-[var(--space-3)]"
                        >
                            {activeOrder === null ? (
                                <p className="text-meta text-fg-muted">
                                    {t('workspace.orders.queue.desktop.detail.empty')}
                                </p>
                            ) : (
                                <OrderDetail order={activeOrder} />
                            )}
                        </aside>
                    </div>
                </>
            ) : null}

            {menu !== null ? (
                /*
                    BAĞLAM MENÜSÜ — fareyle ve klavyeyle aynı menü.

                    Konum işaretçiden gelir; klavyeden açıldığında satırın sol
                    alt köşesinden. Ayrı bir "klavye menüsü" yazmak, iki
                    menünün zamanla ayrışması demekti.
                */
                <div
                    ref={menuRef}
                    role="menu"
                    aria-label={t('workspace.orders.queue.desktop.menu')}
                    style={{ position: 'fixed', left: menu.x, top: menu.y }}
                    className="z-50 flex min-w-[12rem] flex-col rounded-[var(--radius-md)] border border-border bg-surface p-1 shadow-lg"
                    onKeyDown={(event) => {
                        if (event.key === 'Escape') {
                            event.preventDefault();
                            const target = menu.orderId;

                            setMenu(null);
                            focusRow(target);
                        }
                    }}
                >
                    <button
                        type="button"
                        role="menuitem"
                        autoFocus
                        onClick={() => {
                            setMenu(null);
                            void runAction(menuTargets, 'confirmed');
                        }}
                        className="flex items-center gap-2 rounded-[var(--radius-sm)] px-[var(--space-3)] py-[var(--space-2)] text-start text-body text-fg hover:bg-surface-accent"
                    >
                        <Check size={16} weight="bold" aria-hidden="true" />
                        {menuTargets.length > 1
                            ? t('workspace.orders.queue.desktop.bulkConfirm', {
                                  count: String(menuTargets.length),
                              })
                            : t('workspace.orders.confirm')}
                    </button>
                    <button
                        type="button"
                        role="menuitem"
                        onClick={() => startRejection(menuTargets)}
                        className="flex items-center gap-2 rounded-[var(--radius-sm)] px-[var(--space-3)] py-[var(--space-2)] text-start text-body text-fg-danger hover:bg-surface-accent"
                    >
                        <X size={16} weight="bold" aria-hidden="true" />
                        {menuTargets.length > 1
                            ? t('workspace.orders.queue.desktop.bulkReject', {
                                  count: String(menuTargets.length),
                              })
                            : t('workspace.orders.reject')}
                    </button>
                </div>
            ) : null}
        </section>
    );
}

/** Kalıcı ayrıntı bölmesinin içeriği — seçili siparişin tamamı. */
function OrderDetail({ order }: { order: OrderFeedRow }) {
    const allergens = orderAllergens(order);

    return (
        <div className="flex flex-col gap-[var(--space-2)]">
            <h3 className="text-section font-bold text-fg">
                {t('workspace.orders.table', { name: order.tableName })}
            </h3>
            {order.areaLabel !== null && order.areaLabel !== '' ? (
                <p className="text-meta text-fg-secondary">{order.areaLabel}</p>
            ) : null}

            <ul className="flex flex-col gap-1">
                {order.lines.map((line, index) => (
                    <li key={index} className="flex justify-between gap-[var(--space-2)]">
                        <span className="text-body text-fg">
                            {t('workspace.orders.quantity', { count: String(line.quantity) })}{' '}
                            {line.productName}
                        </span>
                        <span className="text-body text-fg-secondary">
                            {lineTotal(line.lineTotalMinorAmount, line.currencyCode)}
                        </span>
                    </li>
                ))}
            </ul>

            {allergens.length > 0 ? (
                /*
                    ALERJEN siparişin O ANKİ KOPYASINDAN okunur (`docs/115`
                    K4) — ürünün bugünkü hâlinden değil. Yanlış bir alerjen
                    bilgisi bir sağlık olayıdır.
                */
                <p className="text-meta font-medium text-fg-danger">
                    {t('workspace.orders.allergens', { list: allergens.join(', ') })}
                </p>
            ) : null}

            <p className="text-body font-bold text-fg">
                {t('workspace.orders.total')}{' '}
                {lineTotal(order.totalMinorAmount, order.currencyCode)}
            </p>
        </div>
    );
}

function QueueRowDesktop({
    order,
    clockOffsetMs,
    active,
    selected,
    busy,
    registerRef,
    onActivate,
    onConfirm,
    onReject,
    onContextMenu,
    onKeyDown,
}: {
    order: OrderFeedRow;
    clockOffsetMs: number;
    active: boolean;
    selected: boolean;
    busy: boolean;
    registerRef: (node: HTMLLIElement | null) => void;
    onActivate: (additive: boolean, range: boolean) => void;
    onConfirm: () => void;
    onReject: () => void;
    onContextMenu: (x: number, y: number) => void;
    onKeyDown: (event: React.KeyboardEvent<HTMLLIElement>) => void;
}) {
    const allergens = orderAllergens(order);

    return (
        <li
            ref={registerRef}
            role="option"
            aria-selected={selected}
            /*
                ROVING TABINDEX: listeye Tab ile BİR KEZ girilir, içinde
                oklarla gezinilir. Her satır odaklanabilir olsaydı, on
                siparişlik bir kuyruk Tab tuşuna on kez basmak demekti.
            */
            tabIndex={active ? 0 : -1}
            onKeyDown={onKeyDown}
            onClick={(event) => onActivate(event.ctrlKey || event.metaKey, event.shiftKey)}
            onContextMenu={(event) => {
                event.preventDefault();
                onContextMenu(event.clientX, event.clientY);
            }}
            className={[
                'group grid grid-cols-[minmax(0,1fr)_7rem_6rem_auto] items-center gap-[var(--space-3)] border-b border-border px-[var(--space-3)] py-[var(--space-2)] last:border-b-0',
                /*
                    `hover` ANLAMLIDIR ve yalnız burada: dokunmada imleç
                    yoktur, bu yüzden mobil kuyrukta eylemler her zaman
                    görünür (`docs/153` §2).
                */
                'hover:bg-surface-accent',
                selected ? 'bg-surface-accent' : '',
                active ? 'outline outline-2 -outline-offset-2 outline-[var(--color-border)]' : '',
            ].join(' ')}
        >
            <span className="truncate text-body font-medium text-fg">
                {t('workspace.orders.table', { name: order.tableName })}
                {allergens.length > 0 ? (
                    <span className="ms-2 text-meta font-medium text-fg-danger">
                        {t('workspace.orders.allergens', { list: allergens.join(', ') })}
                    </span>
                ) : null}
            </span>

            <span className="text-meta text-fg-secondary">
                {waitingLabel(waitingMinutes(order.placedAt, clockOffsetMs))}
            </span>

            <span className="text-body text-fg-secondary">
                {lineTotal(order.totalMinorAmount, order.currencyCode)}
            </span>

            {/*
                SATIR EYLEMLERİ `hover` ya da odakla görünür.

                `focus-within` şart: yalnız `hover` yazsaydım, klavyeyle
                gezen kullanıcı düğmeleri hiç göremezdi — ve bu ekranın
                asıl kullanıcısı klavyeyle çalışıyor.
            */}
            <span className="flex gap-1 opacity-0 group-hover:opacity-100 group-focus-within:opacity-100">
                <button
                    type="button"
                    tabIndex={-1}
                    disabled={busy}
                    onClick={(event) => {
                        event.stopPropagation();
                        onConfirm();
                    }}
                    aria-label={t('workspace.orders.confirm')}
                    className="rounded-[var(--radius-sm)] border border-border p-1 text-fg"
                >
                    <Check size={16} weight="bold" aria-hidden="true" />
                </button>
                <button
                    type="button"
                    tabIndex={-1}
                    disabled={busy}
                    onClick={(event) => {
                        event.stopPropagation();
                        onReject();
                    }}
                    aria-label={t('workspace.orders.reject')}
                    className="rounded-[var(--radius-sm)] border border-border-danger p-1 text-fg-danger"
                >
                    <X size={16} weight="bold" aria-hidden="true" />
                </button>
                <CursorClick
                    size={16}
                    weight="regular"
                    aria-hidden="true"
                    className="self-center text-fg-muted"
                />
            </span>
        </li>
    );
}

export default OrderQueueDesktop;
