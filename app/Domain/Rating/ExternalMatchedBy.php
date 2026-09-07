<?php

declare(strict_types=1);

namespace App\Domain\Rating;

/**
 * EŞLEMEYİ KİM KURDU — `docs/116` §1 Ö4.
 *
 * "Kim eşledi" ile "kim onayladı" AYRI sorulardır ve ayrı sütunlarda
 * yaşarlar. Tek sütuna sıkıştırsaydık, sahibin onayladığı otomatik bir
 * eşleme ile sahibin kendi elle kurduğu eşleme aynı görünürdü — oysa
 * birincisinde makine bir aday getirdi, ikincisinde sahip kimliği kendisi
 * yazdı. Yanlış eşleme incelenirken aradaki fark, hatanın nerede
 * olduğunu söyleyen tek bilgidir.
 */
enum ExternalMatchedBy: string
{
    /** Bkz. `ExternalSystem::MAX_VALUE_LENGTH` gerekçesi. */
    public const MAX_VALUE_LENGTH = 16;

    /** Bir eşleştirici aday olarak getirdi; bu bir ÖNERİdir. */
    case Automatic = 'automatic';

    /** Sahip kimliği kendisi girdi; girmesi zaten onaydır. */
    case Owner = 'owner';
}
