<?php

declare(strict_types=1);

namespace App\Application\Rating\Dto;

use App\Domain\Rating\ExternalMatchConfidence;
use App\Domain\Rating\ExternalSystem;
use App\Domain\Rating\RatingSubject;
use DateTimeImmutable;

/**
 * Kurulmak üzere hazırlanmış TEK bir eşleme — `docs/116` §1 Ö4 (P7).
 *
 * ═══ BURADA KARAR ALANI YOKTUR VE BU KASITLIDIR ═══
 *
 * Taslak "onaylı mı?" sorusunu TAŞIMAZ. Taşısaydı, bir eşleştirici kendi
 * önerisini onaylı olarak yazabilirdi — ve §5 D4'ün tamamı ("otomatik
 * eşleme bir öneridir") bir yorum satırına dönerdi. Kararı yazan tek yol
 * `ExternalReferenceRepositoryPort::confirm()`/`reject()`'tir ve o yol
 * karar veren KİŞİYİ ister.
 *
 * `matchedBy` de burada yoktur: eşlemeyi kimin kurduğunu taslağı dolduran
 * değil, ÇAĞRILAN METOT belirler (`suggest()` otomatiktir, `mapByOwner()`
 * sahiptir). Alan olsaydı, otomatik bir eşleştirici kendini "sahip" diye
 * yazabilirdi.
 */
final class ExternalReferenceDraft
{
    public function __construct(
        public readonly int $workspaceId,
        /** Bizim varlığımız: şube ya da ürün. */
        public readonly RatingSubject $subjectType,
        public readonly int $subjectId,
        public readonly ExternalSystem $system,
        public readonly string $externalId,
        /** Dış sistemde görünen işletme adı — sahibin kararı için. */
        public readonly ?string $externalLabel,
        /** Eşleştiricinin kendi iddiası; tek başına hiçbir kapıyı açmaz. */
        public readonly ExternalMatchConfidence $confidence,
        /** Eşlemenin kurulduğu an. */
        public readonly DateTimeImmutable $matchedAt,
    ) {}
}
