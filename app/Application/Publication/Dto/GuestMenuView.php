<?php

declare(strict_types=1);

namespace App\Application\Publication\Dto;

/**
 * MİSAFİRİN O AN GÖRECEĞİ ŞEY — iki hâlden biri.
 *
 * 1. SERVİS EDİLİYOR: `publication` doludur ve sayfa menüyü çizer.
 * 2. SERVİS DIŞI: `publication` boştur. Bu bir hata DEĞİLDİR ve bir 404 da
 *    değildir; sahibin o saate koyduğu menünün gösterilebilir bir yayını
 *    yoktur. Misafire dürüst cümle söylenir, boş bir menü değil.
 *
 * İkisini tek bir tipte taşımanın sebebi, çağıranın "yayın var mı" sorusunu
 * ikinci kez sormak zorunda kalmaması: denetleyicilerin ikisi de aynı kararı
 * verirse ürünün iki yüzeyi bir gün ayrışır.
 *
 * `nextServiceClock` GERÇEK VERİDİR ya da `null`'dur — ASLA tahmin değil.
 * Sahibin geçişleri arasında gösterilebilir bir sonraki menü yoksa saat
 * yazılmaz: olmayan bir servisi vaat etmek, hiç saat söylememekten kötüdür.
 * Saat şubenin kendi saat diliminde `HH:MM` biçimindedir.
 */
final class GuestMenuView
{
    public function __construct(
        public readonly int $servingMenuId,
        public readonly ?PublicationRecord $publication = null,
        public readonly ?string $nextServiceClock = null,
    ) {}
}
