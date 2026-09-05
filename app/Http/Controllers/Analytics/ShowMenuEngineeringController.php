<?php

declare(strict_types=1);

namespace App\Http\Controllers\Analytics;

use App\Application\Analytics\Port\AnalyticsRepositoryPort;
use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Entitlement\Exception\EntitlementDeniedException;
use App\Application\Entitlement\UseCase\RequireEntitlement;
use App\Domain\Authorization\Permission;
use App\Domain\Entitlement\Entitlement;
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
        private readonly RequireEntitlement $requireEntitlement,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::AnalyticsView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        /*
            CORE-04: analitik raporlama PLANA BAĞLIDIR (owner kararı,
            2026-08-26) ve bu rapor da analitik raporlamadır — özet ve zaman
            serisi uçlarıyla aynı ölçümden, aynı tablodan konuşur.

            Kapı buraya UNUTULMUŞTU: planı raporlama içermeyen bir sahip,
            özet ekranında 402 alırken panonun "Ölçümlerinizden N öneri"
            bölümünde ürün başına ziyaretçi sayılarını, hiç bakılmayan ürün
            listesini ve sonuçsuz arama terimlerini görmeye devam ediyordu.

            402 kullanılır, 403 değil: kullanıcı yetkisiz DEĞİL, planı bu
            yeteneği içermiyor. Çıkış yolu farklıdır — biri erişim talebi,
            diğeri plan yükseltmesidir.
        */
        try {
            $this->requireEntitlement->handle($workspace, Entitlement::AnalyticsReporting);
        } catch (EntitlementDeniedException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'entitlement' => $e->entitlement->value,
            ], 402);
        }

        $range = (string) $request->query('range', '30d');

        if (! in_array($range, ['today', '7d', '30d'], true)) {
            return response()->json(['message' => 'Unknown range.'], 422);
        }

        $now = Carbon::now();

        $viewers = $this->analytics->itemViewersByMenuItem($workspace, $range, $now);

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

        /*
            EŞİK KİŞİ SAYAR, SATIR TOPLAMI DEĞİL.

            Burada `array_sum($viewers)` vardı: ürün başına farklı ziyaretçi
            sayılarının toplamı. Menüyü baştan sona kaydıran TEK bir misafir
            beş satır bırakır ve o toplam beşi gösterirdi — rapor açılır,
            sahip de "kırk ürününüz son 30 günde bir kez bile açılmadı"
            önerisini okurdu. Oysa ortada ölçülmüş bir sıfır değil, hemen
            hiç ölçülmemiş bir menü vardı.

            "Henüz ölçmedim" ile "ölçtüm, sıfır çıktı" farklı cümlelerdir ve
            yalnız ikincisi bir öneriyi hak eder. Ekranın `not_enough_data`
            metni de zaten ZİYARETÇİ diliyle yazılı ("{observed}/{threshold}
            ziyaretçi"); sayı artık o cümlenin söylediği şeydir.
        */
        $totalViewers = $this->analytics->itemViewVisitorCount($workspace, $range, $now);

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
            /*
                Sahibin göremediği tek talep: menüde OLMAYAN şeyin talebi.

                Sorgu ARTIK PORTTA. Buradaki kopya, aralığı kendi `match`
                bloğuyla hesaplıyordu: biri güncellenip diğeri unutulduğunda
                ürün raporu ile arama listesi farklı pencerelerden konuşur ve
                bu ekranda "son 30 gün" başlığının altında 7 günlük bir liste
                gibi görünürdü.
            */
            'searchesWithNoResults' => $this->analytics->searchesWithNoResults($workspace, $range, $now),
        ]);
    }
}
