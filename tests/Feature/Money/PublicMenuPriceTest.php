<?php

declare(strict_types=1);

namespace Tests\Feature\Money;

use App\Support\Money\PriceLabel;
use Tests\TestCase;

/**
 * Yayınlanan menüde fiyatın doğruluğu — CORE-12 × QR Destination.
 *
 * Fiyat, restoranın müşterisine verdiği taahhüttür. Yanlış bir fiyat, eksik
 * bir fiyattan daha kötüdür.
 *
 * Requirement ID'leri: MENU-PRICE-CURRENCY-04, MENU-PRICE-NO-BLANK-PAGE-05.
 */
final class PublicMenuPriceTest extends TestCase
{
    private function render(array $item): string
    {
        return view('public-menu', ['snapshot' => [
            'categories' => [[
                'name' => 'İçecekler',
                'menuItems' => [array_merge([
                    'productName' => 'Ayran',
                    'priceMinorAmount' => 2500,
                    'currencyCode' => 'TRY',
                    'allergens' => [],
                ], $item)],
            ]],
        ]])->render();
    }

    // --- MENU-PRICE-CURRENCY-04 -------------------------------------------

    public function test_the_published_price_uses_the_currency_own_decimals(): void
    {
        $html = $this->render(['priceMinorAmount' => 1499, 'currencyCode' => 'JPY']);

        self::assertStringContainsString('1,499', $html);
        self::assertStringNotContainsString(
            '14.99',
            $html,
            'MENU-PRICE-CURRENCY-04: sabit 100\'e bölmek yayınlanmış menüde yüz kat yanlış fiyat gösterir.'
        );
    }

    public function test_a_two_decimal_price_still_reads_as_money(): void
    {
        $html = $this->render(['priceMinorAmount' => 2500, 'currencyCode' => 'TRY']);

        // Para birimi kodu ile tutar arasındaki boşluk kırılmaz boşluktur
        // (U+00A0): kod, tutardan ayrı bir satıra düşmemelidir.
        self::assertStringContainsString("TRY\u{00A0}25.00", $html);
    }

    // --- MENU-PRICE-NO-BLANK-PAGE-05 --------------------------------------

    public function test_an_unresolvable_currency_hides_the_price_instead_of_breaking_the_page(): void
    {
        // Müşterinin telefonunda açılan bir sayfada istisna, beyaz ekrandır.
        $html = $this->render(['currencyCode' => 'XYZ']);

        self::assertStringContainsString('Ayran', $html, 'MENU-PRICE-NO-BLANK-PAGE-05: ürün adı görünmeye devam etmeli.');
        // CSS bloğu sınıf adını zaten içerir; aranan şey işaretlemedir.
        self::assertStringNotContainsString(
            '<span class="qr-menu-item-price">',
            $html,
            'MENU-PRICE-NO-BLANK-PAGE-05: çözülemeyen fiyat gösterilmemeli.'
        );
        self::assertNull(PriceLabel::for(2500, 'XYZ'));
    }
}
