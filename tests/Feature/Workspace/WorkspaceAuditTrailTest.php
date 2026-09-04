<?php

declare(strict_types=1);

namespace Tests\Feature\Workspace;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ÇALIŞMA ALANI DENETİM İZİ — "bunu kim, ne zaman yaptı?" (FF-132).
 *
 * Ayarlar'ın dördüncü sekmesi (Denetim) bir ekran değil, bir SORUYA cevaptı:
 * menü bir gece değişti, sabah kimse hatırlamıyor. Depoda kayıtlar zaten
 * vardı ama her biri kendi köşesindeydi — medya izi bir uçta, yayın geçmişi
 * başka bir uçta — ve hiçbiri "çalışma alanında ne oldu" sorusunu tek başına
 * cevaplayamıyordu.
 *
 * Bu uç YENİ BİR KAYIT TUTMAZ. Var olanları tek bir zaman çizgisinde
 * birleştirir; uydurmaz, tamamlamaz, boşluğu doldurmaz. Kaydı olmayan bir
 * olay burada da yoktur ve bu dürüsttür: sahibin "her şey burada" sanması,
 * eksik bir izden daha tehlikelidir.
 *
 * Gereksinim: AUDIT-TRAIL-TENANT-01, AUDIT-TRAIL-PERMISSION-02,
 * AUDIT-TRAIL-MERGE-03, AUDIT-TRAIL-ORDER-04.
 */
final class WorkspaceAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function workspaceFor(User $owner, string $slug, string $role = 'owner'): int
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
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $workspaceId;
    }

    private function recordMediaAudit(int $workspaceId, int $actorId, string $action, string $at): void
    {
        $assetId = (int) DB::table('media_assets')->insertGetId([
            'workspace_id' => $workspaceId,
            'disk_path' => 'media/'.uniqid('', true).'.jpg',
            'original_name' => 'pirzola.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'slot' => 'logo',
            'status' => 'accepted',
            'alt_text' => 'Kuzu pirzola',
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        DB::table('media_audits')->insert([
            'workspace_id' => $workspaceId,
            'media_asset_id' => $assetId,
            'action' => $action,
            'actor_user_id' => $actorId,
            'created_at' => $at,
        ]);
    }

    public function test_the_trail_is_scoped_to_one_workspace_and_never_leaks_another(): void
    {
        $owner = $this->verifiedUser();
        $stranger = $this->verifiedUser();

        $mine = $this->workspaceFor($owner, 'zeytin-audit-mine');
        $theirs = $this->workspaceFor($stranger, 'zeytin-audit-theirs');

        $this->recordMediaAudit($mine, $owner->id, 'uploaded', '2026-09-01 10:00:00');
        $this->recordMediaAudit($theirs, $stranger->id, 'deleted', '2026-09-01 11:00:00');

        $response = $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$mine}/audit-trail");

        $response->assertOk();

        $actions = array_column($response->json('data'), 'action');

        self::assertSame(['uploaded'], $actions);
    }

    public function test_a_stranger_gets_404_not_403(): void
    {
        /*
            403 "böyle bir çalışma alanı var ama sana kapalı" der ve bu da
            bir bilgidir. Görmeye hakkı olmayan için kayıt HİÇ YOKTUR.
        */
        $owner = $this->verifiedUser();
        $stranger = $this->verifiedUser();

        $workspaceId = $this->workspaceFor($owner, 'zeytin-audit-404');

        $this->actingAs($stranger)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/audit-trail")
            ->assertNotFound();
    }

    public function test_a_member_may_not_read_who_did_what(): void
    {
        /*
            İzi okumak YÖNETME izni ister: kimin ne yaptığı, ekipteki
            herkesin göreceği bir şey değildir. Üye olduğu için 404 değil
            403 döner — çalışma alanının varlığını zaten biliyor.
        */
        $owner = $this->verifiedUser();
        $member = $this->verifiedUser();

        $workspaceId = $this->workspaceFor($owner, 'zeytin-audit-viewer');

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $member->id,
            // `member` bu üründeki en dar rol: görür, yönetmez.
            'role' => 'member',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($member)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/audit-trail")
            ->assertForbidden();
    }

    public function test_media_and_publication_events_share_one_timeline_newest_first(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceFor($owner, 'zeytin-audit-merge');

        $this->recordMediaAudit($workspaceId, $owner->id, 'uploaded', '2026-09-01 09:00:00');

        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspaceId,
            'name' => 'Zeytin',
            'slug' => 'zeytin-'.$workspaceId,
            'locale' => 'tr',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'brand_id' => $brandId,
            'workspace_id' => $workspaceId,
            'display_name' => 'Merkez',
            'city' => 'İstanbul',
            'country_code' => 'TR',
            'address_line1' => 'Bağdat Cad. 1',
            'timezone' => 'Europe/Istanbul',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $menuId = (int) DB::table('menus')->insertGetId([
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'name' => 'Ana Menü',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menu_publications')->insert([
            'workspace_id' => $workspaceId,
            'menu_id' => $menuId,
            'location_id' => $locationId,
            'version' => 3,
            'state' => 'published',
            'snapshot' => json_encode(['categories' => []]),
            'published_by' => $owner->id,
            'published_at' => '2026-09-01 12:00:00',
            'created_at' => '2026-09-01 12:00:00',
            'updated_at' => '2026-09-01 12:00:00',
        ]);

        $response = $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/audit-trail");

        $response->assertOk();

        $rows = $response->json('data');

        // İki KAYNAK, tek zaman çizgisi — en yeni önce.
        self::assertSame(['publication', 'media'], array_column($rows, 'source'));

        // Fail E-POSTAYLA yazılır: bir ekipte iki "Mehmet" olabilir ve
        // "Mehmet yayınladı" cümlesi hiçbir soruyu kapatmaz.
        self::assertSame($owner->email, $rows[0]['actor']);
    }
}
