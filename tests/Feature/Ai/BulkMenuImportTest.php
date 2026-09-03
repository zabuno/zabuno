<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Application\Ai\Exception\ProviderCallException;
use App\Application\Ai\Port\AiRequest;
use App\Application\Ai\Port\VisionExtractionPort;
use App\Application\Media\Dto\MediaScanResult;
use App\Application\Media\Dto\MediaScanVerdict;
use App\Application\Media\Port\MalwareScannerPort;
use App\Domain\Ai\AiArtifact;
use App\Domain\Ai\FieldValue;
use App\Domain\Ai\ModelDeployment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TOPLU FOTOĞRAF İÇE AKTARMA — `docs/96` Faz 3.
 *
 * Gerçek durum: bir restoranın menüsü tek bir fotoğrafa sığmaz. Dört
 * sayfalık bir menüyü tek tek okutmak, sahibin aynı işi dört kez yapması
 * demekti — her seferinde inceleme ekranını kapatıp yeniden açarak.
 *
 * En önemli davranış KISMİ BAŞARISIZLIK: dördüncü fotoğraf bulanıksa ilk
 * üçün sonucu çöpe gitmez.
 */
final class BulkMenuImportTest extends TestCase
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
        config(['ai.capabilities' => [
            'menu.extract' => ['candidates' => ['fake'], 'confidence_threshold' => 0.90],
        ]]);
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
            'name' => 'Zeytin', 'slug' => 'blk-'.Str::lower(Str::random(6)), 'state' => 'active',
            'created_by' => $this->owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $this->workspaceId, 'user_id' => $this->owner->id,
            'role' => 'owner', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $this->workspaceId, 'name' => 'Zeytin',
            'slug' => 'blk-b-'.Str::lower(Str::random(6)), 'locale' => 'tr',
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

    private function photo(string $name): int
    {
        return (int) $this->api()->post("/api/workspaces/{$this->workspaceId}/media", [
            'file' => UploadedFile::fake()->image($name, 1200, 1600),
            'altText' => 'Basılı menünün fotoğrafı',
            'slot' => 'menuImportSource',
        ])->json('id');
    }

    private function readBatch(array $mediaAssetIds)
    {
        return $this->api()->postJson(
            "/api/workspaces/{$this->workspaceId}/menu/{$this->menuId}/ai-imports/batch",
            ['mediaAssetIds' => $mediaAssetIds],
        );
    }

    // --- BULK-READS-EVERY-PHOTO-01 ----------------------------------------

    #[Test]
    public function every_photo_becomes_its_own_unapplied_draft(): void
    {
        $ids = [$this->photo('page1.jpg'), $this->photo('page2.jpg'), $this->photo('page3.jpg')];

        $response = $this->readBatch($ids);
        $response->assertStatus(201, 'batch yanıtı: '.$response->getContent());

        $results = (array) $response->json('results');
        self::assertCount(3, $results);

        foreach ($results as $result) {
            self::assertIsInt($result['id'] ?? null);
            self::assertArrayNotHasKey('error', $result);
        }

        // Üç ayrı taslak, hiçbiri uygulanmamış: okumak yazmak değildir.
        self::assertSame(3, DB::table('ai_artifacts')->whereNull('applied_at')->count());
        self::assertSame(0, DB::table('menu_categories')->where('menu_id', $this->menuId)->count());
    }

    // --- BULK-PARTIAL-FAILURE-01 ------------------------------------------

    /**
     * BİR FOTOĞRAF OKUNAMAZSA DİĞERLERİ ÇÖPE GİTMEZ.
     *
     * Tümünü reddetmek, sahibi hiçbir şey kazanmadan baştan başlatırdı —
     * ve zaten ödenmiş üç çağrıyı da boşa harcardı.
     */
    #[Test]
    public function one_unreadable_photo_does_not_discard_the_others(): void
    {
        $good = $this->photo('good.jpg');
        $bad = $this->photo('bad.jpg');

        /*
            Sahte üretici İKİNCİ çağrıda düşer. Dosya adına bakmak
            işlemezdi: depolama yolu rastgele üretiliyor ve testin
            "hangi fotoğraf" varsayımı sessizce yanlış olurdu.
        */
        $this->app->instance(VisionExtractionPort::class, new class implements VisionExtractionPort
        {
            private int $calls = 0;

            public function extract(AiRequest $request, array $filePaths): AiArtifact
            {
                $this->calls++;

                if ($this->calls === 2) {
                    throw new ProviderCallException('fake', 'unparseable');
                }

                return new AiArtifact(
                    capability: $request->capability,
                    model: new ModelDeployment('fake', 'platform', 'fake-vision'),
                    promptVersion: 'fake.v1',
                    schemaVersion: $request->capability->schemaVersion(),
                    fields: [
                        new FieldValue('row.1', [
                            'category' => 'Çorbalar',
                            'product' => 'Mercimek',
                            'priceMinorAmount' => 5000,
                            'currencyCode' => 'TRY',
                        ], 0.95, false),
                    ],
                );
            }
        });

        $response = $this->readBatch([$good, $bad]);
        $response->assertStatus(201);

        $results = (array) $response->json('results');

        self::assertCount(2, $results);
        self::assertIsInt($results[0]['id'] ?? null, 'İlk fotoğraf okunmalıydı.');
        self::assertSame('unparseable', $results[1]['error'] ?? null);

        // Okunabilen taslak KAYDEDİLDİ.
        self::assertSame(1, DB::table('ai_artifacts')->count());
    }

    #[Test]
    public function a_photo_from_another_workspace_is_rejected_row_by_row_not_as_a_whole(): void
    {
        $mine = $this->photo('mine.jpg');

        $stranger = User::factory()->create(['email_verified_at' => now()]);
        $otherWorkspace = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Komşu', 'slug' => 'blk-n-'.Str::lower(Str::random(6)), 'state' => 'active',
            'created_by' => $stranger->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $theirs = (int) DB::table('media_assets')->insertGetId([
            'workspace_id' => $otherWorkspace, 'disk_path' => 'x/y.jpg', 'slot' => 'menuImportSource',
            'original_name' => 'y.jpg', 'alt_text' => 'x', 'status' => 'ready',
            'mime_type' => 'image/jpeg', 'size_bytes' => 10,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->readBatch([$mine, $theirs]);
        $response->assertStatus(201);

        $results = (array) $response->json('results');
        self::assertIsInt($results[0]['id'] ?? null);
        self::assertSame('not-found', $results[1]['error'] ?? null);
    }

    // --- BULK-LIMIT-01 -----------------------------------------------------

    #[Test]
    public function more_than_ten_photos_in_one_request_is_rejected(): void
    {
        // Her fotoğraf dış bir sağlayıcıya para ödetir; sınırsız bir liste
        // faturayı tek bir isteğe bıraktırırdı.
        $this->readBatch(range(1, 11))->assertStatus(422);
    }

    #[Test]
    public function an_empty_list_is_rejected(): void
    {
        $this->readBatch([])->assertStatus(422);
    }

    // --- BULK-APPLY-01 -----------------------------------------------------

    #[Test]
    public function applying_the_batch_writes_every_draft_into_the_same_menu(): void
    {
        $ids = [$this->photo('a.jpg'), $this->photo('b.jpg')];
        $artifactIds = array_column((array) $this->readBatch($ids)->json('results'), 'id');

        $response = $this->api()->postJson(
            "/api/workspaces/{$this->workspaceId}/ai-imports/batch/apply",
            ['artifactIds' => $artifactIds],
        );

        $response->assertStatus(200, 'batch apply yanıtı: '.$response->getContent());
        self::assertGreaterThan(0, (int) $response->json('importedItems'));

        // Ve YAYINA dokunulmadı — taslak menü hâlâ taslak.
        self::assertSame(
            'draft',
            (string) DB::table('menus')->where('id', $this->menuId)->value('state'),
        );
        self::assertSame(2, DB::table('ai_artifacts')->whereNotNull('applied_at')->count());
    }

    #[Test]
    public function another_workspaces_artifact_id_cannot_be_smuggled_into_the_batch(): void
    {
        $ids = [$this->photo('a.jpg')];
        $mine = array_column((array) $this->readBatch($ids)->json('results'), 'id');

        $stranger = User::factory()->create(['email_verified_at' => now()]);
        $otherWorkspace = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Komşu', 'slug' => 'blk-s-'.Str::lower(Str::random(6)), 'state' => 'active',
            'created_by' => $stranger->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $foreign = (int) DB::table('ai_artifacts')->insertGetId([
            'workspace_id' => $otherWorkspace, 'capability' => 'menu.extract',
            'model_identity' => 'fake', 'prompt_version' => 'v1', 'schema_version' => 'v1',
            'fields' => json_encode([]), 'idempotency_key' => Str::random(32),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->api()->postJson(
            "/api/workspaces/{$this->workspaceId}/ai-imports/batch/apply",
            ['artifactIds' => [...$mine, $foreign]],
        );

        $response->assertStatus(200);

        $rejected = (array) $response->json('rejectedRows');
        $reasons = array_column($rejected, 'row');

        self::assertContains('artifact', $reasons, 'Yabancı taslak sessizce uygulandı.');
        self::assertNull(DB::table('ai_artifacts')->where('id', $foreign)->value('applied_at'));
    }

    // --- BULK-AUTHZ-01 -----------------------------------------------------

    #[Test]
    public function a_stranger_cannot_start_or_apply_a_batch(): void
    {
        $stranger = User::factory()->create(['email_verified_at' => now()]);

        $this->api($stranger)->postJson(
            "/api/workspaces/{$this->workspaceId}/menu/{$this->menuId}/ai-imports/batch",
            ['mediaAssetIds' => [1]],
        )->assertStatus(404);

        $this->api($stranger)->postJson(
            "/api/workspaces/{$this->workspaceId}/ai-imports/batch/apply",
            ['artifactIds' => [1]],
        )->assertStatus(404);
    }

    #[Test]
    public function with_ai_off_the_batch_says_so_instead_of_failing(): void
    {
        config(['ai.enabled' => false]);

        $this->readBatch([1, 2])->assertStatus(503);
    }
}
