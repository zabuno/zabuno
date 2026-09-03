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
 * TOPLU ORKESTRA (`docs/98` FF-75, `docs/adr/ADR-L11`): 40 sayfa kuyruğa,
 * parti kalıcı hafızada, toplayıcı tek listede, uygulama insan onaylı.
 *
 * Kullanıcı yolculuğu: Mehmet Usta 12 sayfalık menüsünün fotoğraflarını
 * yükler → "Oku" → ekran "3/12 sayfa okundu…" → bittiğinde TEK liste:
 * 40 satır, "Ayran" iki sayfada da vardı, biri atlandı; 1 sayfa
 * okunamadı, sebebi yazılı → Mehmet Usta "Ekle" der.
 *
 * Kuyruk testte `sync`: işler anında koşar, parti aynı istekte kapanır.
 */
final class MenuBatchOrchestraTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private int $workspaceId;

    private int $menuId;

    /** @var list<array<string,mixed>> sahte sağlayıcının gördüğü istek seçenekleri */
    private array $seenOptions = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config()->set('ai.enabled', true);
        config()->set('ai.capabilities', ['menu.extract' => ['candidates' => ['fake'], 'confidence_threshold' => 0.90]]);
        config()->set('ai.budget.monthly_minor_per_tenant', 100000);
        // `sync` kuyrukta `release()` işi yeniden sıraya KOYMAZ; dakikalık
        // bütçe burada değil, gerçek kuyrukta ölçülür (`RateLimited` ara katmanı).
        config()->set('ai.batch.per_minute', 1000);
        $this->app->instance(MalwareScannerPort::class, new class implements MalwareScannerPort
        {
            public function scan(string $diskPath): MediaScanResult
            {
                return new MediaScanResult(MediaScanVerdict::Clean);
            }
        });

        $this->owner = User::factory()->create(['email_verified_at' => now()]);
        $this->workspaceId = $this->workspace($this->owner);
        $this->menuId = $this->menu($this->workspaceId);
    }

    private function workspace(User $owner): int
    {
        $id = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => 'orc-'.Str::lower(Str::random(6)), 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $id, 'user_id' => $owner->id, 'role' => 'owner', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function menu(int $workspaceId): int
    {
        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspaceId, 'name' => 'Zeytin', 'slug' => 'orc-b-'.Str::lower(Str::random(6)), 'locale' => 'tr',
            'timezone' => 'Europe/Istanbul', 'currency' => 'TRY', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId, 'brand_id' => $brandId, 'display_name' => 'Kadıköy', 'country_code' => 'TR',
            'timezone' => 'Europe/Istanbul', 'city' => 'İstanbul', 'address_line1' => 'Bahariye Cd. No:1',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return (int) DB::table('menus')->insertGetId([
            'public_key' => Str::lower(Str::random(10)), 'workspace_id' => $workspaceId, 'location_id' => $locationId,
            'name' => 'Ana Menü', 'state' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function api(?User $user = null)
    {
        return $this->actingAs($user ?? $this->owner)->withHeaders(['Accept' => 'application/json']);
    }

    private function photo(string $name, ?int $workspaceId = null): int
    {
        $ws = $workspaceId ?? $this->workspaceId;

        return (int) $this->api()->post("/api/workspaces/{$ws}/media", [
            'file' => UploadedFile::fake()->image($name, 1200, 1600), 'altText' => 'Menü sayfası', 'slot' => 'menuImportSource',
        ])->json('id');
    }

    /**
     * Sahte sağlayıcı: her çağrıda sayfa numarasına göre satırlar; "Ayran"
     * her sayfada tekrarlanır (yineleme ayıklama kanıtı); `$failAt`
     * numaralı çağrı düşer.
     */
    private function fakeVision(?int $failAt = null): void
    {
        $seen = &$this->seenOptions;
        $this->app->instance(VisionExtractionPort::class, new class($failAt, $seen) implements VisionExtractionPort
        {
            private int $calls = 0;

            /** @param list<array<string,mixed>> $seen */
            public function __construct(private readonly ?int $failAt, private array &$seen) {}

            public function extract(AiRequest $request, array $filePaths): AiArtifact
            {
                $this->calls++;
                $this->seen[] = $request->options;

                if ($this->calls === $this->failAt) {
                    throw new ProviderCallException('fake', 'unparseable');
                }

                $page = (int) ($request->options['page'] ?? $this->calls);

                return new AiArtifact(
                    capability: $request->capability,
                    model: new ModelDeployment('fake', 'platform', 'fake-vision'),
                    promptVersion: 'fake.v1',
                    schemaVersion: $request->capability->schemaVersion(),
                    fields: [
                        new FieldValue('row.1', ['category' => 'Kebaplar', 'product' => "Kebap {$page}", 'priceMinorAmount' => 25000, 'currencyCode' => 'TRY'], 0.95, false),
                        new FieldValue('row.2', ['category' => 'İçecekler', 'product' => 'Ayran', 'priceMinorAmount' => 3000, 'currencyCode' => 'TRY'], 0.9, false),
                    ],
                );
            }
        });
    }

    private function startBatch(array $ids)
    {
        return $this->api()->postJson("/api/workspaces/{$this->workspaceId}/menu/{$this->menuId}/ai-batches", ['mediaAssetIds' => $ids]);
    }

    // --- ORKESTRA-BATCH-COLLECTED-01 ------------------------------------------

    #[Test]
    public function twelve_pages_become_one_collected_list_with_duplicates_skipped(): void
    {
        $this->fakeVision();
        $ids = [];
        for ($i = 1; $i <= 12; $i++) {
            $ids[] = $this->photo("page{$i}.jpg");
        }

        $started = $this->startBatch($ids)->assertStatus(202);
        $batchId = (int) $started->json('id');
        self::assertSame(12, $started->json('totalPages'));

        $shown = $this->api()->getJson("/api/workspaces/{$this->workspaceId}/ai-batches/{$batchId}")->assertOk()->json();

        self::assertSame('collected', $shown['state']);
        self::assertSame(12, $shown['donePages']);
        self::assertSame(0, $shown['failedPages']);
        self::assertCount(13, $shown['summary']['rows'], '12 kebap + 1 Ayran; 11 Ayran yinelemesi atlanır.');
        self::assertSame(11, $shown['summary']['duplicatesSkipped']);
        self::assertCount(12, $shown['summary']['artifactIds']);
        self::assertSame(12, DB::table('ai_artifacts')->whereNull('applied_at')->count(), 'Okumak yazmak değildir.');
        self::assertSame(0, DB::table('menu_categories')->where('menu_id', $this->menuId)->count());
    }

    // --- ORKESTRA-PURPOSE-BATCH-01 ---------------------------------------------

    #[Test]
    public function every_page_request_carries_the_batch_purpose(): void
    {
        $this->fakeVision();
        $this->startBatch([$this->photo('a.jpg'), $this->photo('b.jpg')])->assertStatus(202);

        self::assertCount(2, $this->seenOptions);
        foreach ($this->seenOptions as $options) {
            self::assertSame('batch', $options['purpose']);
            self::assertIsInt($options['batchId']);
        }
    }

    // --- ORKESTRA-PARTIAL-FAILURE-01 -------------------------------------------

    #[Test]
    public function one_unreadable_page_is_listed_with_its_reason_and_the_rest_are_collected(): void
    {
        $this->fakeVision(failAt: 2);
        $ids = [$this->photo('p1.jpg'), $this->photo('p2.jpg'), $this->photo('p3.jpg')];

        $batchId = (int) $this->startBatch($ids)->assertStatus(202)->json('id');
        $shown = $this->api()->getJson("/api/workspaces/{$this->workspaceId}/ai-batches/{$batchId}")->json();

        self::assertSame('collected', $shown['state']);
        self::assertSame(2, $shown['donePages']);
        self::assertSame(1, $shown['failedPages']);
        self::assertSame([['mediaAssetId' => $ids[1], 'reason' => 'unparseable']], $shown['summary']['failedPages']);
        self::assertSame('failed', $shown['pages'][1]['state']);
    }

    #[Test]
    public function a_batch_where_nothing_could_be_read_is_failed_not_collected(): void
    {
        $this->app->instance(VisionExtractionPort::class, new class implements VisionExtractionPort
        {
            public function extract(AiRequest $request, array $filePaths): AiArtifact
            {
                throw new ProviderCallException('fake', 'network');
            }
        });

        $batchId = (int) $this->startBatch([$this->photo('p1.jpg')])->assertStatus(202)->json('id');

        self::assertSame('failed', DB::table('ai_batches')->where('id', $batchId)->value('state'));
    }

    // --- ORKESTRA-TENANT-01 ----------------------------------------------------

    #[Test]
    public function another_tenants_photo_is_rejected_row_by_row_and_its_batch_is_invisible(): void
    {
        $this->fakeVision();
        $stranger = User::factory()->create(['email_verified_at' => now()]);
        $strangerWs = $this->workspace($stranger);
        $foreign = (int) $this->actingAs($stranger)->withHeaders(['Accept' => 'application/json'])->post("/api/workspaces/{$strangerWs}/media", [
            'file' => UploadedFile::fake()->image('x.jpg', 1200, 1600), 'altText' => 'x', 'slot' => 'menuImportSource',
        ])->json('id');

        $started = $this->startBatch([$this->photo('mine.jpg'), $foreign])->assertStatus(202);
        self::assertSame([$foreign], $started->json('rejected'));
        self::assertSame(1, $started->json('totalPages'));

        $batchId = (int) $started->json('id');
        $this->actingAs($stranger)->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/workspaces/{$strangerWs}/ai-batches/{$batchId}")->assertStatus(404);
    }

    #[Test]
    public function more_pages_than_the_configured_maximum_are_rejected(): void
    {
        config()->set('ai.batch.max_pages', 2);
        $this->fakeVision();

        $this->startBatch([$this->photo('1.jpg'), $this->photo('2.jpg'), $this->photo('3.jpg')])->assertStatus(422);
    }
}
