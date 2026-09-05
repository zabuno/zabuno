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
     * O ANDA KAPALI OLDUĞUMUZU KANITLAYABİLİYOR MUYUZ?
     *
     * Soru bilerek bu yönde sorulur. "Açık mı" diye sorulsaydı, saatini hiç
     * girmemiş bir şube için verilecek cevap ya yanlış ya keyfi olurdu; oysa
     * sessizlik bir olgu değildir. Bu yüzden BOŞ hafta `false` döner: kapalı
     * olduğunu söyleyemeyiz, dolayısıyla söylemeyiz — misafirin ekranında da
     * hiçbir şerit çizilmez.
     *
     * GECE YARISI AŞIMI İKİ YÖNDEN OKUNUR. "18:00–02:00" kaydı 1080 → 1560
     * olarak durur; sabahın 01:00'i için doğru cevap DÜNÜN aralığındadır.
     * Yalnız bugünün satırına bakan bir kontrol, gece menüsü servis ederken
     * misafire "kapalıyız" derdi — hem de tam dolu olduğumuz saatte.
     *
     * @param  int  $isoWeekday  ŞUBENİN kendi günü (1 = Pazartesi … 7 = Pazar).
     * @param  int  $minuteOfDay  ŞUBENİN kendi saatinde, gün başından dakika.
     */
    public function isClosedAt(int $isoWeekday, int $minuteOfDay): bool
    {
        if ($this->days === []) {
            return false;
        }

        $today = $this->forDay($isoWeekday);

        if ($today !== null
            && ! $today->closed
            && $today->opensMinute !== null
            && $today->closesMinute !== null
            && $minuteOfDay >= $today->opensMinute
            && $minuteOfDay < $today->closesMinute
        ) {
            return false;
        }

        $yesterday = $this->forDay($isoWeekday === 1 ? 7 : $isoWeekday - 1);

        if ($yesterday !== null
            && ! $yesterday->closed
            && $yesterday->closesMinute !== null
            && $yesterday->closesMinute > OpeningHoursDay::MINUTES_PER_DAY
            && $minuteOfDay < $yesterday->closesMinute - OpeningHoursDay::MINUTES_PER_DAY
        ) {
            return false;
        }

        return true;
    }

    /**
     * BUNDAN SONRAKİ İLK AÇILIŞ — veriden çıkıyorsa.
     *
     * Yürüyüş bugünden başlar ve çemberi bir tur döner. Bugünün açılışı
     * GEÇMİŞSE atlanır: "bugün 09:00'da açılıyoruz" cümlesi saat 23:30'da
     * yalandır ve misafiri sabahı beklemek yerine kapıda bekletirdi.
     *
     * Yedi günün tamamı kapalıysa `null` döner ve ekranda saat satırı HİÇ
     * çizilmez. Tahmini bir gün ya da uydurma bir saat yazmak, tutulmayacak
     * bir söz vermek olurdu — aynı ilke servis dışı sayfasında da geçerli
     * (`GuestMenuView::$nextServiceClock`).
     *
     * @return array{day: OpeningHoursDay, dayOffset: int}|null `dayOffset` 0
     *                                                          ise açılış BUGÜNDÜR; cümlenin gün adı taşıyıp
     *                                                          taşımayacağına çağıran buna bakarak karar verir.
     */
    public function nextOpeningAfter(int $isoWeekday, int $minuteOfDay): ?array
    {
        if ($this->days === []) {
            return null;
        }

        for ($offset = 0; $offset < 7; $offset++) {
            $entry = $this->forDay((($isoWeekday - 1 + $offset) % 7) + 1);

            if ($entry === null || $entry->closed || $entry->opensMinute === null) {
                continue;
            }

            if ($offset === 0 && $entry->opensMinute <= $minuteOfDay) {
                continue;
            }

            return ['day' => $entry, 'dayOffset' => $offset];
        }

        return null;
    }

    private function forDay(int $isoWeekday): ?OpeningHoursDay
    {
        foreach ($this->days as $entry) {
            if ($entry->day === $isoWeekday) {
                return $entry;
            }
        }

        return null;
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
