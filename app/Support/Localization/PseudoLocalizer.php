<?php

declare(strict_types=1);

namespace App\Support\Localization;

/**
 * Sahte-yerelleştirme — çeviri YAPMADAN çeviriye hazırlığı ölçen dil
 * (`docs/121` §4).
 *
 * Sahibin sırası bağlayıcı: çeviri EN SONDA. Ama "en başında tercüme için
 * gereken tüm önlemleri alacağız; en son tercüme yaparken sorun
 * yaşamamalıyız." `docs/121` on üç önlem sayıyor ve dördü (Ö1, Ö2, Ö3, Ö7)
 * yalnız bu araçla ölçülebilir.
 *
 * ═══ BU BİR ÇEVİRİ DEĞİLDİR ═══
 *
 * Üretilen metin hiçbir dile ait değildir; hiçbir çevirmen çalışmadı, çeviri
 * kilidi açılmadı, `shipped_locales` genişlemedi. Bu bir ÖLÇÜM DİLİDİR ve
 * yalnız geliştirmede açılır.
 *
 * ═══ ÜÇ DÖNÜŞÜM, ÜÇ AYRI KUSURU AÇIĞA ÇIKARIR ═══
 *
 * 1. AKSANLI HARF → katalogdan GEÇMEYEN metin dönüşmeden kalır ve gözle
 *    bulunur (Ö1). Kodda gömülü bir metin katalogda hiç görünmez; kilidi
 *    açmak onu çevrilebilir yapmaz.
 * 2. SONA DOLGU → uzayan metnin kırdığı düzen görünür (Ö7). Almanca
 *    İngilizceden ortalama %35 uzundur ve kısa etiketlerde bu oran %100'e
 *    çıkar. Sabit genişlikli bir düğme İngilizcede güzel, Almancada
 *    kırpılmış görünür — 320 pikselde iki katı acıtır.
 * 3. BAŞ/SON AYRAÇ → ortasından kesilen ya da parça parça kurulan cümle
 *    görünür (Ö2, Ö5). `"Toplam " . $n . " ürün"` ekranda üç ayrı parça
 *    olduğunu kendisi söyler.
 */
final class PseudoLocalizer
{
    /**
     * Dolgu oranı — Almancanın İngilizceye göre ortalama uzaması.
     *
     * `docs/121` §7 bu oranın kendi gerekçe süresini yazıyor: dokuz dilin
     * gerçek katalogları geldiğinde ölçülüp düzeltilebilir. Bugün bir
     * ortalamadır ve ortalama olduğu biliniyor.
     */
    private const EXPANSION_RATIO = 0.40;

    /** Cümlenin sınırını gösteren işaretler — kesilen metin bunlarla görünür. */
    private const OPEN = '⟦';

    private const CLOSE = '⟧';

    /** Dolgu karakteri: metin gibi görünmemeli ki uzunluk artışı fark edilsin. */
    private const PADDING = '·';

    /**
     * Harf eşlemesi.
     *
     * Latin harfleri, GÖZLE OKUNABİLİR kalacak biçimde aksanlanır. Okunmaz
     * hâle getirmek (`ЅѦѵё`) ölçümü işe yaramaz kılardı: geliştirici hangi
     * metnin dönüştüğünü değil, hiçbir şeyi göremezdi.
     *
     * @var array<string, string>
     */
    private const LETTERS = [
        'a' => 'å', 'b' => 'ß', 'c' => 'ç', 'd' => 'ð', 'e' => 'ê', 'f' => 'ƒ',
        'g' => 'ğ', 'h' => 'h', 'i' => 'ï', 'j' => 'j', 'k' => 'k', 'l' => 'ł',
        'm' => 'm', 'n' => 'ñ', 'o' => 'ô', 'p' => 'þ', 'q' => 'q', 'r' => 'ř',
        's' => 'š', 't' => 'ţ', 'u' => 'û', 'v' => 'v', 'w' => 'ŵ', 'x' => 'x',
        'y' => 'ý', 'z' => 'ž',
        'A' => 'Å', 'B' => 'Ɓ', 'C' => 'Ç', 'D' => 'Ð', 'E' => 'Ê', 'F' => 'Ƒ',
        'G' => 'Ğ', 'H' => 'Ĥ', 'I' => 'Ï', 'J' => 'Ĵ', 'K' => 'Ķ', 'L' => 'Ł',
        'M' => 'Ṁ', 'N' => 'Ñ', 'O' => 'Ô', 'P' => 'Þ', 'Q' => 'Q', 'R' => 'Ř',
        'S' => 'Ş', 'T' => 'Ţ', 'U' => 'Û', 'V' => 'V', 'W' => 'Ŵ', 'X' => 'X',
        'Y' => 'Ý', 'Z' => 'Ž',
    ];

    /**
     * Kip AÇIK MI — ve üretimde bu sorunun cevabı her zaman HAYIR.
     *
     * Kapı ayarın kendisine değil ORTAMA bakar. Bir ortam değişkeninin
     * yanlışlıkla üretime taşınması gerçek ve sık bir olaydır; müşterinin
     * ekranında `⟦Şåvê çhàñgêš⟧` görmesi ise ürünün bozulduğu anlamına
     * gelir. Ayara güvenen bir kapı, o gün açık kalırdı.
     */
    public static function isEnabled(): bool
    {
        if (app()->environment('production')) {
            return false;
        }

        return (bool) config('i18n.pseudo_localization', false);
    }

    /**
     * Bir katalog metnini ölçüm diline çevirir.
     *
     * Boş dize boş kalır: boş bir dize çevrilecek bir metin değil, bir
     * arayüz durumudur (`scripts/i18n` bunu katalogdan da dışlar). Ayraç
     * eklenseydi ekranda anlamsız bir `⟦⟧` belirirdi.
     */
    public static function transform(string $text): string
    {
        if ($text === '') {
            return '';
        }

        /*
            ADLI YER TUTUCU DOKUNULMAZ (Ö3).

            `{count}` aksanlansaydı çalışma anında değiştirilemezdi ve ekranda
            ham bir `{çôûñt}` kalırdı. Ölçüm aracının kendisi ürünü bozarsa,
            ölçtüğü hiçbir şeye güvenilmez.
        */
        $parts = preg_split('/(\{[a-zA-Z0-9_]+\})/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];

        $accented = '';

        foreach ($parts as $part) {
            $accented .= str_starts_with($part, '{') ? $part : strtr($part, self::LETTERS);
        }

        $padding = str_repeat(
            self::PADDING,
            max(1, (int) ceil(mb_strlen($text) * self::EXPANSION_RATIO)),
        );

        return self::OPEN.$accented.' '.$padding.self::CLOSE;
    }
}
