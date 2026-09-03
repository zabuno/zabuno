<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy\Features;

use App\Application\Tenancy\Port\FeatureFlagPort;
use Laravel\Pennant\Feature;

/**
 * Pennant üzerinden bayraklar. Tanımlar `AppServiceProvider::boot`'ta;
 * kapsam anahtarı `workspace:{id}` — kullanıcı değil, kiracı.
 */
final class PennantFeatureFlags implements FeatureFlagPort
{
    public static function scope(int $workspaceId): string
    {
        return "workspace:{$workspaceId}";
    }

    public function flagsFor(int $workspaceId): array
    {
        $out = [];

        foreach (Feature::for(self::scope($workspaceId))->all() as $name => $value) {
            $out[(string) $name] = (bool) $value;
        }

        return $out;
    }
}
