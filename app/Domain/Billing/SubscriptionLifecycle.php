<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Abonelik yaşam döngüsünün TEK hesabı (`docs/107` Faz 1.3, `docs/134`).
 *
 * Saf bir hesaptır: veritabanı, saat, yapılandırma okumaz — hepsi dışarıdan
 * verilir. Bu yüzden aynı cevabı üç yerde üretmek yerine üç yer de burayı
 * çağırır: yetenek okuması (`DatabaseEntitlementRepository`), abonelik özeti
 * (`EloquentSubscriptionRepository`) ve panel.
 *
 * ═══ İKİ SORU, İKİ CEVAP ═══
 *
 * 1. **Hangi evredeyiz?** Bitiş tarihi, iptal tarihi ve ödemesiz süre.
 * 2. **Hangi plan geçerli?** Ödenmiş dönem sürerken ödenen plan; dönem
 *    bittikten sonra (varsa) zamanlanmış düşürmenin planı.
 *
 * İkincisi düşürme kararının bütün mekanizmasıdır ve şunu garanti eder:
 * ödenmiş dönemin ortasında hiçbir yetenek elden gitmez, çünkü düşürme
 * `ends_at`'e kadar hiçbir okumayı değiştirmez.
 *
 * ═══ ÖDEMESİZ SÜRE DÖNEMDEN UZUN OLAMAZ ═══
 *
 * Yapılandırmaya bir dönemden büyük bir sayı yazılırsa hiç ödemeyen bir
 * hesap, ödeyenle aynı yeteneklere sahip olurdu. Sınır burada uygulanır,
 * yapılandırmada değil: yapılandırma bir niyet bildirir, sınırı ürün koyar.
 */
final readonly class SubscriptionLifecycle
{
    private function __construct(
        public SubscriptionPhase $phase,
        public ?DateTimeImmutable $endsAt,
        public ?DateTimeImmutable $cancelledAt,
        public ?DateTimeImmutable $graceEndsAt,
        public int $graceDays,
    ) {}

    public static function none(): self
    {
        return new self(SubscriptionPhase::None, null, null, null, 0);
    }

    /**
     * @param  int  $graceDays  `billing.subscription.grace_days`
     * @param  int  $periodDays  `billing.subscription.period_days` — ödemesiz sürenin üst sınırı
     */
    public static function of(
        DateTimeInterface $endsAt,
        ?DateTimeInterface $cancelledAt,
        int $graceDays,
        int $periodDays,
        DateTimeInterface $now,
    ): self {
        $ends = self::immutable($endsAt);
        $cancelled = $cancelledAt === null ? null : self::immutable($cancelledAt);
        $effectiveGrace = self::boundedGraceDays($graceDays, $periodDays);
        $graceEnds = $ends->modify('+'.$effectiveGrace.' days');
        $current = self::immutable($now);

        if ($current <= $ends) {
            return new self(
                $cancelled === null ? SubscriptionPhase::Active : SubscriptionPhase::Cancelling,
                $ends,
                $cancelled,
                $graceEnds,
                $effectiveGrace,
            );
        }

        /*
            İPTAL EDİLMİŞ ABONELİĞE ÖDEMESİZ SÜRE VERİLMEZ.

            Ödemesiz süre, GELMESİ BEKLENEN bir ödemeyi beklemek içindir.
            Sahip "çıkıyorum" dediyse beklenen bir ödeme yoktur; ona yedi gün
            daha yetenek vermek, iptali kabul etmemek olurdu.
        */
        if ($cancelled !== null) {
            return new self(SubscriptionPhase::Ended, $ends, $cancelled, $graceEnds, $effectiveGrace);
        }

        return new self(
            $current <= $graceEnds ? SubscriptionPhase::Grace : SubscriptionPhase::Suspended,
            $ends,
            null,
            $graceEnds,
            $effectiveGrace,
        );
    }

    /**
     * Bugün hangi plan geçerli: ödenen mi, zamanlanmış düşürme mi?
     *
     * Ödenmiş dönem sürerken HER ZAMAN ödenen plan. Dönem bittikten sonra
     * zamanlanmış plan devreye girer — yani düşürmenin farkı, sahibin ödediği
     * dönemin bir günü bile eksilmeden, tam dönem sonunda görünür.
     */
    public function effectivePlanId(int $paidPlanId, ?int $scheduledPlanId): int
    {
        if ($scheduledPlanId === null) {
            return $paidPlanId;
        }

        return $this->phase === SubscriptionPhase::Active || $this->phase === SubscriptionPhase::Cancelling
            ? $paidPlanId
            : $scheduledPlanId;
    }

    public function grantsEntitlements(): bool
    {
        return $this->phase->grantsEntitlements();
    }

    private static function boundedGraceDays(int $graceDays, int $periodDays): int
    {
        if ($graceDays <= 0) {
            return 0;
        }

        $ceiling = $periodDays > 0 ? $periodDays : 0;

        return min($graceDays, $ceiling);
    }

    private static function immutable(DateTimeInterface $value): DateTimeImmutable
    {
        return $value instanceof DateTimeImmutable
            ? $value
            : DateTimeImmutable::createFromInterface($value);
    }
}
