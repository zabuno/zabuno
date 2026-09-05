<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Application\Media\Dto\MediaScanResult;
use App\Application\Media\Dto\MediaScanVerdict;
use App\Application\Media\Port\MalwareScannerPort;
use App\Domain\Media\MediaAssetStatus;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * P0-08 RED — dürüstlük ve silme etkisi (`docs/76`).
 *
 * KRİTER 3. Virüs tarayıcı devre dışıysa ürün bunu kullanıcıya karşı
 * "tarandı" gibi göstermez. Bugün dosya `scanning` durumunda sessizce
 * bekliyor ve sahip hiçbir yerde tarayıcının çalışmadığını okumuyor —
 * yani ürün, yapmadığı bir şeyi yapıyormuş gibi duruyor.
 *
 * KRİTER 4. Bir görsel silindiğinde onu kullanan YAYINLANMIŞ menü
 * kırılmaz. Yayın, sahibin onayladığı donmuş hâldir; panelden yapılan bir
 * temizlik onu misafirin gözü önünde bozamaz.
 *
 * KRİTER 3'ÜN İKİNCİ YARISI (FF-150). "Kayda geçiyor" ile "sahip okuyor"
 * aynı şey değildir. Sebep bugün YALNIZ kütüphane listesinde görünüyordu;
 * sahip dosyayı YÜKLEDİĞİ ekranda "Media upload complete." okuyup ayrılıyor
 * ve bir daha bakmıyor. Sessizce bekleyen dosya, ürünün verebileceği en
 * kötü cevaptır: sahip yanlış bir şey yaptığını sanır, tekrar dener, sonra
 * ürünün bozuk olduğunu düşünür.
 *
 * Aynı sessizlik menüye bağlarken de vardı: ret "İşlenmesi bitince yeniden
 * deneyin" diyordu. Tarayıcı bu ortamda hiç yokken işleme ASLA bitmez —
 * yani ürün olmayacak bir şeyi vaat ediyordu.
 *
 * VE AYNI CÜMLE MARKA LOGOSUNDA DA VARDI (FF-151). Menü kalemi düzeltildi,
 * logo unutuldu; oysa logo misafirin gördüğü İLK şeydir ve sahibi onu
 * kurulumun ilk yarım saatinde bağlamaya çalışır. Aynı yalanı iki ekrandan
 * birinde bırakmak, onu hiç düzeltmemekle aynı kapıya çıkar.
 *
 * VE ZİNCİRİN SON HALKASI: İYİLEŞME YOLU (FF-153). Üç paket boyunca ürün
 * gerçeği söylemeyi öğrendi, ama gerçeği DEĞİŞTİRMENİN yolu yoktu. Sahip
 * sunucuya ClamAV kurduğunda, kesinti boyunca yüklenmiş her dosya sonsuza
 * dek `scanning`de kalıyordu: `media:reprocess` yalnız ZATEN `ready` olan
 * varlıklara bakıyor, hiçbir zamanlanmış görev de onları yeniden denemiyordu.
 * Dürüstçe "bekliyor" demek, sonsuza dek beklettikten sonra bir teselli
 * değildir.
 *
 * Requirement IDs: MEDIA-SCANNER-HONEST-01, MEDIA-DELETE-IMPACT-01,
 * MEDIA-DELETE-UNUSED-OK-01, MEDIA-SCANNER-HONEST-AT-UPLOAD-01,
 * MEDIA-BIND-HELD-HONEST-01, MEDIA-BIND-BRAND-LOGO-HELD-HONEST-01,
 * MEDIA-RESCAN-RECOVERS-01, MEDIA-RESCAN-NEVER-SKIPS-SCAN-01,
 * MEDIA-RESCAN-TENANT-BOUND-01, MEDIA-RESCAN-IDEMPOTENT-01.
 */
final class MediaHonestyAndDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function ownerAndWorkspace(string $slug): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);

        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => $slug, 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId, 'user_id' => $owner->id, 'role' => 'owner',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$owner, $workspaceId];
    }

    // --- MEDIA-SCANNER-HONEST-01 ------------------------------------------

    public function test_when_no_scanner_is_available_the_product_records_that_the_file_was_not_scanned(): void
    {
        Storage::fake('local');
        $this->app->instance(MalwareScannerPort::class, new class implements MalwareScannerPort
        {
            public function scan(string $diskPath): MediaScanResult
            {
                return new MediaScanResult(MediaScanVerdict::Indeterminate);
            }
        });

        [$owner, $workspaceId] = $this->ownerAndWorkspace('scanner-honest');

        $response = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->post(
            "/api/workspaces/{$workspaceId}/media",
            ['file' => UploadedFile::fake()->image('kebap.jpg', 400, 400), 'altText' => 'Adana kebap', 'slot' => 'itemImage']
        );

        $response->assertStatus(201);
        $mediaId = (int) $response->json('id');

        // Dosya kabul edilmiş SAYILMAZ: taranmadı.
        self::assertSame(
            MediaAssetStatus::Scanning->value,
            DB::table('media_assets')->where('id', $mediaId)->value('status'),
            'MEDIA-SCANNER-HONEST-01: taranamayan dosya kabul edilmiş gibi ilerletilemez.'
        );

        // Ve ürün NEDENİNİ kaydeder: sahip "taranıyor" ile "taranamıyor"
        // arasındaki farkı görebilmeli.
        $job = DB::table('media_processing_jobs')
            ->where('media_asset_id', $mediaId)->where('kind', 'scan')->orderByDesc('id')->first();

        self::assertNotNull($job, 'MEDIA-SCANNER-HONEST-01: tarama denemesi bir iz bırakmalı.');
        self::assertSame('held', (string) $job->state);
        self::assertNotEmpty((string) $job->failure_reason);

        // Sahibin gördüğü listede de bu sebep var.
        $listed = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/workspaces/{$workspaceId}/media")->json();

        $row = collect($listed['data'] ?? $listed)->firstWhere('id', $mediaId);
        self::assertNotEmpty(
            $row['statusReason'] ?? '',
            'MEDIA-SCANNER-HONEST-01: sahip ekranda neden beklediğini okuyabilmeli.'
        );
    }

    // --- MEDIA-SCANNER-HONEST-AT-UPLOAD-01 --------------------------------

    public function test_the_upload_answer_itself_carries_the_reason_the_file_is_waiting(): void
    {
        Storage::fake('local');
        $this->app->instance(MalwareScannerPort::class, new class implements MalwareScannerPort
        {
            public function scan(string $diskPath): MediaScanResult
            {
                return new MediaScanResult(MediaScanVerdict::Indeterminate);
            }
        });

        [$owner, $workspaceId] = $this->ownerAndWorkspace('scanner-at-upload');

        $response = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->post(
            "/api/workspaces/{$workspaceId}/media",
            ['file' => UploadedFile::fake()->image('kebap.jpg', 400, 400), 'altText' => 'Adana kebap', 'slot' => 'itemImage']
        );

        $response->assertStatus(201);

        /*
            Sebep YÜKLEMENİN KENDİ CEVABINDA olmalı. Kütüphane listesine
            koymak yetmiyor: sahip yükleme ekranından ayrılmadan önce
            dosyasının beklediğini öğrenmeli, sonraki sekmede değil.
        */
        self::assertNotEmpty(
            (string) $response->json('statusReason'),
            'MEDIA-SCANNER-HONEST-AT-UPLOAD-01: sahip, yüklediği YERDE neden beklediğini okumalı.'
        );

        // Ve cevap taranmış gibi davranmıyor: durum hâlâ ilerlememiş.
        self::assertSame(MediaAssetStatus::Scanning->value, $response->json('status'));
    }

    // --- MEDIA-BIND-HELD-HONEST-01 ----------------------------------------

    public function test_binding_a_held_image_to_a_menu_item_does_not_promise_that_processing_will_finish(): void
    {
        Storage::fake('local');
        $this->app->instance(MalwareScannerPort::class, new class implements MalwareScannerPort
        {
            public function scan(string $diskPath): MediaScanResult
            {
                return new MediaScanResult(MediaScanVerdict::Indeterminate);
            }
        });

        [$owner, $workspaceId] = $this->ownerAndWorkspace('bind-held-honest');
        $menuItemId = $this->menuItemIn($workspaceId, 'bind-held-honest');

        $mediaId = (int) $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->post(
            "/api/workspaces/{$workspaceId}/media",
            ['file' => UploadedFile::fake()->image('kebap.jpg', 400, 400), 'altText' => 'Adana kebap', 'slot' => 'itemImage']
        )->json('id');

        $response = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->putJson(
                "/api/workspaces/{$workspaceId}/menu-items/{$menuItemId}/image",
                ['mediaAssetId' => $mediaId],
            );

        // Güvenlik sınırı DEĞİŞMEZ: taranmamış görsel menüye bağlanmaz.
        $response->assertStatus(422);
        self::assertSame(
            0,
            DB::table('media_usages')->where('media_asset_id', $mediaId)->count(),
            'MEDIA-BIND-HELD-HONEST-01: taranmamış görsel yine de bağlanmamalı.'
        );

        $message = (string) $response->json('message');

        /*
            Ret, olmayacak bir şeyi VAAT ETMEZ. Bu ortamda tarayıcı hiç
            yok; "işlenmesi bitince" diye beklenecek bir an yok. Sahip
            saatlerce yenilemesin diye sebep, kaydedilmiş gerçek sebeptir.
        */
        self::assertStringNotContainsString('İşlenmesi bitince', $message);
        self::assertSame(
            (string) DB::table('media_processing_jobs')
                ->where('media_asset_id', $mediaId)->where('kind', 'scan')
                ->orderByDesc('id')->value('failure_reason'),
            $message,
            'MEDIA-BIND-HELD-HONEST-01: ret, kayda geçen gerçek sebebi söylemeli.'
        );
    }

    // --- MEDIA-BIND-BRAND-LOGO-HELD-HONEST-01 -----------------------------

    public function test_binding_a_held_image_as_the_brand_logo_does_not_promise_that_processing_will_finish(): void
    {
        Storage::fake('local');
        $this->app->instance(MalwareScannerPort::class, new class implements MalwareScannerPort
        {
            public function scan(string $diskPath): MediaScanResult
            {
                return new MediaScanResult(MediaScanVerdict::Indeterminate);
            }
        });

        [$owner, $workspaceId] = $this->ownerAndWorkspace('brand-logo-held-honest');
        $this->brandIn($workspaceId, 'brand-logo-held-honest');

        $mediaId = (int) $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->post(
            "/api/workspaces/{$workspaceId}/media",
            ['file' => UploadedFile::fake()->image('logo.png', 512, 512), 'altText' => 'Zeytin logosu', 'slot' => 'logo']
        )->json('id');

        $response = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->putJson("/api/workspaces/{$workspaceId}/brand/logo", ['mediaAssetId' => $mediaId]);

        // Güvenlik sınırı DEĞİŞMEZ: taranmamış görsel logo da olamaz.
        $response->assertStatus(422);
        self::assertSame(
            0,
            DB::table('media_usages')->where('media_asset_id', $mediaId)->count(),
            'MEDIA-BIND-BRAND-LOGO-HELD-HONEST-01: taranmamış görsel yine de bağlanmamalı.'
        );

        $message = (string) $response->json('message');

        /*
            Logo, menü kalemiyle AYNI cümleyi söylemeli. Sahip aynı gerçeği
            iki ekranda iki farklı şekilde okursa hangisinin doğru olduğunu
            bilemez — ve "işlenmesi bitince" olanı, hiç bitmeyecek bir anı
            işaret ediyordu.
        */
        self::assertStringNotContainsString('İşlenmesi bitince', $message);
        self::assertSame(
            (string) DB::table('media_processing_jobs')
                ->where('media_asset_id', $mediaId)->where('kind', 'scan')
                ->orderByDesc('id')->value('failure_reason'),
            $message,
            'MEDIA-BIND-BRAND-LOGO-HELD-HONEST-01: ret, kayda geçen gerçek sebebi söylemeli.'
        );
    }

    public function test_the_brand_logo_refusal_never_borrows_a_reason_from_another_workspace(): void
    {
        Storage::fake('local');
        $this->app->instance(MalwareScannerPort::class, new class implements MalwareScannerPort
        {
            public function scan(string $diskPath): MediaScanResult
            {
                return new MediaScanResult(MediaScanVerdict::Indeterminate);
            }
        });

        [$owner, $mineId] = $this->ownerAndWorkspace('logo-tenant-mine');
        $this->brandIn($mineId, 'logo-tenant-mine');

        [$stranger, $theirsId] = $this->ownerAndWorkspace('logo-tenant-theirs');

        // Yabancının varlığı: kimliği geçerli, ama BAŞKA kiracıya ait.
        $theirMediaId = (int) $this->actingAs($stranger)->withHeaders(['Accept' => 'application/json'])->post(
            "/api/workspaces/{$theirsId}/media",
            ['file' => UploadedFile::fake()->image('logo.png', 512, 512), 'altText' => 'Başkasının logosu', 'slot' => 'logo']
        )->json('id');

        $response = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->putJson("/api/workspaces/{$mineId}/brand/logo", ['mediaAssetId' => $theirMediaId]);

        $response->assertStatus(422);

        /*
            KİRACI SINIRI. Varlık kimlikle bulunur ve kimlik kiracı sormaz;
            yabancının dosyasına yazılmış sebebi buraya basmak, o dosyanın
            VAR OLDUĞUNU ele verirdi. Burada eski, genel cümle doğru olandır.
        */
        self::assertSame(
            'Bu görsel henüz kullanıma hazır değil. İşlenmesi bitince yeniden deneyin.',
            (string) $response->json('message'),
            'MEDIA-BIND-BRAND-LOGO-HELD-HONEST-01: başka kiracının sebebi sızdırılamaz.'
        );
    }

    /** Markanın kendisi — logo testinin gerektirdiği tek satır. */
    private function brandIn(int $workspaceId, string $slug): int
    {
        return (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspaceId, 'name' => 'Zeytin Restoranları',
            'slug' => "brand-{$slug}", 'locale' => 'tr',
            'timezone' => 'Europe/Istanbul', 'currency' => 'TRY',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * Menüye bağlama testinin gerektirdiği en küçük gerçek zincir:
     * marka → şube → menü → kategori → ürün → menü satırı.
     *
     * Satırlar elle yazılıyor çünkü bu test menü KURMAYI değil, kurulmuş
     * bir menüye taranmamış bir görseli bağlamayı ölçüyor.
     */
    private function menuItemIn(int $workspaceId, string $slug): int
    {
        $brandId = $this->brandIn($workspaceId, $slug);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId, 'brand_id' => $brandId,
            'display_name' => 'Kadıköy', 'country_code' => 'TR',
            'timezone' => 'Europe/Istanbul', 'city' => 'İstanbul',
            'address_line1' => 'Bahariye Cd. No:1',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $menuId = (int) DB::table('menus')->insertGetId([
            /*
                ANAHTAR SLUG'DAN TÜRETİLMEZ, ÖZÜTLENİR.

                `public_key` sütunu ON İKİ karakterdir ve bu ürünün gerçek
                bir kısıtıdır: misafirin adres çubuğuna girdiği anahtar
                kısa olmak zorunda. Yardımcı önce `"pk-{$slug}"` yazıyordu
                ve kısa slug'larla çalışıyordu — ilk uzun slug'da PostgreSQL
                testi kırdı. SQLite bunu SESSİZCE geçiriyor, yani hata
                yerelde değil yalnız CI'da görünüyordu.

                Özüt, slug ne olursa olsun sığar ve testler arasında
                benzersiz kalır (sütun `unique`).
            */
            'public_key' => 'pk'.substr(md5($slug), 0, 10), 'workspace_id' => $workspaceId,
            'location_id' => $locationId, 'name' => 'Ana Menü', 'state' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $categoryId = (int) DB::table('menu_categories')->insertGetId([
            'menu_id' => $menuId, 'name' => 'Kebaplar', 'position' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $productId = (int) DB::table('products')->insertGetId([
            'workspace_id' => $workspaceId, 'name' => 'Adana Kebap',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return (int) DB::table('menu_items')->insertGetId([
            'category_id' => $categoryId, 'product_id' => $productId,
            'price_minor_amount' => 38000, 'currency_code' => 'TRY',
            'position' => 0, 'is_visible' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_a_clean_scan_leaves_no_misleading_reason(): void
    {
        Storage::fake('local');
        $this->app->instance(MalwareScannerPort::class, new class implements MalwareScannerPort
        {
            public function scan(string $diskPath): MediaScanResult
            {
                return new MediaScanResult(MediaScanVerdict::Clean);
            }
        });

        [$owner, $workspaceId] = $this->ownerAndWorkspace('scanner-clean');

        $mediaId = (int) $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->post(
            "/api/workspaces/{$workspaceId}/media",
            ['file' => UploadedFile::fake()->image('kebap.jpg', 400, 400), 'altText' => 'Adana kebap', 'slot' => 'itemImage']
        )->json('id');

        $listed = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/workspaces/{$workspaceId}/media")->json();

        $row = collect($listed['data'] ?? $listed)->firstWhere('id', $mediaId);

        self::assertSame(MediaAssetStatus::Ready->value, $row['status'] ?? null);
        self::assertNull($row['statusReason'] ?? null, 'Sorunsuz bir dosyaya sebep yazmak gürültüdür.');
    }

    // --- MEDIA-DELETE-IMPACT-01 -------------------------------------------

    public function test_deleting_an_image_used_by_a_published_menu_is_refused_with_an_explanation(): void
    {
        Storage::fake('local');
        $this->app->instance(MalwareScannerPort::class, new class implements MalwareScannerPort
        {
            public function scan(string $diskPath): MediaScanResult
            {
                return new MediaScanResult(MediaScanVerdict::Clean);
            }
        });

        [$owner, $workspaceId] = $this->ownerAndWorkspace('delete-impact');

        $mediaId = (int) $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->post(
            "/api/workspaces/{$workspaceId}/media",
            ['file' => UploadedFile::fake()->image('kebap.jpg', 400, 400), 'altText' => 'Adana kebap', 'slot' => 'itemImage']
        )->json('id');

        // Görsel YAYINLANMIŞ bir menüde kullanılıyor.
        DB::table('media_usages')->insert([
            'workspace_id' => $workspaceId,
            'media_asset_id' => $mediaId,
            'entity_type' => 'menu_item',
            'entity_id' => 1,
            'slot' => 'itemImage',
            'publication_id' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->deleteJson("/api/workspaces/{$workspaceId}/media/{$mediaId}");

        $response->assertStatus(409);
        self::assertNotEmpty(
            $response->json('message'),
            'MEDIA-DELETE-IMPACT-01: ret sessiz olamaz; sahip NEDEN silinemediğini okumalı.'
        );

        self::assertNotNull(
            DB::table('media_assets')->where('id', $mediaId)->first(),
            'MEDIA-DELETE-IMPACT-01: yayınlanmış menüdeki görsel silinmemeli.'
        );
    }

    // --- MEDIA-DELETE-UNUSED-OK-01 ----------------------------------------

    public function test_deleting_an_unused_image_still_works(): void
    {
        Storage::fake('local');
        $this->app->instance(MalwareScannerPort::class, new class implements MalwareScannerPort
        {
            public function scan(string $diskPath): MediaScanResult
            {
                return new MediaScanResult(MediaScanVerdict::Clean);
            }
        });

        [$owner, $workspaceId] = $this->ownerAndWorkspace('delete-unused');

        $mediaId = (int) $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->post(
            "/api/workspaces/{$workspaceId}/media",
            ['file' => UploadedFile::fake()->image('kebap.jpg', 400, 400), 'altText' => 'Adana kebap', 'slot' => 'itemImage']
        )->json('id');

        $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->deleteJson("/api/workspaces/{$workspaceId}/media/{$mediaId}")
            ->assertStatus(204);

        // Yumuşak silme: satır tarihçe için kalır, kullanımdan kalkar.
        self::assertNotNull(
            DB::table('media_assets')->where('id', $mediaId)->value('deleted_at'),
            'MEDIA-DELETE-UNUSED-OK-01: kullanılmayan görsel silinebilmeli.'
        );
    }

    // --- FF-153: İYİLEŞME YOLU --------------------------------------------

    /**
     * Tarayıcının bu ortamda ne cevap vereceğini belirler.
     *
     * Anonim ikiz, dosyayı gerçekten okumaz: bu testlerin ölçtüğü şey
     * ClamAV'ın doğru çalışıp çalışmadığı değil, ÜRÜNÜN verilen bir hükme
     * ne yaptığıdır.
     */
    private function bindScanner(MediaScanVerdict $verdict): void
    {
        $this->app->instance(MalwareScannerPort::class, new class($verdict) implements MalwareScannerPort
        {
            public function __construct(private readonly MediaScanVerdict $verdict) {}

            public function scan(string $diskPath): MediaScanResult
            {
                return new MediaScanResult($this->verdict);
            }
        });
    }

    /**
     * "Sahip sunucuya ClamAV kurdu" anının kendisi.
     *
     * İKİ ŞEY BİRDEN değişir ve ikisi de gereklidir. Ortam (`config`)
     * artık bir tarayıcı olduğunu söyler — kurtarma komutunun ön koşulu
     * budur. Bağlanan ikiz ise o tarayıcının ne CEVAP verdiğini söyler.
     * Yalnız birini değiştirmek gerçek dünyada olmayan bir durumu taklit
     * ederdi: kurulu ama hiç konuşmayan, ya da konuşan ama kurulu olmayan
     * bir tarayıcı.
     *
     * İkili, `tempnam` ile açılmış çalıştırılabilir boş bir dosyadır;
     * ortam denetiminin sorduğu tek şey "var mı, çalıştırılabilir mi".
     */
    private function installScanner(MediaScanVerdict $verdict): string
    {
        $binary = tempnam(sys_get_temp_dir(), 'clamscan');
        self::assertIsString($binary);
        chmod($binary, 0o755);

        config()->set('media.scanner.driver', 'clamav');
        config()->set('media.scanner.clamav.binary_path', $binary);
        config()->set('media.scanner.clamav.timeout_seconds', 10.0);

        $this->bindScanner($verdict);

        return $binary;
    }

    /** Tarayıcı YOKKEN yüklenmiş, `scanning`de mahsur kalmış bir dosya. */
    private function strandedUpload(User $owner, int $workspaceId, string $name = 'kebap.jpg'): int
    {
        $this->bindScanner(MediaScanVerdict::Indeterminate);

        $mediaId = (int) $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->post(
            "/api/workspaces/{$workspaceId}/media",
            ['file' => UploadedFile::fake()->image($name, 400, 400), 'altText' => 'Adana kebap', 'slot' => 'itemImage']
        )->json('id');

        self::assertSame(
            MediaAssetStatus::Scanning->value,
            DB::table('media_assets')->where('id', $mediaId)->value('status'),
            'Ön koşul: tarayıcı yokken dosya taranmamış olarak bekler.'
        );

        return $mediaId;
    }

    // --- MEDIA-RESCAN-RECOVERS-01 -----------------------------------------

    /**
     * Kesinti boyunca mahsur kalan dosya, tarayıcı gelince GERÇEKTEN
     * kullanılabilir olur.
     *
     * Ölçü "durum sütunu değişti" değildir; sahibin yapmak istediği işin
     * yapılabilmesidir: fotoğrafı menü kalemine bağlayabilmek. Durumu
     * `ready`ye çekip bağlamanın hâlâ reddedilmesi, bir dürüstlük paketinin
     * üretebileceği en ince yalan olurdu.
     */
    public function test_a_file_stranded_by_a_missing_scanner_becomes_usable_once_the_scanner_arrives(): void
    {
        Storage::fake('local');

        [$owner, $workspaceId] = $this->ownerAndWorkspace('rescan-recovers');
        $menuItemId = $this->menuItemIn($workspaceId, 'rescan-recovers');
        $mediaId = $this->strandedUpload($owner, $workspaceId);

        // Ön koşul: bugün bağlanamıyor.
        $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->putJson("/api/workspaces/{$workspaceId}/menu-items/{$menuItemId}/image", ['mediaAssetId' => $mediaId])
            ->assertStatus(422);

        $binary = $this->installScanner(MediaScanVerdict::Clean);

        $exit = Artisan::call('media:rescan-held', ['--workspace' => $workspaceId]);

        self::assertSame(Command::SUCCESS, $exit, 'MEDIA-RESCAN-RECOVERS-01: kurtarma koştu ve iş kalmadı.');

        self::assertSame(
            MediaAssetStatus::Ready->value,
            DB::table('media_assets')->where('id', $mediaId)->value('status'),
            'MEDIA-RESCAN-RECOVERS-01: temiz çıkan dosya normal işleme akışına devam etmeli.'
        );

        // VE bağlanabiliyor — ürünün sahibe borçlu olduğu şey buydu.
        $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->putJson("/api/workspaces/{$workspaceId}/menu-items/{$menuItemId}/image", ['mediaAssetId' => $mediaId])
            ->assertStatus(200);

        /*
            Ve eski sebep ORTADA KALMAZ. `held` iş kaydı tarihçe olarak
            durur (bir şeyin beklediği gerçeği silinmez), ama sahibin
            kütüphanede okuduğu cümle ARTIK GEÇERLİ OLANDIR. `ready` bir
            dosyanın yanında "taranmadan yayına alınmaz" yazması, düzeltilen
            sorunu düzeltilmemiş göstermek olurdu.
        */
        $listed = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/workspaces/{$workspaceId}/media")->json();

        $row = collect($listed['data'] ?? $listed)->firstWhere('id', $mediaId);
        self::assertNull(
            $row['statusReason'] ?? null,
            'MEDIA-RESCAN-RECOVERS-01: iyileşen dosya, artık geçerli olmayan bir sebebi taşımamalı.'
        );

        @unlink($binary);
    }

    // --- MEDIA-RESCAN-NEVER-SKIPS-SCAN-01 ---------------------------------

    /**
     * Tarayıcı HÂLÂ yokken komut hiçbir şeyi ilerletmez ve bunu söyler.
     *
     * Sessizce "başarılı" raporlamak buradaki en kötü sonuçtur: sahip
     * kurtarmanın işlediğini sanır, dosyalarını yayına aldığını sanır ve
     * gerçeği ancak misafir boş bir menüye baktığında öğrenir.
     */
    public function test_the_command_progresses_nothing_and_says_why_while_the_scanner_is_still_missing(): void
    {
        Storage::fake('local');

        [$owner, $workspaceId] = $this->ownerAndWorkspace('rescan-no-scanner');
        $mediaId = $this->strandedUpload($owner, $workspaceId);

        $jobsBefore = (int) DB::table('media_processing_jobs')->where('media_asset_id', $mediaId)->count();

        // Ortam DEĞİŞMEDİ: sürücü hâlâ `unavailable`.
        $exit = Artisan::call('media:rescan-held', ['--workspace' => $workspaceId]);
        $output = Artisan::output();

        self::assertSame(
            Command::FAILURE,
            $exit,
            'MEDIA-RESCAN-NEVER-SKIPS-SCAN-01: yapılamayan bir iş başarı olarak raporlanamaz.'
        );

        self::assertNotSame('', trim($output), 'MEDIA-RESCAN-NEVER-SKIPS-SCAN-01: ret sessiz olamaz.');

        self::assertSame(
            MediaAssetStatus::Scanning->value,
            DB::table('media_assets')->where('id', $mediaId)->value('status'),
            'MEDIA-RESCAN-NEVER-SKIPS-SCAN-01: bu komut "bekleyeni geçir" komutu değildir.'
        );

        /*
            Ve tek bir deneme kaydı bile yazılmaz. Tarayıcının olmadığı
            ORTAM düzeyinde bellidir; her dosya için ayrı ayrı denemek,
            iş kaydını hiçbir şey öğretmeyen satırlarla doldururdu.
        */
        self::assertSame(
            $jobsBefore,
            (int) DB::table('media_processing_jobs')->where('media_asset_id', $mediaId)->count(),
            'MEDIA-RESCAN-NEVER-SKIPS-SCAN-01: denenmeyen bir tarama iz bırakmamalı.'
        );
    }

    /** Tarama KİRLİ çıkarsa dosya kullanılabilir OLMAZ — komut güvenlik kuralını delmez. */
    public function test_a_file_that_turns_out_infected_never_becomes_usable(): void
    {
        Storage::fake('local');

        [$owner, $workspaceId] = $this->ownerAndWorkspace('rescan-infected');
        $menuItemId = $this->menuItemIn($workspaceId, 'rescan-infected');
        $mediaId = $this->strandedUpload($owner, $workspaceId);

        $binary = $this->installScanner(MediaScanVerdict::Infected);

        Artisan::call('media:rescan-held', ['--workspace' => $workspaceId]);

        self::assertSame(
            MediaAssetStatus::Rejected->value,
            DB::table('media_assets')->where('id', $mediaId)->value('status'),
            'MEDIA-RESCAN-NEVER-SKIPS-SCAN-01: zararlı dosya reddedilir, ilerletilmez.'
        );

        $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->putJson("/api/workspaces/{$workspaceId}/menu-items/{$menuItemId}/image", ['mediaAssetId' => $mediaId])
            ->assertStatus(422);

        self::assertSame(
            0,
            DB::table('media_usages')->where('media_asset_id', $mediaId)->count(),
            'MEDIA-RESCAN-NEVER-SKIPS-SCAN-01: kurtarma komutu güvenlik sınırını delemez.'
        );

        @unlink($binary);
    }

    // --- MEDIA-RESCAN-TENANT-BOUND-01 -------------------------------------

    /**
     * `--workspace=` bir SÜZGEÇ değil, bir SINIRDIR.
     *
     * Kiracı izolasyonu yapısaldır: bir çalışma alanının kurtarma komutu
     * başka bir çalışma alanının dosyasına dokunamaz. Bir operatör tek bir
     * restoranın sorununu çözerken bütün müşterilerin dosyalarını
     * kımıldatmamalı.
     */
    public function test_the_command_never_touches_another_workspaces_file(): void
    {
        Storage::fake('local');

        [$owner, $mineId] = $this->ownerAndWorkspace('rescan-mine');
        [$stranger, $theirsId] = $this->ownerAndWorkspace('rescan-theirs');

        $mine = $this->strandedUpload($owner, $mineId);
        $theirs = $this->strandedUpload($stranger, $theirsId);

        $binary = $this->installScanner(MediaScanVerdict::Clean);

        Artisan::call('media:rescan-held', ['--workspace' => $mineId]);

        self::assertSame(
            MediaAssetStatus::Ready->value,
            DB::table('media_assets')->where('id', $mine)->value('status'),
            'MEDIA-RESCAN-TENANT-BOUND-01: istenen çalışma alanı kurtarılmalı.'
        );

        self::assertSame(
            MediaAssetStatus::Scanning->value,
            DB::table('media_assets')->where('id', $theirs)->value('status'),
            'MEDIA-RESCAN-TENANT-BOUND-01: başka çalışma alanının dosyasına dokunulamaz.'
        );

        @unlink($binary);
    }

    // --- MEDIA-RESCAN-IDEMPOTENT-01 ---------------------------------------

    /**
     * Aynı komutu iki kez koşturmak ikinci bir kopya üretmez.
     *
     * Operatör bir kurtarma komutunu neredeyse her zaman iki kez koşar:
     * bir kez "acaba oldu mu", bir kez de "emin olayım" diye. İkincisinin
     * her dosyayı ikinci bir sürümle çoğaltması, kotayı sessizce
     * tüketmek ve sürüm geçmişini anlamsızlaştırmak olurdu.
     */
    public function test_running_the_command_twice_creates_no_second_copy(): void
    {
        Storage::fake('local');

        [$owner, $workspaceId] = $this->ownerAndWorkspace('rescan-idempotent');
        $mediaId = $this->strandedUpload($owner, $workspaceId);

        $binary = $this->installScanner(MediaScanVerdict::Clean);

        Artisan::call('media:rescan-held', ['--workspace' => $workspaceId]);
        Artisan::call('media:rescan-held', ['--workspace' => $workspaceId]);

        self::assertSame(
            1,
            (int) DB::table('media_assets')->where('workspace_id', $workspaceId)->count(),
            'MEDIA-RESCAN-IDEMPOTENT-01: ikinci koşu ikinci bir varlık yaratmamalı.'
        );

        self::assertSame(
            1,
            (int) DB::table('media_versions')->where('media_asset_id', $mediaId)->count(),
            'MEDIA-RESCAN-IDEMPOTENT-01: ikinci koşu ikinci bir sürüm yaratmamalı.'
        );

        self::assertSame(
            MediaAssetStatus::Ready->value,
            DB::table('media_assets')->where('id', $mediaId)->value('status')
        );

        @unlink($binary);
    }

    /** Kuru çalıştırma: ne olacağını yazar, hiçbir şeye dokunmaz. */
    public function test_a_dry_run_reports_the_work_without_doing_it(): void
    {
        Storage::fake('local');

        [$owner, $workspaceId] = $this->ownerAndWorkspace('rescan-dry-run');
        $mediaId = $this->strandedUpload($owner, $workspaceId);

        $binary = $this->installScanner(MediaScanVerdict::Clean);

        $jobsBefore = (int) DB::table('media_processing_jobs')->where('media_asset_id', $mediaId)->count();

        Artisan::call('media:rescan-held', ['--workspace' => $workspaceId, '--dry-run' => true]);

        self::assertSame(
            MediaAssetStatus::Scanning->value,
            DB::table('media_assets')->where('id', $mediaId)->value('status'),
            'Kuru çalıştırma hiçbir durumu değiştirmemeli.'
        );

        self::assertSame(
            $jobsBefore,
            (int) DB::table('media_processing_jobs')->where('media_asset_id', $mediaId)->count(),
            'Kuru çalıştırma iş kaydı yazmamalı.'
        );

        @unlink($binary);
    }
}
