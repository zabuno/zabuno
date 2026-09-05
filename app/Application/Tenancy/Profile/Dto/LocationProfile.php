<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Profile\Dto;

use App\Domain\Tenancy\ValueObject\WeeklyOpeningHours;

final class LocationProfile
{
    public function __construct(
        public readonly int $id,
        public readonly int $workspaceId,
        public readonly int $brandId,
        public readonly string $displayName,
        public readonly string $countryCode,
        /** IANA kimliği (`Europe/Istanbul`) — sabit offset DEĞİL (docs/62). */
        public readonly string $timezone,
        public readonly string $city,
        public readonly string $addressLine1,
        public readonly ?string $addressLine2,
        public readonly ?string $postalCode,
        /**
         * Şubenin masa sayısı — `docs/109` §6.4 (Şubeler kartı).
         *
         * Şube kartı "N masa" yazar ve bu ölçüm `dining_tables` satırlarından
         * gelir. Listeye eklenmesinin sebebi kapsam değil MALİYET: sayı
         * burada olmasaydı, beş şubeli bir markanın şube ekranı beş ayrı QR
         * listesi isteği atmak zorunda kalırdı.
         *
         * Varsayılan sıfırdır ve sıfır GERÇEK bir cevaptır: henüz masası
         * girilmemiş, yani kurulumu bitmemiş bir şube.
         */
        public readonly int $tableCount = 0,
        /**
         * Şubenin haftalık çalışma saatleri — `docs/109` §6.4.
         *
         * Kaynağın kartındaki ÜÇÜNCÜ ölçü ("09:00–23:00"). Masa sayısı gibi
         * liste ucunda taşınır: kart onu ayrı bir istek atmadan çizebilmeli,
         * yoksa beş şubeli bir marka beş ek istek atardı.
         *
         * BOŞ hafta uydurma bir varsayılan değil, sessizliktir: saati
         * girilmemiş şube `[]` döner ve kart o satırı HİÇ çizmez. `null` ile
         * `WeeklyOpeningHours::none()` bu DTO'da aynı anlama gelir —
         * "söylenmemiş"; ikisini ayırmak yalnız YAZMA yolunda gerekir
         * (`LocationRepositoryPort::update`).
         */
        public readonly ?WeeklyOpeningHours $openingHours = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspaceId,
            'brand_id' => $this->brandId,
            'display_name' => $this->displayName,
            'country_code' => $this->countryCode,
            'timezone' => $this->timezone,
            'city' => $this->city,
            'address_line1' => $this->addressLine1,
            'address_line2' => $this->addressLine2,
            'postal_code' => $this->postalCode,
            'table_count' => $this->tableCount,
            'opening_hours' => $this->openingHours?->toArray() ?? [],
        ];
    }
}
