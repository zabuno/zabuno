<?php

declare(strict_types=1);

namespace App\Support\QrDestination;

use App\Domain\QrDestination\CardOrientation;
use App\Domain\QrDestination\CardSize;
use App\Domain\QrDestination\CardTheme;
use App\Domain\QrDestination\QrContrast;

/**
 * Basılacak kartın TEK bestecisi — FF-120.
 *
 * Çıktı SVG'dir ve bu bir tercih değil bir zorunluluk: kart bir kez basılır ve
 * pleksiglasın içinde aylarca durur. Raster bir görsel 4 cm'lik bir karekodda
 * modül kenarlarını bulanıklaştırır; vektör her ölçekte keskindir. PDF de bu
 * SVG'den üretilir — iki ayrı besteci yazmak, ekrandaki önizlemenin bir şey,
 * yazıcıdan çıkan kartın başka bir şey olması demekti.
 *
 * Ölçüler MİLİMETREDİR. `viewBox` mm cinsinden kurulur ve `width`/`height`
 * `mm` biriminde yazılır; böylece dosya hangi programda açılırsa açılsın
 * gerçek fiziksel boyutunda basılır.
 *
 * Karekodun kendisi Endroid'in SVG çıktısından ALINIR, yeniden çizilmez:
 * modül yerleşimi, sessiz bölge ve hata düzeltme seviyesi zaten orada
 * doğrulanmış (`EndroidQrCodeImageExportAdapter` çıktıyı gerçekten geri
 * okuyarak sınıyor). Burada yalnız konumlandırılır.
 *
 * METİN SOLA HİZALIDIR ve bu bir tasarım kaprisi değil bir taşıyıcılık
 * kararıdır. `text-anchor="middle"` geçerli SVG'dir ama mPDF'in SVG
 * ayrıştırıcısı onu YOK SAYAR: ilk denemede kartın PDF'i üretildi, gözle
 * bakıldı ve marka adı kartın sol kenarından dışarı taşmıştı ("…ner"). Metni
 * ortalamak için genişliğini tahmin etmek gerekirdi — font metriği tahmini
 * her yazı tipinde ve her dilde biraz yanılır ve basılmış bir kartta o
 * yanılma görünür. Sol kenar ise her ayrıştırıcıda aynı yerdedir.
 *
 * Karekod ise geometriyle ortalanır: bir kare için font metriği gerekmez.
 */
final class QrCardSvg
{
    /** Kartın kenar boşluğu, kısa kenarın oranı olarak. */
    private const float MARGIN_RATIO = 0.08;

    /**
     * Koyu tasarımın kart zemini — panel v3.1 kanonik kaynağının kendi değeri
     * (`panel-v3.1.dc.html`, `koyu` teması). Uydurulmuş bir gri değil.
     */
    private const string DARK_GROUND = '#0D0A24';

    /**
     * @param  string  $qrSvg  Endroid'in ürettiği, doğrulanmış QR SVG'si.
     */
    public static function compose(
        string $qrSvg,
        CardTheme $theme,
        CardSize $size,
        CardOrientation $orientation,
        string $brandName,
        string $headline,
        ?string $brandColor,
        /**
         * Markanın logosu — BAYT olarak, adres olarak değil (FF-124).
         *
         * Kart SVG'si matbaaya gider ve orada internet bağlantısı olmayabilir.
         * `<image href="https://…">` yazan bir kart, matbaanın bilgisayarında
         * logosuz basılır ve bunu ancak baskıdan sonra fark ederiz.
         *
         * @var array{bytes: string, mimeType: string}|null
         */
        ?array $logo = null,
        /**
         * KARTIN HANGİ MASAYA AİT OLDUĞU — panel v3.1 kanonik kaynağı
         * ("Her kartta masa numarası basılır — karışmaz").
         *
         * Bu bir süs değil: toplu arşivde masa adı yalnız DOSYA ADINDA
         * yazıyordu. Kırk kart basıldıktan sonra dosya adı yok olur; masaya
         * dağıtan kişinin elinde birbirinden ayırt edilemeyen kırk kâğıt
         * kalır ve hangi kodun hangi masaya gittiğini artık kimse bilemez —
         * yani masa bazlı ölçüm (`scanCount`) daha ilk gün anlamsızlaşır.
         *
         * Masaya bağlı olmayan kod (giriş kodu) için `null`; uydurulmuş bir
         * ad, hiç ad olmamasından kötüdür.
         */
        ?string $tableName = null,
    ): string {
        [$width, $height] = $orientation->apply($size);

        $accent = self::accentColor($brandColor);
        $margin = min($width, $height) * self::MARGIN_RATIO;
        $dark = $theme->hasDarkGround();

        $body = self::background($theme, $width, $height, $accent);

        $brandSize = self::brandFontSize($width, $height);
        $printsBrandName = $theme->showsBrandName() && trim($brandName) !== '';
        $logoSide = $logo === null ? 0.0 : $brandSize * 1.6;

        $top = self::contentTop($theme, $width, $height, $printsBrandName, $logo !== null);
        $bottom = $height - $margin;
        $textLeft = $margin;

        if ($logo !== null) {
            /*
                LOGO ADIN SOLUNDA. Üstüne koymak kartın dikey alanını yer ve
                karekodu küçültürdü; karekodu küçültmek taranabilirliği
                düşürür ve logo bunun bedelini ödettiremez.
            */
            $logoY = $theme === CardTheme::Banner
                ? $height * 0.16 * 0.5 - $logoSide / 2
                : self::headerTop($theme, $width, $height);

            $body .= self::image($logo, $margin, $logoY, $logoSide);
            $textLeft = $margin + $logoSide + $margin * 0.4;
        }

        if ($printsBrandName) {
            if ($theme === CardTheme::Banner) {
                // Şeridin İÇİNDE, dikeyde ortalanmış: şeridin altında duran
                // beyaz bir yazı okunmazdı.
                $body .= self::text($brandName, $textLeft, $height * 0.16 * 0.5 + $brandSize * 0.36, $brandSize, 'bold', '#FFFFFF');
            } else {
                $body .= self::text(
                    $brandName,
                    $textLeft,
                    self::headerTop($theme, $width, $height) + $brandSize * 0.8,
                    $brandSize,
                    'bold',
                    // Koyu zeminde marka rengi okunmaz: vurgu zaten ZEMİNİN
                    // kendisidir, yazı ise onun üstünde okunabilir olmak
                    // zorundadır.
                    $dark ? '#FFFFFF' : $accent,
                );
            }
        }

        $captionSize = self::captionFontSize($width, $height);
        $lines = self::captionLines($headline, $tableName);

        // Karekod KALAN alanın tamamını alır ama kısa kenarı asla aşmaz:
        // taşan bir kod, kesildiğinde kenarından kırpılır.
        $qrSide = self::qrSide($width, $height, $top, $bottom, $captionSize, count($lines));

        $body .= self::qr($qrSvg, ($width - $qrSide) / 2, $top, $qrSide, $dark);

        /*
            ALT BLOK YUKARI DOĞRU DİZİLİR. Satır sayısı değiştiğinde (masa adı
            olan ve olmayan kart) kartın ALT kenarı sabit kalır; yukarıdan
            dizmek, tek satırlık bir kartta cümleyi havada bırakırdı.
        */
        $lineHeight = $captionSize * 1.5;
        $count = count($lines);

        foreach ($lines as $index => $line) {
            $body .= self::text(
                $line[0],
                $margin,
                $bottom - $captionSize * 0.4 - ($count - 1 - $index) * $lineHeight,
                $captionSize,
                $line[1],
                $dark ? '#FFFFFF' : '#333333',
            );
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" '
            ."width=\"{$width}mm\" height=\"{$height}mm\" "
            .'viewBox="0 0 '.self::number($width).' '.self::number($height).'">'
            .$body
            .'</svg>';
    }

    /**
     * KAREKODUN BASILACAĞI GERÇEK ÖLÇÜ, milimetre.
     *
     * Ekran bu sayıyı YAZAR ("Kod 88 mm — masa mesafesinden rahat okunur")
     * çünkü kaynağın önizleme paneli taranabilirliği bir temenniyle değil bir
     * ÖLÇÜYLE anlatıyor. Sayı bestecinin kendi geometrisinden gelir: elle
     * yazılmış bir tahmin, yerleşim bir gün değiştiğinde sessizce yalan olur
     * ve o yalan ancak kırk kart basıldıktan sonra fark edilir.
     *
     * İSTEMCİDE BİR AYNASI VAR (`resources/js/lib/qrCardGeometry.ts`) ve iki
     * taraf da AYNI tabloyu sınayan testlerle çakılıdır: biri kayarsa diğerinin
     * testi kırılır. Aynanın olması, önizlemenin her ayar değişiminde sunucuya
     * ikinci bir istek atmasından ucuzdur.
     *
     * VARSAYIM İHTİYATLI YÖNDEDİR: marka adının basıldığı varsayılır. Adı boş
     * olan bir markada gerçek kod bir satır kadar DAHA BÜYÜK çıkar — yani not
     * asla "okunur" derken okunmayan bir kod üretmez. Logo varlığı geometriyi
     * hiç değiştirmez: logo kutusu ile marka satırı aynı yüksekliktedir.
     */
    public static function codeSideMm(
        CardTheme $theme,
        CardSize $size,
        CardOrientation $orientation,
        bool $printsTableName,
    ): float {
        [$width, $height] = $orientation->apply($size);

        $margin = min($width, $height) * self::MARGIN_RATIO;
        $top = self::contentTop($theme, $width, $height, $theme->showsBrandName(), false);

        // Başlık HER ZAMAN basılır: sahip kendi cümlesini yazmazsa sunucu
        // misafir alanındaki hazır cümleyi koyar (`ExportQrCardController`).
        $lineCount = $printsTableName ? 2 : 1;

        return self::qrSide(
            $width,
            $height,
            $top,
            $height - $margin,
            self::captionFontSize($width, $height),
            $lineCount,
        );
    }

    /**
     * Marka rengi karta uygulanabilir mi?
     *
     * Aynı kısıt karekodda da geçerli (`QrContrast`): beyaz üstünde okunmayan
     * bir renk, kartın başlığında da okunmaz. Uygun değilse siyaha düşülür —
     * uydurulmuş bir renk, okunmayan bir başlıktan iyi değildir.
     *
     * Tabela tasarımında aynı kontrol İKİ İŞ görür: zemin markanın rengidir ve
     * üstündeki yazı beyazdır. Beyaza karşı yeterli olan bir renk, beyaz yazıyı
     * da taşır — çift aynıdır, yalnız yerleri değişmiştir.
     */
    private static function accentColor(?string $brandColor): string
    {
        if ($brandColor === null) {
            return '#111111';
        }

        $hex = strtoupper(ltrim(trim($brandColor), '#'));

        return QrContrast::isScannable($hex, 'FFFFFF') ? '#'.$hex : '#111111';
    }

    private static function background(CardTheme $theme, float $width, float $height, string $accent): string
    {
        $ground = match ($theme) {
            CardTheme::Dark => self::DARK_GROUND,
            // Tabela'nın zemini markanın kendi rengidir; renk okunamayacak
            // kadar açıksa `accentColor` zaten koyuya düşmüştür.
            CardTheme::Signage => $accent,
            default => '#FFFFFF',
        };

        $svg = '<rect x="0" y="0" width="'.self::number($width).'" height="'.self::number($height).'" fill="'.$ground.'"/>';

        return $svg.match ($theme->accentRole()) {
            // Marka renginde geniş bir başlık şeridi: uzaktan görünür.
            'banner' => '<rect x="0" y="0" width="'.self::number($width).'" height="'
                .self::number($height * 0.16).'" fill="'.$accent.'"/>',
            // İnce çerçeve: kesildiğinde kenarı belli olur.
            'frame' => '<rect x="'.self::number($width * 0.02).'" y="'.self::number($height * 0.02)
                .'" width="'.self::number($width * 0.96).'" height="'.self::number($height * 0.96)
                .'" fill="none" stroke="'.$accent.'" stroke-width="'.self::number(min($width, $height) * 0.012).'"/>',
            // İnce bir çizgi: adı koddan ayırır, mürekkep yakmaz.
            'rule' => '<rect x="'.self::number($width * 0.08).'" y="'.self::number($height * 0.145)
                .'" width="'.self::number($width * 0.84).'" height="'
                .self::number(max(0.4, $height * 0.004)).'" fill="'.$accent.'"/>',
            default => '',
        };
    }

    /**
     * Başlık bloğunun (logo + marka adı) üst kenarı.
     *
     * Şeritli tasarımda içerik şeridin ALTINDAN başlar; diğerlerinde kartın
     * kenar boşluğundan.
     */
    private static function headerTop(CardTheme $theme, float $width, float $height): float
    {
        $margin = min($width, $height) * self::MARGIN_RATIO;

        return $theme === CardTheme::Banner
            ? max($margin, $height * 0.16 + $margin * 0.5)
            : $margin;
    }

    /** Karekodun başlayabileceği ilk nokta: başlık bloğu bittikten sonrası. */
    private static function contentTop(
        CardTheme $theme,
        float $width,
        float $height,
        bool $printsBrandName,
        bool $hasLogo,
    ): float {
        $top = self::headerTop($theme, $width, $height);

        if ($theme === CardTheme::Banner) {
            // Ad şeridin İÇİNDE yazılır; kartın gövdesinden yer almaz.
            return $top;
        }

        if (! $hasLogo && ! $printsBrandName) {
            return $top;
        }

        // Satır yüksekliği, logo ile ad hangisi uzunsa ona göre — ikisi de
        // punto ölçüsünün 1,6 katıdır, yani hangisi varsa aynı yeri kaplar.
        return $top + self::brandFontSize($width, $height) * 1.6;
    }

    /**
     * Alt bloğun satırları: çağrı cümlesi ve masa adı.
     *
     * @return list<array{0: string, 1: string}> metin ve yazı ağırlığı
     */
    private static function captionLines(string $headline, ?string $tableName): array
    {
        $lines = [];

        if (trim($headline) !== '') {
            $lines[] = [$headline, 'normal'];
        }

        if ($tableName !== null && trim($tableName) !== '') {
            // Masa adı KALIN: kartı masaya dağıtan kişi ona bakarak dağıtır.
            $lines[] = [$tableName, 'bold'];
        }

        return $lines;
    }

    /** Karekodun kenarı: dikeyde kalan yer ile kartın kısa kenarından küçük olanı. */
    private static function qrSide(
        float $width,
        float $height,
        float $top,
        float $bottom,
        float $captionSize,
        int $lineCount,
    ): float {
        $margin = min($width, $height) * self::MARGIN_RATIO;

        $captionSpace = $lineCount === 0
            ? 0.0
            : $captionSize * 2.2 + ($lineCount - 1) * $captionSize * 1.5;

        return max(0.0, min($bottom - $captionSpace - $top, $width - 2 * $margin));
    }

    /**
     * Endroid'in SVG'sinden yolu alır ve karta yerleştirir.
     *
     * Yeniden çizilmez: modül yerleşimi, sessiz bölge ve hata düzeltme
     * seviyesi orada zaten gerçek bir geri-okuma sınavından geçti.
     */
    private static function qr(string $qrSvg, float $x, float $y, float $side, bool $needsPlate): string
    {
        $viewBox = self::qrViewBoxSize($qrSvg);
        $path = self::qrPath($qrSvg);

        if ($viewBox <= 0.0 || $path === null || $side <= 0.0) {
            // Kod okunamadıysa boş bir kare basmaktansa hiçbir şey basmamak
            // daha dürüst: boş kare, basıldıktan sonra fark edilir.
            return '';
        }

        $scale = $side / $viewBox;

        /*
            KOYU ZEMİNDE BEYAZ PLAKA ŞART.

            Karekodun sessiz bölgesi (ISO/IEC 18004: 4 modül) Endroid'in kendi
            marjıdır ve yolun İÇİNDE gelir — yani boşluktur, beyaz değildir.
            Beyaz kartta bunun bir önemi yok, kartın kendisi zaten beyaz. Koyu
            bir kartta ise sessiz bölge koyu kalır ve tarayıcı kodun nerede
            bittiğini bulamaz: kart basılır, masaya konur ve okunmaz.
        */
        $plate = $needsPlate
            ? '<rect x="'.self::number($x).'" y="'.self::number($y).'" width="'.self::number($side)
                .'" height="'.self::number($side).'" fill="#FFFFFF"/>'
            : '';

        return $plate
            .'<g transform="translate('.self::number($x).' '.self::number($y).') scale('
            .self::number($scale, 6).')">'
            .'<path fill="#000000" d="'.$path.'"/>'
            .'</g>';
    }

    private static function qrViewBoxSize(string $qrSvg): float
    {
        if (preg_match('/viewBox="0 0 ([0-9.]+) ([0-9.]+)"/', $qrSvg, $match) !== 1) {
            return 0.0;
        }

        return (float) $match[1];
    }

    private static function qrPath(string $qrSvg): ?string
    {
        if (preg_match('/<path[^>]*\sd="([^"]+)"/', $qrSvg, $match) !== 1) {
            return null;
        }

        return $match[1];
    }

    /**
     * Logoyu SVG'ye GÖMER.
     *
     * `href` ve `xlink:href` birlikte yazılır: SVG 2 birincisini, eski
     * ayrıştırıcılar (mPDF dahil) ikincisini okur. Birini atlamak, kartın bazı
     * programlarda logosuz açılması demekti — ve bunu fark eden kişi matbaacı
     * olurdu.
     *
     * @param  array{bytes: string, mimeType: string}  $logo
     */
    private static function image(array $logo, float $x, float $y, float $side): string
    {
        $uri = 'data:'.$logo['mimeType'].';base64,'.base64_encode($logo['bytes']);

        return '<image x="'.self::number($x).'" y="'.self::number($y).'" '
            .'width="'.self::number($side).'" height="'.self::number($side).'" '
            // Oran KORUNUR: kare olmayan bir logo ezilmez, kutuya sığdırılır.
            .'preserveAspectRatio="xMinYMid meet" '
            .'href="'.$uri.'" xlink:href="'.$uri.'"/>';
    }

    private static function text(
        string $value,
        float $x,
        float $y,
        float $fontSize,
        string $weight,
        string $fill,
    ): string {
        return '<text x="'.self::number($x).'" y="'.self::number($y).'" '
            .'font-family="Helvetica, Arial, sans-serif" '
            .'font-size="'.self::number($fontSize).'" font-weight="'.$weight.'" '
            // `text-anchor` YOK: mPDF'in SVG ayrıştırıcısı onu yok sayar ve
            // metin kartın dışına taşar. Bkz. sınıf başlığı.
            .'fill="'.$fill.'">'
            .htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8')
            .'</text>';
    }

    private static function brandFontSize(float $width, float $height): float
    {
        return round(min($width, $height) * 0.075, 2);
    }

    private static function captionFontSize(float $width, float $height): float
    {
        return round(min($width, $height) * 0.045, 2);
    }

    private static function number(float $value, int $precision = 2): string
    {
        return rtrim(rtrim(number_format($value, $precision, '.', ''), '0'), '.') ?: '0';
    }
}
