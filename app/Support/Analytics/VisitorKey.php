<?php

declare(strict_types=1);

namespace App\Support\Analytics;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Yaklaşık benzersiz ziyaretçi anahtarı — `docs/68`.
 *
 * MVP analitiği "kaç tarama" kadar "kaç KİŞİ" sorusunu da cevaplamalı: aynı
 * masadaki bir müşterinin menüyü altı kez açması altı müşteri demek değildir
 * ve ham sayaç bu iki durumu ayırt edemez.
 *
 * Anahtarın taşımadığı şeyler kasıtlıdır:
 *
 * - **Ham IP ve tarayıcı bilgisi SAKLANMAZ.** Yalnız türetilmiş özet yazılır.
 * - **Tuz her gün döner.** Dünün anahtarı bugünün anahtarıyla eşleşmez;
 *   böylece bir ziyaretçi günler boyunca izlenemez. Bunun bedeli, benzersiz
 *   sayımın gün sınırında sıfırlanmasıdır — ve bu bir kusur değil, ödenen
 *   bedeldir.
 * - **Kiracıya göre ayrılır.** Aynı kişi iki farklı restoranın menüsünü
 *   açtığında iki farklı anahtar üretilir; iki marka arasında eşleştirme
 *   yapılamaz.
 *
 * Sonuç bir kimlik değil, bir SAYIM aracıdır. "Yaklaşık" kelimesi ekranda da
 * yazar: proxy arkasındaki iki müşteri tek görünebilir, tarayıcısını
 * değiştiren bir kişi iki görünebilir.
 */
final class VisitorKey
{
    /**
     * Uygulama anahtarı tuzun temelidir: dışarıdan bilinemez, dolayısıyla
     * özet tabloyu ele geçiren biri bile IP'yi geri hesaplayamaz.
     */
    public static function forRequest(Request $request, int $workspaceId, Carbon $on): string
    {
        $salt = implode('|', [
            (string) config('app.key'),
            $on->toDateString(),
            (string) $workspaceId,
        ]);

        $material = implode('|', [
            (string) ($request->ip() ?? ''),
            (string) ($request->userAgent() ?? ''),
        ]);

        return hash_hmac('sha256', $material, $salt);
    }
}
