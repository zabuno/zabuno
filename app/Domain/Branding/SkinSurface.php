<?php

declare(strict_types=1);

namespace App\Domain\Branding;

/**
 * Kontrastın ÖLÇÜLDÜĞÜ zemin.
 *
 * Ölçüm soyut bir "beyaz"a karşı değil, misafirin gerçekten göreceği zemine
 * karşı yapılır. Misafir menüsünün iki zemini vardır ve hangisinin
 * seçileceğine misafirin cihazı karar verir — bu yüzden rampa İKİSİ İÇİN DE
 * ayrı türetilir ve ayrı ölçülür. Tek temada ölçüp diğerini varsaymak,
 * gece menüyü açan misafiri hesaba katmamak olurdu.
 *
 * Buradaki değerler misafir yüzeyinin bugünkü zeminleridir
 * (`public-menu.blade.php` `--qr-bg` / `--qr-fg`); ürünündür, kiracının
 * değil.
 */
enum SkinSurface: string
{
    case Light = 'light';
    case Dark = 'dark';

    /** Sayfanın zemini. */
    public function canvasHex(): string
    {
        return match ($this) {
            self::Light => '#ffffff',
            self::Dark => '#111827',
        };
    }

    /** Zeminin üstündeki birincil metin rengi. */
    public function inkHex(): string
    {
        return match ($this) {
            self::Light => '#1f2937',
            self::Dark => '#f9fafb',
        };
    }

    /**
     * Marka dolgusunun ÜSTÜNE yazılabilecek iki aday.
     *
     * Kiracının rengi hangi tema açıksa açık olsun aynı dolguyu üretir;
     * dolgunun üstündeki yazı ise dolgunun kendi açıklığına göre seçilir.
     * Bu yüzden adaylar temaya değil, ürünün kendi uç mürekkeplerine
     * bağlıdır.
     *
     * @return array{0: string, 1: string}
     */
    public static function onAccentCandidates(): array
    {
        return ['#ffffff', '#111827'];
    }
}
