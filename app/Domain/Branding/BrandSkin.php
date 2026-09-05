<?php

declare(strict_types=1);

namespace App\Domain\Branding;

/**
 * RESTORANIN MARKA KİMLİĞİ — bir ton, bir biçim ve ölçülmüş kanıtı.
 *
 * Bu depo bugüne kadar kiracı rengini bilerek dekorasyona hapsetti (4 px
 * şerit + 2 px çizgi) ve gerekçesini misafir şablonuna yazdı: *"kontrastı
 * biz garanti edemeyiz."* Gerekçe doğruydu. Bu yüzden kısıt KALDIRILMIYOR,
 * ÖLÇÜYE ÇEVRİLİYOR (`docs/113` §5.2):
 *
 *   1. Kiracı bir TON verir; tek tek renk değerleri girmez.
 *   2. Ürün ondan iki temanın rampasını türetir ve her metin/zemin çiftini
 *      WCAG 2.2 AA'ya karşı ÖLÇER; geçmiyorsa tonu geçene kadar ayarlar.
 *   3. Kiracı ayrıca bir BİÇİM seçer — değer değil, platformun bir kez
 *      ölçtüğü altı varyanttan biri (`SkinVariant`).
 *
 * Ve hepsi yayına DONAR: `toSnapshot()` ile yazılan blok, `fromSnapshot()`
 * ile aynen geri okunur; hiçbir şey yeniden hesaplanmaz. Ocak'ta AA geçen
 * bir yayın, Mart'ta kural değişse bile kendi kanıtını taşımaya devam eder.
 */
final readonly class BrandSkin
{
    /** @param  array<string, BrandRamp>  $ramps  zemin anahtarı → rampa */
    private function __construct(
        /** Kiracının GİRDİĞİ ton. Yayınlanan değer değil, girdisidir. */
        public string $seedHex,
        public SkinVariant $variant,
        private array $ramps,
    ) {}

    public static function derive(string $seedHex, SkinVariant $variant): self
    {
        $seed = SrgbColor::fromHex($seedHex);
        $ramps = [];

        // İKİ TEMA DA ÖLÇÜLÜR. Hangisinin açılacağına misafirin cihazı karar
        // verir; yalnız birini ölçmek, gece menüyü açan misafiri hesaba
        // katmamak olurdu.
        foreach (SkinSurface::cases() as $surface) {
            $ramps[$surface->value] = BrandRamp::derive($seed, $surface);
        }

        return new self($seed->toHex(), $variant, $ramps);
    }

    public function ramp(SkinSurface $surface): BrandRamp
    {
        return $this->ramps[$surface->value];
    }

    /**
     * Rampanın YAYINLANABİLİR biçimi: token adı → renk.
     *
     * Bileşene ham renk girmez (`DS-RAW-PALETTE-BANNED-01`); bileşen token
     * okur. Bu yüzden misafir yüzeyi bu haritayı olduğu gibi bir custom
     * property bloğuna yazar ve hiçbir kural kendi rengini seçmez.
     *
     * @return array<string, string>
     */
    public function cssCustomProperties(SkinSurface $surface): array
    {
        return $this->ramp($surface)->cssCustomProperties();
    }

    /**
     * Türetilen her çift eşiğini geçti mi?
     *
     * Yayın kapısının soracağı tek soru budur. Türetme zaten geçene kadar
     * ayarladığı için normal yolda hep `true`'dur; bu metot o sözü
     * kanıtlanabilir kılar — söz, kendini denetleyebildiği sürece sözdür.
     */
    public function meetsContrastFloor(): bool
    {
        foreach ($this->ramps as $ramp) {
            foreach ($ramp->values() as $value) {
                if ($value->ratio < $value->floor) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @return array{seed: string, variant: string, surfaces: array<string, array<string, mixed>>} */
    public function toSnapshot(): array
    {
        $surfaces = [];

        foreach ($this->ramps as $key => $ramp) {
            $surfaces[$key] = $ramp->toSnapshot();
        }

        return [
            'seed' => $this->seedHex,
            'variant' => $this->variant->value,
            'surfaces' => $surfaces,
        ];
    }

    /**
     * Yayından geri okur — HESAPLAMAZ.
     *
     * Eksik ya da tanınmayan bir blok `null` döner ve sayfa bugünkü nötr
     * görünümüne düşer. Yarısı okunan bir bloğu tamamlamak, misafire
     * sahibin hiç onaylamadığı bir renk göstermek olurdu.
     *
     * @param  array<string, mixed>  $entry
     */
    public static function fromSnapshot(array $entry): ?self
    {
        $seed = SrgbColor::tryFromHex(is_string($entry['seed'] ?? null) ? $entry['seed'] : null);
        $variant = SkinVariant::tryFromKey(is_string($entry['variant'] ?? null) ? $entry['variant'] : null);

        if ($seed === null || $variant === null) {
            return null;
        }

        $surfaces = is_array($entry['surfaces'] ?? null) ? $entry['surfaces'] : [];
        $ramps = [];

        foreach (SkinSurface::cases() as $surface) {
            $raw = $surfaces[$surface->value] ?? null;

            if (! is_array($raw)) {
                return null;
            }

            $ramp = BrandRamp::fromSnapshot($surface, $raw);

            if ($ramp === null) {
                return null;
            }

            $ramps[$surface->value] = $ramp;
        }

        return new self($seed->toHex(), $variant, $ramps);
    }
}
