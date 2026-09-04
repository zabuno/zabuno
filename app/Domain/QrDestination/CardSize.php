<?php

declare(strict_types=1);

namespace App\Domain\QrDestination;

/**
 * Basılacak kartın ÖLÇÜSÜ — FF-120, sahibin talebi (2026-09-04).
 *
 * Sahip iki ayrı liste istedi: kâğıt boyları (A3–A6, B3–B6) ve en-boy oranları
 * (1:2, 4:3, 16:9). İkisini yan yana iki açılır liste olarak sunmak çelişki
 * üretirdi: A4'ün zaten bir oranı var (1:√2) ve "A4 + 16:9" seçen kullanıcıya
 * ne basılacağı belirsizdir.
 *
 * Bu yüzden ölçü TEK bir seçimdir ve iki aileden gelir:
 *
 * - **Kâğıt ailesi (A/B):** standart bir sayfaya basılır — duvara asılacak
 *   afiş ya da kesilecek kart tabakası.
 * - **Kart ailesi (oran):** pleksiglas standın kendi ölçüsü vardır ve bir
 *   kâğıt boyuna karşılık gelmez. Oran verilir, uzun kenar 150 mm'ye sabitlenir
 *   (yaygın masa standı ölçüsü) ve ürün milimetreyi EKRANDA YAZAR.
 *
 * Oran GENİŞLİK:YÜKSEKLİK olarak okunur. Yön çevrildiğinde iki kenar yer
 * değiştirir; ölçü listesi ikiye katlanmaz.
 */
enum CardSize: string
{
    case A3 = 'A3';
    case A4 = 'A4';
    case A5 = 'A5';
    case A6 = 'A6';
    case B3 = 'B3';
    case B4 = 'B4';
    case B5 = 'B5';
    case B6 = 'B6';
    case Ratio1x2 = '1:2';
    case Ratio4x3 = '4:3';
    case Ratio16x9 = '16:9';

    /** Oran ailesinde uzun kenar: yaygın bir masa standı ölçüsü. */
    private const float RATIO_LONG_EDGE_MM = 150.0;

    /**
     * Dikey yerleşimdeki genişlik ve yükseklik (mm).
     *
     * @return array{0: float, 1: float}
     */
    public function dimensionsMm(): array
    {
        return match ($this) {
            // ISO 216.
            self::A3 => [297.0, 420.0],
            self::A4 => [210.0, 297.0],
            self::A5 => [148.0, 210.0],
            self::A6 => [105.0, 148.0],
            self::B3 => [353.0, 500.0],
            self::B4 => [250.0, 353.0],
            self::B5 => [176.0, 250.0],
            self::B6 => [125.0, 176.0],
            self::Ratio1x2 => self::fromRatio(1, 2),
            self::Ratio4x3 => self::fromRatio(4, 3),
            self::Ratio16x9 => self::fromRatio(16, 9),
        };
    }

    /** Kâğıt ailesinden mi (standart sayfaya basılır) yoksa kart ailesinden mi? */
    public function isPaper(): bool
    {
        return ! str_contains($this->value, ':');
    }

    /**
     * @return array{0: float, 1: float}
     */
    private static function fromRatio(int $width, int $height): array
    {
        $long = max($width, $height);
        $scale = self::RATIO_LONG_EDGE_MM / $long;

        return [round($width * $scale, 1), round($height * $scale, 1)];
    }
}
