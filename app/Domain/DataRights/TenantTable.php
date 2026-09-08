<?php

declare(strict_types=1);

namespace App\Domain\DataRights;

/**
 * Bir tablonun kiracıya ne kadar ait olduğu — FF-226 (`docs/138`).
 *
 * Üç ayrı soru üç ayrı alandır ve BİRLEŞTİRİLMEZ:
 *
 * 1. Satırlar nasıl bulunur? (`workspaceColumn` ya da `parentTable`+`foreignKey`)
 * 2. Dışa aktarılır mı? (`exported`)
 * 3. Silinir mi, yoksa saklanır mı ve NEDEN? (`erased`, `retentionReason`)
 *
 * İkinci ve üçüncü sorunun cevabı aynı değildir: faturalar dışa AKTARILIR
 * (kiracının kendi belgeleridir) ama SİLİNMEZ (yasal saklamaya tabidir).
 * Tek bir bayrak olsaydı bu ayrım söylenemezdi.
 *
 * `erased === false` ise `retentionReason` ZORUNLUDUR. Sebebi yazılmayan
 * bir saklama, ekranda "bunlar kalır" diye gösterilemez; gösterilemeyen
 * bir istisna da kullanıcıya "her şey silindi" demenin başka bir adıdır.
 */
final readonly class TenantTable
{
    public function __construct(
        public string $name,
        public ?string $workspaceColumn,
        public ?string $parentTable,
        public ?string $foreignKey,
        public bool $exported,
        public bool $erased,
        public ?string $retentionReason = null,
        public ?string $exclusionReason = null,
    ) {
        if (! $erased && ($retentionReason === null || $retentionReason === '')) {
            throw new \InvalidArgumentException("Silinmeyen tablo sebebini yazmalı: {$name}.");
        }

        if (! $exported && ($exclusionReason === null || $exclusionReason === '')) {
            throw new \InvalidArgumentException("Dışa aktarılmayan tablo sebebini yazmalı: {$name}.");
        }

        if ($workspaceColumn === null && ($parentTable === null || $foreignKey === null)) {
            throw new \InvalidArgumentException("Satırları bulunamayan tablo: {$name}.");
        }
    }

    public static function direct(
        string $name,
        bool $exported = true,
        bool $erased = true,
        ?string $retentionReason = null,
        ?string $exclusionReason = null,
        string $column = 'workspace_id',
    ): self {
        return new self($name, $column, null, null, $exported, $erased, $retentionReason, $exclusionReason);
    }

    public static function child(
        string $name,
        string $parentTable,
        string $foreignKey,
        bool $exported = true,
        bool $erased = true,
        ?string $retentionReason = null,
        ?string $exclusionReason = null,
    ): self {
        return new self($name, null, $parentTable, $foreignKey, $exported, $erased, $retentionReason, $exclusionReason);
    }
}
