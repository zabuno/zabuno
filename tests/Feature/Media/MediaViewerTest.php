<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * GÖRÜNTÜLE — dosya türüne göre okuyucu (kanonik kaynak:
 * `docs/reference/media-manager/Medya Yonetimi v2.dc.html`, ekran etiketi
 * "Görüntüle"; sıra gerekçesi `docs/108` §3 madde 8).
 *
 * Kullanıcı yolculuğu: restoran sahibi kütüphanede "alerjen-tablosu.pdf"
 * satırını görüyor ve içinde ne yazdığını okumak istiyor. Bugün yapabildiği
 * tek şey dosyayı İNDİRMEK: telefonunda indirilenler klasörüne düşen bir
 * dosyayı ayrı bir uygulamada açıyor, panele geri dönüyor, hangi dosyaya
 * baktığını unutuyor. "Görüntüle" o dosyayı panelin içinde açar.
 *
 * Bu dosya ÜÇ sözü dondurur ve üçü de güvenlik/dürüstlük sözüdür:
 *
 *   1. TARAMADAN GEÇMEMİŞ DOSYA PANELDE AÇILMAZ. Karantinadaki bir dosya
 *      tanım gereği "henüz temiz olduğunu bilmediğimiz" dosyadır; onu
 *      tarayıcıya gömmek, taramanın var oluş sebebini ortadan kaldırırdı.
 *   2. GÖMÜLEN DOSYA BETİK ÇALIŞTIRAMAZ. PDF ve SVG birer BELGEDİR: kendi
 *      adresinden açıldığında içindeki bağlantı ve stil canlanır. Yanıt
 *      `sandbox` + `default-src 'none'` ile gelir ve yalnız kendi
 *      kökenimizden çerçevelenebilir (`frame-ancestors 'self'`) —
 *      `ServeOriginalController`/`ServeRenditionController` deseninin
 *      devamı (FF-134).
 *   3. SAYFA SAYISI UYDURULMAZ. PDF'in sayfa sayısı ancak dosyanın kendi
 *      baytlarından İKİ AYRI işaret aynı sayıyı söylediğinde bildirilir;
 *      aksi halde `null` döner ve ekran sayfa gezintisi çizmez. "1 / 12"
 *      yazıp 12'yi bilmemek, kullanıcıya olmayan bir kesinlik satmaktır.
 */
final class MediaViewerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private int $workspaceId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->owner = User::factory()->create(['email_verified_at' => now()]);
        $this->workspaceId = $this->workspace($this->owner);
    }

    private function workspace(User $owner): int
    {
        $id = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => 'viewer-'.Str::lower(Str::random(6)), 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $id, 'user_id' => $owner->id, 'role' => 'owner',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * Varlığı ALIM KAPISINDAN GEÇMEDEN yazar.
     *
     * Bilinçli: alım kapısı bugün yalnız JPEG/PNG/GIF/WebP ve (vektör
     * slotlarında) SVG kabul ediyor — bir PDF ürünün içine hiç giremiyor
     * (`StoreMediaRequest`). Okuyucunun PDF dalını ancak satırı doğrudan
     * yazarak sınayabiliriz; bu testin kendisi de o engelin kaydıdır.
     */
    private function asset(string $mime, string $name, string $status, string $body, ?int $workspaceId = null): int
    {
        $path = 'media/'.Str::lower(Str::random(10));
        Storage::disk('local')->put($path, $body);

        return (int) DB::table('media_assets')->insertGetId([
            'workspace_id' => $workspaceId ?? $this->workspaceId,
            'disk_path' => $path,
            'original_name' => $name,
            'mime_type' => $mime,
            'size_bytes' => strlen($body),
            'alt_text' => 'Alerjen tablosu',
            'slot' => 'itemImage',
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function api(?User $user = null)
    {
        return $this->actingAs($user ?? $this->owner)->withHeaders(['Accept' => 'application/json']);
    }

    /** İki sayfalı, sıkıştırılmamış, elle yazılmış en küçük PDF. */
    private function twoPagePdf(): string
    {
        return "%PDF-1.4\n"
            ."1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
            ."2 0 obj << /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >> endobj\n"
            ."3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] >> endobj\n"
            ."4 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] >> endobj\n"
            ."trailer << /Root 1 0 R >>\n%%EOF\n";
    }

    #[Test]
    public function test_a_scanned_image_can_be_opened_in_the_panel(): void
    {
        $id = $this->asset('image/jpeg', 'kunefe-tepsi.jpg', 'ready', 'binary-jpeg-bytes');

        $facts = $this->api()->getJson("/api/workspaces/{$this->workspaceId}/media/{$id}/viewer");

        $facts->assertOk()
            ->assertJsonPath('kind', 'image')
            ->assertJsonPath('embeddable', true)
            ->assertJsonPath('blockedReason', null)
            ->assertJsonPath('mimeType', 'image/jpeg')
            ->assertJsonPath('originalName', 'kunefe-tepsi.jpg')
            // Sayfa sayısı GÖRSELDE anlamsızdır; sıfır ya da bir yazmak
            // olmayan bir kavramı varmış gibi gösterirdi.
            ->assertJsonPath('pageCount', null)
            ->assertJsonPath('previewUrl', "/api/workspaces/{$this->workspaceId}/media/{$id}/preview");
    }

    #[Test]
    public function test_the_panel_preview_is_served_inline_and_cannot_run_a_script(): void
    {
        $id = $this->asset('image/jpeg', 'kunefe-tepsi.jpg', 'ready', 'binary-jpeg-bytes');

        $response = $this->api()->get("/api/workspaces/{$this->workspaceId}/media/{$id}/preview");

        $response->assertOk();
        $this->assertSame('binary-jpeg-bytes', $response->getContent());
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
        // `inline`: dosya panelin İÇİNDE açılır. `attachment` olsaydı
        // görüntüleyici her seçimde bir indirme başlatırdı.
        $this->assertStringStartsWith('inline', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        // Symfony yönergeleri kendi sırasına dizer; sözleşme SIRA değil,
        // "ne tarayıcı ne ara önbellek saklasın" kararıdır.
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertStringContainsString('sandbox', $csp);
        // Kendi panelimiz çerçeveleyebilsin diye `frame-ancestors 'self'`;
        // `SecurityHeaders` ara katmanının varsayılan `X-Frame-Options: DENY`
        // başlığı çerçeveyi tamamen kapatırdı, o yüzden burada AÇIKÇA
        // `SAMEORIGIN` yazılır (ara katman var olan başlığı ezmez).
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
    }

    #[Test]
    public function test_a_file_that_has_not_passed_the_scan_is_not_opened_in_the_panel(): void
    {
        $id = $this->asset('image/jpeg', 'yeni.jpg', 'quarantined', 'binary-jpeg-bytes');

        $this->api()->getJson("/api/workspaces/{$this->workspaceId}/media/{$id}/viewer")
            ->assertOk()
            ->assertJsonPath('embeddable', false)
            ->assertJsonPath('blockedReason', 'scan')
            ->assertJsonPath('previewUrl', null);

        // Uç, ekranın kararına GÜVENMEZ: adresi elle çağıran da alamaz.
        $this->api()->get("/api/workspaces/{$this->workspaceId}/media/{$id}/preview")
            ->assertStatus(409);
    }

    #[Test]
    public function test_a_type_the_panel_cannot_open_is_said_so_instead_of_being_served(): void
    {
        $id = $this->asset('text/csv', 'stok.csv', 'ready', "ad,adet\nayran,4\n");

        $this->api()->getJson("/api/workspaces/{$this->workspaceId}/media/{$id}/viewer")
            ->assertOk()
            ->assertJsonPath('kind', 'other')
            ->assertJsonPath('embeddable', false)
            ->assertJsonPath('blockedReason', 'type')
            ->assertJsonPath('previewUrl', null);

        $this->api()->get("/api/workspaces/{$this->workspaceId}/media/{$id}/preview")
            ->assertStatus(415);
    }

    #[Test]
    public function test_a_pdf_page_count_is_reported_only_when_the_bytes_really_say_it(): void
    {
        $readable = $this->asset('application/pdf', 'alerjen-tablosu.pdf', 'ready', $this->twoPagePdf());

        $this->api()->getJson("/api/workspaces/{$this->workspaceId}/media/{$readable}/viewer")
            ->assertOk()
            ->assertJsonPath('kind', 'pdf')
            ->assertJsonPath('embeddable', true)
            ->assertJsonPath('pageCount', 2);

        /*
            SIKIŞTIRILMIŞ NESNE AKIŞI (PDF 1.5+) sayfa ağacını baytların
            içinde okunmaz kılar. Kütüphane kurmadan burada tahmin yürütmek
            yanlış bir sayı üretmenin en kısa yoludur; ürün "bilmiyorum"
            der ve ekran sayfa gezintisini hiç çizmez.
        */
        $opaque = $this->asset('application/pdf', 'sikistirilmis.pdf', 'ready', "%PDF-1.5\n1 0 obj << /Type /ObjStm /N 4 /First 20 >> stream\nzzzz\nendstream endobj\n%%EOF\n");

        $this->api()->getJson("/api/workspaces/{$this->workspaceId}/media/{$opaque}/viewer")
            ->assertOk()
            ->assertJsonPath('kind', 'pdf')
            ->assertJsonPath('embeddable', true)
            ->assertJsonPath('pageCount', null);
    }

    #[Test]
    public function test_an_svg_preview_carries_the_same_no_script_headers(): void
    {
        $id = $this->asset('image/svg+xml', 'logo.svg', 'ready', '<svg xmlns="http://www.w3.org/2000/svg"><rect width="4" height="4"/></svg>');

        $response = $this->api()->get("/api/workspaces/{$this->workspaceId}/media/{$id}/preview");

        $response->assertOk();
        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertStringContainsString('sandbox', $csp);
        // SVG'nin KENDİ satır içi stili çalışsın diye tek istisna
        // (`ServeRenditionController` ile aynı taviz); betik yine yasak.
        $this->assertStringContainsString("style-src 'unsafe-inline'", $csp);
    }

    #[Test]
    public function test_another_tenants_file_is_not_found(): void
    {
        $stranger = User::factory()->create(['email_verified_at' => now()]);
        $strangerWorkspace = $this->workspace($stranger);
        $id = $this->asset('image/jpeg', 'gizli.jpg', 'ready', 'binary-jpeg-bytes', $strangerWorkspace);

        $this->api()->getJson("/api/workspaces/{$strangerWorkspace}/media/{$id}/viewer")->assertStatus(404);
        $this->api()->get("/api/workspaces/{$strangerWorkspace}/media/{$id}/preview")->assertStatus(404);

        // Kendi çalışma alanının adresine YABANCI bir varlık kimliği
        // koymak da bir sızıntı yolu olurdu.
        $this->api()->getJson("/api/workspaces/{$this->workspaceId}/media/{$id}/viewer")->assertStatus(404);
    }

    #[Test]
    public function test_a_trashed_file_is_not_opened_in_the_panel(): void
    {
        $id = $this->asset('image/jpeg', 'silinmis.jpg', 'ready', 'binary-jpeg-bytes');
        DB::table('media_assets')->where('id', $id)->update(['deleted_at' => now()]);

        $this->api()->getJson("/api/workspaces/{$this->workspaceId}/media/{$id}/viewer")->assertStatus(404);
        $this->api()->get("/api/workspaces/{$this->workspaceId}/media/{$id}/preview")->assertStatus(404);
    }
}
