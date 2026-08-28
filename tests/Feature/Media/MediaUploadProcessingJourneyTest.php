<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Application\Media\Dto\MediaProcessingOutcome;
use App\Application\Media\Dto\MediaProcessingResult;
use App\Application\Media\Dto\MediaScanResult;
use App\Application\Media\Dto\MediaScanVerdict;
use App\Application\Media\Port\MalwareScannerPort;
use App\Application\Media\Port\MediaAssetProcessorPort;
use App\Domain\Media\MediaAssetStatus;
use App\Infrastructure\Media\Processing\GdMediaAssetProcessor;
use App\Infrastructure\Media\Processing\UnavailableMediaAssetProcessor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Blind targeted RED for the media upload-to-processing HTTP journey
 * (frozen scope: MEDIA-PROCESS-03, synthesized scope
 * `/tmp/zabuno-media-upload-processing-synthesized-scope.md`). The
 * scan-to-process orchestration seam in `StoreMediaController` is not yet
 * wired: a real multipart upload today ends at the scan outcome
 * (`accepted`/`rejected`/`scanning`) and never invokes
 * `ProcessAcceptedMediaAsset`, and `AppServiceProvider` does not yet bind
 * `MediaAssetProcessorPort` to its default `UnavailableMediaAssetProcessor`
 * adapter. Every assertion below that expects `processing`/`ready` or a
 * resolvable default processor binding is therefore expected to fail RED
 * against real HTTP, database and Storage::fake state - not against a
 * broken fixture.
 */
final class MediaUploadProcessingJourneyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * GÜNCELLENDİ (`docs/76`, P0-08). Bu test eskiden üretim bağlamasının
     * `UnavailableMediaAssetProcessor` OLMASINI donduruyordu.
     *
     * O gün doğruydu: gerçek bir işleyici henüz yoktu ve yer tutucu, sahte
     * bir başarı üretmemek için oradaydı. Ama zamanla anlamı değişti —
     * ürün canlıya çıkarken yüklenen HER fotoğrafı sonsuza kadar bekleten
     * bir varsayılan, "güvenli" değil sessizce bozuk demektir.
     *
     * Yeni sözleşme: varsayılan GERÇEK işleyicidir; yer tutucu yalnız GD
     * bulunmayan bir ortamda yedektir ve orada da dürüstçe "işleyemiyorum"
     * der.
     */
    public function test_default_container_binds_the_real_image_processor(): void
    {
        $processor = $this->app->make(MediaAssetProcessorPort::class);

        self::assertInstanceOf(
            GdMediaAssetProcessor::class,
            $processor,
            'MEDIA-PROCESS-03-DEFAULT-BINDING-01: varsayılan bağlama gerçek görsel işleyici olmalı.'
        );
    }

    public function test_the_placeholder_processor_still_holds_honestly_when_no_image_library_exists(): void
    {
        // Yedek yol hâlâ dürüst: sahte bir başarı üretmez, "belirsiz" der
        // ve varlık terminal bir duruma zorlanmaz.
        $outcome = (new UnavailableMediaAssetProcessor)->process('/yok/olan/dosya')->outcome;

        self::assertSame(MediaProcessingOutcome::Indeterminate, $outcome);
    }

    public function test_authorized_upload_with_clean_scan_and_successful_processor_marks_asset_ready_with_key_bytes_and_no_public_output(): void
    {
        Storage::fake('local');

        $this->app->instance(MalwareScannerPort::class, new FixedVerdictScanner(MediaScanVerdict::Clean));
        $processor = new SpySuccessfulProcessor;
        $this->app->instance(MediaAssetProcessorPort::class, $processor);

        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'zeytin-media-process-ready');

        $file = UploadedFile::fake()->image('logo.jpg', 200, 200)->size(50);
        $originalBytes = file_get_contents($file->getRealPath());
        self::assertNotFalse($originalBytes, 'MEDIA-PROCESS-03-READY-01: upload öncesi kaynak byte\'lar okunabilmeli.');

        $response = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->post(
            "/api/workspaces/{$workspaceId}/media",
            ['file' => $file, 'altText' => 'Zeytin Restoranı logosu', 'slot' => 'menu']
        );

        $response->assertStatus(201);

        $mediaId = (int) $response->json('id');
        $diskPath = (string) DB::table('media_assets')->where('id', $mediaId)->value('disk_path');
        self::assertNotEmpty($diskPath, 'MEDIA-PROCESS-03-READY-01: karantina disk_path kalıcı olmalı.');

        $expectedAbsolutePath = Storage::disk('local')->path($diskPath);

        self::assertSame(
            [$expectedAbsolutePath],
            $processor->receivedAbsolutePaths,
            'MEDIA-PROCESS-03-READY-01: processor, claim edilen asset\'in gerçek mutlak filesystem yolu ile tam olarak bir kez çağrılmalı.'
        );

        self::assertSame(
            'ready',
            $response->json('status'),
            'MEDIA-PROCESS-03-READY-01: yanıt JSON status\'u ready olmalı.'
        );

        self::assertSame(
            MediaAssetStatus::Ready->value,
            DB::table('media_assets')->where('id', $mediaId)->value('status'),
            'MEDIA-PROCESS-03-READY-01: successful processor sonrası asset ready olmalı.'
        );

        self::assertSame(
            $diskPath,
            DB::table('media_assets')->where('id', $mediaId)->value('disk_path'),
            'MEDIA-PROCESS-03-READY-01: tenant-scoped disk_path anahtarı değişmemeli.'
        );

        Storage::disk('local')->assertExists($diskPath);

        self::assertSame(
            $originalBytes,
            Storage::disk('local')->get($diskPath),
            'MEDIA-PROCESS-03-READY-01: disk üzerindeki private byte\'lar upload edilen gerçek dosya byte\'larıyla birebir aynı olmalı.'
        );

        self::assertArrayNotHasKey('url', $response->json() ?? [], 'MEDIA-PROCESS-03-READY-01: yanıt public url içermemeli.');
        self::assertArrayNotHasKey('publicUrl', $response->json() ?? [], 'MEDIA-PROCESS-03-READY-01: yanıt public url içermemeli.');
        self::assertArrayNotHasKey('derivatives', $response->json() ?? [], 'MEDIA-PROCESS-03-READY-01: yanıt derivative içermemeli.');
    }

    public function test_authorized_upload_with_clean_scan_and_default_unavailable_processor_leaves_asset_honestly_processing_with_no_public_output(): void
    {
        Storage::fake('local');

        $this->app->instance(MalwareScannerPort::class, new FixedVerdictScanner(MediaScanVerdict::Clean));

        // GÜNCELLENDİ (`docs/76`): üretimin varsayılanı artık gerçek
        // işleyici. Bu test, GÖRSEL İŞLEME BULUNMAYAN bir ortamın hâlâ
        // dürüst davrandığını dondurur — yer tutucu bilerek bağlanır.
        $this->app->instance(MediaAssetProcessorPort::class, new UnavailableMediaAssetProcessor);

        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'zeytin-media-process-safehold');

        $file = UploadedFile::fake()->image('logo.jpg', 200, 200)->size(50);
        $originalBytes = file_get_contents($file->getRealPath());
        self::assertNotFalse($originalBytes, 'MEDIA-PROCESS-03-SAFEHOLD-01: upload öncesi kaynak byte\'lar okunabilmeli.');

        $response = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->post(
            "/api/workspaces/{$workspaceId}/media",
            ['file' => $file, 'altText' => 'Zeytin Restoranı logosu', 'slot' => 'menu']
        );

        $response->assertStatus(201);

        $mediaId = (int) $response->json('id');
        $diskPath = (string) DB::table('media_assets')->where('id', $mediaId)->value('disk_path');
        self::assertNotEmpty($diskPath, 'MEDIA-PROCESS-03-SAFEHOLD-01: karantina disk_path kalıcı olmalı.');

        self::assertSame(
            'processing',
            $response->json('status'),
            'MEDIA-PROCESS-03-SAFEHOLD-01: yanıt JSON status\'u processing olmalı.'
        );

        self::assertSame(
            MediaAssetStatus::Processing->value,
            DB::table('media_assets')->where('id', $mediaId)->value('status'),
            'MEDIA-PROCESS-03-SAFEHOLD-01: default unavailable processor indeterminate döndürdüğünden asset dürüstçe processing\'de güvenli-beklemede kalmalı.'
        );

        Storage::disk('local')->assertExists($diskPath);

        self::assertSame(
            $originalBytes,
            Storage::disk('local')->get($diskPath),
            'MEDIA-PROCESS-03-SAFEHOLD-01: disk üzerindeki private byte\'lar upload edilen gerçek dosya byte\'larıyla birebir aynı olmalı.'
        );

        self::assertArrayNotHasKey('url', $response->json() ?? [], 'MEDIA-PROCESS-03-SAFEHOLD-01: yanıt public url içermemeli.');
        self::assertArrayNotHasKey('publicUrl', $response->json() ?? [], 'MEDIA-PROCESS-03-SAFEHOLD-01: yanıt public url içermemeli.');
        self::assertArrayNotHasKey('derivatives', $response->json() ?? [], 'MEDIA-PROCESS-03-SAFEHOLD-01: yanıt derivative içermemeli.');
    }

    /**
     * @return array<string, array{0: MediaScanVerdict, 1: string}>
     */
    public static function nonAcceptedScanOutcomeProvider(): array
    {
        return [
            'infected' => [MediaScanVerdict::Infected, MediaAssetStatus::Rejected->value],
            'indeterminate' => [MediaScanVerdict::Indeterminate, MediaAssetStatus::Scanning->value],
        ];
    }

    #[DataProvider('nonAcceptedScanOutcomeProvider')]
    public function test_infected_and_indeterminate_scans_never_reach_the_processor_and_stay_at_their_honest_scan_outcome(
        MediaScanVerdict $verdict,
        string $expectedStatus,
    ): void {
        Storage::fake('local');

        $this->app->instance(MalwareScannerPort::class, new FixedVerdictScanner($verdict));
        $processor = new SpySuccessfulProcessor;
        $this->app->instance(MediaAssetProcessorPort::class, $processor);

        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'zeytin-media-process-nonaccepted-'.$verdict->value);

        $file = UploadedFile::fake()->image('logo.jpg', 200, 200)->size(50);

        $response = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->post(
            "/api/workspaces/{$workspaceId}/media",
            ['file' => $file, 'altText' => 'Zeytin Restoranı logosu', 'slot' => 'menu']
        );

        $response->assertStatus(201);

        $mediaId = (int) $response->json('id');

        self::assertSame(
            [],
            $processor->receivedAbsolutePaths,
            "MEDIA-PROCESS-03-NONACCEPTED-NO-PROCESSOR-CALL-01: {$verdict->value} verdict claim'i accepted'a taşımadığından processor asla çağrılmamalı."
        );

        self::assertSame(
            $expectedStatus,
            $response->json('status'),
            "MEDIA-PROCESS-03-NONACCEPTED-NO-PROCESSOR-CALL-01: {$verdict->value} verdict sonrası yanıt JSON status'u {$expectedStatus} olmalı."
        );

        self::assertSame(
            $expectedStatus,
            DB::table('media_assets')->where('id', $mediaId)->value('status'),
            "MEDIA-PROCESS-03-NONACCEPTED-NO-PROCESSOR-CALL-01: {$verdict->value} verdict sonrası asset dürüstçe {$expectedStatus} durumunda kalmalı."
        );
    }

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function ownerWorkspace(User $owner, string $slugSeed): int
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin Restoranları',
            'slug' => $slugSeed,
            'state' => 'active',
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $owner->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $workspaceId;
    }
}

/**
 * Fixed-verdict scanner fake: always returns the injected verdict,
 * regardless of the real quarantined path it is invoked with.
 */
final class FixedVerdictScanner implements MalwareScannerPort
{
    public function __construct(private readonly MediaScanVerdict $verdict) {}

    public function scan(string $diskPath): MediaScanResult
    {
        return new MediaScanResult($this->verdict);
    }
}

/**
 * Spy processor fake: records every real absolute path it is invoked with
 * and always reports the outcome as explicitly Succeeded.
 */
final class SpySuccessfulProcessor implements MediaAssetProcessorPort
{
    /** @var list<string> */
    public array $receivedAbsolutePaths = [];

    public function process(string $absolutePath, string $slot = ''): MediaProcessingResult
    {
        $this->receivedAbsolutePaths[] = $absolutePath;

        return new MediaProcessingResult(MediaProcessingOutcome::Succeeded);
    }
}
