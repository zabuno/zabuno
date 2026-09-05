<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\ValueObject;

use InvalidArgumentException;

/**
 * Haftanın BİR gününün çalışma saati.
 *
 * Gün ISO-8601'dir (1 = Pazartesi … 7 = Pazar) ve saatler günün
 * başlangıcından itibaren DAKİKADIR. Kapanış gece yarısını aşabilir:
 * "18:00–02:00" → 1080 → 1560. Bu bir istisna değil, ölçünün devamıdır —
 * aksi hâlde tek bir servis iki güne bölünmek zorunda kalırdı.
 */
final class OpeningHoursDay
{
    /** Bir günün dakika sayısı. Gece yarısı aşımı bunun ÜSTÜNE eklenir. */
    public const MINUTES_PER_DAY = 1440;

    private function __construct(
        public readonly int $day,
        public readonly bool $closed,
        public readonly ?int $opensMinute,
        public readonly ?int $closesMinute,
    ) {}

    public static function closed(int $day): self
    {
        self::assertDay($day);

        return new self($day, true, null, null);
    }

    public static function open(int $day, int $opensMinute, int $closesMinute): self
    {
        self::assertDay($day);

        // Açılış her zaman GÜNÜN İÇİNDEDİR. 1440 ("ertesi gün 00:00") bir
        // açılış saati değildir; o gün ertesi günün kendi satırıdır.
        if ($opensMinute < 0 || $opensMinute >= self::MINUTES_PER_DAY) {
            throw new InvalidArgumentException("Opening minute out of range: {$opensMinute}");
        }

        // Sıfır uzunluklu bir gün "açık" değildir; kapalılığın kendi ifadesi
        // var (`closed`). Eşitliğe izin vermek, ekranda "09:00–09:00" yazan
        // ve hiçbir şey anlatmayan bir aralık üretirdi.
        if ($closesMinute <= $opensMinute) {
            throw new InvalidArgumentException(
                "Closing minute must come after the opening minute: {$opensMinute} → {$closesMinute}"
            );
        }

        // Bir gün 24 saatten uzun süremez: sürebilseydi ertesi günün kendi
        // aralığını yutar ve haftanın toplamı 168 saati aşardı.
        if ($closesMinute - $opensMinute > self::MINUTES_PER_DAY) {
            throw new InvalidArgumentException(
                "A single day cannot stay open longer than 24 hours: {$opensMinute} → {$closesMinute}"
            );
        }

        return new self($day, false, $opensMinute, $closesMinute);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        if (! isset($data['day']) || ! is_numeric($data['day'])) {
            throw new InvalidArgumentException('Opening hours entry requires a day.');
        }

        $day = (int) $data['day'];
        $closed = filter_var($data['closed'] ?? false, FILTER_VALIDATE_BOOL);

        if ($closed) {
            return self::closed($day);
        }

        // Kapalı DEĞİLSE saat zorunludur: saati olmayan açık bir gün,
        // ekranda "açığız ama kaçta bilmiyoruz" demek olurdu.
        if (! is_numeric($data['opens_minute'] ?? null) || ! is_numeric($data['closes_minute'] ?? null)) {
            throw new InvalidArgumentException("An open day requires both minutes (day {$day}).");
        }

        return self::open($day, (int) $data['opens_minute'], (int) $data['closes_minute']);
    }

    /**
     * @return array{day: int, closed: bool, opens_minute: int|null, closes_minute: int|null}
     */
    public function toArray(): array
    {
        return [
            'day' => $this->day,
            'closed' => $this->closed,
            'opens_minute' => $this->opensMinute,
            'closes_minute' => $this->closesMinute,
        ];
    }

    private static function assertDay(int $day): void
    {
        if ($day < 1 || $day > 7) {
            throw new InvalidArgumentException("Day of week must be ISO-8601 (1-7), got: {$day}");
        }
    }
}
