<?php

declare(strict_types=1);

namespace App\Domain\Publication;

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

        return new self(
            brandName: trim($brandName),
            locationName: trim($locationName),
            addressLine: $street === [] ? null : implode(', ', $street),
            phone: $phone === '' ? null : $phone,
            primaryColor: self::normaliseColor($primaryColor),
            secondaryColor: self::normaliseColor($secondaryColor),
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

    /** @return array{brandName:string,locationName:string,addressLine:string|null,phone:string|null,primaryColor:string|null,secondaryColor:string|null} */
    public function toSnapshot(): array
    {
        return [
            'brandName' => $this->brandName,
            'locationName' => $this->locationName,
            'addressLine' => $this->addressLine,
            'phone' => $this->phone,
            'primaryColor' => $this->primaryColor,
            'secondaryColor' => $this->secondaryColor,
        ];
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
        );
    }
}
