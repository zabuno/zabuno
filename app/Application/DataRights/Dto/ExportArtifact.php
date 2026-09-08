<?php

declare(strict_types=1);

namespace App\Application\DataRights\Dto;

/**
 * Üretilen arşivin ölçülen hâli — FF-226.
 *
 * SAĞLAMA (sha256) bilerek var: bir hukuk birimine verilen dosyanın
 * "aldığım dosya bu mu?" sorusu bir gün sorulur ve o soruya cevap
 * verebilmek için sağlamanın ÜRETİM anında hesaplanmış olması gerekir.
 */
final readonly class ExportArtifact
{
    /**
     * @param  array<string, int>  $rowCounts  bölüm adı → satır sayısı
     */
    public function __construct(
        public string $path,
        public int $bytes,
        public string $checksumSha256,
        public array $rowCounts,
    ) {}
}
