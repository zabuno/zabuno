<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * DUPLICATE-PRODUCT-DETECTION — taksonomi yinelenen-terim tespiti
 * (`docs/95`/`docs/96` Faz 2, `docs/32` core-taxonomy: "Duplicate terim
 * tespiti/birleştirme önerisi").
 *
 * Yalnız TESPİT eder — hiçbir kaydı BİRLEŞTİRMEZ/SİLMEZ. Advisory: öneri
 * gösterir, insan karar verir (`docs/32` core-taxonomy: assistive).
 */
final class DuplicateProductDetectionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private int $workspaceId;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.enabled' => true]);
        config(['ai.capabilities' => [
            'embedding.text' => ['candidates' => ['fake'], 'confidence_threshold' => 0.0],
        ]]);
        config(['ai.budget.monthly_minor_per_tenant' => 100000]);

        $this->owner = User::factory()->create(['email_verified_at' => now()]);

        $this->workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => 'dup-'.Str::lower(Str::random(6)), 'state' => 'active',
            'created_by' => $this->owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $this->workspaceId, 'user_id' => $this->owner->id,
            'role' => 'owner', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function api(?User $user = null)
    {
        return $this->actingAs($user ?? $this->owner)->withHeaders(['Accept' => 'application/json']);
    }

    private function product(string $name): int
    {
        return (int) DB::table('products')->insertGetId([
            'workspace_id' => $this->workspaceId, 'name' => $name,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // --- DUPLICATE-PRODUCT-CANDIDATE-FOUND-01 -------------------------------

    #[Test]
    public function nearly_identical_names_are_flagged_as_a_candidate(): void
    {
        $this->product('Adana Kebap');
        $this->product('adana kebap');
        $this->product('Mercimek Çorbası');

        $response = $this->api()->getJson(
            "/api/workspaces/{$this->workspaceId}/menu/duplicate-candidates",
        );

        $response->assertStatus(200, 'duplicate-candidates yanıtı: '.$response->getContent());
        $pairs = (array) $response->json('candidates');

        self::assertNotEmpty($pairs, 'DUPLICATE-PRODUCT-DETECTION: neredeyse aynı iki ad yakalanmadı.');

        $names = array_map(
            static fn (array $pair): array => [$pair['productAName'], $pair['productBName']],
            $pairs,
        );
        $flat = array_merge(...$names);
        self::assertContains('Adana Kebap', $flat);
        self::assertContains('adana kebap', $flat);
    }

    // --- DUPLICATE-PRODUCT-DISTINCT-NOT-FLAGGED-01 --------------------------

    #[Test]
    public function clearly_different_names_are_not_flagged(): void
    {
        $this->product('Adana Kebap');
        $this->product('Türk Kahvesi');

        $response = $this->api()->getJson(
            "/api/workspaces/{$this->workspaceId}/menu/duplicate-candidates",
        );

        $response->assertStatus(200);
        self::assertSame([], $response->json('candidates'));
    }

    // --- DUPLICATE-PRODUCT-NEVER-MERGES-01 ----------------------------------

    #[Test]
    public function detection_never_writes_anything(): void
    {
        $this->product('Adana Kebap');
        $this->product('adana kebap');

        $before = DB::table('products')->count();

        $this->api()->getJson("/api/workspaces/{$this->workspaceId}/menu/duplicate-candidates");

        self::assertSame($before, DB::table('products')->count(), 'DUPLICATE-PRODUCT-DETECTION: tespit bir kaydı sildi/birleştirdi.');
        self::assertSame(0, DB::table('ai_artifacts')->count(), 'DUPLICATE-PRODUCT-DETECTION: taslak/artifact üretmemeli, salt öneri.');
    }

    // --- DUPLICATE-PRODUCT-OFF-IS-HONEST-01 ---------------------------------

    #[Test]
    public function with_ai_off_the_endpoint_says_so(): void
    {
        config(['ai.enabled' => false]);
        $this->product('Adana Kebap');

        $this->api()->getJson("/api/workspaces/{$this->workspaceId}/menu/duplicate-candidates")
            ->assertStatus(503);
    }

    // --- DUPLICATE-PRODUCT-TENANT-01 -----------------------------------------

    #[Test]
    public function a_stranger_cannot_see_another_workspaces_candidates(): void
    {
        $stranger = User::factory()->create(['email_verified_at' => now()]);

        $this->api($stranger)->getJson("/api/workspaces/{$this->workspaceId}/menu/duplicate-candidates")
            ->assertStatus(404);
    }

    // --- AI-09 / `docs/97` R16 ------------------------------------------------

    /**
     * İKİ TENANT AYNI ÜRÜN ADINI TAŞIYABİLİR — ve bu bir "tekrar" DEĞİLDİR.
     *
     * `docs/16` AI-09'un kritik senaryosu: iki restoranın ikisinde de "Adana
     * Kebap" vardır. Gömme vektörleri neredeyse aynıdır; eşleştirme
     * workspace sınırını görmezse, A restoranının paneli B restoranının
     * ürününü "olası tekrar" diye gösterirdi — bu, menü içeriğinin
     * tenant'lar arası SIZMASI olurdu.
     *
     * Üstteki 404 testi YETKİYİ kanıtlıyor; bu test EŞLEŞTİRMENİN kendisini
     * kanıtlıyor — yetkili bir kullanıcı kendi workspace'ini sorduğunda bile
     * karşı tarafın verisi asla adaya girmez.
     */
    #[Test]
    public function two_workspaces_with_the_same_product_name_never_pair_across_the_boundary(): void
    {
        $neighbourOwner = User::factory()->create(['email_verified_at' => now()]);

        $neighbourId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Komşu', 'slug' => 'dup-n-'.Str::lower(Str::random(6)), 'state' => 'active',
            'created_by' => $neighbourOwner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Komşuda AYNI iki ad — bizim tarafta hiç yok.
        foreach (['Adana Kebap', 'adana kebap'] as $name) {
            DB::table('products')->insert([
                'workspace_id' => $neighbourId, 'name' => $name,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Bizim tarafta yalnız tek, eşsiz bir ad.
        $this->product('Mercimek Çorbası');

        $response = $this->api()->getJson(
            "/api/workspaces/{$this->workspaceId}/menu/duplicate-candidates",
        );

        $response->assertStatus(200);
        self::assertSame(
            [],
            $response->json('candidates'),
            'AI-09: komşu workspace’in ürünleri bizim aday listemize sızdı.',
        );
    }

    /**
     * GÖMME SONUCU KALICI OLARAK SAKLANMAZ — bugünün kapanış kanıtı.
     *
     * `docs/97` R16: gömmeler ileride önbelleğe alınırsa önbellek anahtarı
     * `workspace_id` içermek ZORUNDA. Bugün önbellek yok; bu test o gerçeği
     * kilitler, böylece bir gün paylaşılan bir önbellek tablosu eklenirse
     * (workspace kolonu olmadan) kırılır ve karar bilinçli alınır.
     */
    #[Test]
    public function embeddings_are_request_scoped_and_leave_no_shared_cache_row(): void
    {
        $this->product('Adana Kebap');
        $this->product('adana kebap');

        $this->api()->getJson("/api/workspaces/{$this->workspaceId}/menu/duplicate-candidates")
            ->assertStatus(200);

        // Sürücüden bağımsız tablo listesi — testin sürücüye çakılmaması için.
        $tables = Schema::getTableListing();

        $cacheLike = array_values(array_filter(
            $tables,
            static fn (string $name): bool => str_contains($name, 'embedding'),
        ));

        self::assertSame(
            [],
            $cacheLike,
            'AI-09/R16: bir gömme önbelleği eklenmiş — anahtarı workspace_id taşımalı ve bu test güncellenmeli.',
        );
    }
}
