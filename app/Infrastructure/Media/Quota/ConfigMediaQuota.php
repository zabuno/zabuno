<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Quota;

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
 */
final class ConfigMediaQuota implements MediaQuotaPort
{
    public function __construct(private readonly SubscriptionRepositoryPort $subscriptions) {}

    public function statusFor(int $workspaceId): MediaQuotaStatus
    {
        [$code, $plan] = $this->planFor($workspaceId);

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
            planLabel: (string) ($plan['label'] ?? $code),
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

    /** @return array{0:string,1:array<string,mixed>} */
    private function planFor(int $workspaceId): array
    {
        $plans = (array) config('media-quota.plans', []);
        $default = (string) config('media-quota.default', 'starter');
        $code = $default;

        try {
            $planCode = $this->subscriptions->currentSubscription($workspaceId)->planCode;

            if ($planCode !== null && isset($plans[$planCode])) {
                $code = $planCode;
            }
        } catch (Throwable) {
            // Abonelik okunamıyorsa en dar plan varsayılır: kota kapısı
            // asla "bilinmiyor, o hâlde sınırsız" demez.
        }

        return [$code, (array) ($plans[$code] ?? $plans[$default])];
    }
}
