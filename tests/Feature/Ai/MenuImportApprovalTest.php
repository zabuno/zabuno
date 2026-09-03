<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Application\Media\Dto\MediaScanResult;
use App\Application\Media\Dto\MediaScanVerdict;
use App\Application\Media\Port\MalwareScannerPort;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P0-05 (foto yolu) RED — insan onaylı aktarım (`docs/92`).
 *
 * MÜŞTERİ SORUNU. Restoranın menüsü basılı ya da PDF. Sahip onu fotoğraflar;
 * makinenin okuduğu şey DOĞRUDAN menüye girmemeli — bir fiyatı yanlış okuyan
 * bir model, misafirin gördüğü menüye yanlış fiyat yazardı.
 *
 * BU PAKET SAĞLAYICI GEREKTİRMEZ. Sağlayıcı adaptörü ayrı ve anahtar
 * olmadan GERÇEK API'ye karşı doğrulanamaz. Burada kurulan şey onay hattı:
 * artifact taslakta durur, insan inceler, onaylanınca TASLAĞA yazılır ve
 * yayına hâlâ dokunulmaz.
 *
 * Requirement IDs: AI-IMPORT-ARTIFACT-UNAPPLIED-01,
 * AI-IMPORT-REVIEW-SHOWS-SOURCE-01, AI-IMPORT-APPLY-ONCE-01,
 * AI-IMPORT-NEVER-TOUCHES-PUBLICATION-01, AI-IMPORT-OFF-IS-HONEST-01,
 * AI-IMPORT-TENANT-01.
 */
final class MenuImportApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private int $workspaceId;

    private int $menuId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['ai.enabled' => true]);

        /*
            Yetenek adı NOKTA içeriyor (`menu.extract`) ve `config()` noktayı
            iç içe anahtar sanıyor. Nokta hem OKUMADA hem YAZMADA tuzak:
            `config(['ai.capabilities.menu.extract.candidates' => ...])`
            çağrısı düz anahtarı değil, iç içe bir yapı kurardı ve bu test
            hiçbir zaman gerçek yolu çalıştırmazdı (`docs/92`).
        */
        config(['ai.capabilities' => [
            'menu.extract' => ['candidates' => ['fake'], 'confidence_threshold' => 0.90],
        ]]);

        /*
            AYLIK BÜTÇE SIFIRSA YETENEK KAPALIDIR — ve bu varsayılan.

            Tavansız harcamayı varsayılan yapmak, bir betiğin faturayı
            uçurmasına açık kapı bırakırdı. Anahtar girilse bile bütçe
            konmadan hiçbir çağrı gitmez; test onu açıkça kurar.
        */
        config(['ai.budget.monthly_minor_per_tenant' => 100000]);

        $this->app->instance(MalwareScannerPort::class, new class implements MalwareScannerPort
        {
            public function scan(string $diskPath): MediaScanResult
            {
                return new MediaScanResult(MediaScanVerdict::Clean);
            }
        });

        $this->owner = User::factory()->create(['email_verified_at' => now()]);

        $this->workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => 'ai-'.Str::lower(Str::random(6)), 'state' => 'active',
            'created_by' => $this->owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $this->workspaceId, 'user_id' => $this->owner->id,
            'role' => 'owner', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $this->workspaceId, 'name' => 'Zeytin',
            'slug' => 'ai-b-'.Str::lower(Str::random(6)), 'locale' => 'tr',
            'timezone' => 'Europe/Istanbul', 'currency' => 'TRY',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $this->workspaceId, 'brand_id' => $brandId,
            'display_name' => 'Kadıköy', 'country_code' => 'TR',
            'timezone' => 'Europe/Istanbul', 'city' => 'İstanbul',
            'address_line1' => 'Bahariye Cd. No:1',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->menuId = (int) DB::table('menus')->insertGetId([
            'public_key' => Str::lower(Str::random(10)), 'workspace_id' => $this->workspaceId,
            'location_id' => $locationId, 'name' => 'Ana Menü', 'state' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function api(?User $user = null)
    {
        return $this->actingAs($user ?? $this->owner)->withHeaders(['Accept' => 'application/json']);
    }

    private function uploadedMenuPhoto(): int
    {
        return (int) $this->api()->post("/api/workspaces/{$this->workspaceId}/media", [
            'file' => UploadedFile::fake()->image('menu.jpg', 1200, 1600),
            'altText' => 'Basılı menünün fotoğrafı',
            'slot' => 'itemImage',
        ])->json('id');
    }

    private function startImport(): array
    {
        $response = $this->api()->postJson(
            "/api/workspaces/{$this->workspaceId}/menu/{$this->menuId}/ai-imports",
            ['mediaAssetId' => $this->uploadedMenuPhoto()],
        );

        // Hata durumunda gövdeyi GÖRELİM: "null is not int" bir teşhis
        // değildir.
        $response->assertStatus(201, 'ai-imports yanıtı: '.$response->getContent());

        return (array) $response->json();
    }

    // --- AI-IMPORT-ARTIFACT-UNAPPLIED-01 ----------------------------------

    public function test_the_machine_reading_lands_in_a_draft_nobody_approved_yet(): void
    {
        $artifact = $this->startImport();

        self::assertIsInt($artifact['id'] ?? null);

        $row = DB::table('ai_artifacts')->where('id', $artifact['id'])->first();

        self::assertNotNull($row);
        self::assertNull(
            $row->applied_at,
            'AI-IMPORT-ARTIFACT-UNAPPLIED-01: makinenin okuduğu şey doğrudan menüye giremez.'
        );
        self::assertNotEmpty($row->idempotency_key);
        self::assertSame('menu.extract', (string) $row->capability);

        // Menü HÂLÂ boş: okumak yazmak değildir.
        self::assertSame(0, DB::table('menu_categories')->where('menu_id', $this->menuId)->count());
    }

    // --- AI-IMPORT-REVIEW-SHOWS-SOURCE-01 ---------------------------------

    public function test_the_review_screen_shows_where_each_field_came_from(): void
    {
        $artifact = $this->startImport();

        $review = $this->api()->getJson(
            "/api/workspaces/{$this->workspaceId}/ai-imports/{$artifact['id']}"
        );

        $review->assertOk();

        // Model kimliği ve prompt sürümü olmadan "bu nereden geldi" sorusu
        // cevapsız kalır.
        self::assertNotEmpty($review->json('modelIdentity'));
        self::assertNotEmpty($review->json('promptVersion'));

        $fields = $review->json('fields');
        self::assertNotEmpty($fields);

        foreach ($fields as $field) {
            self::assertArrayHasKey('name', $field);
            self::assertArrayHasKey('value', $field);
            // BELİRSİZ alanlar işaretli: inceleyen kişi nereye bakacağını
            // bilmeli, 60 satırı tek tek okumak zorunda kalmamalı.
            self::assertArrayHasKey('uncertain', $field);
            self::assertArrayHasKey('confidence', $field);
        }

        self::assertIsInt($review->json('uncertainFieldCount'));
    }

    // --- AI-IMPORT-APPLY-ONCE-01 ------------------------------------------

    public function test_approval_writes_the_draft_and_a_second_approval_changes_nothing(): void
    {
        $artifact = $this->startImport();

        $first = $this->api()->postJson(
            "/api/workspaces/{$this->workspaceId}/ai-imports/{$artifact['id']}/apply"
        );

        $first->assertOk();
        self::assertGreaterThan(0, (int) $first->json('importedItems'));

        $itemsAfterFirst = DB::table('menu_items')
            ->join('menu_categories', 'menu_categories.id', '=', 'menu_items.category_id')
            ->where('menu_categories.menu_id', $this->menuId)->count();

        self::assertGreaterThan(0, $itemsAfterFirst);
        self::assertNotNull(DB::table('ai_artifacts')->where('id', $artifact['id'])->value('applied_at'));

        /*
            İKİNCİ ONAY HİÇBİR ŞEY YAPMAZ.

            Ekran tazelenir, düğmeye ikinci kez basılır ya da istek tekrar
            gönderilir — menü iki katına çıkmamalı. `applied_at` bu sorunun
            cevabıdır.
        */
        $second = $this->api()->postJson(
            "/api/workspaces/{$this->workspaceId}/ai-imports/{$artifact['id']}/apply"
        );

        $second->assertOk();
        self::assertSame(0, (int) $second->json('importedItems'));

        self::assertSame(
            $itemsAfterFirst,
            DB::table('menu_items')
                ->join('menu_categories', 'menu_categories.id', '=', 'menu_items.category_id')
                ->where('menu_categories.menu_id', $this->menuId)->count(),
            'AI-IMPORT-APPLY-ONCE-01: aynı artifact iki kez uygulanmamalı.'
        );
    }

    // --- AI-IMPORT-NEVER-TOUCHES-PUBLICATION-01 ---------------------------

    public function test_nothing_the_machine_read_reaches_a_guest_before_the_owner_publishes(): void
    {
        // Önce elle bir ürün ve bir yayın: karşılaştıracak bir şey olmalı.
        $categoryId = (int) DB::table('menu_categories')->insertGetId([
            'menu_id' => $this->menuId, 'name' => 'Çorbalar', 'position' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $productId = (int) DB::table('products')->insertGetId([
            'workspace_id' => $this->workspaceId, 'name' => 'Mercimek',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('menu_items')->insert([
            'category_id' => $categoryId, 'product_id' => $productId,
            'price_minor_amount' => 5250, 'currency_code' => 'TRY', 'position' => 0,
            'is_visible' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $published = $this->api()->postJson(
            "/api/workspaces/{$this->workspaceId}/menu/{$this->menuId}/publications"
        )->json('snapshot');

        $artifact = $this->startImport();
        $this->api()->postJson(
            "/api/workspaces/{$this->workspaceId}/ai-imports/{$artifact['id']}/apply"
        )->assertOk();

        $current = $this->api()->getJson(
            "/api/workspaces/{$this->workspaceId}/menu/{$this->menuId}/publications/current"
        )->json('snapshot');

        self::assertSame(
            $published,
            $current,
            'AI-IMPORT-NEVER-TOUCHES-PUBLICATION-01: onaylanan artifact TASLAĞA yazar; misafirin gördüğü değişmez.'
        );
    }

    // --- AI-IMPORT-OFF-IS-HONEST-01 ---------------------------------------

    public function test_with_ai_switched_off_the_product_says_so_instead_of_failing(): void
    {
        config(['ai.enabled' => false]);

        $response = $this->api()->postJson(
            "/api/workspaces/{$this->workspaceId}/menu/{$this->menuId}/ai-imports",
            ['mediaAssetId' => $this->uploadedMenuPhoto()],
        );

        // 503: istek doğru, YETENEK yok. 500 vermek sahibi "ürün bozuldu"
        // sanmaya iterdi; sessizce boş dönmek daha kötü olurdu.
        $response->assertStatus(503);
        self::assertNotEmpty($response->json('message'));
        self::assertSame('kill_switch', $response->json('reason'));

        self::assertSame(0, DB::table('ai_artifacts')->count());
    }

    // --- AI-IMPORT-TENANT-01 ----------------------------------------------

    public function test_another_restaurants_artifact_cannot_be_read_or_applied(): void
    {
        $artifact = $this->startImport();

        $stranger = User::factory()->create(['email_verified_at' => now()]);
        $otherWorkspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Komşu', 'slug' => 'ai-other-'.Str::lower(Str::random(6)), 'state' => 'active',
            'created_by' => $stranger->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $otherWorkspaceId, 'user_id' => $stranger->id,
            'role' => 'owner', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->api($stranger)
            ->getJson("/api/workspaces/{$otherWorkspaceId}/ai-imports/{$artifact['id']}")
            ->assertNotFound();

        $this->api($stranger)
            ->postJson("/api/workspaces/{$otherWorkspaceId}/ai-imports/{$artifact['id']}/apply")
            ->assertNotFound();

        self::assertNull(DB::table('ai_artifacts')->where('id', $artifact['id'])->value('applied_at'));
    }
}
