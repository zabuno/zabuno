<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * "YERİ NE DOLDURUYOR?" — kanonik kaynak `docs/reference/media-manager/
 * Medya Yonetimi v2.dc.html` (ekran etiketi "Kota ve çöp"), somut liste
 * `docs/108` §6.4.
 *
 * MÜŞTERİ SORUNU. Kota şeridi bugün tek bir cümle söylüyor: "185 MB / 200
 * MB". Restoran sahibi bunu okuduğunda ne yapacağını BİLMİYOR. Hangi
 * dosyaları silsin? Ürün fotoğrafları mı yer kaplıyor, yoksa geçen yıl
 * taranmış kâğıt menü mü? Tek bir toplam, "dolu" demekten başka bir şey
 * söylemez; kırılım ise bir EYLEM önerir.
 *
 * KATEGORİ EŞLEMESİ — kararın gerekçesi (bu depoda yeniden türetilmesin):
 *
 *   - Kırılım `media_assets.slot` üzerinden yapılır. Slot, bu depoda bir
 *     satırın NE İŞE YARADIĞINI söyleyen TEK sütundur ve `(workspace_id,
 *     slot)` indekslidir. Kaynağın kategori adları da ("Ürünler",
 *     "Kampanyalar") bir BİÇİM değil bir AMAÇ adıdır.
 *   - `mime_type` ile kırmak "3 GB JPEG" derdi; bu, sahibin hangi dosyayı
 *     sileceğine dair hiçbir şey söylemez.
 *   - `asset_kind` sütunu KULLANILMAZ: göçte `default('image')` ile
 *     eklendi ve bugün hiçbir kod ona yazmıyor. Onunla kırmak, hepsi aynı
 *     şeyi söyleyen beş satır çizerdi.
 *   - Kaynağın "Video" satırı ÇİZİLMEZ: bu depo video kabul etmez (her
 *     slotun `formats` listesi görsel biçimlerdir ve alım kapısı
 *     JPEG/PNG/GIF/WebP/SVG dışını reddeder). Kalıcı olarak sıfır kalacak
 *     bir satır, olmayan bir yeteneğe güvendirir.
 *
 * ÇÖP AYRI DURUR ve kategori toplamlarına GİRMEZ. Sebebi tek: çöp, sahibin
 * BUGÜN geri kazanabileceği tek dilimdir. Ürün fotoğraflarının yanına
 * karıştırılırsa "boşaltılabilir yer" görünmez olur.
 *
 * Gereksinim: MEDIA-STORAGE-BREAKDOWN-01, MEDIA-STORAGE-TRASH-SEPARATE-02,
 * MEDIA-STORAGE-PURGED-EXCLUDED-03, MEDIA-STORAGE-TENANT-04,
 * MEDIA-STORAGE-TOTALS-05.
 */
final class MediaStorageBreakdownTest extends TestCase
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

    /**
     * Satır DOĞRUDAN yazılır: bu uç baytları SAYAR, dosya işlemez. Gerçek
     * bir JPEG üretmek testi yavaşlatır ve ölçtüğü şeyi değiştirmez.
     */
    private function asset(int $workspaceId, string $slot, int $bytes, string $lifecycle = 'active'): int
    {
        return (int) DB::table('media_assets')->insertGetId([
            'workspace_id' => $workspaceId,
            'disk_path' => 'quarantine/'.$workspaceId.'/'.bin2hex(random_bytes(6)),
            'original_name' => 'kebap.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => $bytes,
            'alt_text' => 'Adana kebap',
            'slot' => $slot,
            'status' => 'accepted',
            'processing_status' => 'ready',
            'lifecycle_status' => $lifecycle,
            'visibility' => 'private',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<string, array<string, int>> */
    private function categoriesByKey(mixed $categories): array
    {
        $map = [];

        foreach ((array) $categories as $row) {
            $map[(string) $row['key']] = ['bytes' => (int) $row['bytes'], 'assets' => (int) $row['assets']];
        }

        return $map;
    }

    // --- MEDIA-STORAGE-BREAKDOWN-01 ---------------------------------------

    /**
     * Slot AMACA çevrilir: sahip "itemImage" değil "Ürünler" okur.
     *
     * Bir restoran sahibinin yolculuğu: 200 MB'lık planında yer bitiyor.
     * Kırılım "Ürünler 12 MB · Belgeler 40 MB" diyorsa cevabı bulmuştur —
     * yer kaplayan şey yemek fotoğrafı değil, geçen yıl taranmış kâğıt
     * menüdür ve o dosyaya artık ihtiyacı yoktur.
     */
    public function test_breakdown_groups_slots_into_owner_readable_categories(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'yer-kirilim-kategori');

        $this->asset($workspaceId, 'itemImage', 3_000_000);
        $this->asset($workspaceId, 'gallery', 1_000_000);
        $this->asset($workspaceId, 'cover', 2_000_000);
        $this->asset($workspaceId, 'logo', 500_000);
        $this->asset($workspaceId, 'menuImportSource', 8_000_000);

        $response = $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/media/storage-breakdown");

        $response->assertOk();

        $categories = $this->categoriesByKey($response->json('categories'));

        self::assertSame(
            ['bytes' => 4_000_000, 'assets' => 2],
            $categories['products'] ?? [],
            'MEDIA-STORAGE-BREAKDOWN-01: `itemImage` ve `gallery` misafirin gördüğü ÜRÜN fotoğraflarıdır.'
        );
        self::assertSame(['bytes' => 2_000_000, 'assets' => 1], $categories['promotion'] ?? []);
        self::assertSame(['bytes' => 500_000, 'assets' => 1], $categories['brand'] ?? []);
        self::assertSame(
            ['bytes' => 8_000_000, 'assets' => 1],
            $categories['documents'] ?? [],
            'MEDIA-STORAGE-BREAKDOWN-01: taranmış kâğıt menü bir ÇALIŞMA BELGESİDİR, ürün fotoğrafı değil.'
        );

        // En büyük dilim BAŞTA: sahip listeyi tarayarak değil, ilk satırı
        // okuyarak karar verir.
        self::assertSame(
            'documents',
            (string) $response->json('categories.0.key'),
            'MEDIA-STORAGE-BREAKDOWN-01: satırlar bayta göre azalan sıradadır.'
        );

        // Kaynağın "Video" satırı burada YOKTUR ve olmaması bilinçlidir.
        self::assertNotContains('video', array_keys($categories));
    }

    /**
     * Hiç dosyası olmayan kategori satırı ÇİZİLMEZ.
     *
     * Sıfır baytlık dört satır, "bende hiçbir şey yok" cümlesini dört kez
     * söyler ve gerçek dilimi görsel gürültüye gömer.
     */
    public function test_categories_with_no_bytes_are_not_reported(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'yer-kirilim-bos');

        $this->asset($workspaceId, 'itemImage', 1_000_000);

        $categories = $this->categoriesByKey(
            $this->actingAs($owner)
                ->withHeaders(['Accept' => 'application/json'])
                ->get("/api/workspaces/{$workspaceId}/media/storage-breakdown")
                ->json('categories')
        );

        self::assertSame(['products'], array_keys($categories));
    }

    // --- MEDIA-STORAGE-TRASH-SEPARATE-02 ----------------------------------

    /**
     * Çöp KENDİ satırındadır ve kategori toplamlarına girmez.
     *
     * Kota çöpü İÇERİR (`config/media-quota.php`: silmek yer açmaz, kalıcı
     * silme açar). Bu yüzden çöp, kırılımda "geri kazanılabilir yer" olarak
     * ayrı durmalıdır — yoksa sahip toplamı görür ama elindeki tek
     * düğmeyi göremez.
     */
    public function test_trash_is_reported_separately_and_never_inside_a_category(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'yer-kirilim-cop');

        $this->asset($workspaceId, 'itemImage', 1_000_000);
        $this->asset($workspaceId, 'itemImage', 4_000_000, 'trashed');

        $response = $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/media/storage-breakdown");

        $categories = $this->categoriesByKey($response->json('categories'));

        self::assertSame(
            ['bytes' => 1_000_000, 'assets' => 1],
            $categories['products'] ?? [],
            'MEDIA-STORAGE-TRASH-SEPARATE-02: çöpteki dosya ürün diliminde SAYILMAZ.'
        );

        self::assertSame(4_000_000, (int) $response->json('trash.bytes'));
        self::assertSame(1, (int) $response->json('trash.assets'));
    }

    // --- MEDIA-STORAGE-PURGED-EXCLUDED-03 ---------------------------------

    /**
     * Kalıcı silinen (`purged`) satır hiçbir yerde sayılmaz.
     *
     * Satır denetim için durur, dosyası gitmiştir. Onu saymak, sahibin
     * artık var olmayan bir baytı silmeye çalışmasına yol açardı.
     */
    public function test_purged_rows_are_counted_nowhere(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'yer-kirilim-purged');

        $this->asset($workspaceId, 'itemImage', 1_000_000);
        $this->asset($workspaceId, 'itemImage', 9_000_000, 'purged');

        $response = $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/media/storage-breakdown");

        $categories = $this->categoriesByKey($response->json('categories'));

        self::assertSame(['bytes' => 1_000_000, 'assets' => 1], $categories['products'] ?? []);
        self::assertSame(0, (int) $response->json('trash.bytes'));
    }

    // --- MEDIA-STORAGE-TOTALS-05 ------------------------------------------

    /**
     * Kota KARTLARI aynı uçtan gelir ve sunucunun sınırlarını taşır.
     *
     * Kartlar kaynağın §6.4 listesidir; ama depoda karşılığı olan yalnız
     * DEPOLAMA ve DOSYA SAYISIDIR. "Dönüştürme" ve "CDN trafiği" için bu
     * depoda ne sayaç ne sınır vardır; uç onları HİÇ göndermez, ekran da
     * çizmez.
     */
    public function test_totals_carry_the_real_limits_and_nothing_that_is_not_measured(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'yer-kirilim-toplam');

        $this->asset($workspaceId, 'itemImage', 1_000_000);
        $this->asset($workspaceId, 'itemImage', 4_000_000, 'trashed');

        $response = $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/media/storage-breakdown");

        // Kota çöpü İÇERİR: toplam beş milyon bayt, iki dosya.
        self::assertSame(5_000_000, (int) $response->json('totals.bytesUsed'));
        self::assertSame(200 * 1024 * 1024, (int) $response->json('totals.bytesLimit'));
        self::assertSame(2, (int) $response->json('totals.assetsUsed'));
        self::assertSame(100, (int) $response->json('totals.assetsLimit'));
        self::assertSame('Free', (string) $response->json('totals.planLabel'));

        $body = (array) $response->json();

        self::assertArrayNotHasKey('conversions', $body, 'MEDIA-STORAGE-TOTALS-05: sayılmayan kart gönderilmez.');
        self::assertArrayNotHasKey('cdn', $body, 'MEDIA-STORAGE-TOTALS-05: bu depoda CDN diye bir şey yok.');
    }

    // --- MEDIA-STORAGE-TENANT-04 ------------------------------------------

    /** Başka kiracının baytı görünmez; üye olmayan 404 görür. */
    public function test_breakdown_is_tenant_scoped_and_hidden_from_strangers(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'yer-kirilim-kiraci-a');
        $other = $this->verifiedUser();
        $otherWorkspaceId = $this->ownerWorkspace($other, 'yer-kirilim-kiraci-b');

        $this->asset($workspaceId, 'itemImage', 1_000_000);
        $this->asset($otherWorkspaceId, 'itemImage', 7_000_000);

        $categories = $this->categoriesByKey(
            $this->actingAs($owner)
                ->withHeaders(['Accept' => 'application/json'])
                ->get("/api/workspaces/{$workspaceId}/media/storage-breakdown")
                ->json('categories')
        );

        self::assertSame(['bytes' => 1_000_000, 'assets' => 1], $categories['products'] ?? []);

        $stranger = $this->verifiedUser();

        $this->actingAs($stranger)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/media/storage-breakdown")
            ->assertNotFound();
    }
}
