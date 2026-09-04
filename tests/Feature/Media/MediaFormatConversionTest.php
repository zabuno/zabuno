<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Application\Media\Port\MediaAssetProcessorPort;
use App\Application\Media\Port\MediaFormatSupportPort;
use App\Application\Media\UseCase\ProcessAcceptedMediaAsset;
use App\Domain\Media\MediaAssetStatus;
use App\Infrastructure\Media\Persistence\EloquentMediaRepository;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * FF-135 — DÖNÜŞTÜR (kanonik kaynak: `docs/reference/media-manager/
 * Medya Yonetimi v2.dc.html`, ekran etiketi "Dönüştür"; hedef listesi
 * `docs/108` §6.3).
 *
 * Kaynağın kendi cümlesi: "Eski biçimleri modern biçime çevir. Aslı
 * korunur, dönüşen dosya yeni sürüm olur."
 *
 * MÜŞTERİ SORUNU. Restoran sahibinin kütüphanesinde telefondan çıkmış
 * 3 MB'lık JPEG'ler duruyor. Menü sayfası mobil veriyle açıldığında bunun
 * bedelini misafir ödüyor. Sahip "bunları küçült" diyebilmeli — ama
 * ASLINI kaybetme korkusu olmadan.
 *
 * BU DOSYANIN ÇAKTIĞI DÖRT ÇİVİ:
 *
 *  1. Hedef listesi KAYNAĞINDIR, ajanın yorumu değil: AVIF %74, WebP %58,
 *     WebM %62, JPEG %40 — bu sırayla.
 *  2. KAYNAĞIN LİSTESİ TAM, ÜRÜNÜN YETENEĞİ DEĞİL. Bu kurulumda video
 *     dönüştüren bir hat YOK. WebM'i ekranda "yapılabilir" gibi göstermek,
 *     sahibi olmayan bir yeteneğe güvendirir; liste gösterilir ama
 *     desteklenmeyen hedef DESTEKLENMİYOR diye yazılır ve İSTENDİĞİNDE
 *     REDDEDİLİR.
 *  3. Kazanç UYDURULMAZ. Kaynaktaki yüzdeler biçimlerin GENEL iddiasıdır;
 *     ekranda ayrıca BU kiracının gerçekten tartılmış baytı durur ve hiç
 *     ölçüm yoksa hiçbir sayı üretilmez.
 *  4. Dönüştürme YENİ BİR İŞLEME HATTI DEĞİLDİR: var olan
 *     `ReprocessMediaAsset` çalışır. Asıl korunur, yeni SÜRÜM açılır,
 *     eski sürümün türevleri silinmez.
 *
 * Gereksinim: MEDIA-CONVERT-TARGETS-01, MEDIA-CONVERT-HONESTY-02,
 * MEDIA-CONVERT-MEASURED-03, MEDIA-CONVERT-REUSES-REPROCESS-04,
 * MEDIA-CONVERT-ORIGINAL-KEPT-05, MEDIA-CONVERT-UNSUPPORTED-REFUSED-06,
 * MEDIA-CONVERT-LIMIT-07, MEDIA-CONVERT-TENANT-08.
 */
final class MediaFormatConversionTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function ownerWorkspace(User $owner, string $slug): int
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin Restoranları',
            'slug' => $slug,
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

    /** Gerçek bir JPEG — sahte bayt değil, GD'nin çözebileceği görüntü. */
    private function jpegBytes(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);

        // Düz renk bir kare her biçimde neredeyse sıfıra sıkışır ve
        // "dönüştü mü" sorusunu ölçülemez kılar; gürültü gerçek bir
        // fotoğrafın davranışına yakındır.
        for ($x = 0; $x < $width; $x += 4) {
            for ($y = 0; $y < $height; $y += 4) {
                imagefilledrectangle(
                    $image, $x, $y, $x + 3, $y + 3,
                    imagecolorallocate($image, ($x * 7) % 255, ($y * 13) % 255, (($x + $y) * 3) % 255),
                );
            }
        }

        ob_start();
        imagejpeg($image, null, 90);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    /**
     * Yayına hazır (`ready`) bir varlık — ÜRETİMDEKİ boru hattından geçmiş.
     *
     * Testin kendi türevini elle yazması, "dönüştürme gerçekten çalışıyor
     * mu" sorusunu boşa çıkarırdı.
     */
    private function readyAsset(int $workspaceId, string $slot = 'itemImage', string $name = 'kebap.jpg'): MediaAsset
    {
        $bytes = $this->jpegBytes(900, 900);
        $diskPath = "quarantine/{$workspaceId}/".bin2hex(random_bytes(8));
        Storage::disk('local')->put($diskPath, $bytes);

        $asset = MediaAsset::query()->create([
            'workspace_id' => $workspaceId,
            'disk_path' => $diskPath,
            'original_name' => $name,
            'mime_type' => 'image/jpeg',
            'size_bytes' => strlen($bytes),
            'alt_text' => 'Adana kebap',
            'slot' => $slot,
            'status' => MediaAssetStatus::Accepted->value,
        ]);

        (new ProcessAcceptedMediaAsset(
            $this->app->make(MediaAssetProcessorPort::class),
            new EloquentMediaRepository,
        ))($workspaceId, (int) $asset->getKey());

        return $asset->refresh();
    }

    // --- MEDIA-CONVERT-TARGETS-01 -----------------------------------------

    /**
     * Kaynağın dört hedefi, kaynağın sırasıyla ve kaynağın yüzdeleriyle.
     *
     * `docs/108` §6.3 ajanın yorumuna bırakılmaz: sahip ekranda ne
     * yazacağına zaten karar vermiştir.
     */
    public function test_conversion_targets_endpoint_returns_the_four_targets_from_the_source(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'donustur-hedefler');

        $response = $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/media/conversion-targets");

        $response->assertOk();

        $targets = $response->json('targets');

        self::assertSame(
            ['avif', 'webp', 'webm', 'jpeg'],
            array_column($targets, 'format'),
            'Kaynak hedefleri bu sırayla diziyor; alfabetik sıralamak kaynağın kararını bozar.',
        );

        self::assertSame([74, 58, 62, 40], array_column($targets, 'claimedSavingPercent'));
        self::assertSame(
            ['image', 'image', 'video', 'image'],
            array_column($targets, 'family'),
            'WebM bir VİDEO hedefidir; görsel hedeflerle aynı kefeye konamaz.',
        );
    }

    // --- MEDIA-CONVERT-HONESTY-02 -----------------------------------------

    /**
     * Bu kurulumda video dönüştüren bir hat YOK ve uç bunu SÖYLER.
     *
     * Sahip ekranda WebM kartını görür ama kart "bu kurulumda yapılamıyor"
     * der. Kartı gizlemek de yalan olurdu: kaynağın listesi tamdır, eksik
     * olan ÜRÜNÜN yeteneğidir ve fark dürüstçe yazılır.
     */
    public function test_video_target_is_reported_as_unsupported_with_a_reason(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'donustur-durustluk');

        $targets = $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/media/conversion-targets")
            ->json('targets');

        $byFormat = array_column($targets, null, 'format');

        self::assertFalse($byFormat['webm']['supported']);
        self::assertSame('no-video-pipeline', $byFormat['webm']['limitation']);

        // JPEG her GD derlemesinde vardır; desteklenmiyor demek yalan olurdu.
        self::assertTrue($byFormat['jpeg']['supported']);
        self::assertNull($byFormat['jpeg']['limitation']);
    }

    /**
     * Sunucu AVIF üretemiyorsa kart "yapılabilir" demez.
     *
     * AVIF desteği PHP sürümüne ve GD derlemesine bağlıdır; bunu varsaymak,
     * sahibin düğmeye basıp yalnız başarısızlık toplaması demek olurdu.
     */
    public function test_a_format_the_server_cannot_encode_is_never_reported_supported(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'donustur-avif-yok');

        $this->app->instance(MediaFormatSupportPort::class, new FakeFormatSupport(['jpeg', 'webp']));

        $targets = $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/media/conversion-targets")
            ->json('targets');

        $byFormat = array_column($targets, null, 'format');

        self::assertFalse($byFormat['avif']['supported']);
        self::assertSame('encoder-missing', $byFormat['avif']['limitation']);
        self::assertTrue($byFormat['webp']['supported']);
    }

    /**
     * Kaynak dosyalar: yalnız HAZIR görseller, adı ve GERÇEK boyutuyla.
     *
     * Bekleyen ya da reddedilmiş bir dosyayı listeye koymak, sahibi
     * seçemeyeceği bir satırla uğraştırırdı.
     */
    public function test_source_list_carries_ready_images_with_their_measured_size(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'donustur-kaynaklar');

        $ready = $this->readyAsset($workspaceId, 'itemImage', 'kunefe.jpg');

        MediaAsset::query()->create([
            'workspace_id' => $workspaceId,
            'disk_path' => "quarantine/{$workspaceId}/bekleyen",
            'original_name' => 'bekleyen.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'alt_text' => 'Bekleyen',
            'slot' => 'itemImage',
            'status' => MediaAssetStatus::Quarantined->value,
        ]);

        $sources = $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/media/conversion-targets")
            ->json('sources');

        self::assertSame([(int) $ready->getKey()], array_column($sources, 'id'));
        self::assertSame('kunefe.jpg', $sources[0]['name']);
        self::assertSame((int) $ready->size_bytes, $sources[0]['sizeBytes']);
        self::assertSame('jpeg', $sources[0]['format']);
    }

    // --- MEDIA-CONVERT-MEASURED-03 ----------------------------------------

    /**
     * Hiç dönüştürülmemiş bir kiracıda ÖLÇÜLEN KAZANÇ YOKTUR.
     *
     * Kaynaktaki "%74" biçimin genel iddiasıdır. Onu bu kiracının ölçümü
     * gibi göstermek, sahibin sonradan tutmayacak bir sayıya güvenmesi
     * demek olurdu.
     */
    public function test_measured_saving_is_absent_until_something_is_actually_measured(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'donustur-olcum-yok');

        $measured = $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/media/conversion-targets")
            ->json('measured');

        self::assertSame([], $measured);
    }

    /**
     * Dönüştürdükten SONRA ölçüm gerçektir: tartılmış asıl ve tartılmış
     * çıktı, biçim başına.
     */
    public function test_measured_saving_appears_after_a_real_conversion(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'donustur-olcum-var');
        $asset = $this->readyAsset($workspaceId);

        $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->postJson("/api/workspaces/{$workspaceId}/media/convert", [
                'format' => 'webp',
                'assetIds' => [(int) $asset->getKey()],
            ])
            ->assertOk();

        $measured = $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/media/conversion-targets")
            ->json('measured');

        self::assertArrayHasKey('webp', $measured);
        self::assertSame(1, $measured['webp']['assets']);
        self::assertGreaterThan(0, $measured['webp']['originalBytes']);
        self::assertGreaterThan(0, $measured['webp']['convertedBytes']);
    }

    // --- MEDIA-CONVERT-REUSES-REPROCESS-04 / ORIGINAL-KEPT-05 -------------

    /**
     * Dönüşen dosya YENİ SÜRÜM olur ve ASIL yerinde durur.
     *
     * Kaynağın cümlesi bu: "Aslı korunur, dönüşen dosya yeni sürüm olur."
     * Sahibin bir dosyayı dönüştürüp sonra pişman olması olağandır; eski
     * sürüm silinseydi geri dönüş diye bir şey kalmazdı.
     */
    public function test_conversion_opens_a_new_version_and_keeps_the_original(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'donustur-surum');
        $asset = $this->readyAsset($workspaceId);
        $assetId = (int) $asset->getKey();

        $originalPath = (string) $asset->disk_path;
        $originalSize = (int) $asset->size_bytes;
        $firstVersionId = (int) DB::table('media_versions')->where('media_asset_id', $assetId)->value('id');
        $firstRenditionCount = DB::table('media_renditions')->where('media_version_id', $firstVersionId)->count();

        self::assertGreaterThan(0, $firstRenditionCount);

        $response = $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->postJson("/api/workspaces/{$workspaceId}/media/convert", [
                'format' => 'webp',
                'assetIds' => [$assetId],
            ]);

        $response->assertOk();
        $response->assertJsonPath('succeeded', 1);
        $response->assertJsonPath('failed', 0);

        // Asıl DEĞİŞMEDİ: aynı yol, aynı boyut, dosya hâlâ diskte.
        $asset->refresh();
        self::assertSame($originalPath, (string) $asset->disk_path);
        self::assertSame($originalSize, (int) $asset->size_bytes);
        self::assertTrue(Storage::disk('local')->exists($originalPath));

        // Yeni SÜRÜM açıldı; eskisi ve türevleri duruyor.
        self::assertSame(2, DB::table('media_versions')->where('media_asset_id', $assetId)->count());
        self::assertSame(
            $firstRenditionCount,
            DB::table('media_renditions')->where('media_version_id', $firstVersionId)->count(),
            'Eski sürümün türevleri silinmez: onu gösteren bir yayın snapshot\'ı olabilir.',
        );

        $newVersionId = (int) DB::table('media_versions')
            ->where('media_asset_id', $assetId)
            ->where('version_number', 2)
            ->value('id');

        $formats = DB::table('media_renditions')
            ->where('media_version_id', $newVersionId)
            ->pluck('format')
            ->unique()
            ->values()
            ->all();

        self::assertSame(['webp'], $formats, 'İstenen hedef biçim gerçekten üretilmiş olmalı.');

        // Yeni sürüm YENİDEN ÜRETİM olarak işaretlenir; var olan hat
        // kullanıldığının kanıtı budur.
        self::assertSame(
            'reprocess',
            DB::table('media_versions')->where('id', $newVersionId)->value('created_by_kind'),
        );
    }

    // --- MEDIA-CONVERT-UNSUPPORTED-REFUSED-06 -----------------------------

    /**
     * Desteklenmeyen hedef İSTENDİĞİNDE REDDEDİLİR ve hiçbir dosyaya
     * dokunulmaz.
     *
     * "Ekranda gizle, arka uçta kabul et" iki başlı bir dürüstlüktür:
     * bugün ekran gizler, yarın başka bir çağıran ister ve ürün sessizce
     * yanlış iş yapar.
     */
    public function test_unsupported_target_is_refused_and_changes_nothing(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'donustur-webm-red');
        $asset = $this->readyAsset($workspaceId);
        $assetId = (int) $asset->getKey();

        $response = $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->postJson("/api/workspaces/{$workspaceId}/media/convert", [
                'format' => 'webm',
                'assetIds' => [$assetId],
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('limitation', 'no-video-pipeline');

        self::assertSame(1, DB::table('media_versions')->where('media_asset_id', $assetId)->count());
    }

    /** Kaynakta olmayan bir biçim adı hiç denenmez. */
    public function test_an_unknown_format_is_rejected(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'donustur-bilinmeyen');

        $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->postJson("/api/workspaces/{$workspaceId}/media/convert", [
                'format' => 'bmp',
                'assetIds' => [1],
            ])
            ->assertStatus(422);
    }

    /**
     * DESTEKLENDİĞİ SÖYLENEN HER HEDEF GERÇEKTEN ÜRETİLİR.
     *
     * Bu testin makineye göre değişen tek yanı KAÇ hedefi denediğidir;
     * iddiası değişmez: uç "yapılabilir" dediyse yapılır. AVIF'i sabit
     * yazmak, AVIF'siz derlenmiş bir GD'de testi kırardı ve o kırılma
     * ürünün değil makinenin haberidir. Tersi de yasak: hiç deneme
     * yapılmadan geçen bir test, hiçbir şey kanıtlamaz.
     */
    public function test_every_target_reported_supported_is_actually_produced(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'donustur-iddia-tutar');

        $targets = $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/media/conversion-targets")
            ->json('targets');

        $supported = array_values(array_filter(
            $targets,
            static fn (array $target): bool => $target['supported'] && $target['family'] === 'image',
        ));

        self::assertNotSame([], $supported, 'Hiçbir hedef üretilemiyorsa bölüm ürüne girmemeli.');

        /*
            HIZ SINIRI burada KAPATILIR ve bu bir gevşetme değildir: bu test
            biçim başına bir çağrı yapar, ürünün gerçek kullanımı ise tek
            çağrıda çok dosyadır. Sınırın kendisi rota imzası testinde
            (`ModularApiRouteRegistrationTest`) donduruluyor; burada onu
            yeniden ölçmek, kodlayıcı iddiasını ölçmeyi engellerdi.
        */
        $this->withoutMiddleware(ThrottleRequests::class);

        foreach ($supported as $target) {
            $format = (string) $target['format'];
            $assetId = (int) $this->readyAsset($workspaceId, 'itemImage', "{$format}.jpg")->getKey();

            $this->actingAs($owner)
                ->withHeaders(['Accept' => 'application/json'])
                ->postJson("/api/workspaces/{$workspaceId}/media/convert", [
                    'format' => $format,
                    'assetIds' => [$assetId],
                ])
                ->assertOk()
                ->assertJsonPath('succeeded', 1);

            $newVersionId = (int) DB::table('media_versions')
                ->where('media_asset_id', $assetId)
                ->orderByDesc('version_number')
                ->value('id');

            self::assertSame(
                [$format],
                DB::table('media_renditions')
                    ->where('media_version_id', $newVersionId)
                    ->pluck('format')->unique()->values()->all(),
                "'{$format}' desteklendiği söylendi ama üretilmedi.",
            );
        }
    }

    /**
     * Saydam bir görsel JPEG'e ÇEVRİLMEZ ve sebebi iş kaydında yazar.
     *
     * JPEG saydamlık taşımaz; logo beyaz bir kutunun içine düşerdi. Asıl
     * korunduğu için geri dönüş vardır ama sahip bunu ancak menüde
     * görürdü — o kadar geç bir fark ediş ürünün hatasıdır. Varlık `ready`
     * kalır ve eski sürümü geçerliliğini korur.
     */
    public function test_a_transparent_slot_is_not_flattened_into_jpeg(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'donustur-saydam');
        // `logo` slotu saydamlığı KORUR (`config/media-slots.php`).
        $asset = $this->readyAsset($workspaceId, 'logo', 'logo.png');
        $assetId = (int) $asset->getKey();

        $response = $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->postJson("/api/workspaces/{$workspaceId}/media/convert", [
                'format' => 'jpeg',
                'assetIds' => [$assetId],
            ]);

        $response->assertOk();
        $response->assertJsonPath('failed', 1);
        $response->assertJsonPath('succeeded', 0);

        self::assertSame(
            1,
            DB::table('media_versions')->where('media_asset_id', $assetId)->count(),
            'Reddedilen dönüştürme yeni sürüm açmamalı.',
        );

        $reason = (string) DB::table('media_processing_jobs')
            ->where('media_asset_id', $assetId)
            ->orderByDesc('id')
            ->value('failure_reason');

        self::assertStringContainsString('Saydam', $reason, 'Sebep sahibin okuyacağı cümle olmalı.');
    }

    // --- MEDIA-CONVERT-LIMIT-07 -------------------------------------------

    /**
     * Sınır ve KALAN sayısı birlikte söylenir.
     *
     * Dönüştürme senkrondur: çağrıldığı istekte görsel işler. Sınırsız bir
     * toplu iş, iki yüz fotoğraflu bir kiracıda isteği zaman aşımına
     * uğratır ve sahip işin yarıda kaldığını hiçbir yerden öğrenemez.
     */
    public function test_conversion_is_bounded_and_reports_what_is_left(): void
    {
        config(['media-slots.regeneration.batch_limit' => 1]);

        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'donustur-sinir');
        $first = (int) $this->readyAsset($workspaceId, 'itemImage', 'bir.jpg')->getKey();
        $second = (int) $this->readyAsset($workspaceId, 'itemImage', 'iki.jpg')->getKey();

        $response = $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->postJson("/api/workspaces/{$workspaceId}/media/convert", [
                'format' => 'webp',
                'assetIds' => [$first, $second],
            ]);

        $response->assertOk();
        $response->assertJsonPath('processed', 1);
        $response->assertJsonPath('succeeded', 1);
        $response->assertJsonPath('remaining', 1);
    }

    // --- MEDIA-CONVERT-TENANT-08 ------------------------------------------

    /** Üye olmayan 404 görür: "böyle bir kiracı var" da bir bilgidir. */
    public function test_a_stranger_cannot_read_or_start_a_conversion(): void
    {
        $owner = $this->verifiedUser();
        $stranger = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'donustur-yabanci');

        $this->actingAs($stranger)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/media/conversion-targets")
            ->assertNotFound();

        $this->actingAs($stranger)
            ->withHeaders(['Accept' => 'application/json'])
            ->postJson("/api/workspaces/{$workspaceId}/media/convert", [
                'format' => 'webp',
                'assetIds' => [1],
            ])
            ->assertNotFound();
    }

    /** Başka kiracının dosyası sessizce ATLANIR, dönüştürülmez. */
    public function test_an_asset_from_another_workspace_is_not_converted(): void
    {
        $owner = $this->verifiedUser();
        $mine = $this->ownerWorkspace($owner, 'donustur-benim');
        $otherOwner = $this->verifiedUser();
        $theirs = $this->ownerWorkspace($otherOwner, 'donustur-onlarin');
        $theirAsset = (int) $this->readyAsset($theirs)->getKey();

        $response = $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->postJson("/api/workspaces/{$mine}/media/convert", [
                'format' => 'webp',
                'assetIds' => [$theirAsset],
            ]);

        $response->assertOk();
        $response->assertJsonPath('succeeded', 0);

        self::assertSame(
            1,
            DB::table('media_versions')->where('media_asset_id', $theirAsset)->count(),
            'Yabancı kiracının dosyası dönüştürülmemeli.',
        );
    }
}

/**
 * Sunucunun yeteneğini TESTİN belirlediği sahte destek.
 *
 * Gerçek destek `gd_info()`ya bakar ve o, çalıştığı makineye göre değişir.
 * Testin makineye göre değişmesi, "AVIF desteklenmiyorsa ne olur?"
 * sorusunu ölçülemez kılardı.
 */
final class FakeFormatSupport implements MediaFormatSupportPort
{
    /** @param list<string> $supported */
    public function __construct(private readonly array $supported) {}

    public function supports(string $format): bool
    {
        return in_array($format, $this->supported, true);
    }

    public function limitation(string $format): ?string
    {
        return $this->supports($format) ? null : 'encoder-missing';
    }
}
