<?php

declare(strict_types=1);

namespace App\Application\Billing\Dto;

use App\Domain\Billing\SubscriptionPhase;

/**
 * Bir çalışma alanının aboneliği — okuma tarafının tek cevabı.
 *
 * ═══ `state` İLE `phase` AYNI ŞEY DEĞİLDİR ═══
 *
 * `state` eski ve dar soruya cevap verir: "bu çalışma alanının bir abonelik
 * satırı var mı?" (`none` / `active`). Medya kotası ve süperadmin ekranı bu
 * soruyu sorar ve cevabı değişmedi.
 *
 * `phase` yeni ve GERÇEK soruya cevap verir: "bugün ne oluyor?" — sürüyor mu,
 * iptal edildi de dönem sonunu mu bekliyor, ödemesiz sürede mi, askıda mı,
 * bitti mi (`SubscriptionPhase`). İkisini tek alana sıkıştırmak, ödemesiz
 * sürede olan bir aboneliği ya "aktif" (yalan) ya "yok" (yalan) göstermek
 * demekti.
 */
final class SubscriptionSummary
{
    /**
     * @param  string  $state  `none` | `active` — abonelik SATIRI var mı
     */
    private function __construct(
        public readonly string $state,
        public readonly SubscriptionPhase $phase,
        public readonly ?int $planId,
        public readonly ?string $planCode,
        public readonly ?string $planName,
        public readonly ?int $planVersion,
        public readonly ?string $endsAt,
        public readonly ?string $cancelledAt = null,
        public readonly ?string $graceEndsAt = null,
        public readonly ?int $scheduledPlanId = null,
        public readonly ?string $scheduledPlanCode = null,
        public readonly ?string $scheduledPlanName = null,
    ) {}

    public static function none(): self
    {
        return new self('none', SubscriptionPhase::None, null, null, null, null, null);
    }

    /**
     * @param  int  $planId  BUGÜN GEÇERLİ plan — zamanlanmış düşürme dönem
     *                       sonunda devreye girdiyse ödenen plan değil odur
     */
    public static function of(
        SubscriptionPhase $phase,
        int $planId,
        string $planCode,
        string $planName,
        int $planVersion,
        string $endsAtIso,
        ?string $cancelledAtIso = null,
        ?string $graceEndsAtIso = null,
        ?int $scheduledPlanId = null,
        ?string $scheduledPlanCode = null,
        ?string $scheduledPlanName = null,
    ): self {
        return new self(
            'active',
            $phase,
            $planId,
            $planCode,
            $planName,
            $planVersion,
            $endsAtIso,
            $cancelledAtIso,
            $graceEndsAtIso,
            $scheduledPlanId,
            $scheduledPlanCode,
            $scheduledPlanName,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        /*
            ABONELİĞİ OLMAYAN ÇALIŞMA ALANININ CEVABI TAM OLARAK BİR ALANDIR
            ve öyle kalır (`ManualPaymentJourneyTest` MANUAL-PAY-SUB-NONE-01).
            `phase` eklemek, "yok" cevabına ikinci bir "yok" yazmaktı; sözleşme
            testi haklı olarak kırılırdı ve kapıyı gevşetmek yerine alan
            eklenmedi.
        */
        if ($this->state === 'none') {
            return ['state' => 'none'];
        }

        return [
            'state' => $this->state,
            'phase' => $this->phase->value,
            'plan_id' => $this->planId,
            'plan_code' => $this->planCode,
            'plan_name' => $this->planName,
            'plan_version' => $this->planVersion,
            'ends_at' => $this->endsAt,
            'cancelled_at' => $this->cancelledAt,
            'grace_ends_at' => $this->graceEndsAt,
            'scheduled_plan_id' => $this->scheduledPlanId,
            'scheduled_plan_code' => $this->scheduledPlanCode,
            'scheduled_plan_name' => $this->scheduledPlanName,
        ];
    }
}
