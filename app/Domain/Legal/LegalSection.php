<?php

declare(strict_types=1);

namespace App\Domain\Legal;

use InvalidArgumentException;

/**
 * Bir yasal belgenin NUMARALI bölümü — FF-198.
 *
 * Numara burada DEĞİL: bölümün sırası belgedeki yeridir ve şablon o
 * sıradan üretir. Numarayı veriye yazmak, bir bölüm silindiğinde "3, 5,
 * 6" diye sayan bir belge doğururdu.
 */
final class LegalSection
{
    /** @param  list<string>  $paragraphs */
    public function __construct(
        public readonly string $heading,
        public readonly array $paragraphs,
    ) {
        if (trim($heading) === '') {
            throw new InvalidArgumentException('A legal section needs a heading.');
        }

        if ($paragraphs === []) {
            throw new InvalidArgumentException("Legal section \"{$heading}\" has no text.");
        }
    }
}
