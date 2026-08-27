<?php

declare(strict_types=1);

namespace App\Application\Ai\Port;

use App\Domain\Ai\Capability;

/**
 * AI şu anda kullanılabilir mi?
 *
 * Üç ayrı sebepten hayır olabilir ve ÜÇÜ DE farklı bir kullanıcı mesajı
 * gerektirir: kapatma anahtarı, tenant bütçesi, ya da hiçbir aday modelin
 * bulunmaması.
 *
 * Ürün bu portun cevabına bakmadan da çalışmak zorundadır — bu bir
 * kolaylık değil, kabul ölçütüdür (`docs/51` §3.6/1).
 */
interface AiAvailabilityPort
{
    public function isAvailable(int $workspaceId, Capability $capability): AiAvailability;
}
