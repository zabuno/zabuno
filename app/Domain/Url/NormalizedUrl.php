<?php

declare(strict_types=1);

namespace App\Domain\Url;

/**
 * Normalizasyonun sonucu.
 *
 * `changed` alanı ayrı durur çünkü "aynı adres" ile "kanonik adrese eşit
 * adres" farklı sorulardır: yönlendirme yalnız gerçekten değişmişse yapılır.
 * Aksi hâlde her istek kendine yönlenir ve döngü oluşur.
 */
final class NormalizedUrl
{
    public function __construct(
        public readonly string $path,
        public readonly string $query,
        public readonly bool $changed,
    ) {}

    public function target(): string
    {
        return $this->query === '' ? $this->path : $this->path.'?'.$this->query;
    }
}
