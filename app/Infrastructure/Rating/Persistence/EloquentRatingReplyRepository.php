<?php

declare(strict_types=1);

namespace App\Infrastructure\Rating\Persistence;

use App\Application\Rating\Port\RatingReplyRepositoryPort;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sahibin yanıtının yazma yolu — `docs/116` §4 (P6).
 *
 * Bu sınıfta `delete` VARDIR ve `EloquentRatingSignalRepository`'de YOKTUR.
 * Ayrım tek cümlede: misafirin ölçümü sahibin malı değildir, sahibin
 * cümlesi sahibinindir.
 */
final class EloquentRatingReplyRepository implements RatingReplyRepositoryPort
{
    public function put(
        int $workspaceId,
        string $subjectType,
        int $subjectId,
        string $body,
        ?int $authorUserId,
    ): void {
        $now = Carbon::now();

        /*
            VARSA ÜZERİNE YAZ, YOKSA EKLE — bir ürün için restoranın TEK bir
            sesi vardır.

            Yeni satır eklemek iki yanıt demekti ve misafir hangisinin
            bugünkü söz olduğunu bilemezdi. Yanıt bir ölçüm değil bir metin
            alanıdır; düzeltmenin doğru karşılığı üzerine yazmaktır.

            `created_at` GÜNCELLEMEDE KORUNUR ve bu ayrı bir sorgu yazmaya
            değer: "bu restoran bu tabağa ne zaman ilk kez cevap verdi?"
            sorusunun cevabı, ilk cümlenin tarihidir — her düzeltmede
            bugüne çekilirse o cevap kaybolur.
        */
        $existing = DB::table('rating_replies')
            ->where('workspace_id', $workspaceId)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId);

        $values = [
            'body' => $body,
            'author_user_id' => $authorUserId,
            // Düzeltilen bir cümle YENİDEN yayınlanır: misafirin gördüğü
            // metin değiştiyse "ne zaman söylendi" de değişmiştir.
            'published_at' => $now,
            'updated_at' => $now,
        ];

        if ((clone $existing)->exists()) {
            $existing->update($values);

            return;
        }

        DB::table('rating_replies')->insert($values + [
            'workspace_id' => $workspaceId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'created_at' => $now,
        ]);
    }

    public function remove(int $workspaceId, string $subjectType, int $subjectId): void
    {
        DB::table('rating_replies')
            ->where('workspace_id', $workspaceId)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->delete();
    }
}
