<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * MEDYA AYARLARI — kanonik kaynak `docs/reference/media-manager/
 * Medya Yonetimi v2.dc.html` (ekran etiketi "Ayarlar"), somut listeler
 * `docs/108` §6.5 (desen alanları) ve §6.6 (güvenlik anahtarları).
 *
 * BU UÇ SALT OKUNURDUR ve bu bir eksiklik değil, ekranın SÖZÜDÜR.
 *
 * Sahibin kararı (2026-09-05): "Uygulanmayan bir anahtarı çalışıyormuş gibi
 * göstermek yasak." Bir ayar ekranındaki her kontrol bir SÖZDÜR: kullanıcı
 * onu çevirdiğinde bir şeyin değişeceğini söyler. Bu depoda desenler
 * DEĞİŞTİRİLEMEZ ve güvenlik önlemleri KAPATILAMAZ; o yüzden uç, her satır
 * için durumu ve "değiştirilebilir mi" sorusunun cevabını AÇIKÇA gönderir.
 * Ekran da kaydetme kutusu çizmez.
 *
 * VİRÜS TARAMASI — sahibin ayrı kararı: GÖSTERİLİR ama KAPATILAMAZ.
 * Kapatılabilir bir güvenlik anahtarı, kapatıldığı gün bir güvenlik
 * açığıdır. Durumu okunur: "açık" ya da "bu ortamda çalışmıyor".
 *
 * Gereksinim: MEDIA-SETTINGS-PATTERNS-READONLY-01,
 * MEDIA-SETTINGS-SCAN-NOT-SWITCHABLE-02, MEDIA-SETTINGS-HONEST-STATE-03,
 * MEDIA-SETTINGS-TENANT-04.
 */
final class MediaSettingsEndpointTest extends TestCase
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

    /** @return array<string, array<string, mixed>> */
    private function byKey(mixed $rows): array
    {
        $map = [];

        foreach ((array) $rows as $row) {
            $map[(string) $row['key']] = (array) $row;
        }

        return $map;
    }

    // --- MEDIA-SETTINGS-PATTERNS-READONLY-01 ------------------------------

    /**
     * Desen alanları OKUNUR, seçilemez — ve neden seçilemediği söylenir.
     *
     * Kaynak üç seçim sunuyor (dizin yapısı, dosya adı, tarih biçimi). Bu
     * depoda ilk ikisi DEPOLAMA ANAHTARIDIR ve anahtar asla değişmez
     * (`2026_08_27_000400` göçünün kendi cümlesi: "Depolama anahtarı ASLA
     * değişmez; kullanıcının gördüğü ad değişir"). Bir dizin deseni seçtirip
     * hiçbir dosyayı taşımamak, sahibe olmayan bir yetenek satmaktır.
     */
    public function test_pattern_fields_are_reported_as_fixed_and_never_as_a_choice(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'medya-ayar-desen');

        $response = $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/media/settings");

        $response->assertOk();

        $patterns = $this->byKey($response->json('patterns'));

        self::assertSame(['directory', 'fileName', 'date'], array_keys($patterns));

        foreach ($patterns as $key => $pattern) {
            self::assertFalse(
                (bool) $pattern['changeable'],
                "MEDIA-SETTINGS-PATTERNS-READONLY-01: `{$key}` deseni bu depoda seçilemez; seçtirmek yalan olurdu."
            );
        }

        // Değer, GERÇEK davranışın adıdır — kaynağın seçenek adı değil.
        self::assertSame('workspaceFolder', (string) $patterns['directory']['value']);
        self::assertSame('opaqueKey', (string) $patterns['fileName']['value']);
        self::assertSame('deviceLocale', (string) $patterns['date']['value']);
    }

    // --- MEDIA-SETTINGS-SCAN-NOT-SWITCHABLE-02 ----------------------------

    /**
     * Virüs taraması GÖSTERİLİR, KAPATILAMAZ (sahip kararı 2026-09-05).
     *
     * Tarayıcı bu ortamda bağlı değilse durum `unavailable` döner. Bunu
     * "kapalı" diye göstermek iki ayrı şeyi karıştırırdı: bir kullanıcı
     * kararını ve bir ortam gerçeğini.
     */
    public function test_virus_scan_is_visible_but_never_switchable(): void
    {
        config()->set('media.scanner.driver', 'unavailable');

        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'medya-ayar-tarama');

        $security = $this->byKey(
            $this->actingAs($owner)
                ->withHeaders(['Accept' => 'application/json'])
                ->get("/api/workspaces/{$workspaceId}/media/settings")
                ->json('security')
        );

        self::assertArrayHasKey('virusScan', $security);
        self::assertFalse(
            (bool) $security['virusScan']['switchable'],
            'MEDIA-SETTINGS-SCAN-NOT-SWITCHABLE-02: kapatılabilir bir güvenlik anahtarı, kapatıldığı gün bir açıktır.'
        );
        self::assertSame('unavailable', (string) $security['virusScan']['state']);
    }

    /** Tarayıcı gerçekten çalıştırılabiliyorsa durum "açık" olur. */
    public function test_virus_scan_reads_as_on_when_a_real_binary_is_configured(): void
    {
        $binary = tempnam(sys_get_temp_dir(), 'clamscan');
        self::assertIsString($binary);
        chmod($binary, 0o755);

        config()->set('media.scanner.driver', 'clamav');
        config()->set('media.scanner.clamav.binary_path', $binary);

        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'medya-ayar-tarama-acik');

        $security = $this->byKey(
            $this->actingAs($owner)
                ->withHeaders(['Accept' => 'application/json'])
                ->get("/api/workspaces/{$workspaceId}/media/settings")
                ->json('security')
        );

        self::assertSame('on', (string) $security['virusScan']['state']);

        @unlink($binary);
    }

    /**
     * Sürücü `clamav` yazsa bile ÇALIŞTIRILABİLİR bir ikili yoksa durum
     * "bu ortamda çalışmıyor"dur.
     *
     * `ClamavMalwareScanner` bu durumda her dosya için `Indeterminate`
     * döner; yani tarama fiilen yapılmaz. Ekranda "açık" yazmak, sahibe
     * olmayan bir korumayı vaat etmek olurdu.
     */
    public function test_a_misconfigured_scanner_binary_reads_as_unavailable(): void
    {
        config()->set('media.scanner.driver', 'clamav');
        config()->set('media.scanner.clamav.binary_path', '/bin/kesinlikle-olmayan-clamscan');

        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'medya-ayar-tarama-bozuk');

        $security = $this->byKey(
            $this->actingAs($owner)
                ->withHeaders(['Accept' => 'application/json'])
                ->get("/api/workspaces/{$workspaceId}/media/settings")
                ->json('security')
        );

        self::assertSame('unavailable', (string) $security['virusScan']['state']);
    }

    // --- MEDIA-SETTINGS-HONEST-STATE-03 -----------------------------------

    /**
     * Kaynağın beş anahtarının HER BİRİ gerçek durumuyla döner.
     *
     * Bugünün gerçeği, tek tek doğrulandı:
     *
     *   - `contentSignature`: `StoreMediaRequest` dosyanın kendi ilk
     *     baytına bakar; uzantıya ve istemcinin MIME'ına asla güvenmez.
     *     Uygulanıyor, kapatılamaz.
     *   - `metadataStrip`: `partial`. Türevler GD ile YENİDEN kodlanır ve
     *     EXIF taşımaz — misafirin gördüğü dosyada konum yoktur. Ama ASIL
     *     dosya geldiği gibi saklanır. "Tamamen temizleniyor" demek
     *     yalan olurdu.
     *   - `signedLink`: asıl dosyanın herkese açık adresi yoktur; indirme
     *     10 dakikalık imzalı adresle olur. Uygulanıyor, kapatılamaz.
     *   - `watermark`: bu depoda filigran diye bir kod YOKTUR. Durum
     *     `missing` — ekran "henüz yok" der, anahtar çizmez.
     */
    public function test_every_security_measure_reports_the_state_the_repository_actually_has(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'medya-ayar-durustluk');

        $security = $this->byKey(
            $this->actingAs($owner)
                ->withHeaders(['Accept' => 'application/json'])
                ->get("/api/workspaces/{$workspaceId}/media/settings")
                ->json('security')
        );

        self::assertSame(
            ['virusScan', 'contentSignature', 'metadataStrip', 'signedLink', 'watermark'],
            array_keys($security),
            'MEDIA-SETTINGS-HONEST-STATE-03: kaynağın beş anahtarı, kaynağın sırasıyla.'
        );

        self::assertSame('on', (string) $security['contentSignature']['state']);
        self::assertSame('partial', (string) $security['metadataStrip']['state']);
        self::assertSame('on', (string) $security['signedLink']['state']);
        self::assertSame('missing', (string) $security['watermark']['state']);

        foreach ($security as $key => $measure) {
            self::assertFalse(
                (bool) $measure['switchable'],
                "MEDIA-SETTINGS-HONEST-STATE-03: `{$key}` bugün bir ayara bağlı değil; anahtar çizmek yalan olurdu."
            );
        }
    }

    // --- MEDIA-SETTINGS-TENANT-04 -----------------------------------------

    /** Üye olmayan 404 görür; 403 "böyle bir kiracı var" derdi. */
    public function test_settings_are_hidden_from_strangers(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'medya-ayar-yabanci');
        $stranger = $this->verifiedUser();

        $this->actingAs($stranger)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/media/settings")
            ->assertNotFound();
    }
}
