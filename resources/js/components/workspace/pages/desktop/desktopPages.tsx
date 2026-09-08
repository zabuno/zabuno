import { lazy, Suspense } from 'react';

import type { WorkspacePageOverrideMap } from '../pageOverride';

/**
 * MASAÜSTÜ PAKETİNİN KENDİ SAYFALARI — `docs/149` §5.
 *
 * Bu dosyayı YALNIZ `workspace.desktop.tsx` içeri alır. Telefon buraya hiç
 * ulaşmaz, dolayısıyla altındaki hiçbir modülün baytı mobil pakete girmez —
 * ve `scripts/adaptive-bundle-gate` bunu her koşuda kanıtlar
 * (`pages/desktop/**` klasör örüntüsü).
 *
 * `desktopInspectors` ile aynı desenin bölümün TAMAMI için olanı. Fark
 * şurada: panel bir bölümün YANINDA durur ve olmadığında iş yine yapılır;
 * buradaki kayıt bölümün KENDİSİNİ değiştirir.
 *
 * ## Neden `lazy`
 *
 * Harita giriş noktasından statik olarak içeri alınır; ekranlar da statik
 * alınsaydı hepsi masaüstü GİRİŞ yığınına binerdi — yani sipariş almayan
 * bir restoran bile sipariş kuyruğunun kodunu ilk açılışta indirirdi.
 * Bölüm kayıtlarındaki kural burada da geçerli: metadata eager, ÇİZİM
 * ertelenir (FF-137).
 *
 * ## Buraya ne yazılır
 *
 * Yalnız GERÇEKTEN ayrışan ekranlar. Bir ekranın masaüstünde farkı yoksa
 * buraya yazılmaz ve paylaşılan kaydıyla çizilir — boş bir kayıt eklemek,
 * bakılacak ikinci bir dosya yaratıp hiçbir şey kazandırmazdı.
 */
const OrdersScreenDesktop = lazy(async () => ({
    default: (await import('./OrdersScreenDesktop')).OrdersScreenDesktop,
}));

export const desktopPages: WorkspacePageOverrideMap = {
    /*
        SİPARİŞ KUYRUĞU — masaüstüne taşınan İLK ekran (`docs/149` §6).

        Gerekçe orada yazılı: aynı veriye bakan iki farklı iş var. Salondaki
        garson tek siparişle ilgilenir; kasadaki kişi aynı anda ona bakar,
        klavyesi vardır ve aynı işi arka arkaya yapar.
    */
    orders: (ctx) => (
        /*
            Bekleme metni YOK: sayfa zaten kendi yükleme durumunu anlatır ve
            parça milisaniyeler içinde iner. İki katmanlı "yükleniyor" yazısı,
            kullanıcıya bir şeyin takıldığını düşündürür (FF-137 ile aynı
            karar).
        */
        <Suspense fallback={null}>
            <OrdersScreenDesktop ctx={ctx} />
        </Suspense>
    ),
};

export default desktopPages;
