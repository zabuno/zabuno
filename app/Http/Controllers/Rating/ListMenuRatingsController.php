<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rating;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Rating\Dto\RatingSummary;
use App\Application\Rating\Port\RatingScoreQueryPort;
use App\Domain\Authorization\Permission;
use App\Domain\Rating\RatingAlgorithm;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * SAHİBİN PUAN EKRANININ VERİSİ — `docs/116` §3 (P5).
 *
 * ═══ PANEL DE MİSAFİRLE AYNI KARARI OKUR ═══
 *
 * Eşik altında bu uç da sayı vermez (`RatingSummary` bunu yapıcıda
 * uyguluyor). İki yüzeye iki farklı kural koysaydık, sahip "misafir 4,2
 * görüyor, ben neden görmüyorum?" sorusunun cevabını hiçbir yerde
 * bulamazdı.
 *
 * ═══ AMA SAYIM SAHİPTEN GİZLENMEZ ═══
 *
 * `signalCount` eşik altında da doludur. Gizlenen şey PUAN, yani henüz
 * güvenilmeyen türetilmiş değerdir; kaç oy geldiği bilinen bir ölçümdür ve
 * sahibin "eşiğe ne kadar kaldı?" sorusunun tek cevabıdır.
 *
 * ═══ SAYININ YAŞI DA VERİLİR ═══
 *
 * `computedAt` ve `serverTime` birlikte gider. Türetilmiş puan bir komutun
 * (`rating:recompute`) çıktısıdır; komut çalışmadıysa ekrandaki sayı
 * dünkü sayıdır. Garson kuyruğunda da aynı desen var ve aynı sebeple:
 * donmuş bir ekranla dolu bir ekran aynı görünür.
 */
final class ListMenuRatingsController extends Controller
{
    public function __construct(
        private readonly RatingScoreQueryPort $scores,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace, int $menu): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::RatingView, $workspace)) {
            // Enumeration-safe: komşu kiracı "böyle bir menü var ama sana
            // kapalı"yı bile öğrenemez.
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $belongsToWorkspace = DB::table('menus')
            ->where('id', $menu)
            ->where('workspace_id', $workspace)
            ->exists();

        if (! $belongsToWorkspace) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $algorithm = RatingAlgorithm::current();

        return response()->json([
            'data' => array_map(
                static fn (RatingSummary $summary): array => $summary->toArray(),
                $this->scores->forMenu($workspace, $menu, $algorithm->version),
            ),
            // Hangi kuralın çıktısına bakıldığı YAZILIR (`docs/116` §1 Ö3):
            // sürüm görünmezse "bu puan neden düştü — kural mı değişti, oy
            // mu geldi?" sorusunun cevabı yoktur.
            'algorithmVersion' => $algorithm->version,
            'scaleMax' => $algorithm->scaleMax,
        ]);
    }
}
