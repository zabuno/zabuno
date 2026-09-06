<?php

declare(strict_types=1);

namespace App\Application\Support\Port;

/**
 * Referans üreteci — port, çünkü rastgelelik test edilemez ama çarpışma
 * davranışı test edilmek ZORUNDADIR. Test, üreteci "iki kez aynı numara,
 * sonra yenisi" diyen bir sahteyle değiştirir.
 */
interface SupportReferenceGeneratorPort
{
    /** `App\Domain\Support\SupportReference::PATTERN` biçiminde bir aday. */
    public function generate(): string;
}
