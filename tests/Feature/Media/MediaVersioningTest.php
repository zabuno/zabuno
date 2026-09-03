<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Application\Media\Dto\MediaScanResult;
use App\Application\Media\Dto\MediaScanVerdict;
use App\Application\Media\Port\MalwareScannerPort;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ASIL / SÜRÜM / RENDITION — `docs/49` Faz 3, `docs/98` FF-69.
 *
 * Dört iddia: asıl değişmez (parmak izi sabit), yeniden üretim YENİ sürüm
 * açar (eskisi silinmez), geri alma da yeni sürümdür (geçmiş yeniden
 * yazılmaz), aynı dosya ikinci kez gelince sahibe söylenir — kiracı içinde.
 */
final class MediaVersioningTest extends TestCase
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

    private function workspace(User $owner): int
    {
        $id = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => 'ver-'.Str::lower(Str::random(6)), 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $id, 'user_id' => $owner->id, 'role' => 'owner',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function api(?User $user = null)
    {
        return $this->actingAs($user ?? $this->owner)->withHeaders(['Accept' => 'application/json']);
    }

    /** Aynı baytlar için deterministik bir JPEG — "aynı dosya" iddiası buna dayanır. */
    private function jpeg(int $seed = 1): UploadedFile
    {
        $image = imagecreatetruecolor(1200, 1200);
        imagefilledrectangle($image, 0, 0, 1200, 1200, imagecolorallocate($image, 40 * $seed % 255, 80, 120));
        ob_start();
        imagejpeg($image, null, 85);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return UploadedFile::fake()->createWithContent("photo-{$seed}.jpg", $bytes);
    }

    private function upload(UploadedFile $file, ?int $workspaceId = null, ?User $as = null): int
    {
        $ws = $workspaceId ?? $this->workspaceId;
        $response = $this->api($as)->post("/api/workspaces/{$ws}/media", [
            'file' => $file, 'altText' => 'Menemen', 'slot' => 'itemImage',
        ]);
        $response->assertStatus(201);

        return (int) $response->json('id');
    }

    // --- FAZ3-ORIGINAL-IMMUTABLE-01 ------------------------------------------

    #[Test]
    public function reprocessing_opens_a_new_version_and_leaves_the_original_untouched(): void
    {
        $id = $this->upload($this->jpeg());
        $before = DB::table('media_assets')->where('id', $id)->first();
        self::assertSame('ready', (string) $before->status);
        self::assertNotEmpty($before->original_checksum_sha256);
        $originalBytes = Storage::disk('local')->get((string) $before->disk_path);

        $response = $this->api()->postJson("/api/workspaces/{$this->workspaceId}/media/{$id}/reprocess")
            ->assertStatus(200);

        self::assertSame('reprocessed', $response->json('outcome'));
        $numbers = array_column($response->json('versions'), 'number');
        self::assertSame([2, 1], $numbers, 'Yeniden üretim v2 açmalı, v1 durmalı.');
        self::assertSame('reprocess', $response->json('versions.0.createdBy'));

        $after = DB::table('media_assets')->where('id', $id)->first();
        self::assertSame($before->original_checksum_sha256, $after->original_checksum_sha256, 'Asıl DEĞİŞMEZ.');
        self::assertSame($originalBytes, Storage::disk('local')->get((string) $after->disk_path));
        self::assertSame('ready', (string) $after->status);
    }

    // --- FAZ3-RESTORE-IS-A-NEW-VERSION-01 ------------------------------------

    #[Test]
    public function restoring_an_old_version_appends_a_new_version_with_the_same_renditions(): void
    {
        $id = $this->upload($this->jpeg());
        $this->api()->postJson("/api/workspaces/{$this->workspaceId}/media/{$id}/reprocess")->assertStatus(200);

        $v1Renditions = DB::table('media_renditions')
            ->join('media_versions', 'media_versions.id', '=', 'media_renditions.media_version_id')
            ->where('media_versions.media_asset_id', $id)->where('media_versions.version_number', 1)
            ->orderBy('media_renditions.width')->pluck('media_renditions.media_blob_id')->all();

        $response = $this->api()->postJson("/api/workspaces/{$this->workspaceId}/media/{$id}/versions/1/restore")
            ->assertStatus(200);

        $numbers = array_column($response->json('versions'), 'number');
        self::assertSame([3, 2, 1], $numbers, 'Geri alma geçmişi yeniden yazmaz; v3 açar.');
        self::assertSame('restore:v1', $response->json('versions.0.createdBy'));

        $v3Renditions = DB::table('media_renditions')
            ->join('media_versions', 'media_versions.id', '=', 'media_renditions.media_version_id')
            ->where('media_versions.media_asset_id', $id)->where('media_versions.version_number', 3)
            ->orderBy('media_renditions.width')->pluck('media_renditions.media_blob_id')->all();

        // Aynı blob'lar: adres parmak izi aynı kalır, önbellek bozulmaz.
        self::assertSame($v1Renditions, $v3Renditions);
        self::assertNotEmpty($v3Renditions);

        // Var olmayan sürüm 404 — 500 değil.
        $this->api()->postJson("/api/workspaces/{$this->workspaceId}/media/{$id}/versions/9/restore")->assertStatus(404);
    }

    // --- FAZ3-DUPLICATE-IN-TENANT-01 -----------------------------------------

    #[Test]
    public function the_same_bytes_uploaded_twice_are_flagged_as_a_duplicate_of_the_first(): void
    {
        $first = $this->upload($this->jpeg(3));
        $second = $this->upload($this->jpeg(3));
        $other = $this->upload($this->jpeg(4));

        $list = collect($this->api()->getJson("/api/workspaces/{$this->workspaceId}/media")->json('data'))
            ->keyBy('id');

        self::assertNull($list[$first]['duplicateOfId']);
        self::assertSame($first, $list[$second]['duplicateOfId']);
        self::assertNull($list[$other]['duplicateOfId']);
    }

    #[Test]
    public function duplicate_detection_never_crosses_the_tenant_boundary(): void
    {
        $neighbourOwner = User::factory()->create(['email_verified_at' => now()]);
        $neighbourWorkspace = $this->workspace($neighbourOwner);

        $this->upload($this->jpeg(5));
        $theirs = $this->upload($this->jpeg(5), $neighbourWorkspace, $neighbourOwner);

        $list = collect($this->api($neighbourOwner)->getJson("/api/workspaces/{$neighbourWorkspace}/media")->json('data'))->keyBy('id');

        // Aynı baytlar, ama komşu bunu BİLMEZ: başka bir restoranın aynı
        // dosyaya sahip olduğunu söylemek bile sızıntıdır.
        self::assertNull($list[$theirs]['duplicateOfId']);
    }

    // --- FAZ3-VERSIONS-AUTHZ-01 ----------------------------------------------

    #[Test]
    public function a_stranger_sees_404_on_every_version_route(): void
    {
        $id = $this->upload($this->jpeg());
        $stranger = User::factory()->create(['email_verified_at' => now()]);

        $this->api($stranger)->getJson("/api/workspaces/{$this->workspaceId}/media/{$id}/versions")->assertStatus(404);
        $this->api($stranger)->postJson("/api/workspaces/{$this->workspaceId}/media/{$id}/reprocess")->assertStatus(404);
        $this->api($stranger)->postJson("/api/workspaces/{$this->workspaceId}/media/{$id}/versions/1/restore")->assertStatus(404);
    }

    #[Test]
    public function only_a_ready_asset_can_be_reprocessed(): void
    {
        $id = $this->upload($this->jpeg());
        DB::table('media_assets')->where('id', $id)->update(['status' => 'quarantined']);

        $this->api()->postJson("/api/workspaces/{$this->workspaceId}/media/{$id}/reprocess")->assertStatus(409);
    }

    // --- FAZ3-BULK-REPROCESS-01 ----------------------------------------------

    #[Test]
    public function the_artisan_command_reprocesses_every_ready_asset_of_a_workspace(): void
    {
        $a = $this->upload($this->jpeg(6));
        $b = $this->upload($this->jpeg(7));

        $exit = Artisan::call('media:reprocess', ['--workspace' => $this->workspaceId]);

        self::assertSame(0, $exit);
        foreach ([$a, $b] as $id) {
            self::assertSame(2, (int) DB::table('media_versions')->where('media_asset_id', $id)->max('version_number'));
        }
    }
}
