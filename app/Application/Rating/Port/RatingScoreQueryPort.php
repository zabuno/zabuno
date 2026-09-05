<?php

declare(strict_types=1);

namespace App\Application\Rating\Port;

use App\Application\Rating\Dto\RatingSummary;

/**
 * TÜRETİLMİŞ PUANIN OKUMA YOLU — `docs/116` §3 (P5).
 *
 * Yazma yolundan (`RatingSignalRepositoryPort`) ayrı durur ve öyle kalmalı:
 * biri değişmez deftere satır ekler, öteki `rating:recompute`'un ürettiği
 * türetilmiş tabloyu okur. Tek porta toplasaydık, gösterim yolu bir gün
 * ham sinyalden ortalama hesaplamaya kalkardı — ve o ortalama, algoritma
 * dosyasının bilmediği bir ortalama olurdu.
 */
interface RatingScoreQueryPort
{
    /**
     * Panelin okuduğu satırlar — menüdeki HER ürün için bir satır.
     *
     * Hiç oy almamış ürün de listede olur: sahibin ekranında bir tabağın
     * hiç görünmemesi ile "henüz yeterli değerlendirme yok" demesi aynı şey
     * değildir. Birincisi bir boşluk, ikincisi bir cevaptır.
     *
     * @return list<RatingSummary>
     */
    public function forMenu(int $workspaceId, int $menuId, int $algorithmVersion): array;

    /**
     * Misafir menüsünün okuduğu satırlar, menü satırı kimliğine göre.
     *
     * Aynı `RatingSummary` döner ve eşik kararı orada UYGULANMIŞ hâldedir:
     * karar olumsuzsa `score` `null`'dır ve gösterilmeyecek sayı şablona
     * hiç ulaşmaz. Ayrı bir "yalnız eşiği geçenler" sorgusu yazmadık,
     * çünkü sahibin yanıtı eşikten BAĞIMSIZ olarak gösterilir: puanı henüz
     * çizilmeyen bir tabağa sahibin söyleyecek sözü olabilir.
     *
     * @return array<int, RatingSummary>
     */
    public function forGuestMenu(int $workspaceId, int $menuId, int $algorithmVersion): array;
}
