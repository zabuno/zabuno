<?php

declare(strict_types=1);

namespace App\Domain\Support;

/**
 * Kiracı olarak bakma oturumunun SÜRESİ — `docs/122` §5, `docs/133` §2.
 *
 * Süre koda gömülmez, yapılandırmadan gelir
 * (`support.access_session_minutes`, env `SUPPORT_ACCESS_SESSION_MINUTES`).
 * Ama yapılandırma burada KOŞULSUZ okunmaz; iki kural üstüne biner ve
 * ikisi de kapıyı sıkar, gevşetmez:
 *
 * 1. **Okunamayan değer sonsuz değildir.** Boş, sayı olmayan, sıfır ya da
 *    negatif bir değer "sınırsız" anlamına gelmez; `FALLBACK_MINUTES`'a
 *    düşer. Bir yazım hatasının bütün gün açık kalan bir destek oturumuna
 *    dönüşmesi, bu paketin engellemek için var olduğu kusurun ta kendisidir.
 * 2. **Tavan vardır.** Yapılandırma `support.access_session_max_minutes`
 *    üstünde bir değer isterse, tavan uygulanır. Tavan bir SAYI kararı
 *    değil, bir güvenlik sınırıdır: süreyi sahibi seçer, ama "süreli olma"
 *    özelliğini yapılandırma iptal edemez.
 *
 * UZATMA YOKTUR. Bu sınıfın `extend()` benzeri bir kardeşi yoktur ve
 * olmayacak: `docs/122` §5 süreli olmayı şart koşar, uzatılabilir bir süre
 * ise yalnız ertelenmiş bir süresizliktir.
 */
final class SupportAccessWindow
{
    /**
     * Yapılandırma okunamadığında kullanılan süre. Kısa olması kasıtlı:
     * "menüm görünmüyor" çağrısı on beş dakikada ya cevaplanır ya da
     * ikinci bir sebeple ikinci bir kayıt hak eder.
     */
    public const FALLBACK_MINUTES = 15;

    /** Yapılandırma bir tavan söylemediğinde uygulanan tavan. */
    public const FALLBACK_MAX_MINUTES = 60;

    public function minutes(): int
    {
        $ceiling = self::positiveIntOrNull(config('support.access_session_max_minutes'))
            ?? self::FALLBACK_MAX_MINUTES;

        $configured = self::positiveIntOrNull(config('support.access_session_minutes'))
            ?? self::FALLBACK_MINUTES;

        return min($configured, $ceiling);
    }

    private static function positiveIntOrNull(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1) {
            $parsed = (int) trim($value);

            return $parsed > 0 ? $parsed : null;
        }

        return null;
    }
}
