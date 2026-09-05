<?php

declare(strict_types=1);

namespace App\Http\Controllers\MenuCatalog;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\MenuCatalog\Port\MenuCatalogRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MenuCatalog\Support\MenuTreePayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BELİRLİ bir menünün kategorileri ve ürünleri.
 *
 * Menü hapına basıldığında çağrılan yol (`docs/109` §7.1). Bu yol olmadan
 * haplar çizilemezdi: "Kahvaltı"ya basan sahip ekranda hâlâ akşam menüsünü
 * görür ve eklediği ürünün hangi menüye gittiğini bir daha asla bilemezdi
 * — kaynağın kendi hapları tam da bu yüzden hiçbir şey yapmıyordu.
 */
final class ShowMenuTreeController extends Controller
{
    public function __construct(
        private readonly MenuCatalogRepositoryPort $menuCatalog,
        private readonly AuthorizationPort $authorization,
        private readonly MenuTreePayload $payload,
    ) {}

    public function __invoke(Request $request, int $workspace, int $menu): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        // Kiracı sınırı `getDraftTree` içinde de geçerlidir: başka bir
        // çalışma alanının menü kimliği burada `null` döner ve 404 olur.
        $tree = $this->menuCatalog->getDraftTree($workspace, $menu);

        if ($tree === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json($this->payload->build($workspace, $tree));
    }
}
