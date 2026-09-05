<?php

declare(strict_types=1);

namespace App\Application\QrDestination\Port;

/**
 * Bir şubedeki her karekodun KAÇ KEZ okutulduğu — panel v3 (`docs/109` §6.7).
 *
 * Neden QR tarafında ayrı bir okuma portu var da analitik raporunun kendisi
 * çağrılmıyor: rapor bir DÖNEM sorusudur ("son 7 gün ne oldu"), burada
 * sorulan ise kartın kendi ömrüdür ("bu kart hiç okutuldu mu"). İkisini tek
 * çağrıya bindirmek, QR ekranını raporun aralık/karşılaştırma/ısı haritası
 * hesabına ortak ederdi — ekranın sormadığı üç sorunun bedelini ödeyerek.
 *
 * Dönen sayı `qr_resolve` olaylarıdır, menü açılışı DEĞİL: menü açılışı aynı
 * ziyaretçinin ikinci olayıdır ve ikisini toplamak aynı misafiri iki kez
 * saymak olurdu.
 */
interface QrScanCountPort
{
    /**
     * @return array<int, int> qrCodeId => tarama sayısı (hiç taranmamış kod listede YOKTUR)
     */
    public function countsForLocation(int $workspaceId, int $locationId): array;
}
