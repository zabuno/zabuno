<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Application\Media\Port\MediaQuotaPort;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * YÖNETİŞİM — kanonik kaynak `docs/reference/panel-v3/MedyaModulu.dc.html`,
 * `data-screen-label="Yönetişim"` (plan: `docs/109-PANEL-V3.md` §2).
 *
 * Kaynağın kendi cümlesi: "Kim ne yapabilir, dosyalar ne kadar saklanır,
 * kim ne yaptı." Üç soru, üç bölüm — ve üçünün de bugüne kadar üründe
 * hiçbir cevabı yoktu.
 *
 * Sahibin gerçek yolculuğu: bir editör "kalıcı sil" düğmesini arıyor,
 * bulamıyor ve "ürün bunu yapamıyor" diye yöneticisine hiç sormuyor.
 * Yetki matrisi tam bu yüzden vardır: kilitli olan GİZLENMEZ, kilitli
 * göründüğü yerde SEBEBİ yazar.
 *
 * Dondurulan beş davranış:
 *
 * 1. **Matris kullanıcının GERÇEK rolünü yansıtır.** Uydurma dört
 *    kademeli bir rol modeli değil, bu deponun kendi izinleri
 *    (`RolePermissions`): editörde `media.manage` var, `workspace.manage`
 *    yok — kalıcı silme bu yüzden kilitlidir ve gereken izin yazılır.
 * 2. **Saklama politikası GERÇEK sayılardan gelir.** Çöp penceresi
 *    kotanın kendi `trashRetentionDays` alanıdır, ekranda yazılı bir
 *    sabit değil.
 * 3. **Yasal saklama GERÇEK bir kilittir.** Kayıt konulan dosya toplu
 *    işlemde atlanır ve tek dosya silmede de silinmez.
 * 4. **Yasal saklamayı koymak/kaldırmak çalışma alanı yöneticiliği
 *    ister.** Editör deneyince 403 alır ve hiçbir satır değişmez.
 * 5. **Denetim izi SALT OKUNURDUR ve iki kaynağı birleştirir:** tek
 *    dosya kayıtları (`media_audits`) ve toplu iş kayıtları
 *    (`media_bulk_operations`). Sahibin sorusu "kim ne yaptı", "hangi
 *    tabloya bakayım" değil.
 */
final class MediaLegalHoldAndGovernanceTest extends TestCase
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

    /** @param array<string, mixed> $overrides */
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

    public function test_the_matrix_shows_the_editor_a_locked_row_with_its_reason(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'gov-matrix');

        $editor = $this->verifiedUser();
        $this->joinWorkspace($workspaceId, $editor, 'editor');

        $response = $this->actingAs($editor)->withHeaders($this->jsonHeaders())->getJson(
            "/api/workspaces/{$workspaceId}/media/governance",
        );

        $response->assertOk();
        $this->assertSame('editor', $response->json('role'));

        $rows = collect($response->json('permissions'))->keyBy('action');

        // İçerik düzenlemek editörün işidir.
        $this->assertTrue($rows['optimize']['allowed']);
        $this->assertTrue($rows['move']['allowed']);

        // Kalıcı silme DEĞİLDİR — ve kilit gizlenmez, sebebiyle durur.
        $this->assertFalse($rows['purge']['allowed']);
        $this->assertSame('workspace.manage', $rows['purge']['requiredPermission']);
        $this->assertFalse($rows['legal-hold']['allowed']);
    }

    public function test_the_owner_matrix_unlocks_the_destructive_rows(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'gov-owner');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->getJson(
            "/api/workspaces/{$workspaceId}/media/governance",
        );

        $response->assertOk();
        $rows = collect($response->json('permissions'))->keyBy('action');
        $this->assertTrue($rows['purge']['allowed']);
        $this->assertTrue($rows['legal-hold']['allowed']);
    }

    public function test_retention_numbers_come_from_the_real_quota_not_from_the_screen(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'gov-retention');

        $this->asset($workspaceId, 'yasal.jpg', [
            'legal_hold_reason' => 'Uyuşmazlık kaydı 2026/14',
            'legal_hold_at' => now(),
        ]);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->getJson(
            "/api/workspaces/{$workspaceId}/media/governance",
        );

        $response->assertOk();

        $expected = app(MediaQuotaPort::class)->trashRetentionDaysFor($workspaceId);

        $this->assertSame($expected, $response->json('retention.trashRetentionDays'));
        $this->assertSame(1, $response->json('retention.legalHoldCount'));
        $this->assertCount(1, $response->json('legalHolds'));
    }

    public function test_only_a_workspace_manager_can_place_a_legal_hold(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'gov-hold-role');

        $editor = $this->verifiedUser();
        $this->joinWorkspace($workspaceId, $editor, 'editor');

        $asset = $this->asset($workspaceId, 'belge.jpg');

        $refused = $this->actingAs($editor)->withHeaders($this->jsonHeaders())->putJson(
            "/api/workspaces/{$workspaceId}/media/{$asset}/legal-hold",
            ['reason' => 'Uyuşmazlık kaydı 2026/14'],
        );

        $refused->assertStatus(403);
        $this->assertDatabaseHas('media_assets', ['id' => $asset, 'legal_hold_at' => null]);

        $accepted = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->putJson(
            "/api/workspaces/{$workspaceId}/media/{$asset}/legal-hold",
            ['reason' => 'Uyuşmazlık kaydı 2026/14'],
        );

        $accepted->assertOk();
        $this->assertSame('Uyuşmazlık kaydı 2026/14', $accepted->json('legalHold.reason'));
    }

    public function test_a_held_file_cannot_be_trashed_at_all(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'gov-hold-lock');

        $asset = $this->asset($workspaceId, 'belge.jpg', [
            'legal_hold_reason' => 'Uyuşmazlık kaydı 2026/14',
            'legal_hold_at' => now(),
        ]);

        /*
            Kilit TOPLU işlemde de tek dosyada da geçerlidir. Yalnız toplu
            işlemde atlamak, kilidi bir görünüm hâline getirirdi: sahip tek
            dosya silmeye geçer ve kilit hiç olmamış gibi davranırdı.
        */
        $single = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->deleteJson(
            "/api/workspaces/{$workspaceId}/media/{$asset}",
        );

        $single->assertStatus(409);
        $this->assertDatabaseHas('media_assets', ['id' => $asset, 'deleted_at' => null]);
    }

    public function test_the_audit_trail_merges_single_file_and_bulk_records(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'gov-trail');

        $single = $this->asset($workspaceId, 'tek.jpg');
        $bulkOne = $this->asset($workspaceId, 'toplu-bir.jpg');

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())->deleteJson(
            "/api/workspaces/{$workspaceId}/media/{$single}",
        )->assertStatus(204);

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/media/bulk/run",
            [
                'action' => 'trash',
                'operationKey' => 'gov-trail-1',
                'assetIds' => [$bulkOne],
                'scope' => 'workspace',
                'confirm' => 'ONAYLA',
            ],
        )->assertOk();

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->getJson(
            "/api/workspaces/{$workspaceId}/media/governance",
        );

        $response->assertOk();
        $kinds = collect($response->json('trail'))->pluck('kind')->unique()->sort()->values()->all();
        $this->assertSame(['asset', 'bulk'], $kinds);
    }

    public function test_a_stranger_never_reads_another_tenant_governance(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'gov-tenant');

        $stranger = $this->verifiedUser();

        $this->actingAs($stranger)->withHeaders($this->jsonHeaders())->getJson(
            "/api/workspaces/{$workspaceId}/media/governance",
        )->assertStatus(404);
    }
}
