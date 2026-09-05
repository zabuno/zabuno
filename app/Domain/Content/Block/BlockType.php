<?php

declare(strict_types=1);

namespace App\Domain\Content\Block;

/**
 * Bir kurumsal içerik BLOĞUNUN türü — yönerge §15.
 *
 * Yönergenin ürün sayfası şablonu burada bir ENUM'a dönüşüyor, çünkü şablonu
 * belgede bırakmak beş sayfa için beş ayrı yorum üretirdi. Sıra da türün
 * kendisinde yaşıyor: "kısa doğrudan cevap sayfanın BAŞINDA" bir üslup
 * tercihi değil, cevap sistemlerinin okuma biçimidir (§13.3) ve bir
 * geliştiricinin blokları elle doğru sıraya dizmesine bırakılamaz.
 *
 * Türler ARTMAZ: yeni bir sayfa yeni bir tür değil, aynı türlerin farklı
 * içeriğidir. Yüzlerce kopya sayfa bileşeni üretmemenin uygulanışı budur.
 */
enum BlockType: string
{
    /** H1'in hemen altındaki, alıntılanabilir tek cümlelik cevap. */
    case DirectAnswer = 'direct_answer';

    /** Misafirin ya da restoran sahibinin bugün yaşadığı somut sorun. */
    case Problem = 'problem';

    /** Zabuno'nun o soruna verdiği cevap. */
    case Solution = 'solution';

    /** Adım listesi — §13.3'ün "adım listeleri" gereksinimi. */
    case HowItWorks = 'how_it_works';

    /** Ürünün BUGÜN yaptığı işler; her satır depoda bir kanıt taşır. */
    case Capabilities = 'capabilities';

    /** Gereksinim tablosu — §13.3'ün "gereksinim tabloları" gereksinimi. */
    case Requirements = 'requirements';

    /** Ürünün BUGÜN yapmadığı işler. Sayfanın en dürüst bölümü. */
    case Limitations = 'limitations';

    /** Görünür soru-cevap. Yalnız bu blok varsa `FAQPage` üretilir. */
    case Faq = 'faq';

    /** Tek birincil eylem. */
    case Cta = 'cta';

    /**
     * İlgili sayfalar — bağlantılar YAYINLANMIŞ sayfalara SÜZÜLÜR.
     *
     * `docs/105` §2.2(3): yayınlanmamış sayfa hiçbir yerden iç bağlantı
     * almaz. Hiçbir yere götürmeyen bir bağlantı bir yalandır ve deponun
     * kendi bozuk-bağlantı kapısını kırar.
     */
    case Related = 'related';

    /**
     * Şablondaki yeri. Küçük olan üstte durur.
     *
     * Aralıklı numaralandırma bilinçli: §15'te bulunan ama bu pakette
     * yazılmayan bloklar (ekran görüntüsü, entegrasyonlar, müşteri kanıtı)
     * gerçekten var olduklarında araya girer ve mevcut sıralar kaymaz.
     */
    public function rank(): int
    {
        return match ($this) {
            self::DirectAnswer => 10,
            self::Problem => 20,
            self::Solution => 30,
            self::HowItWorks => 40,
            self::Capabilities => 50,
            self::Requirements => 70,
            self::Limitations => 80,
            self::Faq => 120,
            self::Cta => 130,
            self::Related => 140,
        };
    }

    /**
     * Bu blok kendi başlığını taşır mı?
     *
     * Doğrudan cevap taşımaz: o, tek H1'in altındaki giriş cümlesidir. Ona
     * ayrı bir başlık vermek, sayfanın ilk ekranını içerikten önce başlıkla
     * doldurmak olurdu (`TOUCH-FIRST-INTERFACE` madde 3).
     */
    public function needsHeading(): bool
    {
        return $this !== self::DirectAnswer;
    }

    /**
     * Ürün sayfasında BULUNMAK ZORUNDA olan bloklar, şablon sırasında.
     *
     * "İlgili sayfalar" zorunlu değildir ve bu bilinçli: bugün yayınlanmış
     * tek bir kurumsal sayfa yok, dolayısıyla süzgeçten geçen bağlantı da
     * yok. Zorunlu olsaydı, sayfa boş bir başlık çizerdi.
     *
     * @return list<self>
     */
    public static function requiredForProductPage(): array
    {
        return [
            self::DirectAnswer,
            self::Problem,
            self::Solution,
            self::HowItWorks,
            self::Capabilities,
            self::Requirements,
            self::Limitations,
            self::Faq,
            self::Cta,
        ];
    }
}
