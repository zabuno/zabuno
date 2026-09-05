<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\ValueObject;

use InvalidArgumentException;

/**
 * Bir şubenin HAFTASI: ya yedi günün tamamı, ya hiçbiri.
 *
 * ARADA BİR HÂL YOK, ÇÜNKÜ ARADAKİ HÂL BELİRSİZLİKTİR.
 * "Pazartesi–cuma girilmiş, cumartesi hiç söylenmemiş" ile "cumartesi
 * kapalı" ekranda ayırt edilemez. Bir haftanın yedi günü vardır; eksik gün
 * bir veri değil, cevaplanmamış bir sorudur. Bu yüzden yazma yolu haftayı
 * bütün ister ve BOŞ hafta ("hiç söylemiyorum") ayrı, meşru bir hâldir —
 * o zaman kart saat satırını hiç çizmez.
 */
final class WeeklyOpeningHours
{
    /**
     * @param  list<OpeningHoursDay>  $days
     */
    private function __construct(
        private readonly array $days,
    ) {}

    /** Saat GİRİLMEMİŞ şube. Uydurma bir varsayılan değil, sessizlik. */
    public static function none(): self
    {
        return new self([]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function fromArray(array $rows): self
    {
        if ($rows === []) {
            return self::none();
        }

        $byDay = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new InvalidArgumentException('Each opening hours entry must be an object.');
            }

            $entry = OpeningHoursDay::fromArray($row);

            // Aynı gün iki kez yazılamaz: ekranda hangisinin doğru olduğu
            // belirsiz kalırdı ve tabloda da tekil kısıt zaten reddederdi.
            if (isset($byDay[$entry->day])) {
                throw new InvalidArgumentException("Day {$entry->day} appears more than once.");
            }

            $byDay[$entry->day] = $entry;
        }

        for ($day = 1; $day <= 7; $day++) {
            if (! isset($byDay[$day])) {
                throw new InvalidArgumentException(
                    "A week needs all seven days; day {$day} is missing."
                );
            }
        }

        ksort($byDay);

        return new self(array_values($byDay));
    }

    public function isEmpty(): bool
    {
        return $this->days === [];
    }

    /**
     * @return list<OpeningHoursDay>
     */
    public function days(): array
    {
        return $this->days;
    }

    /**
     * @return list<array{day: int, closed: bool, opens_minute: int|null, closes_minute: int|null}>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (OpeningHoursDay $day): array => $day->toArray(),
            $this->days,
        );
    }
}
