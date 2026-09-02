<?php

declare(strict_types=1);

namespace App\Support\Localization;

use Illuminate\Http\Request;

/**
 * Misafirin ARAYÜZ dili — `docs/85` (P1-06).
 *
 * İÇERİK dilinden ayrıdır ve bu ayrım şart: ürün adlarını restoran kendi
 * dilinde yazar, biz onları çevirmiyoruz. Turistik bir restoranda misafirin
 * yarısı Türkçe okumaz; arayüzü kendi diline almak, menüyü okunur kılar —
 * ama menünün İÇERİĞİNİ çevirmek ayrı ve çok daha büyük bir iştir.
 */
final class GuestLocale
{
    /**
     * Desteklenen arayüz dilleri.
     *
     * Liste KISADIR ve kasten: her dil, doldurulması gereken bir çeviri
     * dosyası demektir ve yarısı boş bir dil seçici, misafire çalışmayan bir
     * söz verir.
     */
    public const SUPPORTED = ['tr', 'en'];

    public const COOKIE = 'zabuno_guest_locale';

    /**
     * Seçim sırası: bu istekteki açık seçim → daha önce hatırlanan seçim →
     * restoranın kendi dili.
     *
     * Tarayıcının `Accept-Language` başlığı KULLANILMAZ: aynı karekodu
     * okutan iki kişinin farklı sayfa görmesi, sahibin "menümde ne yazıyor"
     * sorusunu cevapsız bırakırdı — ve seçimi görünür kılmak, tahmin etmekten
     * dürüsttür.
     */
    public static function resolve(Request $request, ?string $contentLocale): string
    {
        $requested = $request->query('lang');

        if (is_string($requested) && self::isSupported($requested)) {
            return strtolower($requested);
        }

        $remembered = $request->cookie(self::COOKIE);

        if (is_string($remembered) && self::isSupported($remembered)) {
            return strtolower($remembered);
        }

        $content = strtolower(trim((string) $contentLocale));

        return self::isSupported($content) ? $content : 'tr';
    }

    public static function isSupported(string $locale): bool
    {
        return in_array(strtolower(trim($locale)), self::SUPPORTED, true);
    }

    /** Sağdan sola yazılan bir arayüz dili eklendiğinde burası tek durak. */
    public static function direction(string $locale): string
    {
        return in_array(strtolower($locale), ['ar', 'fa', 'he', 'ur'], true) ? 'rtl' : 'ltr';
    }
}
