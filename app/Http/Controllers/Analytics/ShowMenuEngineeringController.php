<?php

declare(strict_types=1);

namespace App\Http\Controllers\Analytics;

use App\Application\Analytics\Port\AnalyticsRepositoryPort;
use App\Application\Authorization\Port\AuthorizationPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * "Menümde ne işe yarıyor?" — `docs/84` (P1-08).
 *
 * Bugüne kadarki cevap "menün 214 kez açıldı"ydı. Bu, menüyü DEĞİŞTİRMEK
 * için hiçbir şey söylemez: hangi ürünü büyütmeli, hangisini listeden
 * çıkarmalı, hangi talebi karşılamıyorum?
 */
final class ShowMenuEngineeringController extends Controller
{
    /**
     * Rapor için gereken en az FARKLI ziyaretçi sayısı.
     *
     * Eşiğin altında sayı GÖSTERİLMEZ. Üç ziyaretçinin baktığı bir ürünü
     * "en çok bakılan" diye sunmak, sahibi gürültüye göre menü
     * düzenlettirirdi — ve bir kez yanlış çıkan rapor bir daha okunmaz.
     */
    private const MINIMUM_VIEWERS = 5;

    public function __construct(
        private readonly AnalyticsRepositoryPort $analytics,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::AnalyticsView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $range = (string) $request->query('range', '30d');

        if (! in_array($range, ['today', '7d', '30d'], true)) {
            return response()->json(['message' => 'Unknown range.'], 422);
        }

        $viewers = $this->analytics->itemViewersByMenuItem($workspace, $range, Carbon::now());

        // Yayındaki ürünlerin TAMAMI okunur: "hiç bakılmayan" sorusunun
        // cevabı, olayların değil ÜRÜN LİSTESİNİN içindedir.
        $products = DB::table('menu_items')
            ->join('menu_categories', 'menu_categories.id', '=', 'menu_items.category_id')
            ->join('menus', 'menus.id', '=', 'menu_categories.menu_id')
            ->join('products', 'products.id', '=', 'menu_items.product_id')
            ->where('menus.workspace_id', $workspace)
            ->where('menu_items.is_visible', true)
            ->select([
                'menu_items.id as menu_item_id',
                'products.name as product_name',
                'menu_categories.name as category_name',
            ])
            ->get();

        $totalViewers = array_sum($viewers);

        if ($totalViewers < self::MINIMUM_VIEWERS) {
            /*
                Boş bir tablo, sahibe "ürünüm bozuk" dedirtir.

                Sebep ve EŞİK açıkça söylenir (`docs/66` disiplini): kaç
                ziyaretçi gerektiğini bilmeyen biri, ne kadar bekleyeceğini de
                bilemez.
            */
            return response()->json([
                'state' => 'not_enough_data',
                'threshold' => self::MINIMUM_VIEWERS,
                'observedViewers' => $totalViewers,
                'mostViewed' => [],
                'neverViewed' => [],
                'searchesWithNoResults' => [],
            ]);
        }

        $rows = $products->map(fn (object $row): array => [
            'menuItemId' => (int) $row->menu_item_id,
            'productName' => (string) $row->product_name,
            'categoryName' => (string) $row->category_name,
            'viewers' => $viewers[(int) $row->menu_item_id] ?? 0,
        ]);

        $mostViewed = $rows->filter(static fn (array $row): bool => $row['viewers'] > 0)
            ->sortByDesc('viewers')
            ->take(10)
            ->values()
            ->all();

        $neverViewed = $rows->filter(static fn (array $row): bool => $row['viewers'] === 0)
            ->sortBy('productName')
            ->values()
            ->all();

        return response()->json([
            'state' => 'ready',
            'threshold' => self::MINIMUM_VIEWERS,
            'observedViewers' => $totalViewers,
            'mostViewed' => $mostViewed,
            'neverViewed' => $neverViewed,
            'searchesWithNoResults' => $this->searchesWithNoResults($workspace, $range),
        ]);
    }

    /**
     * Sahibin göremediği tek talep: menüde OLMAYAN şeyin talebi.
     *
     * @return list<array{term:string,searches:int}>
     */
    private function searchesWithNoResults(int $workspaceId, string $range): array
    {
        $cutoff = match ($range) {
            'today' => Carbon::now()->startOfDay(),
            '7d' => Carbon::now()->subDays(7),
            default => Carbon::now()->subDays(30),
        };

        return DB::table('analytics_events')
            ->where('workspace_id', $workspaceId)
            ->where('event_type', 'search_no_results')
            ->whereNotNull('search_term')
            ->where('occurred_at', '>=', $cutoff)
            ->groupBy('search_term')
            ->selectRaw('search_term, COUNT(DISTINCT visitor_key) as searches')
            ->orderByDesc('searches')
            ->limit(10)
            ->get()
            ->map(static fn (object $row): array => [
                'term' => (string) $row->search_term,
                'searches' => (int) $row->searches,
            ])
            ->all();
    }
}
