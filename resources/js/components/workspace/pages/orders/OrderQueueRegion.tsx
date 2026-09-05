import { useCallback, useId, useState } from 'react';
import { Check, X, Warning } from '@phosphor-icons/react';

import { t } from '../../../../i18n/workspace';
import { PageState } from '../shared/PageState';
import { changeOrderStatus } from './changeOrderStatus';
import {
    lineTotal,
    orderAllergens,
    orderStatusLabel,
    updatedAtLabel,
    waitingLabel,
    waitingMinutes,
} from './orderPresentation';
import { useOrderFeed, type OrderFeedRow } from './useOrderFeed';

/**
 * GARSON KUYRUĞU — `docs/115` S4, hikâyeler G1–G5 (FF-179).
 *
 * Sahibin cümlesi: "Kuyruğa düşer. Garson panelden onaylar." Bu ekran o
 * cümlenin tamamıdır ve tek bir işi vardır: masada oturan insanı GÖREN
 * kişiye, misafirin ne istediğini eksiksiz göstermek. Garsonun gözü bu
 * ürünün tek insani kapısıdır; ekran onun yerine karar vermez, ona bakması
 * gerekeni verir.
 *
 * RET SEBEPSİZ OLAMAZ ve bu bir form doğrulaması değil, ürün kararıdır:
 * sebep misafirin ekranında görünür. Sebepsiz bir ret ona yalnız "olmadı"
 * der ve neyi düzelteceğini bilmez.
 */
export type OrderQueueRegionProps = {
    workspaceId: number;
    locationId: number;
    /**
     * Şube sipariş alıyor mu? Boş liste iki farklı şey olabilir ve ikisinin
     * çıkış yolu farklıdır: sessiz bir akşam beklemeyi, kapalı bir şalter
     * ise Ayarlar sekmesini gerektirir (`docs/59`).
     */
    acceptsOrders: boolean | null;
    onNavigateToSettings: () => void;
};

type RowStage =
    | { kind: 'idle' }
    | { kind: 'busy' }
    | { kind: 'rejecting' }
    | { kind: 'conflict'; status: string }
    | { kind: 'failed' };

export function OrderQueueRegion({
    workspaceId,
    locationId,
    acceptsOrders,
    onNavigateToSettings,
}: OrderQueueRegionProps) {
    const feed = useOrderFeed(
        `/api/workspaces/${String(workspaceId)}/locations/${String(locationId)}/orders/pending`,
    );

    const [stages, setStages] = useState<Record<number, RowStage>>({});
    const [reasons, setReasons] = useState<Record<number, string>>({});
    const [reasonErrors, setReasonErrors] = useState<Record<number, boolean>>({});

    const setStage = useCallback((orderId: number, stage: RowStage) => {
        setStages((current) => ({ ...current, [orderId]: stage }));
    }, []);

    const act = useCallback(
        async (
            orderId: number,
            status: 'confirmed' | 'rejected',
            reason?: string,
        ): Promise<void> => {
            setStage(orderId, { kind: 'busy' });

            const result = await changeOrderStatus(
                workspaceId,
                locationId,
                orderId,
                status,
                reason,
            );

            if (result.outcome === 'ok') {
                /*
                    Satır ekrandan ELLE silinmez; besleme yeniden okunur.
                    İyimser bir silme, sunucuda tutmayan bir onaydan sonra
                    ekranı gerçeğe aykırı bırakırdı — ve o sipariş kimsenin
                    listesinde görünmezdi.
                */
                setStage(orderId, { kind: 'idle' });
                feed.refresh();

                return;
            }

            if (result.outcome === 'conflict') {
                setStage(orderId, { kind: 'conflict', status: result.status });
                feed.refresh();

                return;
            }

            setStage(orderId, { kind: 'failed' });
        },
        [feed, locationId, setStage, workspaceId],
    );

    const submitRejection = useCallback(
        (orderId: number): void => {
            const reason = (reasons[orderId] ?? '').trim();

            if (reason === '') {
                // Sebep zorunlu; sunucu da reddediyor. Ekranda durdurmanın
                // sebebi ağdan tasarruf değil: garson ne yazacağını burada
                // öğrenmeli.
                setReasonErrors((current) => ({ ...current, [orderId]: true }));

                return;
            }

            setReasonErrors((current) => ({ ...current, [orderId]: false }));
            void act(orderId, 'rejected', reason);
        },
        [act, reasons],
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

    const timeZone = feed.orders[0]?.timeZone ?? null;

    return (
        <section
            aria-label={t('workspace.orders.queue.region')}
            className="flex flex-col gap-[var(--space-4)]"
        >
            <FeedStatusLine feed={feed} timeZone={timeZone} />

            {feed.orders.length === 0 ? (
                acceptsOrders === false ? (
                    <PageState
                        kind="prerequisite"
                        screen="orders_queue"
                        title={t('workspace.orders.queue.empty.closed.title')}
                        description={t('workspace.orders.queue.empty.closed.description')}
                        action={
                            <button
                                type="button"
                                className="underline"
                                onClick={onNavigateToSettings}
                            >
                                {t('workspace.orders.tab.settings')}
                            </button>
                        }
                    />
                ) : (
                    <PageState
                        kind="empty"
                        screen="orders_queue"
                        title={t('workspace.orders.queue.empty.title')}
                        description={t('workspace.orders.queue.empty.description')}
                        whyNoAction={t('workspace.orders.queue.empty.description')}
                    />
                )
            ) : (
                <>
                    <p className="text-meta text-fg-secondary">
                        {t('workspace.orders.queue.count', {
                            count: String(feed.orders.length),
                        })}
                    </p>
                    <ul className="flex flex-col gap-[var(--space-3)]">
                        {feed.orders.map((order) => (
                            <QueueRow
                                key={order.id}
                                order={order}
                                clockOffsetMs={feed.clockOffsetMs}
                                stage={stages[order.id] ?? { kind: 'idle' }}
                                reason={reasons[order.id] ?? ''}
                                reasonMissing={reasonErrors[order.id] === true}
                                onReasonChange={(value) =>
                                    setReasons((current) => ({ ...current, [order.id]: value }))
                                }
                                onConfirm={() => void act(order.id, 'confirmed')}
                                onStartRejection={() => setStage(order.id, { kind: 'rejecting' })}
                                onCancelRejection={() => setStage(order.id, { kind: 'idle' })}
                                onSubmitRejection={() => submitRejection(order.id)}
                            />
                        ))}
                    </ul>
                </>
            )}
        </section>
    );
}

/**
 * "Ne zaman güncellendi" satırı — `docs/115` §6.
 *
 * Ekran "canlı" demez. Mutfakta donmuş bir ekranla dolu bir ekran aynı
 * görünür; tek ayırt edici şey bu satırdır.
 */
export function FeedStatusLine({
    feed,
    timeZone,
}: {
    feed: { lastUpdatedAt: Date | null; stale: boolean; refresh: () => void };
    timeZone: string | null;
}) {
    return (
        <div className="flex flex-wrap items-center gap-[var(--space-3)]">
            <p className="text-meta text-fg-muted" data-testid="orders-updated-at">
                {updatedAtLabel(feed.lastUpdatedAt, timeZone)}
            </p>
            {feed.stale ? (
                /*
                    `status`, `alert` DEĞİL: ortada bozulmuş bir şey yok,
                    yalnız son deneme tutmadı ve ekrandaki liste eskimiş
                    olabilir. `alert` ekran okuyucuyu bölerdi ve gerçek
                    uyarının değerini düşürürdü (`docs/59`).
                */
                <p role="status" className="flex items-center gap-1 text-meta text-fg-secondary">
                    <Warning size={16} weight="regular" aria-hidden="true" />
                    {t('workspace.orders.stale')}
                </p>
            ) : null}
            <button type="button" className="text-meta underline" onClick={() => feed.refresh()}>
                {t('workspace.orders.refresh')}
            </button>
        </div>
    );
}

function QueueRow({
    order,
    clockOffsetMs,
    stage,
    reason,
    reasonMissing,
    onReasonChange,
    onConfirm,
    onStartRejection,
    onCancelRejection,
    onSubmitRejection,
}: {
    order: OrderFeedRow;
    clockOffsetMs: number;
    stage: RowStage;
    reason: string;
    reasonMissing: boolean;
    onReasonChange: (value: string) => void;
    onConfirm: () => void;
    onStartRejection: () => void;
    onCancelRejection: () => void;
    onSubmitRejection: () => void;
}) {
    const reasonFieldId = useId();
    const allergens = orderAllergens(order);
    const busy = stage.kind === 'busy';

    return (
        <li className="rounded-[var(--radius-lg)] border border-border bg-surface p-[var(--space-4)]">
            <div className="flex flex-wrap items-baseline justify-between gap-[var(--space-2)]">
                <h3 className="text-section font-bold text-fg">
                    {t('workspace.orders.table', { name: order.tableName })}
                    {order.areaLabel !== null && order.areaLabel !== '' ? (
                        <span className="ms-2 text-meta font-normal text-fg-secondary">
                            {order.areaLabel}
                        </span>
                    ) : null}
                </h3>
                {/*
                    BEKLEME SÜRESİ garsonun hangi masaya önce gideceğini
                    belirler; bu yüzden sunucunun saatiyle hesaplanır
                    (`orderPresentation.waitingMinutes`).
                */}
                <p className="text-meta text-fg-secondary">
                    {waitingLabel(waitingMinutes(order.placedAt, clockOffsetMs))}
                </p>
            </div>

            <ul className="mt-[var(--space-3)] flex flex-col gap-[var(--space-2)]">
                {order.lines.map((line, index) => (
                    <li key={index} className="flex flex-wrap justify-between gap-[var(--space-2)]">
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
                    ALERJEN SATIRIN İÇİNDEDİR ve siparişe KOPYALANMIŞTIR
                    (`docs/115` K4). Yanlış bir alerjen bilgisi bir sağlık
                    olayıdır; bu yüzden ürüne değil, siparişin o andaki
                    kopyasına bakılır.
                */
                <p className="mt-[var(--space-2)] text-meta font-medium text-fg-danger">
                    {t('workspace.orders.allergens', { list: allergens.join(', ') })}
                </p>
            ) : null}

            <p className="mt-[var(--space-3)] text-body font-bold text-fg">
                {t('workspace.orders.total')}{' '}
                {lineTotal(order.totalMinorAmount, order.currencyCode)}
            </p>

            {stage.kind === 'conflict' ? (
                <p role="status" className="mt-[var(--space-3)] text-body text-fg-secondary">
                    {t('workspace.orders.conflict', {
                        status: orderStatusLabel(stage.status),
                    })}
                </p>
            ) : null}

            {stage.kind === 'failed' ? (
                <p role="alert" className="mt-[var(--space-3)] text-body text-fg-danger">
                    {t('workspace.orders.actionFailed')}
                </p>
            ) : null}

            {stage.kind === 'rejecting' ? (
                <div className="mt-[var(--space-3)] flex flex-col gap-[var(--space-2)]">
                    <label htmlFor={reasonFieldId} className="text-body font-medium text-fg">
                        {t('workspace.orders.reject.heading')}
                    </label>
                    {/* Garson ne yazdığını bilerek yazsın: bu cümle misafirin
                        ekranında görünür. */}
                    <p className="text-meta text-fg-secondary">
                        {t('workspace.orders.reject.help')}
                    </p>
                    <textarea
                        id={reasonFieldId}
                        value={reason}
                        maxLength={280}
                        rows={2}
                        aria-label={t('workspace.orders.reject.label')}
                        aria-invalid={reasonMissing || undefined}
                        onChange={(event) => onReasonChange(event.target.value)}
                        className="w-full rounded-[var(--radius-md)] border border-border bg-surface p-[var(--space-2)] text-body text-fg"
                    />
                    {reasonMissing ? (
                        <p role="alert" className="text-meta text-fg-danger">
                            {t('workspace.orders.reject.required')}
                        </p>
                    ) : null}
                    <div className="flex flex-wrap gap-[var(--space-2)]">
                        <button
                            type="button"
                            onClick={onSubmitRejection}
                            className="min-h-[44px] rounded-[var(--radius-md)] border border-border-danger px-[var(--space-4)] text-body font-medium text-fg-danger"
                        >
                            {t('workspace.orders.reject.submit')}
                        </button>
                        <button
                            type="button"
                            onClick={onCancelRejection}
                            className="min-h-[44px] px-[var(--space-2)] text-body underline"
                        >
                            {t('workspace.orders.reject.cancel')}
                        </button>
                    </div>
                </div>
            ) : (
                <div className="mt-[var(--space-4)] flex flex-wrap gap-[var(--space-2)]">
                    {/*
                        Hedefler 44 pikselden büyük: servis anında eller
                        meşguldür ve ekrana bakmadan basılır (`docs/115` K3).
                    */}
                    <button
                        type="button"
                        onClick={onConfirm}
                        disabled={busy}
                        className="flex min-h-[44px] items-center gap-2 rounded-[var(--radius-md)] border border-border bg-surface-accent px-[var(--space-4)] text-body font-medium text-fg"
                    >
                        <Check size={20} weight="bold" aria-hidden="true" />
                        {busy
                            ? t('workspace.orders.confirm.pending')
                            : t('workspace.orders.confirm')}
                    </button>
                    <button
                        type="button"
                        onClick={onStartRejection}
                        disabled={busy}
                        className="flex min-h-[44px] items-center gap-2 rounded-[var(--radius-md)] border border-border px-[var(--space-4)] text-body font-medium text-fg"
                    >
                        <X size={20} weight="bold" aria-hidden="true" />
                        {t('workspace.orders.reject')}
                    </button>
                </div>
            )}
        </li>
    );
}

export default OrderQueueRegion;
