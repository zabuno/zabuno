import { ClipboardText } from '@phosphor-icons/react';
import { lazy, Suspense, type ReactNode } from 'react';

import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';
import type { WorkspaceSectionDescriptor } from '../shell/WorkspaceSectionRegistry';
import { ordersPropsFromContext } from './orders/ordersSectionProps';

/*
    EKRAN İSTENDİĞİNDE İNER (FF-97 deseni). Kaydın METADATASI (ad, ikon,
    sıra, izin) kenar çubuğunu çizmek için eager kalır; yalnız ÇİZİM
    ertelenir — sipariş almayan bir restoran bu ekranın kodunu hiç indirmez.
*/
const OrdersPage = lazy(async () => ({
    default: (await import('./OrdersPage')).OrdersPage,
}));

/*
    BU KAYIT CİHAZ TANIMAZ ve tanımamalı (`docs/153` §5).

    Kayıtlar `import.meta.glob` ile TOPLUCA ve EAGER okunur, yani bu dosya
    iki pakete birden girer. Burada masaüstü kuyruğu adıyla anılsaydı, kodu
    telefona da inerdi — çizilmese bile. Masaüstü çizimi bu yüzden kayıtta
    değil, cihaz paketindeki sayfa haritasında durur.
*/
function render(ctx: WorkspaceSectionRuntimeContext): ReactNode {
    return (
        <Suspense fallback={null}>
            <OrdersPage {...ordersPropsFromContext(ctx)} />
        </Suspense>
    );
}

const ordersSection: WorkspaceSectionDescriptor = {
    key: 'orders',
    path: 'orders',
    /*
        SIRA 12 ve bu bilinçli bir GERİ ÇEKİLME.

        Siparişler günlük operasyondur ve ideal bilgi mimarisinde Menüler'in
        hemen yanında dururdu. Ama sıra alanı bu depoda başka ekranların
        testleriyle donmuş durumda; sırf yerleşim için on iki kaydı yeniden
        numaralamak, bu paketin sorusuyla ilgisi olmayan on iki dosyayı
        değiştirmek olurdu. Yeri, sipariş akışı ölçülüp sahibin günlük
        kullanımı görüldükten sonra tek bir pakette düzeltilir.
    */
    order: 12,
    labelKey: 'workspace.shell.nav.orders',
    icon: <ClipboardText size={18} weight="regular" />,
    /*
        `order.view` (`docs/115` §4). Editör bu bölümü hiç görmez: içerik
        düzenler, servis anının işi değildir. Mutfak görür — monitör onun.
    */
    permission: 'order.view',
    group: 'primary',
    render,
};

export default ordersSection;
