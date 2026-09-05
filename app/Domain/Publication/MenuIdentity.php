<?php

declare(strict_types=1);

namespace App\Domain\Publication;

use App\Domain\Branding\BrandSkin;
use App\Domain\Branding\SkinVariant;

/**
 * Bir yayının taşıdığı RESTORAN KİMLİĞİ (`docs/75`, P0-03).
 *
 * Misafirin gördüğü ilk şey "Menü" değil, gittiği yerin adıdır. Bu değer
 * yayın anında DONAR: şubenin adı ya da telefonu sonradan değişse bile
 * geçmiş bir yayın değişmez, çünkü yayın sahibin "bunu onayladım" dediği
 * hâldir.
 */
final class MenuIdentity
{
    public function __construct(
        public readonly string $brandName,
        public readonly string $locationName,
        public readonly ?string $addressLine,
        public readonly ?string $phone,
        /*
            Marka renkleri de kimliğin parçasıdır ve yayınla birlikte DONAR
            (FF-89). Renk yarın değişirse dünkü yayın değişmez; misafirin
            gördüğü sayfa, sahibin onayladığı hâldir.

            Biçim tek: `#rrggbb`, küçük harf. Tanınmayan bir değer `null`
            olur — geçersiz bir dizeyi CSS'e yazmak, sayfanın vurgu rengini
            tarayıcının insafına bırakırdı.
        */
        public readonly ?string $primaryColor = null,
        public readonly ?string $secondaryColor = null,
        /*
            MARKA SKIN'İ: türetilmiş rampa ve ÖLÇÜLMÜŞ kontrast oranları
            (FF-174, `docs/113` §5.2).

            Renk sütunu kiracının GİRDİSİDİR; bu alan ürünün ondan türettiği
            ve okunabilirliğini kanıtladığı hâldir. İkisi ayrı durur çünkü
            girdi değişebilir, kanıt donmalıdır: Ocak'ta AA geçen bir yayın,
            Mart'ta eşik değişse bile kendi kanıtını taşımaya devam eder.
        */
        public readonly ?BrandSkin $skin = null,
    ) {}

    /** `#RRGGBB` → `#rrggbb`; tanınmayan her şey `null`. */
    public static function normaliseColor(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));

        return preg_match('/^#[0-9a-f]{6}$/', $value) === 1 ? $value : null;
    }

    /**
     * Adres tek satırda kurulur: misafir bir form değil, bir adres okur.
     * Boş parçalar sessizce düşer; yoksa "…, ," gibi bir metin çıkardı.
     */
    public static function fromParts(
        string $brandName,
        string $locationName,
        ?string $addressLine1,
        ?string $addressLine2,
        ?string $postalCode,
        ?string $city,
        ?string $phone,
        ?string $primaryColor = null,
        ?string $secondaryColor = null,
        ?string $skinVariant = null,
    ): self {
        $street = array_values(array_filter([
            trim((string) $addressLine1),
            trim((string) $addressLine2),
        ], static fn (string $part): bool => $part !== ''));

        $locality = trim(trim((string) $postalCode).' '.trim((string) $city));

        if ($locality !== '') {
            $street[] = $locality;
        }

        $phone = trim((string) $phone);
        $primary = self::normaliseColor($primaryColor);

        return new self(
            brandName: trim($brandName),
            locationName: trim($locationName),
            addressLine: $street === [] ? null : implode(', ', $street),
            phone: $phone === '' ? null : $phone,
            primaryColor: $primary,
            secondaryColor: self::normaliseColor($secondaryColor),
            /*
                RAMPA YAYIN ANINDA TÜRETİLİR, İSTEKTE DEĞİL.

                Kontrast ölçümü her misafir isteğinde tekrarlansaydı iki şey
                birden bozulurdu: her sayfa açılışında hesap yapılırdı ve
                daha kötüsü, kural değiştiği gün geçmiş yayınlar da sessizce
                yeniden boyanırdı. Ölçüm burada bir kez yapılır ve kanıtıyla
                birlikte donar (`docs/113` §5.2 madde 3).

                Ton yoksa skin de yoktur: seçmeyen restoran, seçmiş gibi
                gösterilmez.
            */
            skin: $primary === null
                ? null
                : BrandSkin::derive($primary, SkinVariant::tryFromKey($skinVariant) ?? SkinVariant::default()),
        );
    }

    /**
     * `tel:` içinde boşluk ve parantez bırakmak bazı telefonlarda çağrıyı
     * bozar; görünen metin insan için, bağlantı makine içindir.
     */
    public function telHref(): ?string
    {
        if ($this->phone === null) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $this->phone) ?? '';

        if ($digits === '') {
            return null;
        }

        return 'tel:'.(str_starts_with(trim($this->phone), '+') ? '+' : '').$digits;
    }

    /** @return array<string, mixed> */
    public function toSnapshot(): array
    {
        $snapshot = [
            'brandName' => $this->brandName,
            'locationName' => $this->locationName,
            'addressLine' => $this->addressLine,
            'phone' => $this->phone,
            'primaryColor' => $this->primaryColor,
            'secondaryColor' => $this->secondaryColor,
        ];

        // Anahtar yalnız skin VARSA yazılır: `'skin' => null` yazmak, eski
        // yayınlarla yeni yayınları ayırt edilemez kılardı.
        if ($this->skin !== null) {
            $snapshot['skin'] = $this->skin->toSnapshot();
        }

        return $snapshot;
    }

    /** @param  array<string,mixed>  $identity */
    public static function fromSnapshot(array $identity): self
    {
        return new self(
            brandName: trim((string) ($identity['brandName'] ?? '')),
            locationName: trim((string) ($identity['locationName'] ?? '')),
            addressLine: ($line = trim((string) ($identity['addressLine'] ?? ''))) === '' ? null : $line,
            phone: ($phone = trim((string) ($identity['phone'] ?? ''))) === '' ? null : $phone,
            /*
                Eski yayınlarda renk alanı YOKTUR ve olmaması normaldir.
                Onları geriye dönük boyamak, "yayın donmuş hâldir" sözünü
                bozardı.
            */
            primaryColor: self::normaliseColor(
                is_string($identity['primaryColor'] ?? null) ? $identity['primaryColor'] : null
            ),
            secondaryColor: self::normaliseColor(
                is_string($identity['secondaryColor'] ?? null) ? $identity['secondaryColor'] : null
            ),
            /*
                Skin OKUNUR, YENİDEN TÜRETİLMEZ (FF-174).

                Yayınlanmış menü, sahibin onayladığı hâldir. Burada yeniden
                hesaplasaydık, türetme kuralının bir sonraki düzeltmesi
                geçmiş her yayının rengini de değiştirirdi — misafir bir gün,
                sahibin hiç görmediği bir menüyü görürdü.
            */
            skin: BrandSkin::fromSnapshot(
                is_array($identity['skin'] ?? null) ? $identity['skin'] : []
            ),
        );
    }
}
