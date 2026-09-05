<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Quota;

use App\Application\Billing\Port\PlanCatalogRepositoryPort;
use App\Application\Billing\Port\SubscriptionRepositoryPort;
use App\Application\Media\Dto\MediaQuotaStatus;
use App\Application\Media\Port\MediaQuotaPort;
use App\Domain\Media\LifecycleStatus;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Plan → kota, `config/media-quota.php`'den. Kullanım `media_assets`'tan
 * SAYILIR, ayrı sayaç tutulmaz: sayaç kayar, tablo kaymaz.
 *
 * Çöp dahildir (silme boş alan açmaz, purge açar); rendition'lar dahil
 * değildir (`docs/98` §7).
 *
 * RAKAMLAR YAPILANDIRMADAN, AD KATALOGDAN gelir. Ad da yapılandırmada
 * dursaydı — ve bir süre durdu — sahibin faturada okuduğu "Restaurant" ile
 * medya ekranında okuduğu ad ayrışırdı; ilk ad değişikliğinde yine
 * ayrışırdı. Bu yüzden burada ikinci bir isim listesi tutulmaz.
 */
final class ConfigMediaQuota implements MediaQuotaPort
{
    public function __construct(
        private readonly SubscriptionRepositoryPort $subscriptions,
        private readonly PlanCatalogRepositoryPort $catalogue,
    ) {}

    public function statusFor(int $workspaceId): MediaQuotaStatus
    {
        [$code, $plan, $subscribedName] = $this->planFor($workspaceId);

        /*
            Katalog SORGUSU BURADA, `planFor` içinde değil: `planFor`'a
            gömülseydi, çalışma alanlarını tek tek gezen çöp temizleme
            komutu (`trashRetentionDaysFor`) hiç kullanmadığı bir ad için
            her satırda bir sorgu daha atardı.
        */
        $label = $subscribedName ?? $this->catalogueName($code);

        $counted = DB::table('media_assets')
            ->where('workspace_id', $workspaceId)
            ->where(function ($query): void {
                $query->whereNull('lifecycle_status')
                    ->orWhere('lifecycle_status', '!=', LifecycleStatus::Purged->value);
            });

        $bytesUsed = (int) (clone $counted)->sum('size_bytes');
        $assetsUsed = (int) (clone $counted)->count();
        $monthlyUsed = (int) (clone $counted)->where('created_at', '>=', now()->startOfMonth())->count();

        $status = new MediaQuotaStatus(
            planCode: $code,
            planLabel: $label,
            originalBytesUsed: $bytesUsed,
            originalBytesLimit: (int) $plan['original_bytes'],
            assetsUsed: $assetsUsed,
            assetsLimit: (int) $plan['assets'],
            monthlyUploadsUsed: $monthlyUsed,
            monthlyUploadsLimit: $plan['monthly_uploads'] === null ? null : (int) $plan['monthly_uploads'],
            trashRetentionDays: (int) $plan['trash_retention_days'],
        );

        return new MediaQuotaStatus(
            $status->planCode, $status->planLabel, $status->originalBytesUsed, $status->originalBytesLimit,
            $status->assetsUsed, $status->assetsLimit, $status->monthlyUploadsUsed, $status->monthlyUploadsLimit,
            // "Dolu mu?" sorusu BİR SONRAKİ yüklemeye göre cevaplanır: bir bayt daha sığmıyorsa doludur.
            $status->trashRetentionDays, $this->reason($status, 1),
        );
    }

    public function admits(int $workspaceId, int $incomingBytes): ?string
    {
        return $this->reason($this->statusFor($workspaceId), max(0, $incomingBytes));
    }

    public function trashRetentionDaysFor(int $workspaceId): int
    {
        return (int) $this->planFor($workspaceId)[1]['trash_retention_days'];
    }

    /** Sebep SAHİBİN cümlesidir: ne doldu, ne yapılır (`docs/76`). */
    private function reason(MediaQuotaStatus $status, int $incomingBytes): ?string
    {
        if ($status->assetsUsed + ($incomingBytes > 0 ? 1 : 0) > $status->assetsLimit) {
            return "Görsel sayısı sınırına ulaşıldı ({$status->assetsLimit}). Çöpü boşaltın ya da planı yükseltin.";
        }

        if ($status->originalBytesUsed + $incomingBytes > $status->originalBytesLimit) {
            $limitMb = (int) round($status->originalBytesLimit / 1048576);

            return "Depolama alanı doldu ({$limitMb} MB). Kullanılmayan görselleri kalıcı silin ya da planı yükseltin.";
        }

        if ($status->monthlyUploadsLimit !== null
            && $status->monthlyUploadsUsed + ($incomingBytes > 0 ? 1 : 0) > $status->monthlyUploadsLimit) {
            return "Bu ayki yükleme sınırına ulaşıldı ({$status->monthlyUploadsLimit}). Gelecek ay devam eder ya da planı yükseltin.";
        }

        return null;
    }

    /**
     * Üçüncü değer ABONELİĞİN kendi satırındaki addır; aboneliği olmayan
     * çalışma alanı için `null`'dır ve adı çağıran katalogdan tamamlar.
     *
     * @return array{0:string,1:array<string,mixed>,2:?string}
     */
    private function planFor(int $workspaceId): array
    {
        $plans = (array) config('media-quota.plans', []);
        $default = (string) config('media-quota.default', 'starter');
        $code = $default;
        $label = null;

        try {
            $subscription = $this->subscriptions->currentSubscription($workspaceId);

            if ($subscription->planCode !== null && isset($plans[$subscription->planCode])) {
                $code = $subscription->planCode;

                /*
                    Ad ABONELİĞİN KENDİ SATIRINDAN okunur (`plans.name`
                    birleştirmesi): sahip parasını tam bu ada ödedi. Katalog
                    listesinden okunsaydı, yayından kaldırılmış bir plana
                    abone olan sahip kendi planının adını göremezdi.
                */
                $label = $subscription->planName;
            }
        } catch (Throwable) {
            // Abonelik okunamıyorsa en dar plan varsayılır: kota kapısı
            // asla "bilinmiyor, o hâlde sınırsız" demez.
        }

        return [$code, (array) ($plans[$code] ?? $plans[$default]), $label];
    }

    /**
     * Aboneliği olmayan çalışma alanının kademesinin adı.
     *
     * Ücretsiz kullanıcı da bir gün fiyat sayfasına bakar; orada "Starter"
     * yazıp panelinde başka bir ad yazsaydı hangi kademede olduğunu
     * bilemezdi. Bu yüzden ad burada da UYDURULMAZ, katalogdan okunur.
     */
    private function catalogueName(string $code): string
    {
        try {
            foreach ($this->catalogue->listActivePlans() as $plan) {
                if ($plan->code === $code) {
                    return $plan->name;
                }
            }
        } catch (Throwable) {
            // Katalog okunamıyorsa da bir ad uydurulmaz.
        }

        /*
            Katalogda karşılığı yoksa KOD yazılır ve bu çirkinlik kasıtlıdır:
            sahibe insanca ama YANLIŞ bir ad göstermektense, adı olmayan bir
            planın adsızlığı görünür kalsın. Katalog her dağıtımda tohumlanır
            (`PlanCatalogueSeeder`), dolayısıyla bu satır ancak bozuk bir
            kurulumda okunur — ve orada susmak, hatayı gizlemek olurdu.
        */
        return $code;
    }
}
