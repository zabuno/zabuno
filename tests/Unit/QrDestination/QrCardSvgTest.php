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
    ): string {
        return QrCardSvg::compose(self::QR_SVG, $theme, $size, $orientation, $brandName, $headline, $brandColor);
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
}
