<?php

declare(strict_types=1);

namespace App\Domain\QrDestination;

/**
 * Basılabilir sayfanın ÖLÇÜLERİ — `docs/104` Döngü 8 ve 9.
 *
 * Bu sınıf bir tercih listesi değil, bir KISIT listesidir. Sayılar
 * araştırmadan gelir ve ürün onlara uymak zorundadır:
 *
 *   - **Sessiz bölge:** karekodun her kenarında en az 4 modülllük açık alan
 *     şarttır (ISO/IEC 18004). Karta gömülür, ayara açılmaz — ayara açılan
 *     bir zorunluluk, kapatılabilen bir zorunluluktur.
 *   - **En küçük basılı boy:** pratikte 2,0–2,5 cm; masa kartı için 2,5–4 cm.
 *     Bu sayfa 40 mm kullanır ve ürün bu sayıyı ekranda YAZAR.
 *   - **10:1 kuralı:** kod genişliği ≈ okuma mesafesinin onda biri. 40 mm,
 *     yaklaşık 40 cm'den okunur — masaya konan bir kart tam olarak bu
 *     mesafededir.
 *
 * Yerleşim 3 sütun × 4 satır: A4'ün 190×277 mm kullanılabilir alanına 12
 * kart sığar, her kart 63×69 mm. 40 masa dört sayfa eder; eskiden kırk
 * sayfa ediyordu.
 */
final class QrPrintSheet
{
    public const float PAGE_WIDTH_MM = 210.0;

    public const float PAGE_HEIGHT_MM = 297.0;

    public const float MARGIN_MM = 10.0;

    public const int COLUMNS = 3;

    public const int ROWS = 4;

    /** Bir A4 sayfaya sığan kart sayısı. */
    public const int CARDS_PER_PAGE = self::COLUMNS * self::ROWS;

    /**
     * Tek istekte basılabilecek kart sayısı.
     *
     * Sınır KEYFİ DEĞİL: her kart için ayrı bir PNG üretilir ve hepsi tek bir
     * PDF'e gömülür. 500 masalık bir istek, isteği zaman aşımına uğratır ve
     * kullanıcıya hiçbir şey vermez. Sınır aşıldığında ürün sessizce kırpmaz
     * — sayfa sayfa indirilebileceğini SÖYLER (`chunk` parametresi).
     */
    public const int CARDS_PER_REQUEST = self::CARDS_PER_PAGE * 4;

    /**
     * Karekod görselinin basılı kenar uzunluğu.
     *
     * Bu 45 mm, TAM KARENİN ölçüsüdür — görselin içine gömülü sessiz bölge
     * dahil. Tarayıcının gördüğü koyu modül alanı bunun yaklaşık %85'idir,
     * yani ~38 mm ≈ 4 cm; ürün ekranda bu sayıyı yazar. İlk denemede 40 mm
     * yazılmıştı ve basılı çıktı ölçüldüğünde koyu alan 34 mm çıktı: sessiz
     * bölgeyi unutmak, vaat edilenden küçük bir kod basmaktı.
     */
    public const float QR_SIZE_MM = 45.0;

    public static function cardWidthMm(): float
    {
        return (self::PAGE_WIDTH_MM - 2 * self::MARGIN_MM) / self::COLUMNS;
    }

    public static function cardHeightMm(): float
    {
        return (self::PAGE_HEIGHT_MM - 2 * self::MARGIN_MM) / self::ROWS;
    }
}
