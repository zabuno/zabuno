<?php

declare(strict_types=1);

namespace App\Application\Ai\Exception;

use RuntimeException;

/**
 * Model şemaya uymayan bir cevap döndürdü.
 *
 * Bu bir BAŞARISIZLIKTIR, kısmi bir sonuç değil. Şemaya uymayan cevabı
 * kullanıcıya göstermek, arayüzde sessiz bozulma üretir (`docs/51` UNK-02).
 */
final class SchemaViolationException extends RuntimeException
{
    /** @param list<string> $problems */
    public function __construct(public readonly string $schemaVersion, public readonly array $problems)
    {
        parent::__construct(
            "Cevap {$schemaVersion} şemasına uymuyor: ".implode('; ', $problems)
        );
    }
}
