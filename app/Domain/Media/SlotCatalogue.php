<?php

declare(strict_types=1);

namespace App\Domain\Media;

use InvalidArgumentException;

/**
 * Slot politikalarının tamamı; yüzeye göre filtrelenebilir.
 */
final readonly class SlotCatalogue
{
    /** @param array<string, SlotPolicy> $policies */
    private function __construct(private array $policies) {}

    /** @param array<string, array<string, mixed>> $rows */
    public static function fromArray(array $rows): self
    {
        $policies = [];

        foreach ($rows as $key => $row) {
            $policies[(string) $key] = SlotPolicy::fromArray((string) $key, $row);
        }

        return new self($policies);
    }

    public function get(string $slot): SlotPolicy
    {
        return $this->policies[$slot]
            ?? throw new InvalidArgumentException("Tanımsız slot: {$slot}");
    }

    public function has(string $slot): bool
    {
        return isset($this->policies[$slot]);
    }

    /**
     * Yalnız bir yüzeyin slotları.
     *
     * Restoran paneli `Menu` ister; Zabuno'nun tanıtım sitesinin slotları
     * oraya hiç gitmez (`docs/50` "3 Neden" kapısı).
     *
     * @return array<string, SlotPolicy>
     */
    public function forSurface(MediaSurface $surface): array
    {
        return array_filter(
            $this->policies,
            static fn (SlotPolicy $policy): bool => $policy->surface === $surface,
        );
    }

    /** @return array<string, SlotPolicy> */
    public function all(): array
    {
        return $this->policies;
    }
}
