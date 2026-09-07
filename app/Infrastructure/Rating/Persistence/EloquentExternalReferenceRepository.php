<?php

declare(strict_types=1);

namespace App\Infrastructure\Rating\Persistence;

use App\Application\Rating\Dto\ExternalReferenceDraft;
use App\Application\Rating\Port\ExternalReferenceRepositoryPort;
use App\Domain\Rating\ExternalMatchedBy;
use App\Domain\Rating\ExternalReferenceDecision;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Eşleme tablosunun yazma yolu — `docs/116` §1 Ö4 / §5 D4 (P7).
 *
 * Puanlama tarafının geri kalanıyla aynı desende `DB::table` kullanır:
 * `external_references`in Eloquent modeli YOKTUR. Model olsaydı
 * `ExternalReference::where(...)->update(['owner_decision' => 'confirmed'])`
 * bir tuş uzaklıkta dururdu ve sahibin onayı, kimin verdiği belli olmayan
 * bir sütun güncellemesine dönerdi.
 *
 * ═══ BU SINIFTA HİÇBİR AĞ ÇAĞRISI YOKTUR ═══
 *
 * P7 yalnız eşleme yüzeyidir. Dış sağlayıcıya konuşan kod (adaptör) P8'in
 * işidir ve bu depoda henüz YOKTUR — `docs/116` §5 D2: resmî API'si olmayan
 * kaynağa adaptör yazılmaz.
 */
final class EloquentExternalReferenceRepository implements ExternalReferenceRepositoryPort
{
    public function suggest(ExternalReferenceDraft $draft): int
    {
        // Kararsız doğar: `owner_decision` boş. Bir öneri misafire hiçbir
        // şey taşımaz ve taşıyamaz.
        return $this->insert($draft, ExternalMatchedBy::Automatic, null, null, null);
    }

    public function mapByOwner(
        ExternalReferenceDraft $draft,
        int $ownerUserId,
        DateTimeImmutable $decidedAt,
    ): int {
        /*
            SAHİBİN KENDİ ELİYLE YAZDIĞI KİMLİK ZATEN ONAYDIR.

            Ayrıca "onaylıyor musunuz?" diye sormak, verdiği cevabı tekrar
            sormaktır — ve cevaplanmayı bekleyen bir satır olarak karar
            kutusunda birikirdi.
        */
        return $this->insert(
            $draft,
            ExternalMatchedBy::Owner,
            ExternalReferenceDecision::Confirmed,
            $ownerUserId,
            $decidedAt,
        );
    }

    public function confirm(
        int $workspaceId,
        int $referenceId,
        int $ownerUserId,
        DateTimeImmutable $decidedAt,
    ): bool {
        return $this->decide(
            $workspaceId,
            $referenceId,
            ExternalReferenceDecision::Confirmed,
            $ownerUserId,
            $decidedAt,
        );
    }

    public function reject(
        int $workspaceId,
        int $referenceId,
        int $ownerUserId,
        DateTimeImmutable $decidedAt,
    ): bool {
        return $this->decide(
            $workspaceId,
            $referenceId,
            ExternalReferenceDecision::Rejected,
            $ownerUserId,
            $decidedAt,
        );
    }

    private function insert(
        ExternalReferenceDraft $draft,
        ExternalMatchedBy $matchedBy,
        ?ExternalReferenceDecision $decision,
        ?int $decidedByUserId,
        ?DateTimeImmutable $decidedAt,
    ): int {
        return (int) DB::table('external_references')->insertGetId([
            'workspace_id' => $draft->workspaceId,
            'subject_type' => $draft->subjectType->value,
            'subject_id' => $draft->subjectId,
            'external_system' => $draft->system->value,
            'external_id' => $draft->externalId,
            'external_label' => $draft->externalLabel,
            'confidence' => $draft->confidence->value,
            'matched_by' => $matchedBy->value,
            'matched_at' => $draft->matchedAt,
            'owner_decision' => $decision?->value,
            'decided_by_user_id' => $decidedByUserId,
            'decided_at' => $decidedAt,
            'created_at' => $draft->matchedAt,
            'updated_at' => $decidedAt ?? $draft->matchedAt,
        ]);
    }

    private function decide(
        int $workspaceId,
        int $referenceId,
        ExternalReferenceDecision $decision,
        int $ownerUserId,
        DateTimeImmutable $decidedAt,
    ): bool {
        $affected = DB::table('external_references')
            ->where('id', $referenceId)
            /*
                KİRACI SORGUNUN İÇİNDE.

                Kimlik tek başına yeterli olsaydı, bir sahip komşusunun
                eşlemesini kendi panelinden onaylayabilir ya da
                reddedebilirdi — yani başkasının restoranının dış puan
                bağlantısını kesebilirdi.
            */
            ->where('workspace_id', $workspaceId)
            /*
                YALNIZ CEVAPLANMAMIŞ SATIR KARAR ALIR.

                Verilmiş bir cevabı sessizce çevirmek, "bu benim restoranım
                değil" demenin kalıcı olmaması demekti. Fikir değişirse yeni
                bir eşleme kurulur; bu bir güncelleme değil, yeni bir
                karardır.
            */
            ->whereNull('owner_decision')
            ->update([
                'owner_decision' => $decision->value,
                'decided_by_user_id' => $ownerUserId,
                'decided_at' => $decidedAt,
                'updated_at' => $decidedAt,
            ]);

        return $affected === 1;
    }
}
