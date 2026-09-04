<?php

declare(strict_types=1);

namespace App\Domain\Media;

use InvalidArgumentException;

/**
 * Adlandırılmış türev kurallarının tamamı — ürünün ölçü dağarcığı.
 *
 * Sıra KORUNUR: kaynak kuralları küçükten büyüğe diziyor (thumb → print) ve
 * ekran o sırayı gösteriyor. Alfabetik sıralamak `large`ı `medium`dan önce
 * koyar ve listeyi okunamaz hâle getirirdi.
 */
final readonly class DerivativeCatalogue
{
    /** @param array<string, DerivativeRule> $rules */
    private function __construct(private array $rules) {}

    /** @param array<string, array<string, mixed>> $rows */
    public static function fromArray(array $rows): self
    {
        $rules = [];

        foreach ($rows as $name => $row) {
            $rules[(string) $name] = DerivativeRule::fromArray((string) $name, (array) $row);
        }

        return new self($rules);
    }

    public function get(string $name): DerivativeRule
    {
        return $this->rules[$name]
            ?? throw new InvalidArgumentException("Tanımsız türev kuralı: {$name}");
    }

    public function has(string $name): bool
    {
        return isset($this->rules[$name]);
    }

    /** @return array<string, DerivativeRule> */
    public function all(): array
    {
        return $this->rules;
    }

    /**
     * Her kural için, o ölçüyü BUGÜN gerçekten üreten slotların adları.
     *
     * Kaynak altı ölçü adlandırıyor; boru hattı bugün bunların yalnız bir
     * kısmını üretiyor. Farkı hesaplamak domain'in işidir — hangi slotun
     * hangi genişlikleri istediği zaten `SlotCatalogue`da yazılı.
     *
     * @return array<string, list<string>>
     */
    public function producedBySlots(SlotCatalogue $slots): array
    {
        $map = [];

        foreach ($this->rules as $name => $rule) {
            $producers = [];

            foreach ($slots->all() as $slotKey => $policy) {
                if ($rule->isProducedBy($policy->renditions)) {
                    $producers[] = (string) $slotKey;
                }
            }

            $map[$name] = $producers;
        }

        return $map;
    }
}
