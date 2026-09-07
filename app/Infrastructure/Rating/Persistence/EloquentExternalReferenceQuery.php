<?php

declare(strict_types=1);

namespace App\Infrastructure\Rating\Persistence;

use App\Application\Rating\Port\ExternalReferenceQueryPort;
use App\Domain\Rating\ExternalMatchConfidence;
use App\Domain\Rating\ExternalMatchedBy;
use App\Domain\Rating\ExternalReference;
use App\Domain\Rating\ExternalReferenceDecision;
use App\Domain\Rating\ExternalSystem;
use App\Domain\Rating\RatingSubject;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Eşleme tablosunun okuma yolu — `docs/116` §1 Ö4 (P7).
 *
 * ═══ KİRACI SINIRI HER SORGUNUN İÇİNDE ═══
 *
 * Tek istisna `identityIsConfirmedElsewhere()`tir ve gerekçesi portun
 * doküman bloğunda yazılıdır: bir dış kimlik platform genelinde tektir.
 * O metot bile yalnız BOOLEAN döner — hangi kiracının taşıdığı dışarı
 * çıkmaz.
 */
final class EloquentExternalReferenceQuery implements ExternalReferenceQueryPort
{
    /** @return list<ExternalReference> */
    public function confirmedForSubject(
        int $workspaceId,
        RatingSubject $subjectType,
        int $subjectId,
    ): array {
        $rows = DB::table('external_references')
            ->where('workspace_id', $workspaceId)
            ->where('subject_type', $subjectType->value)
            ->where('subject_id', $subjectId)
            /*
                DIŞ VERİNİN GEÇECEĞİ TEK KAPI.

                Filtre burada, çağıranda değil: dış veriyi çeken kod
                "hangileri onaylıydı?" sorusunu kendi sorsaydı, bir gün
                sormayı unuturdu ve o gün başkasının puanı bizim menümüzde
                görünürdü.
            */
            ->where('owner_decision', ExternalReferenceDecision::Confirmed->value)
            ->orderBy('id')
            ->get();

        return $rows->map(fn (stdClass $row): ExternalReference => $this->hydrate($row))->all();
    }

    /** @return list<ExternalReference> */
    public function pendingDecisionsForWorkspace(int $workspaceId): array
    {
        $rows = DB::table('external_references')
            ->where('workspace_id', $workspaceId)
            // Reddedilmiş satır BEKLEMİYOR: "hayır" verilmiş bir cevaptır.
            ->whereNull('owner_decision')
            ->orderBy('id')
            ->get();

        return $rows->map(fn (stdClass $row): ExternalReference => $this->hydrate($row))->all();
    }

    public function find(int $workspaceId, int $referenceId): ?ExternalReference
    {
        $row = DB::table('external_references')
            ->where('id', $referenceId)
            ->where('workspace_id', $workspaceId)
            ->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function identityIsConfirmedElsewhere(
        ExternalSystem $system,
        string $externalId,
        int $exceptReferenceId,
    ): bool {
        return DB::table('external_references')
            ->where('external_system', $system->value)
            ->where('external_id', $externalId)
            ->where('owner_decision', ExternalReferenceDecision::Confirmed->value)
            ->where('id', '!=', $exceptReferenceId)
            // BOOLEAN VE BAŞKA HİÇBİR ŞEY: satırın kendisi dışarı çıkmaz.
            ->exists();
    }

    public function subjectIsConfirmedIn(
        int $workspaceId,
        RatingSubject $subjectType,
        int $subjectId,
        ExternalSystem $system,
        int $exceptReferenceId,
    ): bool {
        return DB::table('external_references')
            ->where('workspace_id', $workspaceId)
            ->where('subject_type', $subjectType->value)
            ->where('subject_id', $subjectId)
            ->where('external_system', $system->value)
            ->where('owner_decision', ExternalReferenceDecision::Confirmed->value)
            ->where('id', '!=', $exceptReferenceId)
            ->exists();
    }

    private function hydrate(stdClass $row): ExternalReference
    {
        $decision = $row->owner_decision === null
            ? null
            : ExternalReferenceDecision::from((string) $row->owner_decision);

        return new ExternalReference(
            id: (int) $row->id,
            workspaceId: (int) $row->workspace_id,
            subjectType: RatingSubject::from((string) $row->subject_type),
            subjectId: (int) $row->subject_id,
            system: ExternalSystem::from((string) $row->external_system),
            externalId: (string) $row->external_id,
            externalLabel: $row->external_label === null ? null : (string) $row->external_label,
            confidence: ExternalMatchConfidence::from((string) $row->confidence),
            matchedBy: ExternalMatchedBy::from((string) $row->matched_by),
            matchedAt: new DateTimeImmutable((string) $row->matched_at),
            decision: $decision,
            decidedByUserId: $row->decided_by_user_id === null ? null : (int) $row->decided_by_user_id,
            decidedAt: $row->decided_at === null ? null : new DateTimeImmutable((string) $row->decided_at),
        );
    }
}
