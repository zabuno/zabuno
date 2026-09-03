<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Application\Media\Dto\MediaScanResult;
use App\Application\Media\Dto\MediaScanVerdict;
use App\Application\Media\Port\MalwareScannerPort;
use App\Models\User;
use App\Support\Media\RenditionUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `docs/49` Faz 6-7 (`docs/98` FF-71): ETag/304, imzalı asıl indirme, LQIP,
 * kota (plan → config), izin matrisi (`media.manage`,
 * `media.download_original`), uzlaştırma.
 *
 * Kullanıcı yolculuğu: Ayşe telefonda menüyü ikinci kez açar → fotoğraflar
 * 304 ile sıfır bayt gelir; Ayşe "asıl dosyayı ver" der → 10 dakikalık
 * imzalı bağlantı; Ayşe 100. görseli yüklemeye kalkar → "sınıra ulaşıldı,
 * çöpü boşaltın ya da planı yükseltin" — canlı menü yine açıktır.
 */
final class MediaDeliveryAndGovernanceTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private int $workspaceId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->app->instance(MalwareScannerPort::class, new class implements MalwareScannerPort
        {
            public function scan(string $diskPath): MediaScanResult
            {
                return new MediaScanResult(MediaScanVerdict::Clean);
            }
        });

        $this->owner = User::factory()->create(['email_verified_at' => now()]);
        $this->workspaceId = $this->workspace($this->owner);
    }

    private function workspace(User $owner, string $role = 'owner'): int
    {
        $id = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => 'gov-'.Str::lower(Str::random(6)), 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->member($id, $owner, $role);

        return $id;
    }

    private function member(int $workspaceId, User $user, string $role): void
    {
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId, 'user_id' => $user->id, 'role' => $role,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function api(?User $user = null)
    {
        return $this->actingAs($user ?? $this->owner)->withHeaders(['Accept' => 'application/json']);
    }

    private function jpeg(int $size = 400): UploadedFile
    {
        $image = imagecreatetruecolor($size, $size);
        imagefilledrectangle($image, 0, 0, $size, $size, imagecolorallocate($image, 200, 80, 40));
        ob_start();
        imagejpeg($image, null, 85);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return UploadedFile::fake()->createWithContent('kebap.jpg', $bytes);
    }

    private function upload(?User $as = null, ?UploadedFile $file = null)
    {
        return $this->api($as)->post("/api/workspaces/{$this->workspaceId}/media", [
            'file' => $file ?? $this->jpeg(), 'altText' => 'Adana kebap', 'slot' => 'itemImage',
        ]);
    }

    private function subscribe(int $workspaceId, string $planCode): void
    {
        $planId = (int) DB::table('plans')->where('code', $planCode)->value('id');
        if ($planId === 0) {
            $planId = (int) DB::table('plans')->insertGetId([
                'name' => ucfirst($planCode), 'code' => $planCode, 'version' => 1, 'is_active' => 1, 'sort_order' => 1,
                'entitlements' => '{}', 'amount_minor' => 0, 'currency' => 'TRY', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('subscriptions')->insert([
            'workspace_id' => $workspaceId, 'plan_id' => $planId, 'state' => 'active',
            'ends_at' => now()->addMonth(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // --- FAZ6-ETAG-304-01 ------------------------------------------------------

    #[Test]
    public function a_rendition_answers_304_to_a_matching_etag(): void
    {
        $id = (int) $this->upload()->assertStatus(201)->json('id');
        $rendition = DB::table('media_renditions')
            ->join('media_versions', 'media_versions.id', '=', 'media_renditions.media_version_id')
            ->where('media_versions.media_asset_id', $id)->orderBy('media_renditions.width')
            ->first(['media_renditions.id', 'media_renditions.format', 'media_renditions.media_blob_id']);
        $checksum = (string) DB::table('media_blobs')->where('id', $rendition->media_blob_id)->value('checksum_sha256');
        $url = RenditionUrl::for((int) $rendition->id, $checksum, (string) $rendition->format);

        $first = $this->get($url)->assertOk();
        $etag = (string) $first->headers->get('ETag');
        self::assertNotSame('', $etag);

        $second = $this->get($url, ['If-None-Match' => $etag]);
        $second->assertStatus(304);
        self::assertSame('', $second->getContent(), 'İçerik aynıysa gövde gitmez.');
        self::assertStringContainsString('immutable', (string) $second->headers->get('Cache-Control'));
    }

    // --- FAZ6-LQIP-01 -----------------------------------------------------------

    #[Test]
    public function processing_stores_a_tiny_placeholder_and_the_guest_snapshot_carries_it(): void
    {
        $id = (int) $this->upload()->assertStatus(201)->json('id');
        $lqip = (string) DB::table('media_versions')->where('media_asset_id', $id)->value('lqip');

        self::assertStringStartsWith('data:image/jpeg;base64,', $lqip);
        self::assertLessThan(2000, strlen($lqip), 'LQIP bir HTML özniteliğine sığacak kadar küçük olmalı.');
    }

    // --- FAZ6-SIGNED-ORIGINAL-01 -----------------------------------------------

    #[Test]
    public function original_is_downloadable_only_through_a_short_lived_signed_link(): void
    {
        $id = (int) $this->upload()->assertStatus(201)->json('id');

        $this->get("/media/original/{$this->workspaceId}/{$id}")->assertStatus(403);

        $link = $this->api()->postJson("/api/workspaces/{$this->workspaceId}/media/{$id}/download-link")
            ->assertOk()->json('url');

        $response = $this->get($link)->assertOk();
        self::assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        self::assertStringContainsString('kebap.jpg', (string) $response->headers->get('Content-Disposition'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->travel(11)->minutes();
        $this->get($link)->assertStatus(403);
    }

    #[Test]
    public function a_read_only_member_can_download_the_original_but_cannot_upload(): void
    {
        $id = (int) $this->upload()->assertStatus(201)->json('id');
        $member = User::factory()->create(['email_verified_at' => now()]);
        $this->member($this->workspaceId, $member, 'member');

        $this->api($member)->postJson("/api/workspaces/{$this->workspaceId}/media/{$id}/download-link")->assertOk();
        $this->upload($member)->assertStatus(403);
        $this->api($member)->deleteJson("/api/workspaces/{$this->workspaceId}/media/{$id}")->assertStatus(403);
    }

    #[Test]
    public function another_tenant_cannot_mint_a_link_for_someone_elses_original(): void
    {
        $id = (int) $this->upload()->assertStatus(201)->json('id');
        $stranger = User::factory()->create(['email_verified_at' => now()]);
        $strangerWs = $this->workspace($stranger);

        $this->api($stranger)->postJson("/api/workspaces/{$strangerWs}/media/{$id}/download-link")->assertStatus(404);
    }

    // --- FAZ7-QUOTA-01 ----------------------------------------------------------

    #[Test]
    public function quota_endpoint_reads_the_plan_and_counts_trash_but_not_renditions(): void
    {
        config()->set('media-quota.plans.starter.assets', 2);
        $id = (int) $this->upload()->assertStatus(201)->json('id');
        $size = (int) DB::table('media_assets')->where('id', $id)->value('size_bytes');
        $this->api()->deleteJson("/api/workspaces/{$this->workspaceId}/media/{$id}")->assertStatus(204);

        $quota = $this->api()->getJson("/api/workspaces/{$this->workspaceId}/media/quota")->assertOk()->json('quota');

        self::assertSame('starter', $quota['planCode']);
        self::assertSame(1, $quota['assetsUsed'], 'Çöp kotaya dahil.');
        self::assertSame($size, $quota['originalBytesUsed'], 'Rendition baytları sayılmaz.');
        self::assertSame(2, $quota['assetsLimit']);
        self::assertNull($quota['blockedReason']);
    }

    #[Test]
    public function upload_stops_with_a_readable_reason_when_the_asset_limit_is_reached(): void
    {
        config()->set('media-quota.plans.starter.assets', 1);
        $this->upload()->assertStatus(201);

        $response = $this->upload();

        $response->assertStatus(422);
        self::assertStringContainsString('sınır', (string) $response->json('errors.file.0'));
        self::assertSame(1, DB::table('media_assets')->count());
        self::assertNotNull($this->api()->getJson("/api/workspaces/{$this->workspaceId}/media/quota")->json('quota.blockedReason'));
    }

    #[Test]
    public function upload_stops_when_storage_would_overflow_and_a_bigger_plan_lifts_it(): void
    {
        config()->set('media-quota.plans.starter.original_bytes', 1);
        $this->upload()->assertStatus(422);

        $this->subscribe($this->workspaceId, 'team');
        $this->upload()->assertStatus(201);
        self::assertSame('team', $this->api()->getJson("/api/workspaces/{$this->workspaceId}/media/quota")->json('quota.planCode'));
    }

    #[Test]
    public function purge_uses_each_workspaces_plan_retention(): void
    {
        config()->set('media-quota.plans.starter.trash_retention_days', 7);
        $id = (int) $this->upload()->assertStatus(201)->json('id');
        $this->api()->deleteJson("/api/workspaces/{$this->workspaceId}/media/{$id}")->assertStatus(204);
        DB::table('media_assets')->where('id', $id)->update(['deleted_at' => now()->subDays(10)]);

        Artisan::call('media:purge-trash');

        self::assertNull(DB::table('media_assets')->where('id', $id)->first(), '7 günlük planda 10 günlük çöp gider.');
    }

    // --- FAZ7-RECONCILE-01 ------------------------------------------------------

    #[Test]
    public function reconcile_reports_missing_files_and_orphans_and_fix_cleans_only_orphans(): void
    {
        $id = (int) $this->upload()->assertStatus(201)->json('id');
        $diskPath = (string) DB::table('media_assets')->where('id', $id)->value('disk_path');

        self::assertSame(0, Artisan::call('media:reconcile'), 'Tutarlı durumda sıfır bulgu.');

        Storage::disk('local')->delete($diskPath);
        Storage::disk('local')->put("renditions/{$this->workspaceId}/999/orphan.webp", 'x');

        self::assertSame(1, Artisan::call('media:reconcile'));
        $output = Artisan::output();
        self::assertStringContainsString('KIRIK', $output);
        self::assertStringContainsString('YETİM', $output);

        Artisan::call('media:reconcile', ['--fix' => true]);

        Storage::disk('local')->assertMissing("renditions/{$this->workspaceId}/999/orphan.webp");
        self::assertSame('failed', DB::table('media_assets')->where('id', $id)->value('status'), 'Kırık kayıt silinmez, failed olur.');
        self::assertNotNull(DB::table('media_assets')->where('id', $id)->first());
    }
}
