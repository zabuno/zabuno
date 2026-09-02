<?php

declare(strict_types=1);

namespace App\Http\Controllers\MenuCatalog;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tek ekrandan çoklu işaretleme — `docs/82` (P1-04, kriter 3).
 *
 * Akşam servisinde biten şey tek ürün değildir: balıklar biter. Altı ürünü
 * altı istekle işaretlemek, telefonun başında geçen bir dakikadır ve o
 * dakika servisin ortasındadır.
 */
final class UpdateMenuStockController extends Controller
{
    public function __construct(private readonly AuthorizationPort $authorization) {}

    public function __invoke(Request $request, int $workspace, int $menu): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::MenuManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $menuRow = DB::table('menus')->where('id', $menu)->where('workspace_id', $workspace)->first();

        if ($menuRow === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $validated = $request->validate([
            'outOfStock' => ['present', 'array'],
            'outOfStock.*' => ['integer', 'min:1'],
            'inStock' => ['present', 'array'],
            'inStock.*' => ['integer', 'min:1'],
        ]);

        // Kimlikler BU MENÜYE ait olmalı: başka bir menünün satırını buradan
        // işaretlemek, sahibin görmediği bir menüyü sessizce değiştirirdi.
        $ownIds = DB::table('menu_items')
            ->join('menu_categories', 'menu_categories.id', '=', 'menu_items.category_id')
            ->where('menu_categories.menu_id', $menu)
            ->pluck('menu_items.id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $out = array_values(array_intersect(array_map('intval', $validated['outOfStock']), $ownIds));
        $in = array_values(array_intersect(array_map('intval', $validated['inStock']), $ownIds));

        DB::transaction(function () use ($out, $in): void {
            if ($out !== []) {
                DB::table('menu_items')->whereIn('id', $out)
                    ->update(['out_of_stock_since' => now(), 'updated_at' => now()]);
            }

            if ($in !== []) {
                DB::table('menu_items')->whereIn('id', $in)
                    ->update(['out_of_stock_since' => null, 'updated_at' => now()]);
            }
        });

        return response()->json(['markedOutOfStock' => count($out), 'markedInStock' => count($in)]);
    }
}
