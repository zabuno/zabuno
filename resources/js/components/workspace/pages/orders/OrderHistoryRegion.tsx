import { useEffect, useState } from 'react';

import { t } from '../../../../i18n/workspace';
import { PageState } from '../shared/PageState';
import { lineTotal, orderStatusLabel } from './orderPresentation';
import type { OrderFeedRow } from './useOrderFeed';

/**
 * SİPARİŞ GEÇMİŞİ — `docs/115` S6, Y2 (FF-179).
 *
 * "Silinmez; denetim izi gibi kalıcı." Bu ekranda hiçbir silme, düzeltme ya
 * da gizleme eylemi YOKTUR ve bu bir eksiklik değil, sözleşmenin kendisi.
 *
 * YOKLAMA YOK. Geçmiş "şu anda ne var" sorusu değildir; on saniyede bir
 * tazelenen bir denetim izi, okunurken ayağının altından kayardı. Sayfa
 * açıldığında bir kez okunur.
 */
export type OrderHistoryRegionProps = {
    workspaceId: number;
    locationId: number;
};

type Status = 'loading' | 'ready' | 'error';

type HistoryPage = {
    data: OrderFeedRow[];
    page: number;
    pageCount: number;
};

export function OrderHistoryRegion({ workspaceId, locationId }: OrderHistoryRegionProps) {
    const [status, setStatus] = useState<Status>('loading');
    const [page, setPage] = useState(1);
    const [result, setResult] = useState<HistoryPage>({ data: [], page: 1, pageCount: 1 });

    useEffect(() => {
        let cancelled = false;

        void (async () => {
            /*
                "Yükleniyor" İSTEĞİN durumudur, çizimin değil — bu yüzden
                etkinin gövdesinde değil, isteğin başında yazılır. Etki
                gövdesinde çağrılan bir `setState`, çizimin hemen ardından
                ikinci bir çizim tetikler (`react-hooks/set-state-in-effect`);
                sayfa değiştirme gibi sık bir işte bu, her tıklamada iki kez
                çizilen bir liste demektir.
            */
            setStatus('loading');

            try {
                const response = await fetch(
                    `/api/workspaces/${String(workspaceId)}/locations/${String(locationId)}/orders/history?page=${String(page)}`,
                    { headers: { Accept: 'application/json' } },
                );

                if (cancelled) {
                    return;
                }

                if (!response.ok) {
                    setStatus('error');

                    return;
                }

                const body = (await response.json()) as HistoryPage;

                if (cancelled) {
                    return;
                }

                setResult({
                    data: body.data ?? [],
                    page: body.page ?? 1,
                    pageCount: body.pageCount ?? 1,
                });
                setStatus('ready');
            } catch {
                if (!cancelled) {
                    setStatus('error');
                }
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [workspaceId, locationId, page]);

    if (status === 'loading') {
        return (
            <PageState
                kind="loading"
                screen="orders_history"
                title={t('workspace.orders.history.loading')}
            />
        );
    }

    if (status === 'error') {
        return (
            <PageState
                kind="error"
                screen="orders_history"
                title={t('workspace.orders.history.error.title')}
                description={t('workspace.orders.history.error.description')}
                action={
                    <button type="button" className="underline" onClick={() => setPage(page)}>
                        {t('workspace.orders.refresh')}
                    </button>
                }
            />
        );
    }

    if (result.data.length === 0) {
        return (
            <PageState
                kind="empty"
                screen="orders_history"
                title={t('workspace.orders.history.empty.title')}
                description={t('workspace.orders.history.empty.description')}
                whyNoAction={t('workspace.orders.history.description')}
            />
        );
    }

    return (
        <section
            aria-label={t('workspace.orders.history.region')}
            className="flex flex-col gap-[var(--space-3)]"
        >
            <h3 className="text-section font-bold text-fg">
                {t('workspace.orders.history.heading')}
            </h3>
            {/* Sözleşme ekranda YAZILI durur: kimse bu listeden bir akşamı
                silemez. */}
            <p className="text-meta text-fg-secondary">
                {t('workspace.orders.history.description')}
            </p>

            <ul className="flex flex-col gap-[var(--space-2)]">
                {result.data.map((order) => (
                    <li
                        key={order.id}
                        className="rounded-[var(--radius-md)] border border-border bg-surface p-[var(--space-3)]"
                    >
                        <div className="flex flex-wrap justify-between gap-[var(--space-2)]">
                            <span className="text-body font-medium text-fg">
                                {t('workspace.orders.table', { name: order.tableName })}
                            </span>
                            <span className="text-body text-fg-secondary">
                                {orderStatusLabel(order.status)}
                            </span>
                            <span className="text-body text-fg">
                                {lineTotal(order.totalMinorAmount, order.currencyCode)}
                            </span>
                        </div>
                        {order.rejectionReason !== null && order.rejectionReason !== '' ? (
                            /*
                                Ret sebebi geçmişte de durur: "bu sipariş
                                neden reddedildi" sorusu servis bittikten
                                sonra sorulur ve o an garson evine gitmiştir.
                            */
                            <p className="mt-[var(--space-2)] text-meta text-fg-secondary">
                                {t('workspace.orders.history.rejected', {
                                    reason: order.rejectionReason,
                                })}
                            </p>
                        ) : null}
                    </li>
                ))}
            </ul>

            {result.pageCount > 1 ? (
                <div className="flex items-center gap-[var(--space-3)]">
                    <button
                        type="button"
                        className="min-h-[44px] px-[var(--space-2)] text-body underline"
                        disabled={result.page <= 1}
                        onClick={() => setPage(result.page - 1)}
                    >
                        {t('workspace.orders.history.previous')}
                    </button>
                    <p className="text-meta text-fg-secondary">
                        {t('workspace.orders.history.page', {
                            page: String(result.page),
                            pageCount: String(result.pageCount),
                        })}
                    </p>
                    <button
                        type="button"
                        className="min-h-[44px] px-[var(--space-2)] text-body underline"
                        disabled={result.page >= result.pageCount}
                        onClick={() => setPage(result.page + 1)}
                    >
                        {t('workspace.orders.history.next')}
                    </button>
                </div>
            ) : null}
        </section>
    );
}

export default OrderHistoryRegion;
