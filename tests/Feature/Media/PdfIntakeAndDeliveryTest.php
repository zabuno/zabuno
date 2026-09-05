<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Application\Media\Dto\MediaScanResult;
use App\Application\Media\Dto\MediaScanVerdict;
use App\Application\Media\Port\MalwareScannerPort;
use App\Domain\Media\MediaAssetStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * PDF ALIMI VE TESLİMİ — sahibin 2026-09-05 kararı (`docs/108` §6.2).
 *
 * MÜŞTERİ SORUNU. Restoran sahibinin alerjen tablosu, tedarikçi sözleşmesi
 * ve iç eğitim notu PDF olarak geliyor. Depoda PDF OKUYUCU zaten yazılmıştı
 * ve testliydi (`MediaViewerRegion`, `ShowMediaViewerController`,
 * `ServeMediaPreviewController`, `MediaPreviewPolicy`) — ama alım kapısı
 * PDF'i hiç kabul etmediği için o okuyucu ÖLÜ KODDU. Sahip "PDF açılsın —
 * temizleyiciyle birlikte, aynı pakette" dedi; bu dosya kararın ürün
 * yarısını dondurur.
 *
 * DÖRT SÖZ:
 *
 *   1. ZARARSIZ BİR PDF KABUL EDİLİR ve okuyucuda açılır — artık gerçek bir
 *      yükleme üzerinden, satır elle yazılarak değil.
 *   2. SALDIRI TAŞIYAN PDF HİÇBİR ŞEY SAKLANMADAN REDDEDİLİR (fail-closed,
 *      `MaliciousIntakeGateTest`in sözüyle aynı yön).
 *   3. PDF'İN TÜREVİ YOKTUR ve bu dürüstçe görünür: küçük resim uydurulmaz,
 *      dosya "işlenemedi" diye de damgalanmaz.
 *   4. ASIL BAYT BAYT KORUNUR (koşulsuz kural — bunun bir ayarı yoktur).
 *
 * Requirement IDs: MEDIA-PDF-INTAKE-01, MEDIA-PDF-FAIL-CLOSED-01,
 * MEDIA-PDF-SLOT-ALLOWLIST-01, MEDIA-PDF-NO-DERIVATIVE-HONEST-01,
 * MEDIA-PDF-ORIGINAL-PRESERVED-01.
 */
final class PdfIntakeAndDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private int $workspaceId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        // Virüs tarayıcısı bu ortamda yok; olmadığında dosya İLERLEMEZ ve
        // bu testin konusu tarayıcı değil, PDF kapısı (mevcut medya
        // testlerinin deseni).
        $this->app->instance(MalwareScannerPort::class, new class implements MalwareScannerPort
        {
            public function scan(string $diskPath): MediaScanResult
            {
                return new MediaScanResult(MediaScanVerdict::Clean);
            }
        });

        $this->owner = User::factory()->create(['email_verified_at' => now()]);
        $this->workspaceId = $this->workspaceFor($this->owner, 'owner');
    }

    private function workspaceFor(User $user, string $role): int
    {
        $id = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => 'pdf-'.Str::lower(Str::random(8)), 'state' => 'active',
            'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $id, 'user_id' => $user->id, 'role' => $role,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    /** İki sayfalık, sıkıştırılmamış, zararsız bir alerjen tablosu. */
    private function cleanPdf(): string
    {
        $drawn = 'BT /F1 12 Tf 40 800 Td (Alerjen tablosu) Tj ET';

        return "%PDF-1.4\n"
            ."1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
            ."2 0 obj << /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >> endobj\n"
            ."3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 5 0 R >> endobj\n"
            ."4 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >> endobj\n"
            .'5 0 obj << /Length '.strlen($drawn)." >> stream\n".$drawn."\nendstream endobj\n"
            ."trailer << /Root 1 0 R >>\n%%EOF\n";
    }

    private function upload(
        string $body,
        string $slot = 'document',
        string $name = 'alerjen-tablosu.pdf',
        ?User $as = null,
        ?int $workspaceId = null,
    ): TestResponse {
        $path = (string) tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($path, $body);

        return $this->actingAs($as ?? $this->owner)->withHeaders(['Accept' => 'application/json'])->post(
            '/api/workspaces/'.($workspaceId ?? $this->workspaceId).'/media',
            [
                // İstemcinin bildirdiği tür BİLEREK doğru yazılır: kapının
                // ona GÜVENMEDİĞİNİ, içeriğe baktığını başka testler
                // gösterir.
                'file' => new UploadedFile($path, $name, 'application/pdf', null, true),
                'altText' => 'Alerjen tablosu',
                'slot' => $slot,
            ],
        );
    }

    // --- MEDIA-PDF-INTAKE-01 ------------------------------------------------

    public function test_a_clean_pdf_is_accepted_into_the_document_slot(): void
    {
        $assetId = (int) $this->upload($this->cleanPdf())->assertStatus(201)->json('id');

        self::assertSame(
            MediaAssetStatus::Ready->value,
            (string) DB::table('media_assets')->where('id', $assetId)->value('status'),
            'PDF kabul edildi ama kullanılabilir olmadı; sahip belgesini yine indirip başka uygulamada açar.',
        );

        self::assertSame(
            'application/pdf',
            (string) DB::table('media_assets')->where('id', $assetId)->value('mime_type'),
            'Tür yanlış kaydedilirse okuyucu dosyayı hiç açmaz.',
        );
    }

    /**
     * MEDIA-PDF-NO-DERIVATIVE-HONEST-01.
     *
     * PDF'ten raster önizleme ÜRETİLEMEZ (imagick yok, GD PDF okumaz).
     * Ürünün burada iki dürüst seçeneği vardı: uydurma bir kapak çizmek ya
     * da "önizleme yok" demek. İkincisi seçildi.
     *
     * Ama "türev yok" ile "işlenemedi" AYNI ŞEY DEĞİLDİR: boru hattı PDF'i
     * başarısız sayarsa sahip kütüphanede kırmızı bir satır görür ve
     * yüklediği belgenin bozuk olduğunu sanır.
     */
    public function test_a_pdf_has_no_derivative_and_the_pipeline_does_not_call_that_a_failure(): void
    {
        $assetId = (int) $this->upload($this->cleanPdf())->assertStatus(201)->json('id');

        self::assertSame(
            0,
            (int) DB::table('media_renditions')
                ->join('media_versions', 'media_versions.id', '=', 'media_renditions.media_version_id')
                ->where('media_versions.media_asset_id', $assetId)
                ->count(),
            'PDF için türev doğdu; oysa bu ortamda bir PDF rasterleştirilemez.',
        );

        // İş kaydı "başarısız" DEMEZ: yapılacak bir iş yoktu.
        self::assertSame(
            0,
            (int) DB::table('media_processing_jobs')
                ->where('media_asset_id', $assetId)
                /*
                    Sütunun adı `state`, `status` DEĞİL.

                    Yerel takım SQLite ile koşuyor ve orada bu sorgu hiç
                    çalışmadan sıfır dönüyordu — yani test "başarısız iş
                    yok" diye GEÇİYOR ama aslında hiçbir şey ölçmüyordu.
                    Dağıtım hedefi PostgreSQL sütunu bulamayınca patladı ve
                    ölçümün boş olduğunu ortaya çıkardı.
                */
                ->where('state', 'failed')
                ->count(),
            'PDF "işlenemedi" diye damgalandı; sahip belgesini bozuk sanır.',
        );

        // Kütüphane satırı küçük resim UYDURMAZ: önizlemesi yoktur ve
        // ekran "henüz önizleme yok" der (`MediaLibraryRegion`).
        $row = collect((array) $this->actingAs($this->owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/workspaces/{$this->workspaceId}/media")
            ->assertOk()
            ->json('data'))
            ->firstWhere('id', $assetId);

        self::assertIsArray($row);
        self::assertNull($row['previewUrl'], 'PDF için olmayan bir küçük resim adresi uyduruldu.');
    }

    /**
     * MEDIA-PDF-ORIGINAL-PRESERVED-01 — asıl KOŞULSUZ korunur.
     *
     * Kaynaktaki "Aslını sakla" ANAHTARI bilerek yapılmadı (sahibin ikinci
     * kararı, 2026-09-05): kapatılabilir bir "aslı sakla" anahtarı,
     * kapatıldığı gün geri dönülemez bir veri kaybıdır.
     */
    public function test_the_original_pdf_stays_byte_identical(): void
    {
        $body = $this->cleanPdf();
        $assetId = (int) $this->upload($body)->assertStatus(201)->json('id');

        $diskPath = (string) DB::table('media_assets')->where('id', $assetId)->value('disk_path');

        self::assertSame(
            $body,
            (string) Storage::disk('local')->get($diskPath),
            'Asıl değiştirilmiş: "değişmedi" iddiasının kanıtı yok olur.',
        );
    }

    // --- Okuyucu artık GERÇEK bir yükleme üzerinden çalışıyor ---------------

    public function test_an_uploaded_pdf_opens_in_the_panel_reader_with_its_real_page_count(): void
    {
        $assetId = (int) $this->upload($this->cleanPdf())->assertStatus(201)->json('id');

        $this->actingAs($this->owner)->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/workspaces/{$this->workspaceId}/media/{$assetId}/viewer")
            ->assertOk()
            ->assertJsonPath('kind', 'pdf')
            ->assertJsonPath('embeddable', true)
            ->assertJsonPath('blockedReason', null)
            ->assertJsonPath('pageCount', 2)
            ->assertJsonPath('previewUrl', "/api/workspaces/{$this->workspaceId}/media/{$assetId}/preview");

        $response = $this->actingAs($this->owner)
            ->get("/api/workspaces/{$this->workspaceId}/media/{$assetId}/preview")
            ->assertOk();

        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertStringStartsWith('inline', (string) $response->headers->get('Content-Disposition'));

        // PDF bir BELGEDİR: kendi adresinden açıldığında içindeki bağlantı
        // ve stil canlanır. Denetçi alımda geçti; yine de tek savunma hattı
        // bırakılmaz.
        $csp = (string) $response->headers->get('Content-Security-Policy');
        self::assertStringContainsString("default-src 'none'", $csp);
        self::assertStringContainsString('sandbox', $csp);
    }

    // --- MEDIA-PDF-FAIL-CLOSED-01 ------------------------------------------

    public function test_every_malicious_pdf_fixture_is_rejected_in_the_slot_where_pdf_is_allowed(): void
    {
        /*
            `MaliciousIntakeGateTest` her fixture'ı `itemImage` slotuna
            gönderir ve PDF orada zaten yasaktır — yani o kapı PDF
            fixture'larını SLOT kuralıyla durdurur, denetçiyle değil. Asıl
            soru şudur: PDF'in SERBEST olduğu slotta da duruyorlar mı?
        */
        $fixtures = glob(base_path('tests/fixtures/malicious/*.pdf')) ?: [];

        self::assertGreaterThanOrEqual(5, count($fixtures), 'Kapının koruyacağı PDF fixture\'ı yok.');

        foreach ($fixtures as $path) {
            $name = basename($path);

            $this->upload((string) file_get_contents($path), 'document', $name)->assertStatus(422);

            self::assertSame(0, DB::table('media_assets')->count(), "MALICIOUS-PDF: `{$name}` için satır doğdu.");
            self::assertSame([], Storage::disk('local')->allFiles(), "MALICIOUS-PDF: `{$name}` karantinaya yazıldı.");
        }
    }

    public function test_a_truncated_pdf_is_refused_instead_of_being_trusted(): void
    {
        // Okunamayan gövde "temiz" değildir; hakkında hiçbir şey
        // bilmediğimiz gövdedir (fail-closed).
        $this->upload("%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n")->assertStatus(422);

        self::assertSame(0, DB::table('media_assets')->count());
        self::assertSame([], Storage::disk('local')->allFiles());
    }

    /**
     * MIME VE İLK BAYT, İKİSİ BİRDEN.
     *
     * Uzantı da istemcinin bildirdiği tür de YÜKLEYENİN denetimindedir.
     * Karar dosyanın kendi baytlarına aittir (MEDIA-INTAKE-MIME-SPOOF-
     * REJECT-01 deseninin devamı).
     */
    public function test_a_file_that_only_claims_to_be_a_pdf_is_rejected(): void
    {
        $this->upload("<?php system(\$_GET['c']); ?>\n", 'document', 'fatura.pdf')->assertStatus(422);

        self::assertSame(0, DB::table('media_assets')->count());
        self::assertSame([], Storage::disk('local')->allFiles());
    }

    // --- MEDIA-PDF-SLOT-ALLOWLIST-01 ---------------------------------------

    public function test_a_photographic_slot_still_refuses_a_pdf(): void
    {
        // Bir PDF yemek fotoğrafı değildir; `itemImage` slotunda kabul
        // etmek slot politikasının söylediğiyle çelişirdi (INV-04).
        $this->upload($this->cleanPdf(), 'itemImage')->assertStatus(422);

        self::assertSame(0, DB::table('media_assets')->count());
    }

    public function test_the_document_slot_refuses_a_photograph(): void
    {
        $photo = UploadedFile::fake()->image('menemen.jpg', 1200, 1200);

        $this->actingAs($this->owner)->withHeaders(['Accept' => 'application/json'])->post(
            "/api/workspaces/{$this->workspaceId}/media",
            ['file' => $photo, 'altText' => 'Menemen', 'slot' => 'document'],
        )->assertStatus(422);

        self::assertSame(0, DB::table('media_assets')->count());
    }

    /**
     * SİHİRBAZ, DOLDURAMAYACAĞI BİR YERİ TEKLİF ETMEZ.
     *
     * `/api/media/slot-policies` görsel yükleme sihirbazının kaynağıdır ve
     * o sihirbaz dosyayı `accept="image/*"` ile seçtirir, ölçüsünü okur,
     * kırpar, küçültür — hiçbiri bir PDF'te yapılamaz. Belge slotunu o
     * listeye koymak, kullanıcıya yer seçtirip sonra dosya seçtirmemek
     * olurdu. Kapı sunucuda AÇIKTIR; panelde belge yükleme yolu ayrı bir
     * pakettir ve bu test o sınırın kaydıdır.
     */
    public function test_the_image_wizard_does_not_offer_a_slot_it_cannot_fill(): void
    {
        $keys = collect((array) $this->actingAs($this->owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->getJson('/api/media/slot-policies')
            ->assertOk()
            ->json('slots'))
            ->pluck('key')
            ->all();

        self::assertNotContains('document', $keys, 'Sihirbaz, PDF seçtiremediği bir yeri teklif ediyor.');
        self::assertContains('logo', $keys, 'Görsel slotları listeden düştü; sihirbaz çalışmaz hâle gelir.');
    }

    // --- Kiracı yalıtımı ve izin kapısı (mevcut medya testleriyle aynı desen) ---

    public function test_a_foreign_workspace_owner_cannot_upload_a_pdf_into_our_workspace(): void
    {
        $stranger = User::factory()->create(['email_verified_at' => now()]);
        $this->workspaceFor($stranger, 'owner');

        // 404, 403 DEĞİL: "yasak" cevabı bile kaynağın var olduğunu söyler.
        $this->upload($this->cleanPdf(), 'document', 'gizli.pdf', $stranger)->assertStatus(404);

        self::assertSame(0, DB::table('media_assets')->count());
    }

    public function test_a_read_only_member_cannot_upload_a_pdf(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $this->workspaceId, 'user_id' => $member->id, 'role' => 'member',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->upload($this->cleanPdf(), 'document', 'salt-okunur.pdf', $member)->assertStatus(403);

        self::assertSame(0, DB::table('media_assets')->count());
    }

    public function test_another_tenants_pdf_is_not_visible_in_our_workspace(): void
    {
        $assetId = (int) $this->upload($this->cleanPdf())->assertStatus(201)->json('id');

        $stranger = User::factory()->create(['email_verified_at' => now()]);
        $strangerWorkspace = $this->workspaceFor($stranger, 'owner');

        $this->actingAs($stranger)->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/workspaces/{$strangerWorkspace}/media/{$assetId}/viewer")
            ->assertStatus(404);

        $this->actingAs($stranger)
            ->get("/api/workspaces/{$strangerWorkspace}/media/{$assetId}/preview")
            ->assertStatus(404);
    }
}
