<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai;

/**
 * BAŞARISIZ BİR ÇAĞRI, HESABIN SUÇU MU? — `docs/14` §2a.
 *
 * Sağlıksız bir hesap aday havuzundan düşer. Ama her hata hesabın hatası
 * değildir: gönderdiğimiz gövde bozuksa (400), model adı yanlışsa (404) ya
 * da şema doğrulaması geçmediyse, hesap gayet çalışıyordur — onu havuzdan
 * düşürmek, kendi hatamız yüzünden sahibin ödediği bir hesabı kullanılamaz
 * kılardı ve bir sonraki hesap da aynı 400'ü alırdı.
 *
 * Havuzdan düşüren tek şey HESABA ait bir arızadır: ulaşılamamak, anahtarın
 * reddedilmesi, kotanın dolması, sağlayıcının kendi sunucu hatası.
 */
final class ProviderHealthVerdict
{
    /** Ağ hatası her zaman hesaba yazılır: adres bize cevap vermiyor. */
    public static function networkFailureDropsAccount(): bool
    {
        return true;
    }

    public static function httpStatusDropsAccount(int $status): bool
    {
        // 401/403 — anahtar reddedildi. 429 — kota doldu (geçici düşüş,
        // tam olarak bu mekanizmanın var oluş sebebi). 5xx — sağlayıcı
        // tarafı. Geri kalan 4xx BİZİM isteğimizle ilgilidir.
        return in_array($status, [401, 403, 429], true) || $status >= 500;
    }
}
