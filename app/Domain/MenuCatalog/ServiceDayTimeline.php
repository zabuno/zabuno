<?php

declare(strict_types=1);

namespace App\Domain\MenuCatalog;

use InvalidArgumentException;

/**
 * BİR ŞUBENİN GÜNÜ — hangi dakikada hangi menü.
 *
 * Gün, aralıklarla değil GEÇİŞ ANLARIYLA temsil edilir: her geçiş "şu
 * dakikadan itibaren şu menü" der. Bir andaki menü, o andan önceki en son
 * geçiştir; günün ilk dakikaları ise günün SON geçişine bağlanır, çünkü
 * gün bir doğru parçası değil bir ÇEMBERDİR.
 *
 * Bunun bedeli bir alışkanlığı bırakmaktır ("her menünün bir bitişi var"),
 * kazancı ise sahibin kuralının kanıtlanabilir olmasıdır: geçişler bir
 * çemberi böldüğü için ÇAKIŞMA ve BOŞLUK doğamaz. Bir geçiş eklemek bir
 * yayı ikiye böler, silmek iki yayı birleştirir. Hiçbir işlem çemberde
 * delik açamaz (`docs/109` §7.1).
 *
 * Gece yarısını aşan aralık BURADA ÖZEL DURUM DEĞİLDİR: "22:00–02:00"
 * yalnızca 1320. ve 120. dakikalarda iki geçiştir; çember gerisini yapar.
 *
 * Bu sınıf çerçeveden bağımsızdır (ADR-L02): saat dilimini ve "şimdi"yi
 * dışarıdan hazır bir dakika olarak alır, kendisi saate BAKMAZ.
 */
final class ServiceDayTimeline
{
    public const MINUTES_PER_DAY = 1440;

    /** @param  list<array{menuId:int,startMinute:int}>  $switches  dakikaya göre artan, tekrarsız */
    private function __construct(private readonly array $switches) {}

    /**
     * @param  list<array{menuId:int,startMinute:int}>  $switches
     */
    public static function fromSwitches(array $switches): self
    {
        $seen = [];
        $normalized = [];

        foreach ($switches as $entry) {
            $minute = $entry['startMinute'];

            if ($minute < 0 || $minute >= self::MINUTES_PER_DAY) {
                throw new InvalidArgumentException('Service switch minute must be within a single day.');
            }

            if (isset($seen[$minute])) {
                /*
                    Buraya düşmek bir VERİ BOZULMASIDIR, bir kullanıcı hatası
                    değil: `unique(location_id, start_minute)` bunu zaten
                    reddeder. Sessizce birini seçmek, iki menüden hangisinin
                    açıldığını kimsenin bilemeyeceği bir ürün doğururdu.
                */
                throw new InvalidArgumentException('Two menus cannot start at the same minute of the day.');
            }

            $seen[$minute] = true;
            $normalized[] = ['menuId' => $entry['menuId'], 'startMinute' => $minute];
        }

        usort($normalized, static fn (array $a, array $b): int => $a['startMinute'] <=> $b['startMinute']);

        return new self(array_values($normalized));
    }

    public function isEmpty(): bool
    {
        return $this->switches === [];
    }

    /**
     * O dakikada servis edilen menü. Hiç geçiş yoksa `null` döner ve çağıran
     * şubenin ÇIPA menüsüne düşer — gün yine cevapsız kalmaz.
     */
    public function menuIdAt(int $minuteOfDay): ?int
    {
        if ($this->switches === []) {
            return null;
        }

        $answer = null;

        foreach ($this->switches as $entry) {
            if ($entry['startMinute'] <= $minuteOfDay) {
                $answer = $entry['menuId'];

                continue;
            }

            break;
        }

        // Gün başında hiçbir geçişe ulaşılamadıysa an, ÖNCEKİ GÜNÜN son
        // geçişine aittir: 22:00'de başlayan gece menüsü 01:00'de hâlâ açıktır.
        return $answer ?? $this->switches[count($this->switches) - 1]['menuId'];
    }

    /**
     * Her geçişin kapladığı yay.
     *
     * Tek geçiş varsa `startMinute === endMinute` olur ve bu "TÜM GÜN"
     * demektir; sıfır uzunlukta bir aralık değil, çemberin tamamı.
     *
     * @return list<array{menuId:int,startMinute:int,endMinute:int}>
     */
    public function windows(): array
    {
        $count = count($this->switches);
        $windows = [];

        for ($index = 0; $index < $count; $index++) {
            $windows[] = [
                'menuId' => $this->switches[$index]['menuId'],
                'startMinute' => $this->switches[$index]['startMinute'],
                'endMinute' => $this->switches[($index + 1) % $count]['startMinute'],
            ];
        }

        return $windows;
    }

    /** "07:30" → 450. Saat dilimi taşımaz: bu yalnız bir gün-içi konumdur. */
    public static function minuteFromClock(string $clock): int
    {
        if (preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])$/', trim($clock), $matches) !== 1) {
            throw new InvalidArgumentException('Clock must be written as HH:MM between 00:00 and 23:59.');
        }

        return ((int) $matches[1]) * 60 + (int) $matches[2];
    }

    /** 450 → "07:30". */
    public static function clockFromMinute(int $minute): string
    {
        if ($minute < 0 || $minute >= self::MINUTES_PER_DAY) {
            throw new InvalidArgumentException('Minute must be within a single day.');
        }

        return sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
    }
}
