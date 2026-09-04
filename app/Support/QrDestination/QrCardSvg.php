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
    ): string {
        [$width, $height] = $orientation->apply($size);

        $accent = self::accentColor($brandColor);
        $margin = min($width, $height) * self::MARGIN_RATIO;

        $body = self::background($theme, $width, $height, $accent);

        $top = $margin;
        $bottom = $height - $margin;

        if ($theme === CardTheme::Banner) {
            // Şerit kartın en üstünü kaplar; içerik onun altından başlar.
            $top = max($top, $height * 0.16 + $margin * 0.5);
        }

        if ($theme->showsBrandName() && trim($brandName) !== '') {
            $brandSize = self::brandFontSize($width, $height);

            if ($theme === CardTheme::Banner) {
                // Şeridin İÇİNDE, dikeyde ortalanmış: şeridin altında duran
                // beyaz bir yazı okunmazdı.
                $body .= self::text($brandName, $margin, $height * 0.16 * 0.5 + $brandSize * 0.36, $brandSize, 'bold', '#FFFFFF');
            } else {
                $body .= self::text($brandName, $margin, $top + $brandSize * 0.8, $brandSize, 'bold', $accent);
                $top += $brandSize * 1.6;
            }
        }

        $captionSize = self::captionFontSize($width, $height);
        $captionSpace = trim($headline) === '' ? 0.0 : $captionSize * 2.2;

        // Karekod KALAN alanın tamamını alır ama kısa kenarı asla aşmaz:
        // taşan bir kod, kesildiğinde kenarından kırpılır.
        $qrSide = max(0.0, min($bottom - $captionSpace - $top, $width - 2 * $margin));

        $body .= self::qr($qrSvg, ($width - $qrSide) / 2, $top, $qrSide);

        if (trim($headline) !== '') {
            $body .= self::text(
                $headline,
                $margin,
                $bottom - $captionSize * 0.4,
                $captionSize,
                'normal',
                '#333333',
            );
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<svg xmlns="http://www.w3.org/2000/svg" '
            ."width=\"{$width}mm\" height=\"{$height}mm\" "
            .'viewBox="0 0 '.self::number($width).' '.self::number($height).'">'
            .$body
            .'</svg>';
    }

    /**
     * Marka rengi karta uygulanabilir mi?
     *
     * Aynı kısıt karekodda da geçerli (`QrContrast`): beyaz üstünde okunmayan
     * bir renk, kartın başlığında da okunmaz. Uygun değilse siyaha düşülür —
     * uydurulmuş bir renk, okunmayan bir başlıktan iyi değildir.
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
        $svg = '<rect x="0" y="0" width="'.self::number($width).'" height="'.self::number($height).'" fill="#FFFFFF"/>';

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
     * Endroid'in SVG'sinden yolu alır ve karta yerleştirir.
     *
     * Yeniden çizilmez: modül yerleşimi, sessiz bölge ve hata düzeltme
     * seviyesi orada zaten gerçek bir geri-okuma sınavından geçti.
     */
    private static function qr(string $qrSvg, float $x, float $y, float $side): string
    {
        $viewBox = self::qrViewBoxSize($qrSvg);
        $path = self::qrPath($qrSvg);

        if ($viewBox <= 0.0 || $path === null) {
            // Kod okunamadıysa boş bir kare basmaktansa hiçbir şey basmamak
            // daha dürüst: boş kare, basıldıktan sonra fark edilir.
            return '';
        }

        $scale = $side / $viewBox;

        return '<g transform="translate('.self::number($x).' '.self::number($y).') scale('
            .self::number($scale, 6).')">'
            // Sessiz bölge Endroid'in kendi marjıdır ve yolun içinde gelir;
            // beyaz zemin kartın kendisidir.
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
