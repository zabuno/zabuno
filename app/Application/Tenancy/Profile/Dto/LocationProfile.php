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
        /**
         * ŞU AN AÇIK MIYIZ — `docs/109` §8.2/§8.6 (FF-148).
         *
         * `openingHours` bir TARİFEDİR ("09:00–23:00"); bu alan bir DURUMDUR
         * ("şu an açık"). Sahibin şubeler ekranında sorduğu soru ikincisidir
         * ve tarifeden istemcide çıkarılamaz: tarayıcının saati kullanıcının
         * kendi ayarıdır, şubenin saat dilimi ise zaten sunucuda biliniyor
         * (`locations.timezone`, `docs/62`). Cevap bu yüzden okuma yolunda,
         * misafir yüzeyiyle AYNI değer nesnesinden hesaplanır
         * (`WeeklyOpeningHours::isClosedAt`) — ikinci bir hesap bir gün aynı
         * şube için iki farklı cevap verirdi.
         *
         * ÜÇ DEĞERLİDİR ve `null` ile `false` aynı şey DEĞİLDİR: `null`
         * "söylenmemiş"tir (saat girilmemiş, hafta okunamıyor ya da saat
         * dilimi yok) ve ekranda hiçbir rozet çizilmez. Saatini girmemiş bir
         * şubeye "kapalı" demek, sahibin hiç kurmadığı bir cümleyi onun
         * ağzından söylemek olurdu — aynı sessizlik kuralı misafir tarafında
         * da geçerli (`GuestOpeningHoursPort`).
         */
        public readonly ?bool $openNow = null,
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
            // Alan HER ZAMAN bulunur, değeri `null` olsa bile: anahtarı hiç
            // göndermemek, istemciyi "yok" ile "bilinmiyor" arasında ayrım
            // yapamaz hâle getirir ve `undefined` okuyan bir kart sessizce
            // yanlış dalı seçerdi.
            'open_now' => $this->openNow,
        ];
    }
}
