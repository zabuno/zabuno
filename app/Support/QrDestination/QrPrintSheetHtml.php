<?php

declare(strict_types=1);

namespace App\Support\QrDestination;

use App\Application\QrDestination\Dto\QrPrintCard;
use App\Domain\QrDestination\QrPrintSheet;

/**
 * Basılabilir sayfanın HTML'i — saf, bağımlılıksız ve DOĞRUDAN TEST EDİLEBİLİR.
 *
 * PDF'in içinden metin çıkarmak kırılgandır (sıkıştırma, font altkümesi); oysa
 * sayfanın sözleşmesi burada okunabilir: her kartta bir ad, her sayfada on iki
 * kart, her kartın çevresinde bir kesme çizgisi.
 */
final class QrPrintSheetHtml
{
    /**
     * @param  list<QrPrintCard>  $cards
     * @param  string  $caption  Misafire hitap eden cümle: "Menü için okutun".
     *                           Bu metin RESTORANIN dilindedir, uygulamanınkinin
     *                           değil — kartı okuyan kişi masadadır.
     */
    public static function build(array $cards, string $caption, string $brandName): string
    {
        $cardWidth = QrPrintSheet::cardWidthMm();
        $cardHeight = QrPrintSheet::cardHeightMm();
        $qrSize = QrPrintSheet::QR_SIZE_MM;
        $margin = QrPrintSheet::MARGIN_MM;

        $cells = '';

        foreach ($cards as $index => $card) {
            if ($index % QrPrintSheet::COLUMNS === 0) {
                $cells .= $index === 0 ? '<table class="grid"><tr>' : '</tr><tr>';
            }

            $cells .= self::cell($card, $brandName, $caption);
        }

        if ($cards !== []) {
            /*
                SON SATIR DOLDURULUR: eksik hücreler bırakılırsa mPDF son
                satırı sayfa genişliğine yayar ve kartlar birbirinden farklı
                boyda basılır — kesildiklerinde birbirine uymazlar.
            */
            $remainder = count($cards) % QrPrintSheet::COLUMNS;

            if ($remainder !== 0) {
                $cells .= str_repeat('<td class="card empty"></td>', QrPrintSheet::COLUMNS - $remainder);
            }

            $cells .= '</tr></table>';
        }

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
            .'html,body{margin:0;padding:0;}'
            ."body{margin:{$margin}mm;}"
            .'table.grid{border-collapse:collapse;width:100%;}'
            /*
                KESME ÇİZGİSİ: kesikli, ince ve gri. Sürekli siyah bir çerçeve
                kesildikten sonra kartın kenarında kalır ve baskı hatası gibi
                görünür; kesikli çizgi "buradan kes" der ve makasın izinde
                kaybolur.
            */
            ."td.card{width:{$cardWidth}mm;height:{$cardHeight}mm;border:0.2mm dashed #999999;"
            .'text-align:center;vertical-align:middle;padding:0;}'
            .'td.card.empty{border:none;}'
            .'.brand{font-size:7pt;color:#666666;}'
            ."img{width:{$qrSize}mm;height:{$qrSize}mm;}"
            .'.title{font-size:12pt;font-weight:bold;}'
            .'.subtitle{font-size:8pt;color:#666666;}'
            .'.caption{font-size:8pt;color:#333333;}'
            .'</style></head><body>'
            .$cells
            .'</body></html>';
    }

    private static function cell(QrPrintCard $card, string $brandName, string $caption): string
    {
        $dataUri = 'data:image/png;base64,'.base64_encode($card->pngBytes);

        $subtitle = $card->subtitle === ''
            ? ''
            : '<div class="subtitle">'.self::escape($card->subtitle).'</div>';

        $brand = $brandName === ''
            ? ''
            : '<div class="brand">'.self::escape($brandName).'</div>';

        /*
            SIRA: restoran adı → kod → masa adı → çağrı.

            Masa adı KODUN ALTINDADIR çünkü kartı masaya koyan kişi ona yukarı
            dan bakar ve önce kodu görür; ama kartları DAĞITIRKEN aradığı şey
            addır ve kalın yazıldığı için deste hâlindeyken de okunur.
        */
        return '<td class="card">'
            .$brand
            .'<div><img src="'.$dataUri.'" alt=""></div>'
            .'<div class="title">'.self::escape($card->title).'</div>'
            .$subtitle
            .'<div class="caption">'.self::escape($caption).'</div>'
            .'</td>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
