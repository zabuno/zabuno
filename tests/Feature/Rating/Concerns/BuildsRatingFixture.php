<?php

declare(strict_types=1);

namespace Tests\Feature\Rating\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Puanlama yüzeylerinin ortak sahnesi — `docs/116` P4/P5/P6.
 *
 * ÜÇ TEST DE AYNI SAHNEYİ KURAR ve bu zorunlu: misafirin oy verdiği ürün,
 * menüde puanı gösterilen ürün ve sahibin yanıt yazdığı ürün AYNI üründür.
 * Üçüne ayrı kurgu yazsaydık, birinde `product_id` bağı, ötekinde masa bağı
 * eksik kalır ve üç yüzey aynı ürünü farklı şeyler sanardı.
 *
 * Fikstür ELLE `DB::table` yazar (sipariş fikstürüyle aynı gerekçe): puan
 * yazma yolu ürünün kendi kodudur ve test kendi yazma yolunu kurarsa,
 * sınadığı şey ürünün davranışı değil kendi kurgusu olur.
 */
trait BuildsRatingFixture
{
    /**
     * Masaya bağlı bir karekodun arkasındaki tam sahne.
     *
     * @param  list<string>  $productNames
     * @return array{
     *     owner:User, ownerId:int, workspaceId:int, locationId:int, menuId:int,
     *     tableId:int, qrCodeId:int, token:string,
     *     products:array<string, int>, menuItems:array<string, int>
     * }
     */
    private function ratingScene(string $seed, array $productNames = ['Kahve'], string $ownerRole = 'owner'): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);

        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Restoran '.$seed,
            'slug' => $seed,
            'state' => 'active',
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $owner->id,
            'role' => $ownerRole,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspaceId,
            'name' => 'Marka '.$seed,
            'slug' => $seed.'-brand',
            'locale' => 'tr',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId,
            'brand_id' => $brandId,
            'display_name' => 'Şube '.$seed,
            'country_code' => 'TR',
            'timezone' => 'Europe/Istanbul',
            'city' => 'İstanbul',
            'address_line1' => 'Adres '.$seed,
            'accepts_orders' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $menuId = (int) DB::table('menus')->insertGetId([
            'public_key' => Str::lower(Str::random(10)),
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'name' => 'Ana Menü',
            'state' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $categoryId = (int) DB::table('menu_categories')->insertGetId([
            'menu_id' => $menuId,
            'name' => 'Sıcak İçecek',
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $products = [];
        $menuItems = [];
        $snapshotItems = [];
        $position = 0;

        foreach ($productNames as $productName) {
            $productId = (int) DB::table('products')->insertGetId([
                'workspace_id' => $workspaceId,
                'name' => $productName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $menuItemId = (int) DB::table('menu_items')->insertGetId([
                'category_id' => $categoryId,
                'product_id' => $productId,
                'price_minor_amount' => 4250 + $position,
                'currency_code' => 'TRY',
                'position' => $position,
                'is_visible' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $products[$productName] = $productId;
            $menuItems[$productName] = $menuItemId;

            $snapshotItems[] = [
                'menuItemId' => $menuItemId,
                'productName' => $productName,
                'priceMinorAmount' => 4250 + $position,
                'currencyCode' => 'TRY',
                'allergens' => [],
            ];

            $position++;
        }

        $publicationId = (int) DB::table('menu_publications')->insertGetId([
            'workspace_id' => $workspaceId,
            'menu_id' => $menuId,
            'location_id' => $locationId,
            'version' => 1,
            'state' => 'published',
            'snapshot' => json_encode([
                'categories' => [[
                    'name' => 'Sıcak İçecek',
                    'menuItems' => $snapshotItems,
                ]],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'published_by' => $owner->id,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menu_publication_current_pointers')->insert([
            'menu_id' => $menuId,
            'current_publication_id' => $publicationId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $areaId = (int) DB::table('dining_areas')->insertGetId([
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'label' => 'Salon',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tableId = (int) DB::table('dining_tables')->insertGetId([
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'area_id' => $areaId,
            'name' => 'Masa 12',
            'seat_count' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token = Str::random(43);

        $qrCodeId = (int) DB::table('qr_codes')->insertGetId([
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'dining_table_id' => $tableId,
            'token' => $token,
            'state' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $destinationId = (int) DB::table('qr_destinations')->insertGetId([
            'qr_code_id' => $qrCodeId,
            'destination_type' => 'published_menu',
            'menu_id' => $menuId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('qr_code_current_destinations')->insert([
            'qr_code_id' => $qrCodeId,
            'qr_destination_id' => $destinationId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'owner' => $owner,
            'ownerId' => (int) $owner->id,
            'workspaceId' => $workspaceId,
            'locationId' => $locationId,
            'menuId' => $menuId,
            'tableId' => $tableId,
            'qrCodeId' => $qrCodeId,
            'token' => $token,
            'products' => $products,
            'menuItems' => $menuItems,
        ];
    }

    /**
     * Türetilmiş bir puan satırı — `rating:recompute` çıktısının yerine.
     *
     * Yeniden hesaplamanın kendisi P3'ün sınadığı yüzeydir; burada sınanan
     * şey EKRANIN o satırı nasıl okuduğudur. Komutu çağırsaydık, gösterim
     * testi hesaplama testinin kopyası olurdu.
     */
    private function storeRatingScore(
        int $workspaceId,
        int $subjectId,
        float $score,
        int $signalCount,
        float $totalWeight,
        bool $meetsThreshold,
        int $algorithmVersion = 1,
        int $scaleMax = 5,
    ): void {
        DB::table('rating_scores')->insert([
            'workspace_id' => $workspaceId,
            'subject_type' => 'product',
            'subject_id' => $subjectId,
            'algorithm_version' => $algorithmVersion,
            'score_value' => $score,
            'score_scale_max' => $scaleMax,
            'signal_count' => $signalCount,
            'total_weight' => $totalWeight,
            'meets_display_threshold' => $meetsThreshold,
            'computed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
