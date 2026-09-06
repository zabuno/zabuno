<?php

declare(strict_types=1);

namespace App\Domain\Legal;

use DateTimeImmutable;

/**
 * Hukuki inceleme YAPILDI MI? (FF-198)
 *
 * Yasal metinler yayınlanır ama bir hukukçu okuyana kadar sayfa üstte
 * "This text is pending legal review" notu taşır. Not, `LEGAL_REVIEWED_AT`
 * geçerli bir tarih olduğunda kalkar — ve YALNIZ o zaman. "yes", "1" ya da
 * yazım hatası bir inceleme değildir; not kalır. Çeviri kilidiyle aynı
 * mantık (`TranslationGenerationLock`): kolay bir hatayla açılan bir
 * anahtar, anahtar değildir.
 */
final class LegalReview
{
    private function __construct(public readonly ?string $reviewedAt) {}

    public static function fromConfig(): self
    {
        $raw = config('legal.reviewed_at');

        if (! is_string($raw) || trim($raw) === '') {
            return new self(null);
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', trim($raw));

        if ($parsed === false || $parsed->format('Y-m-d') !== trim($raw)) {
            return new self(null);
        }

        return new self($parsed->format('Y-m-d'));
    }

    public function isPending(): bool
    {
        return $this->reviewedAt === null;
    }
}
