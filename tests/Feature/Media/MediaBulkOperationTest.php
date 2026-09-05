<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * TOPLU İŞLEM — kanonik kaynak `docs/reference/panel-v3/MedyaModulu.dc.html`,
 * `data-screen-label="Toplu işlem"` (plan: `docs/109-PANEL-V3.md` §2).
 *
 * Bu bölüm depoda HİÇ YOKTU. Sahibin gerçek yolculuğu şu: "Paşa Döner"in
 * kütüphanesinde telefondan çıkmış 1.800 fotoğraf var, hepsini AVIF'e
 * çevirmek istiyor. Bugün elinde tek bir yol var — dosyaları tek tek
 * seçip tek tek dönüştürmek. Yüz dosyada vazgeçer.
 *
 * Kaynağın sihirbazı beş adımdır: Kapsam → Eylem → Ayar → Etki → Sonuç.
 * Bu dosya o beş adımın SUNUCU yarısını dondurur; ekran yarısı
 * `MediaBulkWizardRegion.test.tsx` içindedir.
 *
 * Dondurulan yedi davranış — hepsi kaynağın kendi cümlelerinden:
 *
 * 1. **"İş başladığı anda liste dondurulur."** Kapsam çözümü bir KİMLİK
 *    LİSTESİ döner ve çalıştırma o listeyi alır. Plan ile çalıştırma
 *    arasında yüklenen dosya işe girmez — yoksa iş hiç bitmeyebilirdi.
 * 2. **Kuru çalışma GERÇEK sayar.** "Etki" adımı hiçbir dosyaya
 *    dokunmadan, her dosyayı tek tek kontrol ederek kaç tanesinin
 *    uygulanacağını ve kaç tanesinin ATLANACAĞINI söyler. Tahmin yok:
 *    plandan sonra çalıştırılan iş aynı sayıyı üretir.
 * 3. **Atlama SEBEBİYLE birlikte.** Karantina, yasal saklama, yayında
 *    kullanım, biçim uygunsuzluğu — her biri ayrı sayılır ve dosya
 *    adıyla listelenir. "20 dosya atlandı" tek başına sahibi karanlıkta
 *    bırakır.
 * 4. **Yıkıcı işlemde onay kutusu yetmez.** Kalıcı silme için sahip
 *    `KALICI SİL` yazar; yazmadan çalıştırma 422 ile durur ve HİÇBİR
 *    satır değişmez.
 * 5. **Yetki GERÇEKTİR.** Kalıcı silme çalışma alanı yöneticiliği ister;
 *    editör plan alabilir (kilidin SEBEBİNİ okur) ama çalıştıramaz.
 * 6. **Aynı işlem anahtarı iki kez çalışmaz.** Çift tıklama, yeniden
 *    deneme ya da geri düğmesi bin dosyayı iki kez işlemez.
 * 7. **Denetim kaydı GERÇEKTEN yazılır.** İş bittiğinde `media_bulk_
 *    operations` satırı durur; kim, ne, hangi kapsam, kaç dosya.
 *
 * Kiracı sınırı ayrı bir testtir: bir restoranın kimlik listesine
 * başkasının dosyasını karıştırmak, toplu işlemin sessizce bozulabilen
 * tek yeridir.
 */
final class MediaBulkOperationTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function workspaceOwnedBy(User $owner, string $slug): int
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Paşa Döner',
            'slug' => $slug,
            'state' => 'active',
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->joinWorkspace($workspaceId, $owner, 'owner');

        return $workspaceId;
    }

    private function joinWorkspace(int $workspaceId, User $user, string $role): void
    {
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $user->id,
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Varlıklar yükleme hattından geçirilmeden doğrudan yazılır: bu paket
     * TOPLU İŞLEMİ sınıyor, karantina/tarama zincirini değil. Tarayıcı bu
     * ortamda `unavailable` (`docs/108` §5) ve gerçek bir yükleme hiçbir
     * zaman `ready` olmazdı — testin kırmızısı o zaman toplu işlem
     * hatasını değil dağıtım engelini ölçerdi.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function asset(int $workspaceId, string $name, array $overrides = []): int
    {
        return (int) DB::table('media_assets')->insertGetId(array_merge([
            'workspace_id' => $workspaceId,
            'disk_path' => 'quarantine/'.$workspaceId.'/'.$name,
            'original_name' => $name,
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'alt_text' => $name,
            'slot' => 'menu',
            'status' => 'ready',
            'lifecycle_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /** @return array<string, string> */
    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    public function test_scope_is_frozen_into_an_explicit_id_list(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'pasa-doner-freeze');

        $first = $this->asset($workspaceId, 'adana-kebap.jpg');
        $second = $this->asset($workspaceId, 'iskender.jpg');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/media/bulk/plan",
            ['action' => 'optimize', 'scope' => 'workspace'],
        );

        $response->assertOk();
        $this->assertSame([$first, $second], $response->json('snapshot.assetIds'));

        /*
            "İş başladığı anda liste dondurulur." Plandan SONRA yüklenen
            dosya listede yoktur; sahip ekranda gördüğü sayıyla çalıştırır.
        */
        $this->asset($workspaceId, 'gec-gelen.jpg');

        $again = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/media/bulk/run",
            [
                'action' => 'optimize',
                'operationKey' => 'bulk-freeze-1',
                'assetIds' => $response->json('snapshot.assetIds'),
                'scope' => 'workspace',
            ],
        );

        $again->assertOk();
        $this->assertCount(2, $again->json('results'));
    }

    public function test_dry_run_counts_real_skips_with_reasons_and_touches_nothing(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'pasa-doner-dry');

        $ready = $this->asset($workspaceId, 'ready.jpg');
        $quarantined = $this->asset($workspaceId, 'karantina.jpg', ['status' => 'quarantined']);
        $held = $this->asset($workspaceId, 'yasal.jpg', [
            'legal_hold_reason' => 'Uyuşmazlık kaydı 2026/14',
            'legal_hold_at' => now(),
        ]);

        /*
            Eylem `optimize`: karantina bir atlama sebebi ancak İŞLEYEN bir
            eylemde olabilir. Çöpe atmada karantinadaki dosya bilerek
            atlanmaz — taraması takılmış bir dosyayı silememek, sahibi
            kütüphanesini temizleyemez hâlde bırakırdı.
        */
        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/media/bulk/plan",
            ['action' => 'optimize', 'scope' => 'workspace'],
        );

        $response->assertOk();
        $this->assertSame(3, $response->json('scope.count'));
        $this->assertSame(1, $response->json('applyCount'));

        $skips = collect($response->json('skips'))->pluck('count', 'reason')->all();
        $this->assertSame(1, $skips['quarantine'] ?? null);
        $this->assertSame(1, $skips['legal-hold'] ?? null);

        // Atlanan dosya ADIYLA listelenir: "1 dosya atlandı" tek başına
        // sahibi hangi dosyayı arayacağını bilmeden bırakır.
        $names = collect($response->json('skippedAssets'))->pluck('id')->all();
        $this->assertContains($quarantined, $names);
        $this->assertContains($held, $names);

        // KURU ÇALIŞMA: hiçbir dosyaya dokunulmadı.
        $this->assertDatabaseHas('media_assets', ['id' => $ready, 'deleted_at' => null]);
        $this->assertDatabaseCount('media_bulk_operations', 0);
    }

    public function test_published_usage_is_a_real_skip_reason_for_destructive_actions(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'pasa-doner-usage');

        $used = $this->asset($workspaceId, 'menude.jpg');
        $free = $this->asset($workspaceId, 'bos.jpg');

        DB::table('media_usages')->insert([
            'workspace_id' => $workspaceId,
            'media_asset_id' => $used,
            'entity_type' => 'menu_item',
            'entity_id' => 1,
            'slot' => 'menu',
            // Yayın bağı `publication_id` ile kurulur: dolu olması
            // "misafirin gördüğü menü bu görseli gösteriyor" demektir.
            'publication_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/media/bulk/plan",
            ['action' => 'trash', 'scope' => 'workspace'],
        );

        $response->assertOk();
        $skips = collect($response->json('skips'))->pluck('count', 'reason')->all();
        $this->assertSame(1, $skips['published-usage'] ?? null);
        $this->assertSame(1, $response->json('applyCount'));

        $run = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/media/bulk/run",
            [
                'action' => 'trash',
                'operationKey' => 'bulk-usage-1',
                'assetIds' => [$used, $free],
                'scope' => 'workspace',
                // Çöpe atmak da yıkıcıdır: kaynağın kuralı gereği yazılı
                // onay ister (kalıcı silmede kelime `KALICI SİL` olur).
                'confirm' => 'ONAYLA',
            ],
        );

        $run->assertOk();
        // Yayındaki görsel çöpe GİTMEDİ; boştaki gitti.
        $this->assertDatabaseMissing('media_assets', ['id' => $free, 'deleted_at' => null]);
        $this->assertDatabaseHas('media_assets', ['id' => $used, 'deleted_at' => null]);
    }

    public function test_permanent_delete_requires_the_typed_word_and_changes_nothing_without_it(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'pasa-doner-purge');

        $trashed = $this->asset($workspaceId, 'copte.jpg', [
            'lifecycle_status' => 'trashed',
            'deleted_at' => now(),
        ]);

        $plan = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/media/bulk/plan",
            ['action' => 'purge', 'scope' => 'workspace'],
        );

        $plan->assertOk();
        $this->assertTrue($plan->json('confirmation.required'));
        $this->assertSame('KALICI SİL', $plan->json('confirmation.word'));
        $this->assertFalse($plan->json('impact.reversible'));

        // Onay kutusu YETMEZ: kelime yazılmadan iş çalışmaz.
        $refused = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/media/bulk/run",
            [
                'action' => 'purge',
                'operationKey' => 'bulk-purge-1',
                'assetIds' => [$trashed],
                'scope' => 'workspace',
                'confirm' => 'evet',
            ],
        );

        $refused->assertStatus(422);
        $this->assertDatabaseCount('media_bulk_operations', 0);
        $this->assertNotNull(
            DB::table('media_assets')->where('id', $trashed)->first(),
            'Yanlış onay kelimesi hiçbir satırı silmemeliydi.',
        );
    }

    public function test_editor_reads_the_locked_reason_but_cannot_run_a_permanent_delete(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'pasa-doner-role');

        $editor = $this->verifiedUser();
        $this->joinWorkspace($workspaceId, $editor, 'editor');

        $trashed = $this->asset($workspaceId, 'copte.jpg', [
            'lifecycle_status' => 'trashed',
            'deleted_at' => now(),
        ]);

        /*
            Kilit GİZLENMEZ, SEBEBİ YAZILIR. Editör plan alabilir — ekranda
            kartı görür, neden kapalı olduğunu okur. Gizleseydik editör
            "bu ürün kalıcı silemiyor" sanırdı ve yöneticisinden istemezdi.
        */
        $plan = $this->actingAs($editor)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/media/bulk/plan",
            ['action' => 'purge', 'scope' => 'workspace'],
        );

        $plan->assertOk();
        $this->assertFalse($plan->json('allowed'));
        $this->assertSame('workspace.manage', $plan->json('requiredPermission'));

        $run = $this->actingAs($editor)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/media/bulk/run",
            [
                'action' => 'purge',
                'operationKey' => 'bulk-role-1',
                'assetIds' => [$trashed],
                'scope' => 'workspace',
                'confirm' => 'KALICI SİL',
            ],
        );

        $run->assertStatus(403);
        $this->assertNotNull(DB::table('media_assets')->where('id', $trashed)->first());
    }

    public function test_the_same_operation_key_never_runs_twice(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'pasa-doner-idem');

        $first = $this->asset($workspaceId, 'bir.jpg');
        $second = $this->asset($workspaceId, 'iki.jpg');

        $payload = [
            'action' => 'trash',
            'operationKey' => 'bulk-idem-1',
            'assetIds' => [$first, $second],
            'scope' => 'workspace',
            'confirm' => 'ONAYLA',
        ];

        $one = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/media/bulk/run", $payload,
        );
        $one->assertOk();
        $this->assertFalse($one->json('replayed'));
        $this->assertSame(2, $one->json('applied'));

        $two = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/media/bulk/run", $payload,
        );
        $two->assertOk();
        $this->assertTrue($two->json('replayed'));

        // Tek satır: iş bir kez çalıştı.
        $this->assertDatabaseCount('media_bulk_operations', 1);
    }

    public function test_a_finished_run_writes_a_real_audit_row(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'pasa-doner-audit');

        $asset = $this->asset($workspaceId, 'bir.jpg');

        $run = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/media/bulk/run",
            [
                'action' => 'trash',
                'operationKey' => 'bulk-audit-1',
                'assetIds' => [$asset],
                'scope' => 'folder',
                'confirm' => 'ONAYLA',
            ],
        );

        $run->assertOk();

        $this->assertDatabaseHas('media_bulk_operations', [
            'workspace_id' => $workspaceId,
            'operation_key' => 'bulk-audit-1',
            'action' => 'trash',
            'scope' => 'folder',
            'applied_count' => 1,
            'actor_user_id' => $owner->id,
        ]);
    }

    public function test_another_tenant_asset_never_enters_the_batch(): void
    {
        $owner = $this->verifiedUser();
        $mine = $this->workspaceOwnedBy($owner, 'pasa-doner-mine');

        $stranger = $this->verifiedUser();
        $theirs = $this->workspaceOwnedBy($stranger, 'pasa-doner-theirs');

        $myAsset = $this->asset($mine, 'benim.jpg');
        $theirAsset = $this->asset($theirs, 'onun.jpg');

        $run = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$mine}/media/bulk/run",
            [
                'action' => 'trash',
                'operationKey' => 'bulk-tenant-1',
                'assetIds' => [$myAsset, $theirAsset],
                'scope' => 'workspace',
                'confirm' => 'ONAYLA',
            ],
        );

        $run->assertOk();
        $this->assertSame(1, $run->json('applied'));
        // Yabancı dosyaya DOKUNULMADI ve varlığı da sızmadı: sonuç
        // listesinde yalnız bu kiracının dosyası var.
        $this->assertDatabaseHas('media_assets', ['id' => $theirAsset, 'deleted_at' => null]);
        $this->assertSame([$myAsset], collect($run->json('results'))->pluck('id')->all());
    }
}
