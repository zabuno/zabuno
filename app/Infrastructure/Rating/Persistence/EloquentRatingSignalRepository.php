<?php

declare(strict_types=1);

namespace App\Infrastructure\Rating\Persistence;

use App\Application\Rating\Dto\RatingSignalDraft;
use App\Application\Rating\Port\RatingSignalRepositoryPort;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Değişmez puan defterinin yazma yolu — `docs/116` §1 Ö2 / §4.
 *
 * Sipariş deposuyla aynı desende `DB::table` kullanır: `rating_signals`'ın
 * Eloquent modeli YOKTUR ve olmayacak. Model olsaydı `->delete()` ve
 * `->update()` bir tuş uzaklıkta dururdu; defterin değişmezliği bir
 * yorumla değil, ELDE OLMAYAN BİR ÇAĞRIYLA korunuyor.
 */
final class EloquentRatingSignalRepository implements RatingSignalRepositoryPort
{
    public function countedSignalsFromTableSince(
        int $workspaceId,
        int $diningTableId,
        DateTimeImmutable $since,
    ): int {
        return DB::table('rating_signals')
            ->where('workspace_id', $workspaceId)
            ->where('dining_table_id', $diningTableId)
            // İşaretli satır sayılmaz: yığılma penceresi SAYILAN oyları
            // ölçer, defterdeki satırları değil.
            ->whereNull('excluded_at')
            ->where('observed_at', '>=', $since)
            ->count();
    }

    public function hasCountedSignal(
        int $workspaceId,
        string $subjectType,
        int $subjectId,
        string $visitorKey,
    ): bool {
        return DB::table('rating_signals')
            ->where('workspace_id', $workspaceId)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('visitor_key', $visitorKey)
            ->whereNull('excluded_at')
            ->exists();
    }

    public function record(RatingSignalDraft $draft): void
    {
        /*
            İŞARETLEME VE YAZMA TEK İŞLEMDE.

            Ayrı yapsaydık iki hâl doğardı ve ikisi de yanlış: önce yazıp
            sonra işaretlemek, arada kalan anda ziyaretçiye iki SAYILAN oy
            verir (ve kısmî benzersizlik indeksi haklı olarak isteği
            reddeder); önce işaretleyip sonra yazmak ise yazma başarısız
            olursa misafirin dünkü oyunu sebepsiz yere silmiş olur.
        */
        DB::transaction(function () use ($draft): void {
            if ($draft->supersedePreviousWithReason !== null && $draft->visitorKey !== null) {
                DB::table('rating_signals')
                    ->where('workspace_id', $draft->workspaceId)
                    ->where('subject_type', $draft->subjectType->value)
                    ->where('subject_id', $draft->subjectId)
                    ->where('visitor_key', $draft->visitorKey)
                    ->whereNull('excluded_at')
                    /*
                        BU TEK `update` DEFTERİN DEĞİŞMEZLİĞİNİ BOZMAZ.

                        Puan, kaynak, zaman ve bağlam alanlarının HİÇBİRİNE
                        dokunulmuyor; yalnız "bu satır artık sayılmıyor" notu
                        düşülüyor. `2026_09_07_000100` göçünün cümlesi:
                        *"Tek istisna işaretlemedir — ve o bile bir silme
                        değil, bir nottur."*
                    */
                    ->update([
                        'excluded_at' => $draft->recordedAt,
                        'exclusion_reason' => $draft->supersedePreviousWithReason,
                        'updated_at' => $draft->recordedAt,
                    ]);
            }

            DB::table('rating_signals')->insert([
                'workspace_id' => $draft->workspaceId,
                'subject_type' => $draft->subjectType->value,
                'subject_id' => $draft->subjectId,
                'source' => $draft->source->value,
                'score_value' => $draft->scoreValue,
                'score_scale_max' => $draft->scoreScaleMax,
                'visitor_key' => $draft->visitorKey,
                'qr_code_id' => $draft->qrCodeId,
                'dining_table_id' => $draft->diningTableId,
                'observed_at' => $draft->observedAt,
                'recorded_at' => $draft->recordedAt,
                'excluded_at' => $draft->excludedReason === null ? null : $draft->recordedAt,
                'exclusion_reason' => $draft->excludedReason,
                'created_at' => $draft->recordedAt,
                'updated_at' => $draft->recordedAt,
            ]);
        });
    }
}
