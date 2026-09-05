<?php

declare(strict_types=1);

namespace App\Domain\Content;

/**
 * Ekmek kırıntısındaki tek basamak.
 *
 * `path` yalnız BAĞLANTI VERİLEBİLİR bir basamakta doludur. Yayınlanmamış
 * bir ata sayfa, kırıntıda adıyla durur ama bağlantı ALMAZ (`docs/105`
 * §2.2(3)): hiçbir yere götürmeyen bir bağlantı bir yalandır ve deponun
 * kendi bozuk-bağlantı kapısını kırar.
 */
final class Breadcrumb
{
    public function __construct(
        public readonly string $label,
        public readonly ?string $path,
    ) {}

    public function isLinkable(): bool
    {
        return $this->path !== null;
    }
}
