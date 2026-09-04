<?php

declare(strict_types=1);

namespace Tests\Unit\QrDestination;

use App\Application\QrDestination\Dto\QrPrintCard;
use App\Domain\QrDestination\QrPrintSheet;
use App\Support\QrDestination\QrPrintSheetHtml;
use PHPUnit\Framework\TestCase;

/**
 * QRSHEET-LAYOUT-01 — FF-111, `docs/104` Döngü 8.
 *
 * Sayfanın sözleşmesi PDF'in içinden değil BURADAN okunur: PDF'te metin
 * aramak sıkıştırmaya ve font altkümesine bağlıdır, oysa ölçülmek istenen şey
 * yerleşimdir — her kartta bir ad, her kartın çevresinde kesme çizgisi, her
 * karekod 40 mm.
 */
final class QrPrintSheetHtmlTest extends TestCase
{
    /** @return list<QrPrintCard> */
    private function cards(int $count): array
    {
        $cards = [];

        for ($i = 1; $i <= $count; $i++) {
            $cards[] = new QrPrintCard("png-{$i}", "T{$i}", $i % 2 === 0 ? 'Bahçe' : '');
        }

        return $cards;
    }

    public function test_every_card_carries_its_name_the_brand_and_the_guest_caption(): void
    {
        $html = QrPrintSheetHtml::build($this->cards(3), 'Menü için okutun', 'Kebapçı Ali');

        self::assertStringContainsString('T1', $html, 'QRSHEET-LAYOUT-01: kartın adı sayfada yazılı olmalı.');
        self::assertStringContainsString('T3', $html);
        self::assertStringContainsString('Bahçe', $html, 'QRSHEET-LAYOUT-01: alan etiketi de basılmalı.');
        self::assertSame(3, substr_count($html, 'Menü için okutun'), 'QRSHEET-LAYOUT-01: çağrı HER kartta olmalı.');
        self::assertSame(3, substr_count($html, 'Kebapçı Ali'), 'QRSHEET-LAYOUT-01: restoran adı HER kartta olmalı — kartlar destede karışır.');
    }

    public function test_each_card_has_a_cut_guide_and_a_forty_millimetre_code(): void
    {
        $html = QrPrintSheetHtml::build($this->cards(1), 'Scan', 'Brand');

        self::assertStringContainsString('dashed', $html, 'QRSHEET-LAYOUT-01: kesme çizgisi kesikli olmalı — sürekli çizgi kesildikten sonra kartın kenarında kalır.');
        /*
            45 mm TAM KARENİN ölçüsüdür: görselin içindeki sessiz bölge dahil.
            Koyu modül alanı bunun ~%85'i, yani ~38 mm ≈ 4 cm — ekranda yazan
            sayı budur. İlk denemede 40 mm yazılmış ve basılı çıktı
            ölçüldüğünde koyu alan 34 mm çıkmıştı.
        */
        self::assertStringContainsString('width:45mm;height:45mm', $html, 'QRSHEET-LAYOUT-01: karekod 45 mm basılmalı (sessiz bölge dahil; 10:1 kuralı, masa mesafesi).');
    }

    public function test_the_last_row_is_padded_so_cards_are_never_stretched(): void
    {
        // İki kart, üç sütun: eksik hücre bırakılırsa mPDF son satırı yayar ve
        // kartlar farklı boyda basılır — kesildiklerinde birbirine uymazlar.
        $html = QrPrintSheetHtml::build($this->cards(2), 'Scan', 'Brand');

        self::assertSame(1, substr_count($html, 'card empty'));
    }

    public function test_the_qr_image_is_embedded_and_never_fetched_over_the_network(): void
    {
        $html = QrPrintSheetHtml::build($this->cards(1), 'Scan', 'Brand');

        // Yazıcıya giden bir belge ağdan resim çekemez.
        self::assertStringContainsString('data:image/png;base64,'.base64_encode('png-1'), $html);
        self::assertStringNotContainsString('src="http', $html);
    }

    public function test_names_are_escaped_so_a_table_name_can_never_inject_markup(): void
    {
        $html = QrPrintSheetHtml::build(
            [new QrPrintCard('png', '<script>x</script>', '')],
            'Scan',
            'Brand',
        );

        self::assertStringNotContainsString('<script>', $html);
    }

    public function test_a_page_holds_twelve_cards(): void
    {
        self::assertSame(12, QrPrintSheet::CARDS_PER_PAGE);
        // 40 masa dört sayfa eder; eskiden kırk sayfa ediyordu.
        self::assertSame(4, (int) ceil(40 / QrPrintSheet::CARDS_PER_PAGE));
    }

    public function test_an_empty_deck_produces_no_grid(): void
    {
        self::assertStringNotContainsString('<table', QrPrintSheetHtml::build([], 'Scan', 'Brand'));
    }
}
