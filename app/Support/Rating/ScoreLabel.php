<?php

declare(strict_types=1);

namespace App\Support\Rating;

use NumberFormatter;

/**
 * GÖSTERİLEN PUANIN METNİ — `docs/116` §3 (P5).
 *
 * ═══ SAYIYI SUNUCU YAZAR ═══
 *
 * `MoneyFormatContract` fiyat için biçim KALIBINI istemciye taşıyor, çünkü
 * sepetin toplamı telefonda oluşuyor ve sunucu o sayıyı önceden bilemiyor.
 * Puan öyle değil: sayı zaten sunucuda hazır ve hiç değişmiyor. Bu yüzden
 * biçimlendirme de sunucuda kalır — istemciye taşınacak tek şey bitmiş
 * metindir. Böylece misafirin sayfası puan için tek bayt betik indirmez.
 *
 * ═══ ONDALIK AYIRICI UYDURULMAZ ═══
 *
 * "4.3" bir Türk misafire yanlış görünür, "4,3" bir İngiliz misafire yanlış
 * görünür. Ayırıcıyı bir tabloya elle yazmak yerine `ext-intl`'e sorulur —
 * aynı motor `MoneyFormatter`'da da kullanılıyor, yani sayfadaki fiyat ile
 * puan aynı ayırıcıyı gösterir. İki farklı ayırıcı taşıyan bir satır,
 * misafire iki farklı yerden gelmiş gibi görünürdü.
 *
 * ═══ TEK ONDALIK ═══
 *
 * "4,3" bir bilgi taşır; "4,2847" taşımaz. Dört basamak, ölçümün
 * taşıyabileceğinden daha fazla kesinlik iddia eder — ve iddia edilen her
 * fazla basamak, misafire olmayan bir hassasiyet vaat eder. Ham değer
 * `rating_scores`'ta dört basamakla duruyor; kaybolan bir şey yok.
 */
final class ScoreLabel
{
    private const FRACTION_DIGITS = 1;

    public static function for(float $score, string $locale): string
    {
        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);
            $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, self::FRACTION_DIGITS);
            $formatted = $formatter->format($score);

            if (is_string($formatted)) {
                return $formatted;
            }
        }

        /*
            `ext-intl` YOKSA SAYFA YİNE ÇIZILİR.

            Eksik bir eklenti yüzünden puanı hiç göstermemek, ölçümü bir
            kurulum ayrıntısına bağlamak olurdu. Nokta ayırıcı her yerde
            OKUNABİLİR; yanlış olabilir ama anlaşılmaz değildir.
        */
        return number_format($score, self::FRACTION_DIGITS, '.', '');
    }
}
