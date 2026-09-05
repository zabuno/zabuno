<?php

declare(strict_types=1);

namespace App\Http\Controllers\MenuCatalog\Support;

use App\Application\Media\Port\MenuMediaPort;
use App\Application\MenuCatalog\Dto\MenuDraftTree;
use App\Domain\MenuCatalog\StockState;
use Illuminate\Support\Facades\DB;

/**
 * Menü ağacının JSON gövdesi — TEK yerde.
 *
 * Çoklu menüyle birlikte aynı gövdeyi iki yol döndürüyor: şubenin şu an
 * servis ettiği menü (`.../locations/{location}/menu`) ve haplardan seçilen
 * belirli bir menü (`.../menu/{menu}`). İki yol iki kopya olsaydı, alan
 * eklendiğinde birinde çıkar ötekinde çıkmazdı ve arayüz hangi menüyü
 * açtığına göre farklı davranırdı.
 */
final class MenuTreePayload
{
    public function __construct(private readonly MenuMediaPort $menuMedia) {}

    /** @return array<string, mixed> */
    public function build(int $workspaceId, MenuDraftTree $tree): array
    {
        // Bağlı görseller TEK sorguda okunur: satır başına sorgu, kırk
        // ürünlük bir menüde kırk gidiş dönüş demekti (`docs/78`).
        // "Bugün" ŞUBENİN saat diliminde bir gündür.
        $timezone = (string) (DB::table('locations')->where('id', $tree->locationId)->value('timezone') ?: 'UTC');

        $menuItemIds = [];

        foreach ($tree->categories as $category) {
            foreach ($category['items'] as $item) {
                $menuItemIds[] = (int) $item['id'];
            }
        }

        $attached = $this->menuMedia->attachedAssetIds($workspaceId, $menuItemIds);

        return [
            'id' => $tree->id,
            'workspaceId' => $tree->workspaceId,
            'locationId' => $tree->locationId,
            'name' => $tree->name,
            'state' => $tree->state,
            'categories' => array_map(static fn (array $category): array => [
                'id' => $category['id'],
                'menuId' => $tree->id,
                'name' => $category['name'],
                'position' => $category['position'],
                'menuItems' => array_map(static fn (array $item): array => [
                    'id' => $item['id'],
                    'categoryId' => $category['id'],
                    'productId' => $item['productId'],
                    'productName' => $item['productName'],
                    'priceMinorAmount' => $item['priceMinorAmount'],
                    'currencyCode' => $item['currencyCode'],
                    'position' => $item['position'],
                    'isVisible' => $item['isVisible'],
                    'allergens' => $item['allergens'],
                    'description' => $item['description'] ?? null,
                    'imageMediaAssetId' => $attached[$item['id']] ?? null,
                    // "Bugün tükendi" GÖRÜNÜRLÜKTEN ayrı bir eksendir
                    // (`docs/82`); panel ikisini karıştırmamalı.
                    'outOfStock' => StockState::isOutOfStockNow(
                        $item['outOfStockSince'] ?? null,
                        $timezone,
                    ),
                ], $category['items']),
            ], $tree->categories),
        ];
    }
}
