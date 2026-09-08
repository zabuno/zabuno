<?php

declare(strict_types=1);

namespace Tests\Feature\DataRights;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * KAYIT KİRACININ KENDİ EKRANINDA — FF-226 (`docs/138` §5, `docs/133` deseni).
 *
 * Bir veri talebinin kaydı platformun denetim kaydında dursaydı, kiracı
 * kendi hakkını kendi ekranından doğrulayamazdı: "istedim, ne oldu?"
 * sorusunun cevabı için bize yazmak zorunda kalırdı — ve tam olarak o
 * yazışmadan kurtulmak için bu paket yazıldı.
 *
 * YENİ BİR EV AÇILMADI. Sahip zaten Ayarlar → Denetim izi ekranına
 * bakıyor; talep oraya üçüncü bir kaynak olarak düşüyor.
 *
 * Gereksinim: DATA-AUDIT-TENANT-VISIBLE-01, DATA-AUDIT-ACTOR-02,
 * DATA-AUDIT-SCOPE-AND-COUNT-03, DATA-AUDIT-TENANT-ONLY-04.
 */
final class DataRightsAppearInTheTenantAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function workspaceFor(User $owner, string $slug): int
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

    public function test_an_export_request_lands_in_the_trail_the_tenant_can_read(): void
    {
        Queue::fake();

        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceFor($owner, 'zeytin-audit-export');

        $this->actingAs($owner)->postJson("/api/workspaces/{$workspaceId}/data-rights/exports")->assertStatus(202);

        $trail = $this->actingAs($owner)
            ->getJson("/api/workspaces/{$workspaceId}/audit-trail")
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($trail);
        $this->assertSame('data_rights', $trail[0]['source']);
        $this->assertSame('export.queued', $trail[0]['action']);
        // FAİL E-POSTAYLA: bir ekipte iki "Mehmet" olabilir.
        $this->assertSame($owner->email, $trail[0]['actor']);
        $this->assertStringContainsString('sections=', (string) $trail[0]['subject']);
    }

    public function test_a_finished_erasure_says_how_many_rows_really_went(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceFor($owner, 'zeytin-audit-erasure');

        DB::table('products')->insert([
            'workspace_id' => $workspaceId,
            'name' => 'Kuzu pirzola',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $requestId = (int) $this->actingAs($owner)
            ->postJson("/api/workspaces/{$workspaceId}/data-rights/erasure", ['confirmation' => 'Zeytin Restoranları'])
            ->assertStatus(202)
            ->json('id');

        $trail = $this->actingAs($owner)->getJson("/api/workspaces/{$workspaceId}/audit-trail")->json('data');
        $this->assertSame('erasure.scheduled', $trail[0]['action']);

        DB::table('workspace_data_requests')->where('id', $requestId)->update(['scheduled_for' => now()->subMinute()]);
        $this->artisan('zabuno:run-due-erasures')->assertExitCode(0);

        /*
            SİLMEDEN SONRA DA OKUNABİLİR OLMALI. Üyelik silindi, yani sahip
            artık bu çalışma alanını göremez ve iz de ona kapanır — bu
            doğrudur ve beklenendir. Kayıt yine de DURUR; okunması gereken
            yer artık platformun kendi kaydı değil, bu satırın kendisidir.
        */
        $row = DB::table('workspace_data_requests')->where('id', $requestId)->first();
        $this->assertNotNull($row);

        /** @var array<string, int> $counts */
        $counts = json_decode((string) $row->deleted_counts, true);
        $this->assertGreaterThan(0, array_sum($counts));

        $this->actingAs($owner)->getJson("/api/workspaces/{$workspaceId}/audit-trail")->assertStatus(404);
    }

    public function test_the_trail_never_shows_another_workspaces_data_request(): void
    {
        Queue::fake();

        $owner = $this->verifiedUser();
        $neighbour = $this->verifiedUser();

        $mine = $this->workspaceFor($owner, 'zeytin-audit-mine-dr');
        $theirs = $this->workspaceFor($neighbour, 'zeytin-audit-theirs-dr');

        $this->actingAs($neighbour)->postJson("/api/workspaces/{$theirs}/data-rights/exports")->assertStatus(202);

        $trail = $this->actingAs($owner)->getJson("/api/workspaces/{$mine}/audit-trail")->assertOk()->json('data');

        $this->assertSame([], $trail);
    }
}
