<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Application\Media\Dto\MediaScanResult;
use App\Application\Media\Dto\MediaScanVerdict;
use App\Application\Media\Port\MalwareScannerPort;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * "ŞU AN HANGİ LOGO SEÇİLİ?" — `docs/98` FF-64.
 *
 * `PUT .../brand/logo` (`docs/77`) bir logoyu bağlıyordu ama hiçbir uç bağlı
 * olanı GERİ SÖYLEMİYORDU: ekran, seçiciyi doğru değerle açamazdı. Marka
 * cevabı artık `logoMediaAssetId` taşır; bağ marka tablosunda değil
 * `media_usages`'ta yaşadığı için oradan okunur.
 */
final class BrandLogoReadTest extends TestCase
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

        $this->workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => 'logo-'.Str::lower(Str::random(6)), 'state' => 'active',
            'created_by' => $this->owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $this->workspaceId, 'user_id' => $this->owner->id,
            'role' => 'owner', 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('brands')->insert([
            'workspace_id' => $this->workspaceId, 'name' => 'Zeytin',
            'slug' => 'logo-b-'.Str::lower(Str::random(6)), 'locale' => 'tr',
            'timezone' => 'Europe/Istanbul', 'currency' => 'TRY',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function api()
    {
        return $this->actingAs($this->owner)->withHeaders(['Accept' => 'application/json']);
    }

    #[Test]
    public function the_brand_reports_no_logo_until_one_is_bound_and_then_reports_which(): void
    {
        $this->api()->getJson("/api/workspaces/{$this->workspaceId}/brand")
            ->assertOk()
            ->assertJsonPath('logoMediaAssetId', null);

        $mediaId = (int) $this->api()->post("/api/workspaces/{$this->workspaceId}/media", [
            'file' => UploadedFile::fake()->image('logo.png', 800, 800),
            'altText' => 'Zeytin logosu',
            'slot' => 'logo',
        ])->json('id');

        $this->api()->putJson("/api/workspaces/{$this->workspaceId}/brand/logo", ['mediaAssetId' => $mediaId])
            ->assertOk();

        $this->api()->getJson("/api/workspaces/{$this->workspaceId}/brand")
            ->assertOk()
            ->assertJsonPath('logoMediaAssetId', $mediaId);

        // Kaldırınca yine null — "logo yok" bir durumdur, hata değil.
        $this->api()->putJson("/api/workspaces/{$this->workspaceId}/brand/logo", ['mediaAssetId' => null])
            ->assertOk();

        $this->api()->getJson("/api/workspaces/{$this->workspaceId}/brand")
            ->assertJsonPath('logoMediaAssetId', null);
    }
}
