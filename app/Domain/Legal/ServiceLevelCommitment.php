<?php

declare(strict_types=1);

namespace App\Domain\Legal;

/**
 * Hizmet seviyesi taahhüdü VAR MI? (FF-228, `docs/107` Faz 3.2)
 *
 * `LegalReview` ve `ResponseCommitment` ile aynı sınıftan bir nesne: bir
 * SAYIYI değil, o sayının GİRİLİP GİRİLMEDİĞİNİ bilir.
 *
 * DÖRDÜ BİRLİKTE YA DA HİÇBİRİ. Hedef oran, oranın okunacağı kamuya açık
 * kaynak, bildirim süresi ve telafi oranı tek bir taahhüdün dört yüzüdür:
 * ölçüsü olmayan bir oran doğrulanamaz, telafisi olmayan bir hedef bir
 * temenni, bildirimi olmayan bir kesinti ise müşterinin kendi başına fark
 * etmesi gereken bir olaydır. Üçü dolu biri boşken "kısmi taahhüt" yazmak,
 * okuyucuya tam bir SLA varmış izlenimi verirdi.
 *
 * SAYI OLMAYAN DEĞER "GİRİLMEDİ"DİR. `SLA_AVAILABILITY_TARGET_PERCENT=çok`
 * ya da `=%99,9` bir taahhüt değil bir yazım hatasıdır; sayfa onu taahhüt
 * diye basmaz (`LegalReview`'ün "yes" bir inceleme değildir kuralı).
 */
final class ServiceLevelCommitment
{
    public const FIELDS = [
        'availability_target_percent',
        'measurement_source',
        'incident_notification_hours',
        'service_credit_percent',
    ];

    private function __construct(
        private readonly ?string $availabilityTargetPercent,
        private readonly ?string $measurementSource,
        private readonly ?int $incidentNotificationHours,
        private readonly ?int $serviceCreditPercent,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            self::percent(config('sla.availability_target_percent')),
            self::text(config('sla.measurement_source')),
            self::positiveInt(config('sla.incident_notification_hours')),
            self::percentInt(config('sla.service_credit_percent')),
        );
    }

    public function availabilityTargetPercent(): ?string
    {
        return $this->availabilityTargetPercent;
    }

    public function measurementSource(): ?string
    {
        return $this->measurementSource;
    }

    public function incidentNotificationHours(): ?int
    {
        return $this->incidentNotificationHours;
    }

    public function serviceCreditPercent(): ?int
    {
        return $this->serviceCreditPercent;
    }

    /**
     * Girilmemiş alanların yapılandırma adları — sayfa onları ADIYLA sayar.
     *
     * "Bir şeyler eksik" bir bilgi değildir: sahip hangi değeri hangi
     * anahtara yazacağını görmeli (`CompanyProfile::missing()` ile aynı
     * karar).
     *
     * @return list<string>
     */
    public function missing(): array
    {
        $missing = [];

        if ($this->availabilityTargetPercent === null) {
            $missing[] = 'availability_target_percent';
        }

        if ($this->measurementSource === null) {
            $missing[] = 'measurement_source';
        }

        if ($this->incidentNotificationHours === null) {
            $missing[] = 'incident_notification_hours';
        }

        if ($this->serviceCreditPercent === null) {
            $missing[] = 'service_credit_percent';
        }

        return $missing;
    }

    public function isComplete(): bool
    {
        return $this->missing() === [];
    }

    /**
     * Yüzde METİN olarak korunur: "99.5" bir kayan noktalı sayıya
     * çevrilip geri yazıldığında "99.5" kalacağının garantisi yoktur ve bir
     * sözleşme rakamı basıldığı gibi görünmek zorundadır.
     */
    private static function percent(mixed $raw): ?string
    {
        if (! is_string($raw) && ! is_int($raw) && ! is_float($raw)) {
            return null;
        }

        $value = trim((string) $raw);

        if (preg_match('/^\d{1,3}(\.\d{1,3})?$/', $value) !== 1) {
            return null;
        }

        return ((float) $value) > 0.0 && ((float) $value) <= 100.0 ? $value : null;
    }

    private static function percentInt(mixed $raw): ?int
    {
        $value = self::positiveInt($raw);

        return $value !== null && $value <= 100 ? $value : null;
    }

    private static function positiveInt(mixed $raw): ?int
    {
        if (is_int($raw)) {
            return $raw > 0 ? $raw : null;
        }

        if (! is_string($raw) || preg_match('/^\d+$/', trim($raw)) !== 1) {
            return null;
        }

        $value = (int) trim($raw);

        return $value > 0 ? $value : null;
    }

    private static function text(mixed $raw): ?string
    {
        return is_string($raw) && trim($raw) !== '' ? trim($raw) : null;
    }
}
