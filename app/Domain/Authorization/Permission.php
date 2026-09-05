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
}
