<?php

declare(strict_types=1);

namespace App\Infrastructure\Support\Reference;

use App\Application\Support\Port\SupportReferenceGeneratorPort;
use App\Domain\Support\SupportReference;

/**
 * Kriptografik rastgelelikle referans üretir.
 *
 * `random_int` bilerek: `mt_rand`/`rand` tahmin edilebilir ve tahmin
 * edilebilir bir referans, bir gün referansla sorgu açılırsa başkasının
 * talebini bulmanın yolu olurdu. Bugün öyle bir sorgu yok (`docs/125`
 * §5) — ama numaranın kendisi yine de tahmin edilemez kalmalı.
 */
final class RandomSupportReferenceGenerator implements SupportReferenceGeneratorPort
{
    public function generate(): string
    {
        $alphabet = SupportReference::ALPHABET;
        $max = strlen($alphabet) - 1;
        $body = '';

        for ($i = 0; $i < SupportReference::LENGTH; $i++) {
            $body .= $alphabet[random_int(0, $max)];
        }

        return SupportReference::PREFIX.$body;
    }
}
