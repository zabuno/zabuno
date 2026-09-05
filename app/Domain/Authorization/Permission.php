<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

enum Permission: string
{
    case WorkspaceView = 'workspace.view';
    case WorkspaceManage = 'workspace.manage';
    case MenuView = 'menu.view';
    case MenuManage = 'menu.manage';
    case MenuPublish = 'menu.publish';
    /*
        MENÜNÜN İKİ DAR EKSENİ (`docs/109` §6.4, Mutfak rolü).

        `menu.manage` bir menüyü YENİDEN YAZMA iznidir: fiyat, ürün, kategori,
        ad, sıra, silme. Ama menünün üstünde iki tane daha var ki ikisi de
        menüyü değiştirmez, yalnız BUGÜNÜN gerçeğini söyler: bir üründe hangi
        alerjen olduğu ve bugün bitip bitmediği.

        İkisini `menu.manage` içinde bırakmak, "alerjeni düzeltebilsin ama
        fiyata dokunamasın" cümlesinin bu depoda SÖYLENEMEMESİ demekti — ve
        o cümle mutfaktaki insanın tarifidir. Ayrı yaşamalarının sebebi bu;
        yeni bir yetki yaratmıyorlar, var olanı bölüyorlar.

        `menu.allergens.manage` misafirin sağlığını ilgilendirir ve yanlışı
        geri alınabilir; `menu.stock.manage` yayın gerektirmeden misafire
        yansır (`docs/82`) ve ertesi gün zaten sıfırlanır. İkisi de düşük
        sonuçlu, yüksek sıklıklı işlerdir — dar bir rolün taşıyabileceği tam
        olarak bu tür işlerdir.
    */
    case MenuAllergensManage = 'menu.allergens.manage';
    case MenuStockManage = 'menu.stock.manage';
    case QrView = 'qr.view';
    case QrCreate = 'qr.create';
    case QrDisable = 'qr.disable';
    case QrDesignManage = 'qr.design.manage';
    case AnalyticsView = 'analytics.view';
    case BillingView = 'billing.view';
    case BillingManage = 'billing.manage';
    case SecurityEvidenceView = 'security.evidence.view';
    /*
        Medya (`docs/49` Faz 7 madde 3). `media.manage` yükler/siler/geri
        alır/yeniden üretir; `media.download_original` AYRI bir izindir —
        sahibin kararıyla bugün her role verilir ("tamamen serbest"), ama
        bir gün daraltmak tek satır olsun diye ayrı yaşar.
    */
    case MediaManage = 'media.manage';
    case MediaDownloadOriginal = 'media.download_original';
    /*
        SİPARİŞİN DÖRT EKSENİ (`docs/115` §4).

        Plan tek cümleyle donduruyor: "Yeni bir izin ekseni gerekiyor; yeni
        bir rol GEREKMİYOR." Dördü de var olan rollerin üstüne biner ve her
        biri servis anındaki AYRI bir insana karşılık gelir:

        - `order.view`     — siparişi GÖRMEK. Görmek yapmak değildir: mutfak
                             da görür, ama onaylayamaz.
        - `order.confirm`  — misafirin TALEBİNİ bir İŞE çevirmek. Bu ürünün
                             tek insani kapısı; masada kimin oturduğunu gören
                             kişinin kararıdır.
        - `order.kitchen`  — onaylanmış işi ilerletmek: hazırlanıyor, hazır.
                             Ocağın kendi cümlesi.
        - `order.settings` — hizmeti açıp kapatmak. Bir işletme kararıdır ve
                             bilerek en dar eksendir; yalnız Sahip taşır.

        Dördü tek bir `order.manage` olsaydı, aşçının onayladığı ya da
        garsonun servisi kapattığı bir ürün çıkardı — ve bu iki cümle de
        sahibin tarifinde YOK.
    */
    case OrderView = 'order.view';
    case OrderConfirm = 'order.confirm';
    case OrderKitchen = 'order.kitchen';
    case OrderSettings = 'order.settings';

    /*
        PUAN EKSENİ (`docs/116` §4) — beşinci soru: "misafirin ölçümü
        karşısında kim ne yapabilir?"

        - `rating.view`  — puanları GÖRMEK. Bir ölçüm okuma yüzeyidir ve
                           izleyici kitlesi `analytics.view` ile aynıdır:
                           hangi tabağın ne aldığını görmek içerik
                           düzenleyen herkesin işine yarar.
        - `rating.reply` — puana YANIT VERMEK. Yanıt misafirin gördüğü
                           menüde durur, yani MARKANIN SESİDİR; menüyü
                           yayınlayamayan bir rol restoran adına cümle de
                           kuramaz.

        BU EKSENDE ÜÇÜNCÜ BİR İZİN — `rating.delete` — YOKTUR ve olmayacak.
        `docs/116` §4: *"Sahip puanı silemez. Yanıt verebilir, kaldıramaz —
        silebiliyorsa ortalama bir pazarlama sayısıdır."* İzin listesinde
        böyle bir satırın bulunmaması, o kuralın yetki katmanındaki
        karşılığıdır: silmeyi kimseye VERMEMEK için önce silmeyi bir
        yetenek olarak ADLANDIRMAMAK gerekir.
    */
    case RatingView = 'rating.view';
    case RatingReply = 'rating.reply';
}
