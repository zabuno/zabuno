<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai;

use App\Application\Ai\Port\AiAvailability;
use App\Application\Ai\Port\AiAvailabilityPort;
use App\Domain\Ai\Capability;

/**
 * Kullanılabilirlik kararı — sırası ÖNEMLİ.
 *
 * Kapatma anahtarı önce bakılır: bütçe hesabı yapmak, kapalı bir sistemde
 * gereksiz sorgu demektir. Sonra rota, sonra bütçe — çünkü aday modeli
 * olmayan bir yetenek için bütçe harcamak da anlamsızdır.
 */
final readonly class ConfiguredAvailability implements AiAvailabilityPort
{
    public function __construct(private AiBudgetLedger $budget) {}

    public function isAvailable(int $workspaceId, Capability $capability): AiAvailability
    {
        if (config('ai.enabled') !== true) {
            return AiAvailability::KillSwitch;
        }

        $candidates = (array) config("ai.capabilities.{$capability->value}.candidates", []);

        if ($candidates === []) {
            return AiAvailability::NoRoute;
        }

        if (! $this->budget->hasRemaining($workspaceId)) {
            return AiAvailability::BudgetExhausted;
        }

        return AiAvailability::Available;
    }
}
