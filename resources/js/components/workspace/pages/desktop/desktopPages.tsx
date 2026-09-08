import { lazy, Suspense } from 'react';

import type { WorkspacePageOverrideMap } from '../pageOverride';

/**
 * MASAÜSTÜ PAKETİNİN KENDİ SAYFALARI — `docs/153` §5.
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

const MediaScreenDesktop = lazy(async () => ({
    default: (await import('./MediaScreenDesktop')).MediaScreenDesktop,
}));

const TeamScreenDesktop = lazy(async () => ({
    default: (await import('./TeamScreenDesktop')).TeamScreenDesktop,
}));

const RatingsScreenDesktop = lazy(async () => ({
    default: (await import('./RatingsScreenDesktop')).RatingsScreenDesktop,
}));

export const desktopPages: WorkspacePageOverrideMap = {
    /*
        SİPARİŞ KUYRUĞU — masaüstüne taşınan İLK ekran (`docs/153` §6).

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

    /*
        MEDYA KÜTÜPHANESİ — `docs/151` §B, `docs/153` §9'da "yüksek" değerli
        iki ekrandan biri.

        Gerekçe: elli dosyalık bir kütüphanede sahibin işi TARAMAdır ve tek
        sütunlu bir liste bunu yaptırmaz. Izgara aynı anda çok dosya
        gösterir, kalıcı bölme seçilenin ayrıntısını yanında tutar, toplu
        seçim on dosyayı tek işlemde siler.
    */
    media: (ctx) => (
        <Suspense fallback={null}>
            <MediaScreenDesktop ctx={ctx} />
        </Suspense>
    ),

    /*
        EKİP — `docs/153` §9'un ikinci "yüksek"i.

        Gerekçe: ad, e-posta ve rol aynı satırda okunur ve sezon başında
        sekiz kişinin rolü tek işlemde değişir. Telefonda üçü alt alta durur
        çünkü orada üç sütunluk yer yoktur.
    */
    team: (ctx) => (
        <Suspense fallback={null}>
            <TeamScreenDesktop ctx={ctx} />
        </Suspense>
    ),

    /*
        PUANLAR — üçüncü ekran, "orta" bandın en ucuz gerçek kazancı
        (`docs/151` §B'de gerekçesi sayılarıyla yazılı).

        Gerekçe: kırk ürünlük bir menüde "hangisi en düşük?" sorusu telefonda
        ancak kaydırarak cevaplanır. Sıralama onu tek tıkla cevaplar, kalıcı
        bölme ise yanıtı liste kaybolmadan yazdırır.

        MENÜ DÜZENLEME BURADA YOK ve sebebi `docs/151` §B'de yazılı:
        `MenuCatalogWorkspace` 4.613 satırlık tek bir dosyadır ve masaüstü
        sürümünün değeri tam olarak o dosyanın veri katmanına bağlıdır. Önce
        `useMenuCatalog` çıkarılmadan yazılacak bir masaüstü menüsü ya iş
        mantığını ikinci kez yazardı (`docs/153` §4 gereği yasak) ya da mobil
        menüyü riske atardı.
    */
    ratings: (ctx) => (
        <Suspense fallback={null}>
            <RatingsScreenDesktop ctx={ctx} />
        </Suspense>
    ),
};

export default desktopPages;
