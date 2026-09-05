<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ordering;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Ordering\Port\OrderQueryPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SİPARİŞ GEÇMİŞİ — `docs/115` S6, Y2 (FF-179).
 *
 * "Silinmez; denetim izi gibi kalıcı." Bu yüzden burada yalnız GET vardır
 * ve bir daha başka bir fiil eklenmemelidir: bu uca bir `DELETE` koymak,
 * bir akşam yanlış giden servisi kaydından silmenin yolunu açardı.
 */
final class ListOrderHistoryController extends Controller
{
    /**
     * Bir sayfada okunan sipariş.
     *
     * Yirmi, sahibin tek bakışta tarayabileceği bir liste; yüz satır kaydırma
     * gerektirir ve kaydırılan bir denetim izi okunmaz.
     */
    private const PER_PAGE = 20;

    public function __construct(
        private readonly OrderQueryPort $orders,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace, int $location): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::OrderView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        // Sayfa numarası kullanıcıdan gelir ve GÜVENİLMEZ; sınır port
        // tarafında da uygulanır, burada yalnız sayıya çevrilir.
        $page = max(1, (int) $request->query('page', '1'));

        return response()->json(
            $this->orders->history($workspace, $location, $page, self::PER_PAGE)->toArray(),
        );
    }
}
