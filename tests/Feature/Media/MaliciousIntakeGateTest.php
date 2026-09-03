<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

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
 * ZARARLI DOSYA KAPISI — `docs/49` Faz 2 kabul ölçütü, `docs/98` FF-68.
 *
 * "`fixtures/malicious/` içindeki her dosya DECODE EDİLMEDEN reddedilir."
 * Bu test o cümlenin CI karşılığıdır. Klasöre yeni bir fixture koymak
 * yeterlidir; test onu kendisi bulur. Reddedilen dosya için ne bir
 * `media_assets` satırı doğar ne de karantinaya bir bayt yazılır.
 *
 * Fixture'lar `tests/fixtures/malicious/` altında ve gerçek: PHP gövdeli
 * .jpg, HTML gövdeli .png, betikli SVG, 100000×100000 iddia eden PNG
 * başlığı (decompression bomb — açılırsa ~40 GB bellek ister, başlıktan
 * okunup reddedilir).
 */
final class MaliciousIntakeGateTest extends TestCase
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
            'name' => 'Zeytin', 'slug' => 'mal-'.Str::lower(Str::random(6)), 'state' => 'active',
            'created_by' => $this->owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $this->workspaceId, 'user_id' => $this->owner->id, 'role' => 'owner',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return list<string> */
    private function fixtures(): array
    {
        $files = glob(base_path('tests/fixtures/malicious/*')) ?: [];
        sort($files);

        return $files;
    }

    #[Test]
    public function the_fixture_directory_is_not_empty(): void
    {
        self::assertGreaterThanOrEqual(4, count($this->fixtures()), 'Kapının koruyacağı bir fixture yok.');
    }

    #[Test]
    public function every_malicious_fixture_is_rejected_before_anything_is_stored(): void
    {
        foreach ($this->fixtures() as $path) {
            $name = basename($path);
            $upload = new UploadedFile($path, $name, null, null, true);

            $response = $this->actingAs($this->owner)->withHeaders(['Accept' => 'application/json'])->post(
                "/api/workspaces/{$this->workspaceId}/media",
                ['file' => $upload, 'altText' => 'zararlı', 'slot' => 'itemImage'],
            );

            self::assertSame(
                422,
                $response->getStatusCode(),
                "MALICIOUS-GATE: `{$name}` reddedilmeliydi, {$response->getStatusCode()} döndü.",
            );
        }

        self::assertSame(0, DB::table('media_assets')->count(), 'Reddedilen dosya için satır doğdu.');
        self::assertSame([], Storage::disk('local')->allFiles(), 'Reddedilen dosya karantinaya yazıldı.');
    }

    /**
     * POLYGLOT: GEÇERLİ görsel başlığı + PHP gövdesi.
     *
     * Bu dosya magic-byte kapısından geçer — ve geçmesi doğrudur, çünkü
     * gerçekten bir GIF'tir. Savunma başka yerde: aslı asla halka servis
     * edilmez; halka giden tek şey GD'nin sıfırdan yeniden encode ettiği
     * rendition'dır ve o rendition'da gömülü gövde yoktur.
     */
    #[Test]
    public function a_polyglot_is_accepted_as_an_image_but_its_payload_never_reaches_a_rendition(): void
    {
        $path = base_path('tests/fixtures/polyglot-gif-php.gif');
        $upload = new UploadedFile($path, 'innocent.gif', null, null, true);

        $response = $this->actingAs($this->owner)->withHeaders(['Accept' => 'application/json'])->post(
            "/api/workspaces/{$this->workspaceId}/media",
            ['file' => $upload, 'altText' => 'polyglot', 'slot' => 'itemImage'],
        );

        // 1×1 GIF, itemImage slotunun en küçük ölçüsünün altında: ya ölçü
        // yüzünden reddedilir ya da kabul edilip işlenir — ikisi de doğru.
        // Tek yanlış, gövdenin halka giden bir bayta sızması olurdu.
        if ($response->getStatusCode() === 201) {
            foreach (Storage::disk('local')->allFiles() as $file) {
                if (str_starts_with($file, 'quarantine/')) {
                    continue; // aslı — halka servis edilmez
                }
                self::assertStringNotContainsString(
                    '<?php',
                    (string) Storage::disk('local')->get($file),
                    "POLYGLOT: rendition `{$file}` PHP gövdesi taşıyor.",
                );
            }
        } else {
            self::assertSame(422, $response->getStatusCode());
        }
    }

    // --- IDEMPOTENCY (`docs/49` Faz 2 madde 1) ---------------------------------

    #[Test]
    public function the_same_idempotency_key_replays_the_first_upload_instead_of_creating_a_second_asset(): void
    {
        $file = UploadedFile::fake()->image('menemen.jpg', 1200, 1200);
        $headers = ['Accept' => 'application/json', 'X-Idempotency-Key' => 'phone-retry-7f3a'];

        $first = $this->actingAs($this->owner)->withHeaders($headers)->post(
            "/api/workspaces/{$this->workspaceId}/media",
            ['file' => $file, 'altText' => 'Menemen', 'slot' => 'itemImage'],
        )->assertStatus(201);

        // Bağlantı koptu, istemci AYNI anahtarla yeniden gönderdi.
        $second = $this->actingAs($this->owner)->withHeaders($headers)->post(
            "/api/workspaces/{$this->workspaceId}/media",
            ['file' => UploadedFile::fake()->image('menemen.jpg', 1200, 1200), 'altText' => 'Menemen', 'slot' => 'itemImage'],
        )->assertStatus(200);

        self::assertSame($first->json('id'), $second->json('id'));
        self::assertTrue($second->json('replayed'));
        self::assertSame(1, DB::table('media_assets')->count(), 'Yeniden deneme ikinci bir görsel yarattı.');
    }

    #[Test]
    public function an_idempotency_key_is_scoped_to_the_workspace(): void
    {
        $neighbour = User::factory()->create(['email_verified_at' => now()]);
        $neighbourWorkspace = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Komşu', 'slug' => 'mal-n-'.Str::lower(Str::random(6)), 'state' => 'active',
            'created_by' => $neighbour->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $neighbourWorkspace, 'user_id' => $neighbour->id, 'role' => 'owner',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $headers = ['Accept' => 'application/json', 'X-Idempotency-Key' => 'shared-key-1'];

        $this->actingAs($this->owner)->withHeaders($headers)->post(
            "/api/workspaces/{$this->workspaceId}/media",
            ['file' => UploadedFile::fake()->image('a.jpg', 1200, 1200), 'altText' => 'A', 'slot' => 'itemImage'],
        )->assertStatus(201);

        // Komşu aynı anahtarı üretti: bizim görselimizi GÖRMEZ, kendi görselini yaratır.
        $this->actingAs($neighbour)->withHeaders($headers)->post(
            "/api/workspaces/{$neighbourWorkspace}/media",
            ['file' => UploadedFile::fake()->image('b.jpg', 1200, 1200), 'altText' => 'B', 'slot' => 'itemImage'],
        )->assertStatus(201);

        self::assertSame(2, DB::table('media_assets')->count());
    }

    // --- LİMİTLER CONFIG'DEN (`media-slots.limits`) ----------------------------

    #[Test]
    public function the_byte_limit_comes_from_config_not_from_a_hardcoded_number(): void
    {
        config(['media-slots.limits.max_bytes' => 10 * 1024]); // 10 KB

        $big = UploadedFile::fake()->image('big.jpg', 1200, 1200)->size(50); // 50 KB

        $this->actingAs($this->owner)->withHeaders(['Accept' => 'application/json'])->post(
            "/api/workspaces/{$this->workspaceId}/media",
            ['file' => $big, 'altText' => 'Büyük', 'slot' => 'itemImage'],
        )->assertStatus(422);
    }
}
