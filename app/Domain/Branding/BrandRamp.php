<?php

declare(strict_types=1);

namespace App\Domain\Branding;

/**
 * TEK BİR TONDAN, TEK BİR ZEMİN İÇİN TÜRETİLEN RAMPA.
 *
 * Kiracı tek tek renk değerleri girmez. Girseydi kontrast garantisi
 * kaybolurdu: açık sarı bir "metin rengi" beyaz üstünde okunmaz ve bunu ilk
 * fark eden kişi masadaki misafir olurdu. Bunun yerine kiracı BİR TON verir
 * ve ürün ondan yüzey/metin/kenar rampasını hesaplar.
 *
 * ## Türetme kuralı, tek cümlede
 *
 * Ton (hue) ve doygunluk (saturation) OLDUĞU GİBİ KORUNUR; yalnız AÇIKLIK
 * (lightness) oynatılır ve **eşiği geçen en yakın açıklık** seçilir. Yani
 * bordo bordo kalır, sadece okunacak kadar koyulaşır — ve gereğinden bir
 * adım fazla koyulaşmaz.
 *
 * Yön de rastgele değildir: açık zeminde önce KOYULAŞTIRILIR, koyu zeminde
 * önce AÇILTILIR. İki yön de eşit uzaklıkta çözüm veriyorsa zeminden
 * uzaklaşan kazanır, çünkü diğeri zemine yaklaşıp rengi eritir.
 *
 * ## Neden her rol ayrı ölçülür
 *
 * Aynı ton düğme zemini, fiyat metni ve 2 px'lik çizgi olarak üç ayrı iş
 * yapar; WCAG 2.2 de bunları ayrı ölçer (`BrandRampRole::floor()`).
 */
final readonly class BrandRamp
{
    /**
     * Açıklık taramasının çözünürlüğü. 500 adım, 8 bitlik bir kanalın
     * ayırt edebileceğinden zaten incedir; daha küçük adım aynı hex'i
     * tekrar ölçmek olurdu.
     */
    private const int SEARCH_STEPS = 500;

    /**
     * Yumuşak tonun zeminden sapma payı. %12: renk fark edilir ama zeminle
     * yarışmaz. Bu bir tasarım kararıdır ve tek yerde durur; okunabilirlik
     * kararı DEĞİLDİR — onu aşağıdaki ölçüm verir.
     */
    private const float SOFT_TINT_FRACTION = 0.12;

    /** @param  array<string, BrandRampValue>  $values  rol anahtarı → basamak */
    private function __construct(
        public SkinSurface $surface,
        private array $values,
    ) {}

    public static function derive(SrgbColor $seed, SkinSurface $surface): self
    {
        $canvas = SrgbColor::fromHex($surface->canvasHex());
        // Açık zeminde kaçış yönü koyuluk, koyu zeminde açıklıktır.
        $preferDarker = $canvas->relativeLuminance() > 0.5;

        $values = [];

        // 1) MARKA METNİ — fiyat, bağlantı, etkin sekme. Zemine karşı 4.5.
        $inkFloor = BrandRampRole::AccentInk->floor();
        [$ink, $inkAdjusted] = self::nearestLightness(
            $seed,
            static fn (SrgbColor $c): bool => $c->contrastRatio($canvas) >= $inkFloor,
            $preferDarker,
        );
        $values[BrandRampRole::AccentInk->value] = self::value(
            BrandRampRole::AccentInk,
            $ink,
            $canvas,
            $inkAdjusted,
        );

        // 2) MARKA DOLGUSU — üstüne yazı gelir; ölçü dolgu ile YAZI arasındadır.
        //    Yazı türetilmez, ürünün iki uç mürekkebinden biri SEÇİLİR:
        //    koyu bir dolguya koyu yazı yazmak bir tercih değil, hatadır.
        $fillFloor = BrandRampRole::AccentSurface->floor();
        $candidates = array_map(
            static fn (string $hex): SrgbColor => SrgbColor::fromHex($hex),
            SkinSurface::onAccentCandidates(),
        );
        [$fill, $fillAdjusted] = self::nearestLightness(
            $seed,
            static function (SrgbColor $c) use ($candidates, $fillFloor): bool {
                foreach ($candidates as $candidate) {
                    if ($c->contrastRatio($candidate) >= $fillFloor) {
                        return true;
                    }
                }

                return false;
            },
            $preferDarker,
        );

        $label = $candidates[0];

        foreach ($candidates as $candidate) {
            if ($fill->contrastRatio($candidate) > $fill->contrastRatio($label)) {
                $label = $candidate;
            }
        }

        $values[BrandRampRole::AccentSurface->value] = self::value(
            BrandRampRole::AccentSurface,
            $fill,
            $label,
            $fillAdjusted,
        );
        $values[BrandRampRole::OnAccentSurface->value] = self::value(
            BrandRampRole::OnAccentSurface,
            $label,
            $fill,
            // Bu değer kiracının tonundan TÜRETİLMEZ, seçilir; "ayarlandı"
            // demek yanlış bir hikâye anlatırdı.
            false,
        );

        // 3) YUMUŞAK TON — çip zemini, seçili satır. Üstünde marka metni durur.
        $softFloor = BrandRampRole::AccentSoftSurface->floor();
        $soft = self::softTint($seed, $canvas, $ink, $softFloor);
        $values[BrandRampRole::AccentSoftSurface->value] = self::value(
            BrandRampRole::AccentSoftSurface,
            $soft,
            $ink,
            $soft->toHex() !== $seed->toHex(),
        );

        // 4) MARKA ÇİZGİSİ — üst şerit, kategori altı çizgi. Metin değil: 3.0.
        $borderFloor = BrandRampRole::AccentBorder->floor();
        [$border, $borderAdjusted] = self::nearestLightness(
            $seed,
            static fn (SrgbColor $c): bool => $c->contrastRatio($canvas) >= $borderFloor,
            $preferDarker,
        );
        $values[BrandRampRole::AccentBorder->value] = self::value(
            BrandRampRole::AccentBorder,
            $border,
            $canvas,
            $borderAdjusted,
        );

        return new self($surface, $values);
    }

    public function role(BrandRampRole $role): BrandRampValue
    {
        return $this->values[$role->value];
    }

    /** @return list<BrandRampValue> */
    public function values(): array
    {
        return array_values($this->values);
    }

    /** @return array<string, string> token adı → `#rrggbb` */
    public function cssCustomProperties(): array
    {
        $properties = [];

        foreach ($this->values as $value) {
            $properties[$value->role->token()] = $value->hex;
        }

        return $properties;
    }

    /** @return array{canvas: string, roles: array<string, array<string, mixed>>} */
    public function toSnapshot(): array
    {
        $roles = [];

        foreach ($this->values as $key => $value) {
            $roles[$key] = $value->toSnapshot();
        }

        return ['canvas' => $this->surface->canvasHex(), 'roles' => $roles];
    }

    /** @param  array<string, mixed>  $entry */
    public static function fromSnapshot(SkinSurface $surface, array $entry): ?self
    {
        $roles = is_array($entry['roles'] ?? null) ? $entry['roles'] : [];
        $values = [];

        foreach (BrandRampRole::cases() as $role) {
            $raw = $roles[$role->value] ?? null;

            if (! is_array($raw)) {
                // Eksik bir basamak SESSİZCE tamamlanmaz. Yarısı donmuş bir
                // rampayı yeniden hesaplayarak tamamlamak, yayının
                // değişmezliğini bozardı.
                return null;
            }

            $value = BrandRampValue::fromSnapshot($role, $raw);

            if ($value === null) {
                return null;
            }

            $values[$role->value] = $value;
        }

        return new self($surface, $values);
    }

    private static function value(
        BrandRampRole $role,
        SrgbColor $color,
        SrgbColor $against,
        bool $adjusted,
    ): BrandRampValue {
        return new BrandRampValue(
            role: $role,
            hex: $color->toHex(),
            againstHex: $against->toHex(),
            // Kanıt iki basamağa yuvarlanır ve BU HÂLİYLE donar: yayına
            // yazılan sayı ile bellekteki sayı ayrışırsa, kanıt kendi
            // rengini anlatmıyor demektir.
            ratio: round($color->contrastRatio($against), 2),
            floor: $role->floor(),
            adjusted: $adjusted,
        );
    }

    /**
     * Eşiği geçen EN YAKIN açıklık.
     *
     * Tohum zaten geçiyorsa hiç oynatılmaz — dürüstlük iki yönlüdür: ürün
     * gerekmedikçe kiracının rengine dokunmaz.
     *
     * @param  callable(SrgbColor): bool  $passes
     * @return array{0: SrgbColor, 1: bool}
     */
    private static function nearestLightness(SrgbColor $seed, callable $passes, bool $preferDarker): array
    {
        if ($passes($seed)) {
            return [$seed, false];
        }

        $lightness = $seed->lightness();
        $order = $preferDarker ? [-1, 1] : [1, -1];

        for ($step = 1; $step <= self::SEARCH_STEPS; $step++) {
            $delta = $step / self::SEARCH_STEPS;

            foreach ($order as $sign) {
                $candidate = $seed->withLightness($lightness + $sign * $delta);

                if ($passes($candidate)) {
                    return [$candidate, true];
                }
            }
        }

        /*
            Buraya düşmek teorik olarak imkânsızdır: uçlar (siyah/beyaz)
            her zemine karşı 21:1'e kadar açılır. Yine de sessiz kalınmaz —
            eşiği geçmeyen bir rengi "geçti" sayarak yayına çıkarmak, bu
            paketin engellemek için var olduğu tek şeydir.
        */
        return [$seed->withLightness($preferDarker ? 0.0 : 1.0), true];
    }

    /**
     * Yumuşak ton: zemine doğru çekilmiş marka rengi.
     *
     * Önce tasarım kararı uygulanır (zeminden %12 sapma), sonra ÖLÇÜLÜR.
     * Marka metni üstünde okunmuyorsa ton zemine doğru yürütülür; yani
     * okunabilirlik kararı estetik kararı ezer (`docs/37` §1).
     */
    private static function softTint(
        SrgbColor $seed,
        SrgbColor $canvas,
        SrgbColor $ink,
        float $floor,
    ): SrgbColor {
        $canvasLightness = $canvas->lightness();
        $start = $canvasLightness + ($seed->lightness() - $canvasLightness) * self::SOFT_TINT_FRACTION;

        for ($step = 0; $step <= self::SEARCH_STEPS; $step++) {
            $progress = $step / self::SEARCH_STEPS;
            $candidate = $seed->withLightness($start + ($canvasLightness - $start) * $progress);

            if ($ink->contrastRatio($candidate) >= $floor) {
                return $candidate;
            }
        }

        // Hiçbir tonlu değer okunmuyorsa yumuşak yüzey ZEMİNİN KENDİSİ olur:
        // tonu olmayan bir çip, okunmayan bir çipten iyidir.
        return $canvas;
    }
}
