<?php

declare(strict_types=1);

namespace App\Domain\Rating;

use InvalidArgumentException;

/**
 * GÖSTERİM EŞİĞİ — `docs/116` §3 (`docs/114`'ten devralındı).
 *
 * Üç kişinin verdiği beş yıldız bir bilgi değildir. Gösterilirse YENİ ÜRÜN
 * HER ZAMAN EN İYİ GÖRÜNÜR: menüye dün eklenmiş bir tatlı, iki arkadaşın
 * oyuyla listenin başına çıkar ve gerçekten iyi olan ürünü aşağı iter.
 *
 * EŞİĞİN ALTINDA SIFIR YILDIZ DEĞİL, "HENÜZ YETERLİ DEĞERLENDİRME YOK"
 * denir. Sıfır bir ÖLÇÜMDÜR ve bilinmeyenin yerine geçemez; sıfır yazmak,
 * hiç oy almamış bir ürünü kötü ürün gibi göstermektir.
 */
final class RatingThresholds
{
    public function __construct(
        /**
         * Puanın gösterilebilmesi için gereken en az sinyal sayısı.
         *
         * Sıfır olamaz: sıfıra düşerse tek oy almış bir ürün "5,0" görünür
         * ve bu korumanın kalktığını kimse fark etmez.
         */
        public readonly int $minimumSignals,
        /**
         * Sinyal sayısı yetse bile gereken en az TOPLAM AĞIRLIK.
         *
         * Sayı tek başına yeterli değildir: on tane üç yıllık oy, sönüm
         * sonrası neredeyse hiçbir şey eder. Ağırlık eşiği olmasaydı, ölü
         * bir ürünün eski puanı sonsuza kadar ekranda kalırdı.
         */
        public readonly float $minimumWeight,
    ) {
        if ($minimumSignals <= 0) {
            throw new InvalidArgumentException('Rating display threshold must require at least one signal.');
        }

        if ($minimumWeight <= 0.0) {
            throw new InvalidArgumentException('Rating display threshold must require a positive weight.');
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public static function fromArray(array $definition): self
    {
        return new self(
            (int) ($definition['minimum_signals'] ?? 0),
            (float) ($definition['minimum_weight'] ?? 0.0),
        );
    }

    /** Bu ürünün puanı ekrana çizilebilir mi? */
    public function areMet(int $signalCount, float $totalWeight): bool
    {
        return $signalCount >= $this->minimumSignals && $totalWeight >= $this->minimumWeight;
    }
}
