import type { ReactNode } from 'react';

import { OrdersPage } from '../OrdersPage';
import { ordersPropsFromContext } from '../orders/ordersSectionProps';
import type { WorkspaceSectionRuntimeContext } from '../../WorkspaceApp';
import { OrderQueueDesktop } from './OrderQueueDesktop';

/**
 * SİPARİŞLER ekranının MASAÜSTÜ bileşimi — `docs/149` §6.
 *
 * Sayfanın kendisi (sekmeler, izin kapıları, şalter okuması) PAYLAŞILANdIR
 * ve buraya kopyalanmaz: iki kopya sekme mantığı, yarın bir izin
 * değiştiğinde yalnız birinde düzeltilirdi. Değişen tek şey KUYRUĞUN
 * çizimidir ve o da bir çizici olarak geçirilir.
 *
 * Yani bu dosya bir sayfa DEĞİL, bir BİLEŞİMdir: paylaşılan sayfa + bu
 * cihazın kuyruğu. Masaüstüne taşınacak sonraki ekranlar da aynı biçimi
 * alır — ortak olan paylaşılır, ayrışan enjekte edilir (`docs/149` §4).
 */
export function OrdersScreenDesktop({ ctx }: { ctx: WorkspaceSectionRuntimeContext }): ReactNode {
    return (
        <OrdersPage
            {...ordersPropsFromContext(ctx)}
            renderQueue={(queue) => <OrderQueueDesktop {...queue} />}
        />
    );
}

export default OrdersScreenDesktop;
