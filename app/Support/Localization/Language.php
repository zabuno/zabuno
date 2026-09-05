<?php

declare(strict_types=1);

namespace App\Support\Localization;

/**
 * Dokuz dilin TEK KAYNAKLI tanımı — `docs/120` §2 ve §6.
 *
 * Sahibin kararı (2026-09-05): "İngilizce, Türkçe, Arapça, Rusça, Farsça,
 * Kürtçe, Almanca, Fransızca, İtalyanca… bugünden itibaren altyapı çok dili
 * desteklemeli."
 *
 * ═══ BU KÜTÜK NEYİ SÖYLER, NEYİ SÖYLEMEZ ═══
 *
 * Söyler: "bu dili TARİF EDEBİLİYOR muyuz?" — kodu, adı, yönü, yazı sistemi
 * ve bölge işareti var mı.
 *
 * Söylemez: "bu dilde eksiksiz bir ÜRÜN verebiliyor muyuz?" — o soruya
 * `i18n.shipped_locales` cevap verir ve bugün cevabı yalnız `en`.
 *
 * İki soruyu tek listeye indirmek, ikisinden birini yalan söylemeye zorlardı
 * (`docs/120` §1). Bu yüzden burada dokuz dil var ve bu, hiçbir dilin
 * sunulduğu anlamına gelmiyor.
 *
 * ═══ NEDEN BİR ENUM, BİR YAPILANDIRMA DİZİSİ DEĞİL ═══
 *
 * Bir dilin endonimi ya da yönü bir AYAR değildir: Arapça yarın soldan sağa
 * yazılmaya başlamaz ve "Türkçe" yarın başka bir kelimeye dönmez. Ayar
 * dosyasına konsaydı bir dağıtımda yanlışlıkla boşaltılabilirdi ve o gün
 * dil değiştirici adsız satırlar çizerdi. Ağırlık zinciri (§4) ayardır,
 * çünkü o gerçekten bir tercihtir — bu değil.
 */
enum Language: string
{
    case English = 'en';
    case Turkish = 'tr';
    case Arabic = 'ar';
    case Russian = 'ru';
    case Persian = 'fa';
    case Kurdish = 'ku';
    case German = 'de';
    case French = 'fr';
    case Italian = 'it';

    /**
     * Dilin KENDİ DİLİNDEKİ adı — asla çevrilmez, asla katalogdan gelmez.
     *
     * Sahibin gerekçesi kendi cümlesinde: "yabancı dil bilmeyen Türk, kendi
     * dilini kendi dilinde okuyabilsin." Bir kullanıcı arayüzü ANLAMADIĞI
     * için dil değiştirmeye gelir; ona dil adını anlamadığı dilde göstermek,
     * aracın kendisini bozar.
     *
     * Katalogdan gelseydi, çeviri kilidi açıldığı gün bir çevirmen
     * "English"i "İngilizce" yapardı — ve bu bir hata olarak görünmezdi.
     */
    public function endonym(): string
    {
        return match ($this) {
            self::English => 'English',
            self::Turkish => 'Türkçe',
            self::Arabic => 'العربية',
            self::Russian => 'Русский',
            self::Persian => 'فارسی',
            self::Kurdish => 'Kurdî',
            self::German => 'Deutsch',
            self::French => 'Français',
            self::Italian => 'Italiano',
        };
    }

    /**
     * Yazım yönü — bir LOCALE özelliğidir, bir şablon kararı değil.
     *
     * Hiçbir bileşenin bunu bilmesi gerekmez (`docs/37` §2.2, kesen eksen
     * X3); belge kökü `<html dir>` yazar, geri kalanı mantıksal CSS
     * özellikleriyle kendiliğinden döner.
     */
    public function direction(): string
    {
        return match ($this) {
            self::Arabic, self::Persian => 'rtl',
            default => 'ltr',
        };
    }

    /**
     * Yazı sistemi (ISO 15924).
     *
     * Kürtçe burada `Latn`: bugün alınan dil Kurmancîdir. Soranî (`ckb`)
     * Arap yazısıyla ve sağdan sola yazılır; ayrı bir dildir ve gerekirse
     * ayrı eklenir (`docs/120` §8). İkisini tek koda sıkıştırmak, birini
     * yanlış yazı sistemiyle göstermek olurdu.
     */
    public function script(): string
    {
        return match ($this) {
            self::Arabic, self::Persian => 'Arab',
            self::Russian => 'Cyrl',
            default => 'Latn',
        };
    }

    /**
     * Dilin birebir eşleştiği ülke (ISO 3166-1 alpha-2) — ya da yoksa `null`.
     *
     * `docs/120` §6, sahibin bayrak isteğinin dürüst sınırı:
     *
     * - `ar` yirmiden fazla ülkenin dilidir; birini seçmek diğerlerini
     *   dışarıda bırakır.
     * - `ku` için devlet bayrağı yoktur ve kullanılan işaretler siyasi
     *   iddia taşır.
     * - `en` için "Birleşik Krallık mı, ABD mi" sorusunun doğru cevabı
     *   yoktur.
     *
     * Yanlış bayrak sessiz bir hata değildir: kullanıcıyı kimliği üzerinden
     * yanlış yerleştirir. Bir ürünün dil menüsü böyle bir iddiada
     * bulunmamalıdır.
     */
    public function countryCode(): ?string
    {
        return match ($this) {
            self::Turkish => 'TR',
            self::Russian => 'RU',
            self::Persian => 'IR',
            self::German => 'DE',
            self::French => 'FR',
            self::Italian => 'IT',
            self::English, self::Arabic, self::Kurdish => null,
        };
    }

    public function hasCountryFlag(): bool
    {
        return $this->countryCode() !== null;
    }

    /**
     * Dil değiştiricide endonimin yanında duran iki harfli işaret.
     *
     * Ülkesi olan dil için ülke kodu, olmayan için DİLİN KENDİ KODU. İkincisi
     * "nötr bölge işareti"dir: hiçbir ülke iddiası taşımaz ama hizayı da
     * bozmaz — boş bırakılsaydı kullanıcı o satırın eksik olduğunu sanırdı.
     *
     * İşaret METİNDİR, emoji değil. Bayrak emojisi iki "regional indicator"
     * kod noktasından oluşur ve emoji bu üründe yasaktır; ayrıca aynı emoji
     * her işletim sisteminde başka çizilir, kimi platformda hiç çizilmez.
     * Metin her yerde aynı okunur. Gerçek bayrak görseli istendiği gün, bu
     * kod bir SVG kümesinin ANAHTARI olur — veri değişmez.
     */
    public function regionMark(): string
    {
        return $this->countryCode() ?? strtoupper($this->value);
    }

    /**
     * Bir etiketi kütükteki dile indirger; tanımadığını `null` döndürür.
     *
     * `tr-TR` taban dile (`tr`) iner: katalog taban dillerle anahtarlanır ve
     * bölgeli bir etiket yüzünden bir dili "desteklenmiyor" saymak, gerçekte
     * desteklenen bir dili yok saymak olurdu.
     */
    public static function tryFromTag(string $tag): ?self
    {
        $base = strtolower(explode('-', str_replace('_', '-', trim($tag)))[0]);

        return self::tryFrom($base);
    }
}
