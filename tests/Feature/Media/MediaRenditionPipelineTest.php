<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Application\Media\Port\MediaAssetProcessorPort;
use App\Application\Media\UseCase\ProcessAcceptedMediaAsset;
use App\Domain\Media\MediaAssetStatus;
use App\Infrastructure\Media\Persistence\EloquentMediaRepository;
use App\Infrastructure\Media\Processing\UnavailableMediaAssetProcessor;
use App\Models\MediaAsset;
use App\Models\User;
use App\Support\Media\RenditionUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * P0-08 RED — medya işleme gerçekten çalışır (`docs/76`).
 *
 * MÜŞTERİ SORUNU. Sahip telefonuyla çektiği fotoğrafı yükler. Bugün o dosya
 * hiçbir zaman kullanılabilir bir görsele dönüşmez: üretimde bağlı olan tek
 * işleyici `UnavailableMediaAssetProcessor` ve o her zaman "belirsiz" der.
 * Dosya `processing` durumunda sonsuza kadar bekler. Sahip ekranda ne bir
 * ilerleme ne bir hata görür — ürün ona sessiz kalır.
 *
 * Requirement IDs: MEDIA-RENDITION-SET-01, MEDIA-NO-UPSCALE-01,
 * MEDIA-FAILURE-VISIBLE-01, MEDIA-UNDECODABLE-SAYS-SO-01,
 * MEDIA-TRANSPARENCY-PRESERVE-01, MEDIA-SERVE-IMMUTABLE-01.
 */
final class MediaRenditionPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function workspaceId(string $slug): int
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);

        $id = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => $slug, 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $id, 'user_id' => $owner->id, 'role' => 'owner',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    /** Gerçek bir JPEG üretir — sahte bayt değil, GD'nin çözebileceği görüntü. */
    private function jpegBytes(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 200, 80, 40));
        ob_start();
        imagejpeg($image, null, 90);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    /** Alfa kanallı gerçek bir PNG. */
    private function transparentPngBytes(int $size): string
    {
        $image = imagecreatetruecolor($size, $size);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefilledrectangle($image, 0, 0, $size, $size, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagefilledellipse($image, (int) ($size / 2), (int) ($size / 2), $size, $size, imagecolorallocate($image, 10, 90, 200));
        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    private function acceptedAsset(int $workspaceId, string $slot, string $bytes, string $mime): MediaAsset
    {
        $diskPath = "quarantine/{$workspaceId}/".bin2hex(random_bytes(8));
        Storage::disk('local')->put($diskPath, $bytes);

        return MediaAsset::query()->create([
            'workspace_id' => $workspaceId,
            'disk_path' => $diskPath,
            'original_name' => 'yemek.jpg',
            'mime_type' => $mime,
            'size_bytes' => strlen($bytes),
            'alt_text' => 'Adana kebap',
            'slot' => $slot,
            'status' => MediaAssetStatus::Accepted->value,
        ]);
    }

    private function runPipeline(int $workspaceId, MediaAsset $asset): void
    {
        // ÜRETİMDE bağlı olan işleyici kullanılır: bu paketin asıl iddiası
        // "gerçek işleyici artık varsayılan" olduğu için, testin kendi
        // örneğini kurması iddiayı boşa çıkarırdı.
        $processor = $this->app->make(MediaAssetProcessorPort::class);

        /*
            2026-09-05'e kadar burada `GdMediaAssetProcessor` SINIFI
            aranıyordu. SVG kabulü açılınca (sahip kararı, `docs/108` §6.2)
            GD bir sarmalayıcının içine girdi — sınıf adı değişti ama bu
            testin KORUDUĞU şey değişmedi.

            Korunan şey hiçbir zaman "hangi sınıf" değildi: "varsayılan
            bağlama, yüklenen fotoğrafı sonsuza kadar bekleten yer tutucu
            OLMASIN"dı (`docs/76`, P0-08). Sınıf adına bakan bir test, her
            kompozisyon değişikliğinde iddiasını kaybederek kırılır; asıl
            iddia zaten aşağıda, üretilen türevlerde sınanıyor.
        */
        self::assertNotInstanceOf(
            UnavailableMediaAssetProcessor::class,
            $processor,
            'MEDIA-RENDITION-SET-01: üretim bağlaması gerçek görsel işleyici olmalı.'
        );

        (new ProcessAcceptedMediaAsset($processor, new EloquentMediaRepository))(
            $workspaceId,
            (int) $asset->getKey(),
        );
    }

    // --- MEDIA-RENDITION-SET-01 -------------------------------------------

    public function test_an_uploaded_photo_becomes_a_ready_set_of_renditions(): void
    {
        Storage::fake('local');
        $workspaceId = $this->workspaceId('rendition-set');

        // `itemImage` slotu 320/480/640/960 ister; kaynak hepsinden büyük.
        $asset = $this->acceptedAsset($workspaceId, 'itemImage', $this->jpegBytes(1200, 1200), 'image/jpeg');

        $this->runPipeline($workspaceId, $asset);

        $asset->refresh();
        self::assertSame(
            MediaAssetStatus::Ready->value,
            $asset->status,
            'MEDIA-RENDITION-SET-01: işlenmiş görsel karantinada beklemez, hazır olur.'
        );

        $versionId = (int) DB::table('media_versions')->where('media_asset_id', $asset->getKey())->value('id');
        self::assertGreaterThan(0, $versionId, 'MEDIA-RENDITION-SET-01: ilk işleme bir SÜRÜM yaratmalı.');

        $renditions = DB::table('media_renditions')->where('media_version_id', $versionId)->orderBy('width')->get();
        self::assertSame(
            [320, 480, 640, 960],
            $renditions->pluck('width')->map(fn ($w) => (int) $w)->all(),
            'MEDIA-RENDITION-SET-01: slot politikasındaki genişliklerin hepsi üretilmeli.'
        );

        // 1:1 slot: yükseklik genişliğe eşit.
        foreach ($renditions as $rendition) {
            self::assertSame((int) $rendition->width, (int) $rendition->height);

            $blob = DB::table('media_blobs')->where('id', $rendition->media_blob_id)->first();
            self::assertNotNull($blob, 'MEDIA-RENDITION-SET-01: her rendition gerçek bir dosyaya işaret etmeli.');
            self::assertTrue(
                Storage::disk((string) $blob->disk)->exists((string) $blob->storage_key),
                'MEDIA-RENDITION-SET-01: rendition baytları diske gerçekten yazılmalı.'
            );
            self::assertGreaterThan(0, (int) $blob->size_bytes);
            self::assertSame(64, strlen((string) $blob->checksum_sha256));
        }
    }

    // --- MEDIA-NO-UPSCALE-01 ----------------------------------------------

    public function test_a_rendition_wider_than_the_source_is_not_invented(): void
    {
        Storage::fake('local');
        $workspaceId = $this->workspaceId('no-upscale');

        // Kaynak 500 px. 640 ve 960 üretilirse menüde BULANIK görünür;
        // büyütmek bilgi eklemez, sadece yalan söyler (INV-01).
        $asset = $this->acceptedAsset($workspaceId, 'itemImage', $this->jpegBytes(500, 500), 'image/jpeg');

        $this->runPipeline($workspaceId, $asset);

        $versionId = (int) DB::table('media_versions')->where('media_asset_id', $asset->getKey())->value('id');
        $widths = DB::table('media_renditions')->where('media_version_id', $versionId)
            ->orderBy('width')->pluck('width')->map(fn ($w) => (int) $w)->all();

        self::assertSame(
            [320, 480, 500],
            $widths,
            'MEDIA-NO-UPSCALE-01: kaynaktan büyük rendition üretilmez; kaynağın kendi ölçüsü en büyük olandır.'
        );
    }

    // --- MEDIA-FAILURE-VISIBLE-01 -----------------------------------------

    public function test_a_corrupt_file_fails_visibly_with_a_reason(): void
    {
        Storage::fake('local');
        $workspaceId = $this->workspaceId('corrupt');

        $asset = $this->acceptedAsset($workspaceId, 'itemImage', 'bu bir görsel değil', 'image/jpeg');

        $this->runPipeline($workspaceId, $asset);

        $asset->refresh();
        self::assertSame(
            MediaAssetStatus::Failed->value,
            $asset->status,
            'MEDIA-FAILURE-VISIBLE-01: çözülemeyen dosya sessizce beklemez, GÖRÜNÜR biçimde başarısız olur.'
        );

        $job = DB::table('media_processing_jobs')
            ->where('media_asset_id', $asset->getKey())->orderByDesc('id')->first();

        self::assertNotNull($job, 'MEDIA-FAILURE-VISIBLE-01: her işleme denemesi bir iş kaydı bırakmalı.');
        self::assertSame('failed', (string) $job->state);
        self::assertNotEmpty(
            (string) $job->failure_reason,
            'MEDIA-FAILURE-VISIBLE-01: sahip NEDEN olmadığını okuyabilmeli.'
        );
        self::assertNotNull($job->finished_at);
    }

    public function test_a_successful_run_also_leaves_a_finished_job_record(): void
    {
        Storage::fake('local');
        $workspaceId = $this->workspaceId('job-success');

        $asset = $this->acceptedAsset($workspaceId, 'itemImage', $this->jpegBytes(1000, 1000), 'image/jpeg');
        $this->runPipeline($workspaceId, $asset);

        $job = DB::table('media_processing_jobs')
            ->where('media_asset_id', $asset->getKey())->orderByDesc('id')->first();

        self::assertNotNull($job);
        self::assertSame('succeeded', (string) $job->state);
        self::assertNull($job->failure_reason);
        self::assertNotNull($job->started_at);
        self::assertNotNull($job->finished_at);
    }

    // --- MEDIA-UNDECODABLE-SAYS-SO-01 -------------------------------------

    public function test_a_format_the_server_cannot_read_says_so_instead_of_hanging(): void
    {
        Storage::fake('local');
        $workspaceId = $this->workspaceId('heic');

        // HEIC gerçek bir başlıkla taklit edilir. GD onu çözemez; ürün
        // BUNU SÖYLEMELİ — telefondan gelen fotoğrafların çoğu HEIC'tir ve
        // sahip "yükledim ama bir şey olmadı" ile bırakılamaz.
        $heic = "\x00\x00\x00\x18ftypheic\x00\x00\x00\x00mif1heic".str_repeat("\x00", 64);
        $asset = $this->acceptedAsset($workspaceId, 'itemImage', $heic, 'image/heic');

        $this->runPipeline($workspaceId, $asset);

        $asset->refresh();
        self::assertSame(MediaAssetStatus::Failed->value, $asset->status);

        $reason = (string) DB::table('media_processing_jobs')
            ->where('media_asset_id', $asset->getKey())->orderByDesc('id')->value('failure_reason');

        self::assertNotEmpty($reason, 'MEDIA-UNDECODABLE-SAYS-SO-01: sebep boş bırakılamaz.');
    }

    // --- MEDIA-TRANSPARENCY-PRESERVE-01 -----------------------------------

    public function test_a_logo_keeps_its_transparency(): void
    {
        Storage::fake('local');
        $workspaceId = $this->workspaceId('logo-alpha');

        // `logo` slotu `transparency: preserve` — alfa düz beyaza
        // çevrilirse logo koyu zeminde beyaz bir kutu içinde görünür.
        $asset = $this->acceptedAsset($workspaceId, 'logo', $this->transparentPngBytes(600), 'image/png');

        $this->runPipeline($workspaceId, $asset);

        $asset->refresh();
        self::assertSame(MediaAssetStatus::Ready->value, $asset->status);

        $versionId = (int) DB::table('media_versions')->where('media_asset_id', $asset->getKey())->value('id');
        $rendition = DB::table('media_renditions')->where('media_version_id', $versionId)
            ->orderByDesc('width')->first();
        self::assertNotNull($rendition);

        $blob = DB::table('media_blobs')->where('id', $rendition->media_blob_id)->first();
        $bytes = Storage::disk((string) $blob->disk)->get((string) $blob->storage_key);

        $image = imagecreatefromstring((string) $bytes);
        self::assertNotFalse($image, 'MEDIA-TRANSPARENCY-PRESERVE-01: üretilen rendition okunabilir bir görsel olmalı.');

        // Sol üst köşe kaynakta tamamen saydamdı.
        $alpha = (imagecolorat($image, 1, 1) >> 24) & 0x7F;
        imagedestroy($image);

        self::assertGreaterThan(
            100,
            $alpha,
            'MEDIA-TRANSPARENCY-PRESERVE-01: logonun saydam köşesi saydam kalmalı.'
        );
    }

    // --- MEDIA-SERVE-IMMUTABLE-01 -----------------------------------------

    public function test_a_rendition_is_served_over_an_immutable_url(): void
    {
        Storage::fake('local');
        $workspaceId = $this->workspaceId('serve');

        $asset = $this->acceptedAsset($workspaceId, 'itemImage', $this->jpegBytes(1000, 1000), 'image/jpeg');
        $this->runPipeline($workspaceId, $asset);

        $versionId = (int) DB::table('media_versions')->where('media_asset_id', $asset->getKey())->value('id');
        $rendition = DB::table('media_renditions')->where('media_version_id', $versionId)
            ->orderBy('width')->first();
        $blob = DB::table('media_blobs')->where('id', $rendition->media_blob_id)->first();

        $url = RenditionUrl::for((int) $rendition->id, (string) $blob->checksum_sha256, (string) $rendition->format);

        $response = $this->get($url);

        $response->assertOk();
        $response->assertHeader('Content-Type', (string) $blob->mime_type);
        self::assertStringContainsString(
            'immutable',
            (string) $response->headers->get('Cache-Control'),
            'MEDIA-SERVE-IMMUTABLE-01: rendition adresi değişmez; tarayıcı onu süresiz saklayabilmeli.'
        );

        // Yanlış sağlama toplamı olan bir adres servis edilmez: adresler
        // sayılarak taranamaz.
        $this->get(RenditionUrl::for((int) $rendition->id, str_repeat('0', 64), (string) $rendition->format))
            ->assertNotFound();
    }
}
