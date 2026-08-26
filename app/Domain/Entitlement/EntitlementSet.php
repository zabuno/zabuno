<?php

declare(strict_types=1);

namespace App\Domain\Entitlement;

/**
 * Bir workspace'in sahip olduğu yetenek kümesi.
 *
 * Tanınmayan anahtarlar kurulum sırasında DÜŞÜRÜLÜR: bir plan
 * `['qr.bulk-generation', 'uydurma.anahtar']` taşıyorsa ikincisi hiçbir şey
 * açmaz. Bilinmeyeni yok saymak, bilinmeyene güvenmekten güvenlidir.
 */
final class EntitlementSet
{
    /** @var array<string, true> */
    private array $granted;

    /** @param list<Entitlement> $entitlements */
    private function __construct(array $entitlements)
    {
        $this->granted = [];

        foreach ($entitlements as $entitlement) {
            $this->granted[$entitlement->value] = true;
        }
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /** @param list<string> $keys */
    public static function fromKeys(array $keys): self
    {
        $known = [];

        foreach ($keys as $key) {
            $entitlement = Entitlement::tryFromKey($key);

            if ($entitlement !== null) {
                $known[] = $entitlement;
            }
        }

        return new self($known);
    }

    public function grants(Entitlement $entitlement): bool
    {
        return isset($this->granted[$entitlement->value]);
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->granted);
    }
}
