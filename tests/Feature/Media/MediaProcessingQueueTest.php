<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * FF-131 — KUYRUK (kanonik kaynak: `docs/reference/media-manager/
 * Medya Yonetimi v2.dc.html`, ekran etiketi "Kuyruk"; gerekçe `docs/108`
 * §3 madde 5).
 *
 * MÜŞTERİ SORUNU. Yükleme ve yeniden üretim İŞ ÜRETİR. Bugün o iş
 * `media_processing_jobs` tablosuna yazılıyor ama hiçbir ekranda
 * görünmüyor. Restoran sahibi on fotoğraf yükleyip kütüphanede önizleme
 * çıkmadığını gördüğünde tek sorusu şudur: "takıldı mı, yoksa hâlâ
 * çalışıyor mu?" Cevabı yoksa aynı fotoğrafı tekrar tekrar yükler — ve
 * kotayı kendi eliyle doldurur.
 *
 * Kuyruk SALT OKUNURDUR: burada iş başlatılmaz. "Yeniden dene", var olan
 * tek-varlık yeniden üretim ucuna gider; kuyruğun kendi işleme hattı
 * yoktur.
 *
 * DÜRÜSTLÜK: tabloda YÜZDE sütunu yok. Çalışan bir işin ilerlemesi
 * BİLİNMİYOR ve uydurulmuş bir "%40" sahibi yanıltır. Uç bunu `null`
 * ilerleme olarak söyler; biten iş için ilerleme bellidir.
 *
 * Gereksinim: MEDIA-QUEUE-LIST-01, MEDIA-QUEUE-COUNTS-02,
 * MEDIA-QUEUE-NO-FAKE-PROGRESS-03, MEDIA-QUEUE-TENANT-04.
 */
final class MediaProcessingQueueTest extends TestCase
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

    private function asset(int $workspaceId, string $altText): int
    {
        return (int) DB::table('media_assets')->insertGetId([
            'workspace_id' => $workspaceId,
            'disk_path' => 'quarantine/'.$workspaceId.'/'.bin2hex(random_bytes(6)),
            'original_name' => 'kebap.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'alt_text' => $altText,
            'slot' => 'itemImage',
            'status' => 'ready',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function job(int $workspaceId, int $assetId, string $kind, string $state, ?string $reason = null): int
    {
        return (int) DB::table('media_processing_jobs')->insertGetId([
            'workspace_id' => $workspaceId,
            'media_asset_id' => $assetId,
            'kind' => $kind,
            'state' => $state,
            'attempts' => 1,
            'failure_reason' => $reason,
            'started_at' => now(),
            'finished_at' => $state === 'running' || $state === 'pending' ? null : now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // --- MEDIA-QUEUE-LIST-01 ----------------------------------------------

    /**
     * İş listesi: ne yapılıyor, hangi dosyada, hangi durumda.
     *
     * Dosyanın ADI satırda yazar. Yalnız kimlik numarası yazsaydı sahip
     * "hangi fotoğraf?" sorusunu ancak kütüphaneye dönüp arayarak
     * cevaplayabilirdi.
     */
    public function test_the_queue_lists_recent_jobs_with_the_file_they_belong_to(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'kuyruk-liste');
        $assetId = $this->asset($workspaceId, 'Adana kebap');

        $this->job($workspaceId, $assetId, 'rendition', 'succeeded');
        $this->job($workspaceId, $assetId, 'scan', 'failed', 'Tarayıcı konuşamadı.');

        $response = $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/media/jobs");

        $response->assertOk();

        $rows = (array) $response->json('data');

        self::assertCount(2, $rows);

        // En yeni önce: sahip ekranı açtığında en son olan biteni görür.
        self::assertSame('scan', $rows[0]['kind']);
        self::assertSame('failed', $rows[0]['state']);
        self::assertSame('Tarayıcı konuşamadı.', $rows[0]['failureReason']);
        self::assertSame($assetId, $rows[0]['mediaAssetId']);
        self::assertSame(
            'Adana kebap',
            $rows[0]['assetName'],
            'MEDIA-QUEUE-LIST-01: satır dosyanın ADINI taşır, yalnız numarasını değil.'
        );

        self::assertSame('rendition', $rows[1]['kind']);
        self::assertSame('succeeded', $rows[1]['state']);
        self::assertNull($rows[1]['failureReason']);
    }

    // --- MEDIA-QUEUE-COUNTS-02 --------------------------------------------

    /**
     * Sayaçlar: "kaç iş çalışıyor, kaçı başarısız".
     *
     * `held`, `failed`ten AYRI sayılır — depo bu ayrımı zaten yapıyor
     * (dosyada sorun yok, tarayıcı konuşamadı). İkisini tek sayaçta
     * toplamak sahibi "dosyalarım bozuk" sanmaya iter.
     */
    public function test_the_queue_counts_each_state_separately(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'kuyruk-sayac');
        $assetId = $this->asset($workspaceId, 'Lahmacun');

        $this->job($workspaceId, $assetId, 'rendition', 'running');
        $this->job($workspaceId, $assetId, 'rendition', 'succeeded');
        $this->job($workspaceId, $assetId, 'rendition', 'succeeded');
        $this->job($workspaceId, $assetId, 'rendition', 'failed', 'Görsel çözülemedi.');
        $this->job($workspaceId, $assetId, 'scan', 'held', 'Tarayıcı kurulu değil.');

        $counts = (array) $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/media/jobs")
            ->json('counts');

        self::assertSame(1, $counts['running']);
        self::assertSame(2, $counts['succeeded']);
        self::assertSame(1, $counts['failed']);
        self::assertSame(1, $counts['held']);
        self::assertSame(0, $counts['pending']);
        self::assertSame(5, $counts['total']);
    }

    // --- MEDIA-QUEUE-NO-FAKE-PROGRESS-03 ----------------------------------

    /**
     * Çalışan işin ilerlemesi BİLİNMİYOR; uydurulmuyor.
     */
    public function test_a_running_job_reports_unknown_progress_rather_than_an_invented_percentage(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'kuyruk-ilerleme');
        $assetId = $this->asset($workspaceId, 'Künefe');

        $this->job($workspaceId, $assetId, 'rendition', 'running');

        $rows = (array) $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/media/jobs")
            ->json('data');

        self::assertNull(
            $rows[0]['progress'],
            'MEDIA-QUEUE-NO-FAKE-PROGRESS-03: tabloda yüzde sütunu yok; olmayan sayı uydurulmaz.'
        );
        self::assertTrue($rows[0]['finished'] === false);
    }

    /** Biten iş için ilerleme bellidir: iş kapandı. */
    public function test_a_finished_job_is_marked_finished(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'kuyruk-biten');
        $assetId = $this->asset($workspaceId, 'Baklava');

        $this->job($workspaceId, $assetId, 'rendition', 'succeeded');

        $rows = (array) $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/media/jobs")
            ->json('data');

        self::assertTrue($rows[0]['finished']);
        // JSON tam sayı kesirini korumaz (`1.0` telde `1` olur); önemli olan
        // sayının BİLİNİYOR olması — `null` değil, tam.
        self::assertNotNull($rows[0]['progress']);
        self::assertSame(1.0, (float) $rows[0]['progress']);
    }

    // --- MEDIA-QUEUE-TENANT-04 --------------------------------------------

    /**
     * Başka kiracının işi bu kuyruğa hiç düşmez; üye olmayan 404 görür.
     */
    public function test_the_queue_is_scoped_to_one_workspace(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'kuyruk-kiraci');
        $otherOwner = $this->verifiedUser();
        $otherId = $this->ownerWorkspace($otherOwner, 'kuyruk-oteki');

        $this->job($workspaceId, $this->asset($workspaceId, 'Bizim'), 'rendition', 'succeeded');
        $this->job($otherId, $this->asset($otherId, 'Onlarınki'), 'rendition', 'succeeded');

        $rows = (array) $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/media/jobs")
            ->json('data');

        self::assertCount(1, $rows);
        self::assertSame('Bizim', $rows[0]['assetName']);

        $this->actingAs($this->verifiedUser())
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/media/jobs")
            ->assertNotFound();
    }
}
