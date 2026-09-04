<?php

declare(strict_types=1);

namespace App\Application\QrDestination\Port;

/**
 * Salonun bölümleri — FF-123.
 *
 * Toplu üretim bunları "Area 1", "Area 2" diye açıyor ve bu bir yer tutucudur:
 * hiçbir restoran sahibi salonunu böyle adlandırmaz. Sahibin cümlesi "salon üst
 * kat, salon içerisi, salon bahçe" idi. Kart basarken alanı seçen kişi, kendi
 * kullandığı adı görmeli — yoksa hangi "Area"nın bahçe olduğunu hatırlamak
 * zorunda kalır ve yanlış kartları bastırır.
 */
interface DiningAreaRepositoryPort
{
    /**
     * @return list<array{id: int, label: string, tableCount: int}>
     */
    public function listForLocation(int $workspaceId, int $locationId): array;

    /** Alan bu şubeye ait mi? Kiracı sınırı burada da geçerlidir. */
    public function belongsToLocation(int $areaId, int $workspaceId, int $locationId): bool;

    public function rename(int $areaId, string $label): void;
}
