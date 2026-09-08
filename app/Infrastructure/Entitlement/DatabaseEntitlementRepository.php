<?php

declare(strict_types=1);

namespace App\Infrastructure\Entitlement;

use App\Application\Entitlement\Port\EntitlementRepositoryPort;
use App\Domain\Billing\SubscriptionLifecycle;
use App\Domain\Entitlement\EntitlementSet;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Abonelik → (geçerli) plan → entitlements zincirini okur.
 *
 * `plans.entitlements` serbest string listesi tutar; tanınmayan anahtarlar
 * `EntitlementSet` kurulumunda düşürülür.
 *
 * ═══ TARİH, DURUM SÜTUNUNDAN GÜVENİLİRDİR ═══
 *
 * Yetki kararı `subscriptions.state` alanına değil TARİHE bakar; bu karar bu
 * dosyada zaten vardı ve `SubscriptionLifecycle` onu bir yerde toplar. Bu
 * okumanın iki yeni sorumluluğu var (`docs/107` Faz 1.3, `docs/134`):
 *
 * 1. **Ödemesiz süre.** Dönem bittiği saniye yetenekler kapanmaz; ödeme
 *    gelmeden geçen `billing.subscription.grace_days` gün boyunca açık kalır.
 *    Süre dolduğunda kapanır ve başarılı bir ödeme onları elle müdahale
 *    olmadan geri açar.
 *
 * 2. **Zamanlanmış düşürme.** Ödenmiş dönem sürerken HER ZAMAN ödenen planın
 *    hakları verilir — düşürme kararı o dönemden tek bir yetenek eksiltmez.
 *    Dönem bittikten sonra geçerli plan, inilen plandır.
 *
 * MİSAFİRE ETKİSİ YOK. Bu okuma yalnız CANLI planı söyler; misafirin gördüğü
 * yayının hakları yayına DONDURULMUŞTUR (`menu_publications.entitlements`).
 * Bir restoranın ödeme sorunu, masadaki basılı karekodun gösterdiği menüyü
 * değiştirmez.
 */
final class DatabaseEntitlementRepository implements EntitlementRepositoryPort
{
    /** Yetenek veren abonelik durumları. Diğer her durum boş küme demektir. */
    private const GRANTING_STATES = ['active', 'trialing'];

    public function __construct(private readonly ConfigRepository $config) {}

    public function forWorkspace(int $workspaceId): EntitlementSet
    {
        $row = DB::table('subscriptions')
            ->where('workspace_id', $workspaceId)
            ->whereIn('state', self::GRANTING_STATES)
            ->select(['plan_id', 'scheduled_plan_id', 'ends_at', 'cancelled_at'])
            ->first();

        if ($row === null || $row->ends_at === null) {
            return EntitlementSet::empty();
        }

        $lifecycle = SubscriptionLifecycle::of(
            Carbon::parse($row->ends_at),
            $row->cancelled_at === null ? null : Carbon::parse($row->cancelled_at),
            max((int) $this->config->get('billing.subscription.grace_days', 0), 0),
            $this->periodDays(),
            Carbon::now(),
        );

        if (! $lifecycle->grantsEntitlements()) {
            return EntitlementSet::empty();
        }

        $planId = $lifecycle->effectivePlanId(
            (int) $row->plan_id,
            $row->scheduled_plan_id === null ? null : (int) $row->scheduled_plan_id,
        );

        $entitlements = DB::table('plans')
            ->where('id', $planId)
            ->where('is_active', true)
            ->value('entitlements');

        if ($entitlements === null) {
            return EntitlementSet::empty();
        }

        $decoded = json_decode((string) $entitlements, true);

        return is_array($decoded)
            ? EntitlementSet::fromKeys(array_values(array_filter($decoded, 'is_string')))
            : EntitlementSet::empty();
    }

    private function periodDays(): int
    {
        $days = (int) $this->config->get('billing.subscription.period_days', 30);

        return $days > 0 ? $days : 30;
    }
}
