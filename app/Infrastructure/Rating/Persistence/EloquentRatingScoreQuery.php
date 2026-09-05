<?php

declare(strict_types=1);

namespace App\Infrastructure\Rating\Persistence;

use App\Application\Rating\Dto\RatingSummary;
use App\Application\Rating\Port\RatingScoreQueryPort;
use App\Domain\Rating\RatingAlgorithm;
use App\Domain\Rating\RatingSubject;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Türetilmiş puanın okuma yolu — `docs/116` §3 (P5).
 *
 * ═══ MENÜ SATIRINDAN BAŞLAR, PUANDAN DEĞİL ═══
 *
 * Sorgu `menu_items`'tan başlayıp `rating_scores`'a SOL BİRLEŞİMLE bağlanır.
 * Ters yönde kursaydık (puandan menüye), hiç oy almamış tabaklar listede
 * hiç görünmezdi — ve sahibin ekranında bir tabağın YOK olması ile "henüz
 * yeterli değerlendirme yok" demesi aynı şey değildir. Birincisi bir
 * boşluk, ikincisi bir cevaptır.
 *
 * ═══ SÜRÜM BİRLEŞİMİN İÇİNDEDİR ═══
 *
 * `algorithm_version` `WHERE`'e değil `ON`'a yazılır. `WHERE`'e yazsaydık
 * sol birleşim iç birleşime dönerdi: o sürümde satırı olmayan tabaklar
 * elenirdi ve az önceki cümle çürürdü.
 */
final class EloquentRatingScoreQuery implements RatingScoreQueryPort
{
    public function forMenu(int $workspaceId, int $menuId, int $algorithmVersion): array
    {
        $rows = [];

        foreach ($this->rows($workspaceId, $menuId, $algorithmVersion) as $row) {
            $rows[] = $this->summary($row);
        }

        return $rows;
    }

    public function forGuestMenu(int $workspaceId, int $menuId, int $algorithmVersion): array
    {
        $map = [];

        foreach ($this->rows($workspaceId, $menuId, $algorithmVersion) as $row) {
            $map[(int) $row->menu_item_id] = $this->summary($row);
        }

        return $map;
    }

    /**
     * @return Collection<int, object>
     */
    private function rows(int $workspaceId, int $menuId, int $algorithmVersion): Collection
    {
        $subject = RatingSubject::Product->value;

        return DB::table('menu_items')
            ->join('menu_categories', 'menu_categories.id', '=', 'menu_items.category_id')
            ->join('products', 'products.id', '=', 'menu_items.product_id')
            ->leftJoin('rating_scores', function ($join) use ($workspaceId, $algorithmVersion, $subject): void {
                $join->on('rating_scores.subject_id', '=', 'menu_items.product_id')
                    ->where('rating_scores.workspace_id', '=', $workspaceId)
                    ->where('rating_scores.subject_type', '=', $subject)
                    // Sürüm damgası birleşimin İÇİNDE (bkz. sınıf yorumu).
                    ->where('rating_scores.algorithm_version', '=', $algorithmVersion);
            })
            ->leftJoin('rating_replies', function ($join) use ($workspaceId, $subject): void {
                $join->on('rating_replies.subject_id', '=', 'menu_items.product_id')
                    ->where('rating_replies.workspace_id', '=', $workspaceId)
                    ->where('rating_replies.subject_type', '=', $subject);
            })
            ->where('menu_categories.menu_id', $menuId)
            ->where('products.workspace_id', $workspaceId)
            /*
                HER SÜTUN TAKMA ADLI.

                Dört tablonun dördünde de `id`, üçünde `workspace_id`,
                ikisinde `subject_id` var. Takma adsız seçim, sürücüye göre
                birinin diğerini ezmesi demektir — ve PostgreSQL ile SQLite
                bu konuda aynı davranmaz, yani hata yerelde görünmez.
            */
            ->select([
                'menu_items.id as menu_item_id',
                'menu_items.product_id as product_id',
                'products.name as product_name',
                'rating_scores.score_value as score_value',
                'rating_scores.score_scale_max as score_scale_max',
                'rating_scores.signal_count as signal_count',
                'rating_scores.meets_display_threshold as meets_display_threshold',
                'rating_scores.computed_at as computed_at',
                'rating_replies.body as reply_body',
                'rating_replies.published_at as reply_published_at',
            ])
            ->orderBy('menu_categories.position')
            ->orderBy('menu_items.position')
            ->get();
    }

    private function summary(object $row): RatingSummary
    {
        return new RatingSummary(
            (int) $row->menu_item_id,
            (int) $row->product_id,
            (string) $row->product_name,
            $row->score_value === null ? null : (float) $row->score_value,
            // Ölçek satırın kendisinden okunur; satır yoksa gösterilecek
            // puan da yok, ama ekranın "5 üzerinden" diyebilmesi için
            // yürürlükteki ölçek gerekir.
            $row->score_scale_max === null
                ? RatingAlgorithm::current()->scaleMax
                : (int) $row->score_scale_max,
            (int) ($row->signal_count ?? 0),
            /*
                SATIRI OLMAYAN TABAK EŞİĞİ GEÇMİŞ SAYILMAZ.

                `null` burada `false`a çevrilir ve bu çeviri tek yerde
                yapılır. Dağıtılsaydı, bir okuyucunun `null`'ı "bilinmiyor,
                o hâlde göster" diye yorumlaması an meselesiydi.
            */
            (bool) ($row->meets_display_threshold ?? false),
            $row->computed_at === null
                ? null
                : (new DateTimeImmutable((string) $row->computed_at))->format(DateTimeImmutable::ATOM),
            $row->reply_body === null ? null : (string) $row->reply_body,
            $row->reply_published_at === null
                ? null
                : (new DateTimeImmutable((string) $row->reply_published_at))->format(DateTimeImmutable::ATOM),
        );
    }
}
