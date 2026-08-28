<?php

declare(strict_types=1);

namespace Tests\Feature\MenuCatalog;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P0-05 (CSV) + P0-09 RED — menüyü almak ve geri koymak (`docs/80`).
 *
 * MÜŞTERİ SORUNU — İKİ YÖNLÜ.
 *
 * İÇERİ. Restoranın menüsü zaten var: basılı, PDF ya da Excel. Bugün Zabuno
 * onu TEK TEK elle yeniden yazdırıyor; 60 kalemlik bir menü, 60 ayrı form
 * gönderimi. Bu pilotta ekibin saatini, self-service'te müşteriyi yakar.
 *
 * DIŞARI. Sahip "menümü alıp gidebilir miyim?" diye sorar. Bugünkü cevap
 * hayır. Pilot restoranın kilitlenme korkusunu kaldıran şey budur — ve
 * KVKK/GDPR kapsamında bir haktır.
 *
 * Requirement IDs: MENU-EXPORT-CSV-01, MENU-EXPORT-ISOLATION-01,
 * MENU-EXPORT-FORMULA-SAFE-01, MENU-IMPORT-BULK-01,
 * MENU-IMPORT-PARTIAL-REPORT-01, MENU-IMPORT-NO-PUBLISH-01,
 * MENU-CSV-ROUNDTRIP-01, MENU-CSV-AUTHZ-01.
 */
final class MenuCsvRoundTripTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:int,2:int} [owner, workspaceId, menuId] */
    private function workspaceWithMenu(string $seed): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);

        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => $seed, 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId, 'user_id' => $owner->id, 'role' => 'owner',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspaceId, 'name' => 'Zeytin', 'slug' => $seed.'-b',
            'locale' => 'tr', 'timezone' => 'Europe/Istanbul', 'currency' => 'TRY',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId, 'brand_id' => $brandId,
            'display_name' => 'Kadıköy', 'country_code' => 'TR',
            'timezone' => 'Europe/Istanbul', 'city' => 'İstanbul',
            'address_line1' => 'Bahariye Cd. No:1',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $menuId = (int) DB::table('menus')->insertGetId([
            'public_key' => Str::lower(Str::random(10)), 'workspace_id' => $workspaceId,
            'location_id' => $locationId, 'name' => 'Ana Menü', 'state' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$owner, $workspaceId, $menuId];
    }

    private function seedItem(
        int $workspaceId,
        int $menuId,
        string $category,
        string $product,
        int $priceMinor,
        ?string $description = null,
    ): void {
        $categoryId = DB::table('menu_categories')->where('menu_id', $menuId)->where('name', $category)->value('id');

        if ($categoryId === null) {
            $categoryId = DB::table('menu_categories')->insertGetId([
                'menu_id' => $menuId, 'name' => $category,
                'position' => (int) DB::table('menu_categories')->where('menu_id', $menuId)->count(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $productId = (int) DB::table('products')->insertGetId([
            'workspace_id' => $workspaceId, 'name' => $product, 'description' => $description,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('menu_items')->insert([
            'category_id' => (int) $categoryId, 'product_id' => $productId,
            'price_minor_amount' => $priceMinor, 'currency_code' => 'TRY',
            'position' => (int) DB::table('menu_items')->where('category_id', $categoryId)->count(),
            'is_visible' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function exportCsv(User $owner, int $workspaceId, int $menuId): string
    {
        $response = $this->actingAs($owner)
            ->get("/api/workspaces/{$workspaceId}/menu/{$menuId}/export.csv");

        $response->assertOk();

        return $response->getContent();
    }

    // --- MENU-EXPORT-CSV-01 -----------------------------------------------

    public function test_the_owner_can_take_the_menu_with_them(): void
    {
        [$owner, $workspaceId, $menuId] = $this->workspaceWithMenu('csv-export');
        $this->seedItem($workspaceId, $menuId, 'Çorbalar', 'Mercimek', 5250, 'Ev yapımı.');
        $this->seedItem($workspaceId, $menuId, 'Kebaplar', 'Adana', 38000);

        $csv = $this->exportCsv($owner, $workspaceId, $menuId);

        $rows = array_map('str_getcsv', array_filter(explode("\n", trim($csv))));

        self::assertSame(
            ['category', 'product', 'price', 'currency', 'allergens', 'description', 'visible'],
            $rows[0],
            'MENU-EXPORT-CSV-01: başlık satırı sabittir; sahip dosyayı Excel\'de açıp anlayabilmeli.'
        );

        self::assertCount(3, $rows, 'İki ürün ve bir başlık satırı.');
        self::assertSame(['Çorbalar', 'Mercimek', '52.50', 'TRY', '', 'Ev yapımı.', 'yes'], $rows[1]);
        self::assertSame(['Kebaplar', 'Adana', '380.00', 'TRY', '', '', 'yes'], $rows[2]);
    }

    // --- MENU-EXPORT-ISOLATION-01 -----------------------------------------

    public function test_the_export_carries_no_other_restaurants_row(): void
    {
        [$owner, $workspaceId, $menuId] = $this->workspaceWithMenu('csv-mine');
        $this->seedItem($workspaceId, $menuId, 'Çorbalar', 'Mercimek', 5250);

        [, $otherWorkspaceId, $otherMenuId] = $this->workspaceWithMenu('csv-theirs');
        $this->seedItem($otherWorkspaceId, $otherMenuId, 'Gizli', 'Komşunun Kebabı', 9900);

        $csv = $this->exportCsv($owner, $workspaceId, $menuId);

        self::assertStringContainsString('Mercimek', $csv);
        self::assertStringNotContainsString(
            'Komşunun Kebabı',
            $csv,
            'MENU-EXPORT-ISOLATION-01: ikinci bir kiracının tek satırı bile sızmamalı.'
        );

        // Başkasının menüsünü indirmeye çalışmak da bulunamaz.
        $this->actingAs($owner)
            ->get("/api/workspaces/{$otherWorkspaceId}/menu/{$otherMenuId}/export.csv")
            ->assertNotFound();
    }

    // --- MENU-EXPORT-FORMULA-SAFE-01 --------------------------------------

    public function test_a_product_name_cannot_become_a_spreadsheet_formula(): void
    {
        [$owner, $workspaceId, $menuId] = $this->workspaceWithMenu('csv-formula');

        // Excel, `=` ile başlayan bir hücreyi FORMÜL olarak çalıştırır.
        // Menüsünü indiren sahibin makinesinde komut çalıştırmak, bizim
        // ürettiğimiz bir dosyayla olmamalı.
        $this->seedItem($workspaceId, $menuId, 'Çorbalar', '=1+1', 1000);
        $this->seedItem($workspaceId, $menuId, 'Çorbalar', '@SUM(A1)', 1000);
        $this->seedItem($workspaceId, $menuId, 'Çorbalar', '+HYPERLINK("x")', 1000);

        $csv = $this->exportCsv($owner, $workspaceId, $menuId);

        foreach (array_slice(array_map('str_getcsv', array_filter(explode("\n", trim($csv)))), 1) as $row) {
            self::assertStringStartsWith(
                "'",
                $row[1],
                'MENU-EXPORT-FORMULA-SAFE-01: formül gibi başlayan hücre nötrlenmeli.'
            );
        }
    }

    // --- MENU-IMPORT-BULK-01 / MENU-IMPORT-PARTIAL-REPORT-01 ---------------

    public function test_sixty_items_arrive_in_one_go_and_bad_rows_are_named(): void
    {
        [$owner, $workspaceId, $menuId] = $this->workspaceWithMenu('csv-import');

        $lines = ['category,product,price,currency,allergens,description,visible'];

        for ($i = 1; $i <= 60; $i++) {
            $lines[] = "Kebaplar,Ürün {$i},{$i}0.00,TRY,süt;gluten,Açıklama {$i},yes";
        }

        // İki bozuk satır: biri fiyatsız, biri ürünsüz. Geçerli 60 satır
        // BUNLAR yüzünden kaybolmamalı — 60 kalemi yeniden yazmak, sahibin
        // en başta kaçtığı iştir.
        $lines[] = 'Kebaplar,Fiyatsız Ürün,,TRY,,,yes';
        $lines[] = 'Kebaplar,,120.00,TRY,,,yes';

        $response = $this->actingAs($owner)->post(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/import",
            ['file' => UploadedFile::fake()->createWithContent('menu.csv', implode("\n", $lines))]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('importedItems', 60);
        $response->assertJsonPath('importedCategories', 1);

        $errors = $response->json('rejectedRows');
        self::assertCount(2, $errors, 'MENU-IMPORT-PARTIAL-REPORT-01: iki satır reddedilmeli.');
        // Satır numarası DOSYADAKİ numaradır: başlık 1. satırdır ve sahip
        // hatayı kendi dosyasında bulabilmeli.
        self::assertSame(62, $errors[0]['line']);
        self::assertNotEmpty($errors[0]['reason']);
        self::assertSame(63, $errors[1]['line']);

        self::assertSame(60, DB::table('menu_items')
            ->join('menu_categories', 'menu_categories.id', '=', 'menu_items.category_id')
            ->where('menu_categories.menu_id', $menuId)->count());
    }

    // --- MENU-IMPORT-NO-PUBLISH-01 ----------------------------------------

    public function test_an_import_never_touches_what_the_guest_sees(): void
    {
        [$owner, $workspaceId, $menuId] = $this->workspaceWithMenu('csv-no-publish');
        $this->seedItem($workspaceId, $menuId, 'Çorbalar', 'Mercimek', 5250);

        $before = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->postJson("/api/workspaces/{$workspaceId}/menu/{$menuId}/publications")->json('snapshot');

        $this->actingAs($owner)->post(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/import",
            ['file' => UploadedFile::fake()->createWithContent(
                'menu.csv',
                "category,product,price,currency,allergens,description,visible\nKebaplar,Adana,380.00,TRY,,,yes"
            )]
        )->assertStatus(200);

        $after = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/current")->json('snapshot');

        self::assertSame(
            $before,
            $after,
            'MENU-IMPORT-NO-PUBLISH-01: aktarım TASLAĞA yazar; misafirin gördüğü, sahip yayınlayana kadar değişmez.'
        );
    }

    // --- MENU-CSV-ROUNDTRIP-01 --------------------------------------------

    public function test_what_comes_out_can_go_back_in(): void
    {
        [$owner, $workspaceId, $menuId] = $this->workspaceWithMenu('csv-roundtrip');
        $this->seedItem($workspaceId, $menuId, 'Çorbalar', 'Mercimek Çorbası', 5250, 'Ev yapımı, günlük.');
        $this->seedItem($workspaceId, $menuId, 'Kebaplar', 'Adana Kebap', 38000);

        $csv = $this->exportCsv($owner, $workspaceId, $menuId);

        // İkinci bir menüye AYNI dosya yüklenir.
        [$otherOwner, $otherWorkspaceId, $otherMenuId] = $this->workspaceWithMenu('csv-roundtrip-2');

        $this->actingAs($otherOwner)->post(
            "/api/workspaces/{$otherWorkspaceId}/menu/{$otherMenuId}/import",
            ['file' => UploadedFile::fake()->createWithContent('menu.csv', $csv)]
        )->assertStatus(200);

        $reexported = $this->exportCsv($otherOwner, $otherWorkspaceId, $otherMenuId);

        self::assertSame(
            trim($csv),
            trim($reexported),
            'MENU-CSV-ROUNDTRIP-01: dışa aktarılan dosya geri yüklenince aynı menüyü vermeli — '
            .'aksi hâlde "menümü alıp gidebilirim" bir söz değil, bir slogan olurdu.'
        );
    }

    // --- MENU-CSV-AUTHZ-01 ------------------------------------------------

    public function test_a_read_only_member_can_export_but_cannot_import(): void
    {
        [, $workspaceId, $menuId] = $this->workspaceWithMenu('csv-authz');
        $member = User::factory()->create(['email_verified_at' => now()]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId, 'user_id' => $member->id, 'role' => 'member',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($member)
            ->get("/api/workspaces/{$workspaceId}/menu/{$menuId}/export.csv")
            ->assertOk();

        $this->actingAs($member)->post(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/import",
            ['file' => UploadedFile::fake()->createWithContent('menu.csv', "category,product,price,currency,allergens,description,visible\nA,B,1.00,TRY,,,yes")]
        )->assertStatus(403);
    }
}
