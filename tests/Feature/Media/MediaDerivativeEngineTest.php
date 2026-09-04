<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Application\Media\Port\MediaAssetProcessorPort;
use App\Application\Media\UseCase\ProcessAcceptedMediaAsset;
use App\Domain\Media\MediaAssetStatus;
use App\Infrastructure\Media\Persistence\EloquentMediaRepository;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * FF-131 — BOYUT MOTORU (kanonik kaynak: `docs/reference/media-manager/
 * Medya Yonetimi v2.dc.html`, ekran etiketi "Boyut motoru"; somut tablo
 * `docs/108` §6.1).
 *
 * MÜŞTERİ SORUNU. Bugün türev kuralı `config/media-slots.php` içinde slot
 * başına DÜZ BİR GENİŞLİK LİSTESİDİR (`renditions: [320, 640, …]`). `320`
 * bir sayıdır; `small · menü kartı · telefon` bir karardır. Restoran sahibi
 * "menü kartındaki fotoğraf neden bulanık?" diye sorduğunda sayı listesi ona
 * hiçbir şey söylemez — hangi ekranın hangi ölçüden beslendiğini yalnız
 * ADLANDIRILMIŞ kural söyler.
 *
 * Bu test dosyası ÜÇ şeyi çivi gibi çakar:
 *
 *  1. Adlandırılmış kural sunucuda OKUNABİLİR ve kaynağın verdiği altı satırı
 *     birebir taşır (thumb/small/medium/large/social/print).
 *  2. Kural ile BUGÜN ÜRETİLEN arasındaki fark gizlenmez. Kaynak altı ölçü
 *     adlandırıyor; depo bugün bunların yalnız bir kısmını gerçekten
 *     üretiyor. Bir ekranda "print · 2480 px" yazıp o dosyanın hiç
 *     üretilmediğini söylememek, sahibi olmayan bir yeteneğe güvendirir.
 *  3. Toplu yeniden üretim, VAR OLAN yeniden üretim işine dayanır: asıl
 *     korunur, her varlık YENİ SÜRÜM açar, hiçbir satır silinmez. Yeni kural
 *     kendiliğinden eskiye uygulanmaz — ancak açık bir yeniden üretim
 *     isteğiyle uygulanır.
 *
 * Gereksinim: MEDIA-DERIVATIVE-RULES-01, MEDIA-DERIVATIVE-HONESTY-02,
 * MEDIA-REGEN-STATS-03, MEDIA-REGEN-BATCH-REUSES-04,
 * MEDIA-REGEN-ORIGINAL-KEPT-05, MEDIA-DERIVATIVE-TENANT-06.
 */
final class MediaDerivativeEngineTest extends TestCase
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
        imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 200, 80, 40));
        ob_start();
        imagejpeg($image, null, 90);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    /**
     * Yayına hazır (`ready`) bir varlık — ÜRETİMDEKİ boru hattından geçmiş.
     *
     * Testin kendi türevini elle yazması, "yeniden üretim gerçekten çalışıyor
     * mu" sorusunu boşa çıkarırdı.
     */
    private function readyAsset(int $workspaceId, string $slot = 'itemImage'): MediaAsset
    {
        $bytes = $this->jpegBytes(1200, 1200);
        $diskPath = "quarantine/{$workspaceId}/".bin2hex(random_bytes(8));
        Storage::disk('local')->put($diskPath, $bytes);

        $asset = MediaAsset::query()->create([
            'workspace_id' => $workspaceId,
            'disk_path' => $diskPath,
            'original_name' => 'kebap.jpg',
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

    // --- MEDIA-DERIVATIVE-RULES-01 ----------------------------------------

    /**
     * Kaynağın altı kuralı, kaynağın verdiği değerlerle.
     *
     * `docs/108` §6.1 tablosu ajanın yorumuna bırakılmaz: sahip ekranda ne
     * yazacağına zaten karar vermiştir.
     */
    public function test_derivative_rules_endpoint_returns_the_named_rules_from_the_source(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'boyut-motoru-kural');

        $response = $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/media/derivative-rules");

        $response->assertOk();

        $names = array_column((array) $response->json('rules'), 'name');

        self::assertSame(
            ['thumb', 'small', 'medium', 'large', 'social', 'print'],
            $names,
            'MEDIA-DERIVATIVE-RULES-01: kaynağın adlandırdığı altı türev, kaynağın sırasıyla.'
        );

        $rules = collect((array) $response->json('rules'))->keyBy('name');

        self::assertSame(160, $rules['thumb']['width']);
        self::assertSame('crop', $rules['thumb']['fit']);
        self::assertSame(['avif', 'webp'], $rules['thumb']['formats']);

        self::assertSame(320, $rules['small']['width']);
        self::assertSame('contain', $rules['small']['fit']);

        self::assertSame(768, $rules['medium']['width']);
        self::assertSame(['avif', 'webp', 'jpeg'], $rules['medium']['formats']);

        self::assertSame(1440, $rules['large']['width']);

        // Kaynağın tek SABİT ÇERÇEVELİ kuralı: 1200×630 kırpma.
        self::assertSame(1200, $rules['social']['width']);
        self::assertSame(630, $rules['social']['height']);
        self::assertSame('crop', $rules['social']['fit']);

        self::assertSame(2480, $rules['print']['width']);
        self::assertSame(['jpeg'], $rules['print']['formats']);
    }

    // --- MEDIA-DERIVATIVE-HONESTY-02 --------------------------------------

    /**
     * Kural ADLANDIRILDI diye ÜRETİLİYOR olmaz.
     *
     * Boru hattı bugün slot başına genişlik listesinden üretiyor. `thumb`
     * (160 px) hiçbir slotun listesinde yok; `small` (320 px) dört slotta
     * var. Uç, hangi kuralın hangi slotta gerçekten üretildiğini söyler —
     * ekran "print · 2480 px" yazıp o dosyanın hiç var olmadığını
     * söylemezse, sahibi olmayan bir yeteneğe güvendirmiş oluruz.
     */
    public function test_derivative_rules_report_which_slots_actually_produce_them(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'boyut-motoru-durustluk');

        $rules = collect((array) $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/media/derivative-rules")
            ->json('rules'))->keyBy('name');

        self::assertSame(
            [],
            $rules['thumb']['producedBySlots'],
            'MEDIA-DERIVATIVE-HONESTY-02: 160 px hiçbir slotun türev listesinde yok; uç bunu gizlememeli.'
        );

        self::assertContains(
            'itemImage',
            $rules['small']['producedBySlots'],
            'MEDIA-DERIVATIVE-HONESTY-02: 320 px menü ürünü slotunda gerçekten üretiliyor.'
        );

        self::assertSame([], $rules['print']['producedBySlots']);
    }

    // --- MEDIA-REGEN-STATS-03 ---------------------------------------------

    /**
     * "Etkilenecek dosya" GERÇEK bir sayıdır.
     *
     * Sahip "yeniden üretimi başlat" düğmesine basmadan önce kaç dosyanın
     * dokunulacağını bilmelidir; uydurulmuş bir sayı, kararı bilgisiz
     * bırakır.
     */
    public function test_regeneration_stats_count_only_this_workspaces_ready_assets(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'boyut-motoru-istatistik');
        $otherId = $this->ownerWorkspace($this->verifiedUser(), 'baska-kiraci');

        $this->readyAsset($workspaceId);
        $this->readyAsset($workspaceId);
        $this->readyAsset($otherId);

        $response = $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/media/derivative-rules");

        $response->assertOk();

        self::assertSame(
            2,
            $response->json('regeneration.affectedAssets'),
            'MEDIA-REGEN-STATS-03: yalnız bu kiracının hazır varlıkları sayılır.'
        );

        self::assertGreaterThan(
            0,
            (int) $response->json('regeneration.existingRenditions'),
            'MEDIA-REGEN-STATS-03: var olan türev sayısı gerçek satırlardan okunur.'
        );

        /*
            ÖLÇÜLEN KAZANÇ UYDURULMAZ. Kaynak "%74 küçük" gibi rakamlar
            gösteriyor; bunlar biçimlerin genel iddiasıdır, BU kiracının
            dosyalarının ölçümü değil. Uç yalnız gerçekten tartılmış baytı
            döner: asıl dosya ve o dosyadan üretilmiş EN BÜYÜK türev.
        */
        self::assertSame(2, $response->json('measured.assets'));
        self::assertGreaterThan(0, (int) $response->json('measured.originalBytes'));
        self::assertGreaterThan(0, (int) $response->json('measured.largestRenditionBytes'));
    }

    /**
     * Hiç hazır dosya yokken ölçüm SIFIR döner, uydurulmuş bir oran değil.
     */
    public function test_measured_saving_is_zero_when_nothing_has_been_processed(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'boyut-motoru-bos');

        $response = $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/media/derivative-rules");

        $response->assertOk();
        self::assertSame(0, $response->json('measured.assets'));
        self::assertSame(0, $response->json('measured.originalBytes'));
        self::assertSame(0, $response->json('measured.largestRenditionBytes'));
    }

    // --- MEDIA-REGEN-BATCH-REUSES-04 / MEDIA-REGEN-ORIGINAL-KEPT-05 -------

    /**
     * Toplu yeniden üretim YENİ BİR HAT DEĞİLDİR.
     *
     * Var olan `ReprocessMediaAsset` her varlık için çalışır: her biri yeni
     * bir SÜRÜM açar (eski sürüm silinmez, onu gösteren yayın snapshot'ı
     * göstermeye devam eder) ve her biri kuyruğa bir İŞ satırı bırakır.
     */
    public function test_batch_regeneration_opens_a_new_version_per_asset_and_keeps_the_original(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'boyut-motoru-toplu');

        $first = $this->readyAsset($workspaceId);
        $second = $this->readyAsset($workspaceId);

        $originalBytes = (int) $first->size_bytes;
        $originalPath = (string) $first->disk_path;

        $jobsBefore = DB::table('media_processing_jobs')->where('workspace_id', $workspaceId)->count();

        $response = $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->post("/api/workspaces/{$workspaceId}/media/reprocess");

        $response->assertOk();

        self::assertSame(2, $response->json('processed'));
        self::assertSame(2, $response->json('succeeded'));
        self::assertSame(0, $response->json('failed'));

        foreach ([$first, $second] as $asset) {
            self::assertSame(
                2,
                DB::table('media_versions')->where('media_asset_id', $asset->getKey())->count(),
                'MEDIA-REGEN-BATCH-REUSES-04: her varlık İKİNCİ bir sürüm kazanır, eskisi durur.'
            );
        }

        self::assertSame(
            $jobsBefore + 2,
            DB::table('media_processing_jobs')->where('workspace_id', $workspaceId)->count(),
            'MEDIA-REGEN-BATCH-REUSES-04: toplu üretim kuyruğa GÖRÜNÜR iş bırakır.'
        );

        // ASIL KORUNUR: dosya yerinde ve tek bayt değişmemiş.
        $first->refresh();
        self::assertSame($originalPath, (string) $first->disk_path);
        self::assertSame($originalBytes, (int) $first->size_bytes);
        self::assertTrue(Storage::disk('local')->exists($originalPath));
        self::assertSame(
            MediaAssetStatus::Ready->value,
            (string) $first->status,
            'MEDIA-REGEN-ORIGINAL-KEPT-05: yeniden üretim varlığı `processing`te bırakmaz.'
        );
    }

    /**
     * Yeni kural KENDİLİĞİNDEN eskiye uygulanmaz.
     *
     * Kuralı okumak (GET) tek bir dosyayı bile değiştirmez; değişim ancak
     * açık bir yeniden üretim isteğiyle olur (`docs/108` §4).
     */
    public function test_reading_the_rules_never_touches_an_existing_asset(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'boyut-motoru-dokunmaz');

        $asset = $this->readyAsset($workspaceId);
        $versionsBefore = DB::table('media_versions')->where('media_asset_id', $asset->getKey())->count();

        $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/media/derivative-rules")
            ->assertOk();

        self::assertSame(
            $versionsBefore,
            DB::table('media_versions')->where('media_asset_id', $asset->getKey())->count(),
            'Kuralı okumak eski dosyayı değiştirmez.'
        );
    }

    // --- MEDIA-DERIVATIVE-TENANT-06 ---------------------------------------

    /**
     * Üye olmayan için 404 — 403 "böyle bir kiracı var" derdi.
     */
    public function test_a_non_member_sees_neither_the_rules_nor_the_batch_action(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'boyut-motoru-yabanci');
        $stranger = $this->verifiedUser();

        $this->actingAs($stranger)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/media/derivative-rules")
            ->assertNotFound();

        $this->actingAs($stranger)
            ->withHeaders(['Accept' => 'application/json'])
            ->post("/api/workspaces/{$workspaceId}/media/reprocess")
            ->assertNotFound();
    }
}
