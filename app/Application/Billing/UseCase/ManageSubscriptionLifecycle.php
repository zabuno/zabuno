<?php

declare(strict_types=1);

namespace App\Application\Billing\UseCase;

use App\Application\Billing\Dto\PlanChangePreview;
use App\Application\Billing\Dto\PlanSummary;
use App\Application\Billing\Dto\SubscriptionSummary;
use App\Application\Billing\Exception\SubscriptionActionNotAllowedException;
use App\Application\Billing\Exception\WorkspaceNotFoundException;
use App\Application\Billing\Port\PlanCatalogRepositoryPort;
use App\Application\Billing\Port\SubscriptionRepositoryPort;
use App\Application\Platform\Port\PlatformAuditPort;
use App\Domain\Entitlement\Entitlement;

/**
 * ABONELİĞİN EKSİK YARISI — çıkış, düşürme ve geri dönüş (`docs/107` Faz 1.3,
 * `docs/134`).
 *
 * ═══ NEDEN DEFTERE YAZMIYOR ═══
 *
 * Defter (`ledger_entries`) çift kayıtlı bir PARA defteridir ve her satırı bir
 * tutarı bir hesaptan diğerine taşır. İptal, iptalden cayma, zamanlanmış
 * düşürme ve askıya alma — dördü de para HAREKET ETTİRMEZ: tahsilat yoktur,
 * iade yoktur, borç doğmaz. Bunlara defter satırı yazmak, sıfır tutarlı ya da
 * uydurma tutarlı kayıtlarla defteri kirletmek olurdu; defterin okunabilirliği
 * tam olarak "burada yazan her satır gerçekten para" olmasından gelir.
 *
 * Aynı sebeple FATURA da kesilmez: fatura bir tahsilatın karşılığındaki
 * belgedir (`ManageInvoices::ensureForPayment`). Para hareketi gerektiren tek
 * durum — iade — zaten FF-197'nin yolundan geçer ve orada hem ters defter
 * kaydını hem karşı belgeyi (credit note) doğurur. İkinci bir mekanizma
 * icat edilmedi.
 *
 * Bu dört kararın kalıcı izi `platform_audits`tir: kim, ne zaman, hangi
 * dönemde, hangi plandan hangi plana. Denetim kaydı da defter gibi
 * append-only'dur.
 */
final class ManageSubscriptionLifecycle
{
    public function __construct(
        private readonly SubscriptionRepositoryPort $subscriptions,
        private readonly PlanCatalogRepositoryPort $plans,
        private readonly PlatformAuditPort $audit,
    ) {}

    /**
     * İPTAL — yenileme durur, ödenmiş dönem sürer.
     *
     * @throws WorkspaceNotFoundException
     * @throws SubscriptionActionNotAllowedException
     */
    public function cancel(int $workspaceId, int $actorUserId): SubscriptionSummary
    {
        $before = $this->subscriptions->currentSubscription($workspaceId);
        $after = $this->subscriptions->cancel($workspaceId, $actorUserId);

        $this->audit->record(
            'billing.subscription',
            'cancelled',
            'workspace:'.$workspaceId,
            [
                'plan_code' => $before->planCode,
                'plan_name' => $before->planName,
                'phase_before' => $before->phase->value,
                'phase_after' => $after->phase->value,
                // Hangi DÖNEMDE iptal edildiği: hizmetin ne zamana kadar
                // süreceği bu tarihtir ve ekranda sahibe de bu yazılır.
                'service_until' => $after->endsAt,
                'cancelled_at' => $after->cancelledAt,
            ],
            $actorUserId,
        );

        return $after;
    }

    /**
     * İPTALDEN CAYMA — dönem bitmeden fikir değiştiren sahip yeniden ödeme
     * yapmak zorunda kalmaz.
     *
     * @throws WorkspaceNotFoundException
     * @throws SubscriptionActionNotAllowedException
     */
    public function resume(int $workspaceId, int $actorUserId): SubscriptionSummary
    {
        $before = $this->subscriptions->currentSubscription($workspaceId);
        $after = $this->subscriptions->resumeCancelled($workspaceId);

        $this->audit->record(
            'billing.subscription',
            'cancellation_withdrawn',
            'workspace:'.$workspaceId,
            [
                'plan_code' => $after->planCode,
                'cancelled_at' => $before->cancelledAt,
                'service_until' => $after->endsAt,
            ],
            $actorUserId,
        );

        return $after;
    }

    /**
     * PLAN DÜŞÜRME — dönem SONUNDA yürürlüğe girer, fark iade edilmez.
     *
     * @throws WorkspaceNotFoundException
     * @throws SubscriptionActionNotAllowedException
     */
    public function scheduleDowngrade(int $workspaceId, int $targetPlanId, int $actorUserId): SubscriptionSummary
    {
        $before = $this->subscriptions->currentSubscription($workspaceId);
        $after = $this->subscriptions->schedulePlanChange($workspaceId, $targetPlanId, $actorUserId);

        $this->audit->record(
            'billing.subscription',
            'downgrade_scheduled',
            'workspace:'.$workspaceId,
            [
                'from_plan_code' => $before->planCode,
                'to_plan_code' => $after->scheduledPlanCode,
                'effective_at' => $after->endsAt,
                // Sahibin kaybedeceği yetenekler denetim kaydına da girer:
                // "haberim yoktu" iddiası bir tarihle karşılanabilmeli.
                'losing' => array_column($this->difference($before->planId, $targetPlanId), 'key'),
            ],
            $actorUserId,
        );

        return $after;
    }

    /**
     * @throws WorkspaceNotFoundException
     * @throws SubscriptionActionNotAllowedException
     */
    public function cancelScheduledDowngrade(int $workspaceId, int $actorUserId): SubscriptionSummary
    {
        $before = $this->subscriptions->currentSubscription($workspaceId);
        $after = $this->subscriptions->cancelScheduledPlanChange($workspaceId);

        $this->audit->record(
            'billing.subscription',
            'downgrade_withdrawn',
            'workspace:'.$workspaceId,
            [
                'plan_code' => $after->planCode,
                'withdrawn_plan_code' => $before->scheduledPlanCode,
            ],
            $actorUserId,
        );

        return $after;
    }

    /**
     * "Bu plana geçersem ne olur?" — karar vermeden ÖNCE, sunucudan.
     *
     * @throws WorkspaceNotFoundException
     * @throws SubscriptionActionNotAllowedException
     */
    public function previewPlanChange(int $workspaceId, int $targetPlanId): PlanChangePreview
    {
        $subscription = $this->subscriptions->currentSubscription($workspaceId);

        if ($subscription->planId === null || $subscription->endsAt === null) {
            throw SubscriptionActionNotAllowedException::noSubscription();
        }

        $current = $this->plans->findPlan($subscription->planId);
        $target = $this->plans->findPlan($targetPlanId);

        if ($current === null || $target === null) {
            throw SubscriptionActionNotAllowedException::planUnavailable();
        }

        return new PlanChangePreview(
            $current->id,
            $current->name,
            $target->id,
            $target->name,
            $this->direction($current, $target),
            $current->amountMinor,
            $target->amountMinor,
            $target->currency ?? $current->currency,
            /*
                YÜRÜRLÜK TARİHİ HER İKİ YÖNDE DE DÜRÜSTTÜR: düşürme ödenmiş
                dönemin sonunda başlar, yükseltme ödeme yapıldığı an. İkisine
                de aynı tarihi yazmak, sahibin bugün ödeyip yarın kullanacağını
                sanmasına yol açardı.
            */
            $subscription->endsAt,
            $this->difference($current->id, $target->id),
            $this->difference($target->id, $current->id),
            $subscription->scheduledPlanId === $target->id,
        );
    }

    /** @return 'upgrade'|'downgrade'|'same'|'unpriced' */
    private function direction(PlanSummary $current, PlanSummary $target): string
    {
        if ($current->id === $target->id) {
            return 'same';
        }

        if ($current->amountMinor === null || $target->amountMinor === null) {
            // Fiyatsız bir plan satın alınamaz; yön bir para karşılaştırması
            // olduğu için burada UYDURULMAZ, "bilinmiyor" denir.
            return 'unpriced';
        }

        return $target->amountMinor < $current->amountMinor ? 'downgrade' : 'upgrade';
    }

    /**
     * `$fromPlanId`'de olup `$toPlanId`'de olmayan TANINAN yetenekler.
     *
     * Etiket `Entitlement` enum'undan gelir: tanınmayan bir anahtar hiçbir şey
     * açmadığı için kaybı da yoktur, ve listeye girseydi sahibe hiç sahip
     * olmadığı bir yeteneği kaybediyormuş gibi gösterirdi.
     *
     * @return list<array{key: string, label: string}>
     */
    private function difference(?int $fromPlanId, ?int $toPlanId): array
    {
        if ($fromPlanId === null || $toPlanId === null) {
            return [];
        }

        $from = $this->plans->findPlan($fromPlanId);
        $to = $this->plans->findPlan($toPlanId);

        if ($from === null || $to === null) {
            return [];
        }

        $lost = [];

        foreach (array_diff($from->entitlements, $to->entitlements) as $key) {
            $entitlement = Entitlement::tryFromKey($key);

            if ($entitlement !== null) {
                $lost[] = ['key' => $entitlement->value, 'label' => $entitlement->label()];
            }
        }

        return $lost;
    }
}
