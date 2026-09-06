<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Persistence;

use App\Application\Billing\Dto\BillingModeStatus;
use App\Application\Billing\Exception\BillingModeRejectedException;
use App\Application\Billing\Port\BillingModePort;
use App\Application\Platform\Port\PlatformAuditPort;
use App\Application\Platform\Port\PlatformCredentialAdminPort;
use App\Domain\Billing\BillingMode;
use App\Domain\Platform\Credential\CredentialProvider;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Kip anahtarı — üç kapı (docs/123).
 *
 *   etkin = live  ⇔  dağıtım `live` VE süperadmin `live` istedi VE kasa dolu
 *
 * Kasa "dolu" mu sorusu ADMİN portuna sorulur (`status()->configured`),
 * resolver'a değil: resolver kasa boşken `.env`'e düşer ve sandbox
 * anahtarını "var" gösterirdi. Admin yüzeyi yalnız kasanın kendisine bakar.
 */
final class EloquentBillingMode implements BillingModePort
{
    private const SETTING_KEY = 'billing.mode';

    public function __construct(
        private readonly PlatformCredentialAdminPort $vault,
        private readonly PlatformAuditPort $audit,
        private readonly ConfigRepository $config,
    ) {}

    public function effective(): BillingMode
    {
        return $this->status()->effective;
    }

    public function status(): BillingModeStatus
    {
        $row = DB::table('platform_settings')->where('key', self::SETTING_KEY)->first();
        $requested = $row === null
            ? BillingMode::Sandbox
            : (BillingMode::tryFrom((string) json_decode((string) $row->value, true)) ?? BillingMode::Sandbox);

        $deployment = $this->deploymentMode();
        $vaultConfigured = $this->vault->status(CredentialProvider::Iyzico)->configured;
        $reasons = $requested === BillingMode::Live ? $this->closedGates($deployment, $vaultConfigured) : [];

        return new BillingModeStatus(
            requested: $requested,
            effective: $requested === BillingMode::Live && $reasons === [] ? BillingMode::Live : BillingMode::Sandbox,
            deploymentMode: $deployment,
            vaultConfigured: $vaultConfigured,
            reasons: $reasons,
            updatedAt: $row?->updated_at === null ? null : Carbon::parse($row->updated_at)->toIso8601String(),
            updatedByUserId: $row?->updated_by_user_id === null ? null : (int) $row->updated_by_user_id,
        );
    }

    public function request(BillingMode $mode, ?int $byUserId): BillingModeStatus
    {
        if ($mode === BillingMode::Live) {
            $reasons = $this->closedGates(
                $this->deploymentMode(),
                $this->vault->status(CredentialProvider::Iyzico)->configured,
            );

            if ($reasons !== []) {
                throw new BillingModeRejectedException($reasons[0], 'Live mode cannot be enabled: '.implode(', ', $reasons).'.');
            }
        }

        $previous = $this->status()->requested;

        if ($previous !== $mode) {
            DB::table('platform_settings')->updateOrInsert(
                ['key' => self::SETTING_KEY],
                [
                    'value' => json_encode($mode->value),
                    'updated_by_user_id' => $byUserId,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );

            $this->audit->record('billing.mode', $mode->value, null, [
                'previous' => $previous->value,
                'next' => $mode->value,
            ], $byUserId);
        }

        return $this->status();
    }

    private function deploymentMode(): BillingMode
    {
        $configured = $this->config->get('services.iyzico.mode');

        return is_string($configured) ? (BillingMode::tryFrom($configured) ?? BillingMode::Sandbox) : BillingMode::Sandbox;
    }

    /** @return list<string> */
    private function closedGates(BillingMode $deployment, bool $vaultConfigured): array
    {
        $reasons = [];

        if ($deployment !== BillingMode::Live) {
            $reasons[] = 'deployment_mode_sandbox';
        }

        if (! $vaultConfigured) {
            $reasons[] = 'vault_missing';
        }

        return $reasons;
    }
}
