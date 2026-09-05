<?php

declare(strict_types=1);

namespace App\Domain\Rating;

use InvalidArgumentException;

/**
 * ALGORİTMANIN HEDEFİ — `docs/116` §2 (sahibin 2026-09-05 kararı).
 *
 * *"Puanlamanın KPI'ları, OKR'ları bir algoritma dosyasına bağlıdır."*
 *
 * Hedef yoksa "iyileşti" denemez. Bir algoritmanın ölçülebilir bir hedefi
 * yazılı değilse, her değişiklik kendi başarısının da hakemi olur: ağırlık
 * değişir, puanlar kayar ve kimse bunun iyi mi kötü mü olduğunu
 * söyleyemez.
 *
 * HEDEF METNİ İNGİLİZCEDİR. Bu depoda gönderilen tek dil İngilizce; hedef
 * bir gün panelde görünürse çeviri katmanı olmadan doğru dilde çıkar.
 * Gerekçeler (bu yorumlar) Türkçe kalır — onlar sahibe ve geliştiriciye
 * yazılmıştır, kullanıcıya değil.
 */
final class RatingOkr
{
    public function __construct(
        /** Neyi başarmaya çalışıyoruz — bir cümle, ölçülebilir olana bağlı. */
        public readonly string $objective,
        /** Hedef değer. Sıfır bir hedef değildir; sıfır hedef, hedefsizliktir. */
        public readonly float $target,
        /**
         * Bugünkü değer. Başlangıçta 0.0 olabilir ve bu dürüsttür: henüz
         * hiç ölçüm yok. Hedefin kendisi sıfır olamaz, mevcut değer olabilir.
         */
        public readonly float $current,
        /** Değerin birimi — "hedef 0,8" tek başına bir şey söylemez. */
        public readonly string $unit,
    ) {
        if (trim($objective) === '') {
            throw new InvalidArgumentException('Rating OKR objective cannot be empty.');
        }

        if ($target <= 0.0) {
            throw new InvalidArgumentException('Rating OKR target must be greater than zero.');
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public static function fromArray(array $definition): self
    {
        return new self(
            (string) ($definition['objective'] ?? ''),
            (float) ($definition['target'] ?? 0.0),
            (float) ($definition['current'] ?? 0.0),
            (string) ($definition['unit'] ?? ''),
        );
    }
}
