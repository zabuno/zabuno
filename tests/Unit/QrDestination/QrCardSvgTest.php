<?php

declare(strict_types=1);

namespace Tests\Unit\QrDestination;

use App\Domain\QrDestination\CardOrientation;
use App\Domain\QrDestination\CardSize;
use App\Domain\QrDestination\CardTheme;
use App\Support\QrDestination\QrCardSvg;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * CARD-COMPOSE-01 — FF-120, sahibin talebi (2026-09-04).
 *
 * "Themes" bu ekranda yanlış şeyi adlandırıyordu: karekodun piksel renklerini.
 * Sahibin sorduğu şey masadaki pleksiglas kartın nasıl görüneceğiydi. Karekodun
 * rengi bir tema değil bir KISITTIR; tema kartın kendisidir ve markadan beslenir.
 */
final class QrCardSvgTest extends TestCase
{
    private const QR_SVG = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" '
        .'width="528px" height="528px" viewBox="0 0 528 528">'
        .'<rect x="0" y="0" width="528" height="528" fill="#ffffff"/>'
        .'<path fill="#000000" d="M43,43L106,43L106,52L43,52Z"/></svg>';

    private function compose(
        CardTheme $theme = CardTheme::Classic,
        CardSize $size = CardSize::A6,
        CardOrientation $orientation = CardOrientation::Portrait,
        string $brandName = 'Paşa Döner',
        string $headline = 'Menü için okutun',
        ?string $brandColor = '#1B4332',
        ?string $tableName = null,
    ): string {
        return QrCardSvg::compose(self::QR_SVG, $theme, $size, $orientation, $brandName, $headline, $brandColor, null, $tableName);
    }

    public function test_the_card_is_measured_in_real_millimetres(): void
    {
        /*
            Kart bir kez basılır ve pleksiglasın içinde aylarca durur. `mm`
            birimi olmadan dosya açıldığı programın varsayılan çözünürlüğüne
            göre ölçeklenir ve 105 mm'lik bir kart 148 mm çıkabilir.
        */
        $svg = $this->compose(size: CardSize::A6);

        self::assertStringContainsString('width="105mm"', $svg);
        self::assertStringContainsString('height="148mm"', $svg);
        self::assertStringContainsString('viewBox="0 0 105 148"', $svg);
    }

    public function test_orientation_swaps_the_edges_instead_of_doubling_the_size_list(): void
    {
        $svg = $this->compose(size: CardSize::A6, orientation: CardOrientation::Landscape);

        self::assertStringContainsString('width="148mm"', $svg);
        self::assertStringContainsString('height="105mm"', $svg);
    }

    public function test_the_ratio_family_states_a_real_physical_size(): void
    {
        // Pleksiglas standın kendi ölçüsü vardır ve bir kâğıt boyuna karşılık
        // gelmez; oran verilir, uzun kenar sabittir ve mm yazılır.
        [$width, $height] = CardSize::Ratio16x9->dimensionsMm();

        self::assertSame(150.0, $width);
        self::assertSame(84.4, $height);
        self::assertFalse(CardSize::Ratio16x9->isPaper());
        self::assertTrue(CardSize::A4->isPaper());
    }

    public function test_the_qr_is_taken_from_the_verified_renderer_not_redrawn(): void
    {
        /*
            Modül yerleşimi, sessiz bölge ve hata düzeltme seviyesi Endroid'in
            çıktısında zaten GERÇEK bir geri-okuma sınavından geçti. Kartta
            yeniden çizmek, o sınavı geçmemiş bir kod basmak olurdu.
        */
        $svg = $this->compose();

        self::assertStringContainsString('d="M43,43L106,43L106,52L43,52Z"', $svg);
    }

    public function test_a_card_without_a_readable_qr_prints_nothing_rather_than_an_empty_square(): void
    {
        // Boş bir kare, basıldıktan sonra fark edilir.
        $svg = QrCardSvg::compose(
            '<svg></svg>',
            CardTheme::Classic,
            CardSize::A6,
            CardOrientation::Portrait,
            'Paşa',
            'Okutun',
            null,
        );

        self::assertStringNotContainsString('<path', $svg);
    }

    /** @return list<array{0: CardTheme}> */
    public static function themes(): array
    {
        return array_map(static fn (CardTheme $theme): array => [$theme], CardTheme::cases());
    }

    #[DataProvider('themes')]
    public function test_every_theme_prints_the_code_and_the_call_to_action(CardTheme $theme): void
    {
        $svg = $this->compose(theme: $theme);

        self::assertStringContainsString('Menü için okutun', $svg);
        self::assertStringContainsString('<path fill="#000000"', $svg);
    }

    #[DataProvider('themes')]
    public function test_no_theme_relies_on_text_anchor(CardTheme $theme): void
    {
        /*
            `text-anchor="middle"` geçerli SVG'dir ama mPDF'in SVG
            ayrıştırıcısı onu YOK SAYAR. İlk denemede kartın PDF'i üretildi,
            gözle bakıldı ve marka adı kartın sol kenarından dışarı taşmıştı
            ("…ner"). Ekranda doğru, yazıcıdan yanlış çıkan bir kart en kötü
            kusurdur: kusur ancak yüz kart basıldıktan sonra görülür.
        */
        self::assertStringNotContainsString('text-anchor', $this->compose(theme: $theme));
    }

    public function test_the_brand_colour_is_used_but_never_on_the_code_itself(): void
    {
        $svg = $this->compose(theme: CardTheme::Banner, brandColor: '#1B4332');

        self::assertStringContainsString('#1B4332', $svg);
        // Kod HER ZAMAN siyah basılır: taranabilirlik pazarlık konusu değil ve
        // masadaki okunmayan bir kart, hiç kart olmamasından kötüdür.
        self::assertStringContainsString('<path fill="#000000"', $svg);
    }

    public function test_a_brand_colour_too_pale_to_read_falls_back_to_black(): void
    {
        // Beyaz üstünde okunmayan bir renk, kartın başlığında da okunmaz.
        $svg = $this->compose(theme: CardTheme::Banner, brandColor: '#FFE066');

        self::assertStringNotContainsString('#FFE066', $svg);
        self::assertStringContainsString('#111111', $svg);
    }

    public function test_the_minimal_theme_deliberately_omits_the_brand_name(): void
    {
        // Adı zaten standın üstünde yazan işletme için ikinci kez yazmak
        // kartı kalabalıklaştırır. Bu bir eksiklik değil bir karar.
        self::assertStringNotContainsString('Paşa Döner', $this->compose(theme: CardTheme::Minimal));
        self::assertStringContainsString('Paşa Döner', $this->compose(theme: CardTheme::Classic));
    }

    public function test_the_logo_is_embedded_in_the_file_not_linked_from_the_internet(): void
    {
        /*
            Kart SVG'si matbaaya gider ve orada internet bağlantısı
            olmayabilir. `<image href="https://…">` yazan bir kart, matbaanın
            bilgisayarında LOGOSUZ basılır ve bunu ancak baskıdan sonra fark
            ederiz.
        */
        $svg = QrCardSvg::compose(
            self::QR_SVG,
            CardTheme::Classic,
            CardSize::A6,
            CardOrientation::Portrait,
            'Paşa Döner',
            'Menü için okutun',
            '#1B4332',
            ['bytes' => 'logo-bytes', 'mimeType' => 'image/png'],
        );

        self::assertStringContainsString('data:image/png;base64,'.base64_encode('logo-bytes'), $svg);
        self::assertStringNotContainsString('href="http', $svg);
    }

    public function test_the_logo_is_declared_for_old_and_new_svg_readers(): void
    {
        // SVG 2 `href` okur, eski ayrıştırıcılar (mPDF dahil) `xlink:href`.
        // Birini atlamak, kartın bazı programlarda logosuz açılması demekti.
        $svg = QrCardSvg::compose(
            self::QR_SVG,
            CardTheme::Banner,
            CardSize::A6,
            CardOrientation::Portrait,
            'Paşa',
            'Okutun',
            null,
            ['bytes' => 'x', 'mimeType' => 'image/png'],
        );

        self::assertStringContainsString(' href="data:', $svg);
        self::assertStringContainsString('xlink:href="data:', $svg);
        self::assertStringContainsString('xmlns:xlink=', $svg);
        // Kare olmayan bir logo EZİLMEZ, kutuya sığdırılır.
        self::assertStringContainsString('preserveAspectRatio="xMinYMid meet"', $svg);
    }

    public function test_a_brand_with_no_logo_prints_a_card_without_one(): void
    {
        // Logo yoksa yer tutucu bir kutu çizilmez: boş bir çerçeve, basıldıktan
        // sonra hata gibi görünür.
        self::assertStringNotContainsString('<image', $this->compose());
    }

    public function test_a_name_with_markup_can_never_break_the_document(): void
    {
        $svg = $this->compose(brandName: '<script>x</script>');

        self::assertStringNotContainsString('<script>', $svg);
    }

    public function test_the_card_says_which_table_it_belongs_to(): void
    {
        /*
            Panel v3.1 kanonik kaynağı: "Her kartta masa numarası basılır —
            karışmaz."

            Masa adı yalnız TOPLU ARŞİVİN DOSYA ADINDA yazıyordu. Kırk kart
            basıldıktan sonra dosya adı yok olur: masaya dağıtan kişinin
            elinde birbirinden ayırt edilemeyen kırk kâğıt kalır ve hangi
            kodun hangi masaya gittiğini artık kimse bilemez — yani masa
            bazlı tarama ölçümü daha ilk gün anlamsızlaşır.
        */
        self::assertStringContainsString('Masa 12', $this->compose(tableName: 'Masa 12'));
    }

    public function test_a_code_with_no_table_prints_no_invented_name(): void
    {
        // Giriş kodunun masası yoktur; uydurulmuş bir ad, hiç ad
        // olmamasından kötüdür.
        $svg = $this->compose(brandName: '', tableName: null);

        self::assertSame(1, substr_count($svg, '<text'));
    }

    public function test_the_table_line_shrinks_the_code_instead_of_overlapping_it(): void
    {
        /*
            İki satırlık alt blok, tek satırlık bloktan daha çok yer kaplar.
            Yer AÇILMAZSA satır kodun üstüne biner ve bunu ancak yazıcıdan
            çıkan kart gösterir. Yatay kart seçildi çünkü orada dikey alan
            kısıttır: dikey kartta kod zaten kısa kenara takılıdır ve fark
            görünmez.
        */
        $withTable = QrCardSvg::codeSideMm(CardTheme::Classic, CardSize::A6, CardOrientation::Landscape, true);
        $withoutTable = QrCardSvg::codeSideMm(CardTheme::Classic, CardSize::A6, CardOrientation::Landscape, false);

        self::assertLessThan($withoutTable, $withTable);
    }

    /**
     * KOD ÖLÇÜSÜNÜN ÇAKILI TABLOSU.
     *
     * Aynı tablo istemcide de sınanıyor
     * (`resources/js/lib/qrCardGeometry.test.ts`). Ekran "Kod 88 mm" yazarken
     * sunucunun bastığı kodun gerçekten 88 mm olduğunu bu iki test birlikte
     * garanti eder: biri kayarsa diğeri kırılır. Tek taraflı bir sayı, sahibin
     * kırk kart bastırmasını sağlayan cümledir.
     *
     * @return list<array{0: CardTheme, 1: CardSize, 2: CardOrientation, 3: bool, 4: float}>
     */
    public static function codeSizes(): array
    {
        return [
            [CardTheme::Classic, CardSize::A6, CardOrientation::Portrait, false, 88.2],
            [CardTheme::Classic, CardSize::A6, CardOrientation::Portrait, true, 88.2],
            [CardTheme::Classic, CardSize::A6, CardOrientation::Landscape, false, 65.186],
            [CardTheme::Classic, CardSize::A6, CardOrientation::Landscape, true, 58.091],
            [CardTheme::Minimal, CardSize::A6, CardOrientation::Portrait, false, 88.2],
            [CardTheme::Banner, CardSize::A6, CardOrientation::Portrait, false, 88.2],
            [CardTheme::Dark, CardSize::Ratio1x2, CardOrientation::Portrait, false, 63.0],
            [CardTheme::Signage, CardSize::Ratio16x9, CardOrientation::Portrait, true, 46.708],
            [CardTheme::Classic, CardSize::A3, CardOrientation::Portrait, false, 249.48],
        ];
    }

    #[DataProvider('codeSizes')]
    public function test_the_printed_code_size_is_measured_not_guessed(
        CardTheme $theme,
        CardSize $size,
        CardOrientation $orientation,
        bool $printsTableName,
        float $expectedMm,
    ): void {
        self::assertEqualsWithDelta(
            $expectedMm,
            QrCardSvg::codeSideMm($theme, $size, $orientation, $printsTableName),
            0.01,
        );
    }

    public function test_a_dark_card_still_prints_a_dark_code_on_a_light_plate(): void
    {
        /*
            Kaynağın koyu tasarımı BİR KEZ reddedilmişti ve red doğruydu: eski
            kaynak kodu beyaz modül / siyah zemin çiziyordu ve ters basılan bir
            kod birçok telefonda hiç okunmaz (ISO/IEC 18004 koyu-üstüne-açık
            varsayar). Panel v3.1 o kusuru kendi düzeltti — koyulaşan şey
            KARTIN ZEMİNİ, kodun kendisi değil.

            Sessiz bölge Endroid'in kendi marjıdır ve BOŞLUKTUR, beyaz değil:
            koyu bir kartta plaka konmazsa o bölge koyu kalır ve tarayıcı kodun
            nerede bittiğini bulamaz.
        */
        $svg = $this->compose(theme: CardTheme::Dark);

        self::assertStringContainsString('fill="#0D0A24"', $svg);
        self::assertStringContainsString('<path fill="#000000"', $svg);
        self::assertStringContainsString('fill="#FFFFFF"/><g transform=', $svg);
    }

    public function test_the_signage_card_takes_the_brand_colour_as_its_ground(): void
    {
        $svg = $this->compose(theme: CardTheme::Signage, brandColor: '#1B4332');

        self::assertStringContainsString('height="148" fill="#1B4332"', $svg);
        // Kod yine siyah beyaz: marka rengi taranabilirliğin önüne geçmez.
        self::assertStringContainsString('<path fill="#000000"', $svg);
    }

    public function test_a_signage_ground_too_pale_for_white_text_falls_back_to_ink(): void
    {
        /*
            Tabela'nın zemini markanın rengi, yazısı beyazdır. Beyaza karşı
            okunmayan bir renk, üstündeki beyaz yazıyı da taşımaz — çift
            aynıdır, yalnız yerleri değişmiştir. Uydurulmuş bir sarı zemin
            üstünde beyaz bir başlık, uzaktan hiç okunmaz.
        */
        $svg = $this->compose(theme: CardTheme::Signage, brandColor: '#FFE066');

        self::assertStringNotContainsString('#FFE066', $svg);
        self::assertStringContainsString('height="148" fill="#111111"', $svg);
    }

    public function test_text_on_a_dark_card_is_light_enough_to_read(): void
    {
        // `#333333` bir başlık koyu zeminde okunmaz; yazının rengi zeminden
        // TÜRETİLİR, temaya elle yazılmaz.
        $svg = $this->compose(theme: CardTheme::Dark);

        self::assertStringNotContainsString('fill="#333333"', $svg);
    }
}
