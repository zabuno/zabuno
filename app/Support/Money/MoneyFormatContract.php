<?php

declare(strict_types=1);

namespace App\Support\Money;

use App\Domain\Money\MoneyFormatter;
use App\Support\Localization\DocumentLocale;
use InvalidArgumentException;

/**
 * PARANIN BİÇİMİ, İSTEMCİYE TAŞINABİLİR HÂLİ — `docs/115` S3 (FF-178).
 *
 * Sepet misafirin telefonunda toplanıyor (`docs/115` §2) ve toplam anında
 * güncellenmek zorunda; yani sayı istemcide oluşuyor. Ama BİÇİM orada
 * DOĞMAMALI: ondalık basamak sayısı para biriminin kendi özelliğidir —
 * yende sıfır, dinarda üçtür — ve sabit 100'e bölen bir istemci, yayınlanmış
 * bir menüde yanlış fiyat gösterir (`docs/13` §4).
 *
 * İkinci bir biçimlendirici KURMAK da çözüm değildi: tarayıcının
 * `Intl.NumberFormat`'ı sunucununkinden başka bir cevap verebilir ve
 * aradaki fark ancak masada hesap istendiğinde anlaşılırdı.
 *
 * BU YÜZDEN BİÇİM SORULMAZ, ÖLÇÜLÜR. Kanonik biçimlendiriciye iki bilinen
 * tutar verilir ve ürettiği metinden parçalar SÖKÜLÜR: ön ek, son ek,
 * ondalık ayırıcı, binlik ayırıcı. İstemci hiçbir işaret ya da ayırıcı
 * uydurmaz; yalnız sunucunun ürettiği kalıba rakam yerleştirir.
 *
 * Requirement ID: GUEST-CART-MONEY-05.
 */
final class MoneyFormatContract
{
    private function __construct(
        /** Rakamlardan ÖNCE gelen her şey — "₺" ya da boş. */
        public readonly string $prefix,
        /** Rakamlardan SONRA gelen her şey — " TRY" ya da boş. */
        public readonly string $suffix,
        public readonly string $decimalSeparator,
        /** Binlik ayırıcı; gruplamayan bir biçimde boş dizedir. */
        public readonly string $groupSeparator,
        public readonly int $fractionDigits,
        /**
         * Bu biçimin SIFIR rakamı.
         *
         * Latin rakamları evrensel değildir: `ext-intl` bazı dillerde
         * Arap-Hint rakamları üretir. İstemci ASCII rakamla hesaplar ve
         * yazarken bu rakamın kod noktasına kaydırır; kaydırmayı sunucuda
         * yapamayız, çünkü sayı henüz yoktur.
         */
        public readonly string $zeroDigit,
    ) {}

    /**
     * Bir para biriminin biçim kalıbı — çözülemiyorsa `null`.
     *
     * `null` bir hata değil bir KARARDIR: `PriceLabel` de para birimini
     * çözemediğinde fiyatı hiç göstermiyor. Biçimi bilinmeyen bir para
     * biriminde toplam yazmak, uydurulmuş bir sayıyı masada ödetmek olurdu.
     */
    public static function for(string $currencyCode, ?string $locale = null): ?self
    {
        $locale ??= DocumentLocale::tag();

        try {
            $digits = MoneyFormatter::fractionDigitsFor($currencyCode);
            $zero = MoneyFormatter::format(0, $currencyCode, $locale);
            // Binlik ayırıcı ancak DÖRT BASAMAKLI bir sayıda görünür.
            $thousand = MoneyFormatter::format(10 ** ($digits + 3), $currencyCode, $locale);
        } catch (InvalidArgumentException) {
            return null;
        }

        $zeroCore = self::digitRun($zero);
        $thousandCore = self::digitRun($thousand);

        if ($zeroCore === null || $thousandCore === null) {
            return null;
        }

        [$start, $core] = $zeroCore;
        $characters = self::characters($core);

        // "0,00" → ondalık ayırıcı, kesir basamaklarından hemen öncedir.
        // Basamaksız bir para biriminde ("¥0") ayırıcı hiç yoktur.
        $decimal = $digits > 0 ? ($characters[count($characters) - $digits - 1] ?? '') : '';

        return new self(
            mb_substr($zero, 0, $start),
            mb_substr($zero, $start + mb_strlen($core)),
            $decimal,
            self::groupSeparatorOf($thousandCore[1], $digits),
            $digits,
            $characters[0] ?? '0',
        );
    }

    /**
     * İstemciye giden hâl — anahtarlar KISADIR, çünkü bu harita her misafir
     * sayfasında telden geçiyor.
     *
     * @return array{prefix:string, suffix:string, decimal:string, group:string, digits:int, zero:string}
     */
    public function toArray(): array
    {
        return [
            'prefix' => $this->prefix,
            'suffix' => $this->suffix,
            'decimal' => $this->decimalSeparator,
            'group' => $this->groupSeparator,
            'digits' => $this->fractionDigits,
            'zero' => $this->zeroDigit,
        ];
    }

    /**
     * Biçimlenmiş metnin İLK rakamından SON rakamına kadarki parçası.
     *
     * Ayırıcılar bu parçanın içinde kalır; para işareti ve boşluklar dışında.
     *
     * @return array{0:int, 1:string}|null Başlangıç (karakter cinsinden) ve parça.
     */
    private static function digitRun(string $formatted): ?array
    {
        // `\p{Nd}` Latin olmayan rakamları da yakalar; `PREG_OFFSET_CAPTURE`
        // BAYT verir, karakter değil — mb_substr için çevrilir.
        if (preg_match_all('/\p{Nd}/u', $formatted, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return null;
        }

        $first = $matches[0][0];
        $last = $matches[0][count($matches[0]) - 1];

        $start = mb_strlen(substr($formatted, 0, $first[1]));
        $end = mb_strlen(substr($formatted, 0, $last[1] + strlen($last[0])));

        return [$start, mb_substr($formatted, $start, $end - $start)];
    }

    /**
     * "1.000,00" içindeki binlik ayırıcı.
     *
     * Kesir kısmı atılır, kalan "1.000"dur ve ikinci karakter ayırıcıdır.
     * Bin'i gruplamayan bir biçimde ("1000") o karakter bir rakamdır ve
     * ayırıcı BOŞ döner — var olmayan bir ayırıcıyı uydurmak, toplamı
     * misafirin okuyamayacağı bir biçime sokardı.
     */
    private static function groupSeparatorOf(string $core, int $digits): string
    {
        $characters = self::characters($core);

        if ($digits > 0) {
            // Kesir basamakları + ondalık ayırıcı.
            $characters = array_slice($characters, 0, count($characters) - $digits - 1);
        }

        $candidate = $characters[1] ?? '';

        return preg_match('/^\p{Nd}$/u', $candidate) === 1 ? '' : $candidate;
    }

    /** @return list<string> */
    private static function characters(string $value): array
    {
        return preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
