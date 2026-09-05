<?php

declare(strict_types=1);

namespace Tests\Feature\Team;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\GrantsPlanEntitlements;
use Tests\TestCase;

/**
 * MUTFAK ROLÜNE DAVET VE ROL DÜZELTME — kaynak `panel.dc.html`
 * (`data-screen-label="Takım"`): davet kartı üç hap çiziyor (Editör ·
 * Yönetici · Mutfak) ve üye satırındaki rol seçicide de "Mutfak" var.
 *
 * NEDEN KIRMIZI: `MembershipRole::invitable()` bugün yalnız `editor` ve
 * `manager` döndürüyor; davet isteği ve rol düzeltme isteği doğrulama
 * kurallarını TEK YERDEN, o listeden okuyor. Yani `role=kitchen` gönderen
 * bir sahip bugün 422 alır.
 *
 * MÜŞTERİ YOLCULUĞU. Sahip Ayşe, şef Hasan'ı e-postayla davet eder ve
 * "Mutfak" hapına basar. Hasan daveti kabul ettiğinde alerjen ve "bugün
 * bitti" dışında hiçbir şeye dokunamaz — Ayşe'nin ona `editor` verip bütün
 * fiyatları açmasına gerek kalmaz.
 *
 * SAHİPLİK KURALI DEĞİŞMEDİ: yeni bir rolün davet edilebilir olması,
 * sahipliğin de davet edilebilir olduğu anlamına gelmez. Sahiplik
 * DEVREDİLİR.
 *
 * Requirement IDs: KITCHEN-INVITE-01, KITCHEN-INVITE-ACCEPT-01,
 * KITCHEN-ROLE-CHANGE-01, KITCHEN-OWNERSHIP-STILL-NOT-INVITED-01.
 */
final class InviteKitchenRoleTest extends TestCase
{
    use GrantsPlanEntitlements;
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create([
            'email' => sprintf('%s@example.test', bin2hex(random_bytes(6))),
            'email_verified_at' => now(),
        ]);
    }

    private function workspaceOwnedBy(User $owner, string $slug): int
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => $slug, 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId, 'user_id' => $owner->id, 'role' => 'owner',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->grantEntitlements($workspaceId);

        return $workspaceId;
    }

    private function api(User $user)
    {
        return $this->actingAs($user)->withHeaders(['Accept' => 'application/json']);
    }

    // --- KITCHEN-INVITE-01 -----------------------------------------------

    public function test_owner_can_invite_someone_to_the_kitchen_role(): void
    {
        Mail::fake();

        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'kitchen-invite');

        $this->api($owner)
            ->postJson("/api/workspaces/{$workspaceId}/team/invitations", [
                'email' => 'hasan@example.test',
                'role' => 'kitchen',
            ])
            ->assertCreated()
            ->assertJsonPath('role', 'kitchen');

        $this->assertSame('kitchen', (string) DB::table('team_invitations')
            ->where('workspace_id', $workspaceId)
            ->where('email', 'hasan@example.test')
            ->value('role'));
    }

    // --- KITCHEN-INVITE-ACCEPT-01 ----------------------------------------

    public function test_accepting_a_kitchen_invitation_creates_a_kitchen_membership(): void
    {
        Mail::fake();

        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'kitchen-accept');

        $chef = User::factory()->create([
            'email' => 'hasan-accept@example.test',
            'email_verified_at' => now(),
        ]);

        $this->api($owner)
            ->postJson("/api/workspaces/{$workspaceId}/team/invitations", [
                'email' => $chef->email,
                'role' => 'kitchen',
            ])
            ->assertCreated();

        /*
            Ham jeton yalnız e-postada ve yanıtta yaşamaz; kabul akışının
            kendi testi (`AcceptTeamInvitationTest`) onu davet satırındaki
            karmadan üretemez. Burada kabul akışını taklit etmek yerine
            ÜRÜNÜN kendi ucunu çağırabilmek için jetonu yeniden üretiyoruz:
            davet oluşturmanın yanıtı ham jetonu taşımıyorsa satırı
            doğrudan bilinen bir karma ile güncelleriz — sınanan şey rolün
            taşınmasıdır, jeton üretimi değil.
        */
        $rawToken = bin2hex(random_bytes(16));

        DB::table('team_invitations')
            ->where('workspace_id', $workspaceId)
            ->where('email', $chef->email)
            ->update(['token_hash' => hash('sha256', $rawToken)]);

        $this->api($chef)
            ->postJson("/api/invitations/accept/{$rawToken}")
            ->assertSuccessful();

        $this->assertSame('kitchen', (string) DB::table('workspace_memberships')
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $chef->id)
            ->value('role'));
    }

    // --- KITCHEN-ROLE-CHANGE-01 ------------------------------------------

    public function test_owner_can_move_an_existing_member_to_the_kitchen_role(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'kitchen-role-change');
        $editor = $this->verifiedUser();

        $membershipId = (int) DB::table('workspace_memberships')->insertGetId([
            'workspace_id' => $workspaceId, 'user_id' => $editor->id, 'role' => 'editor',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Yanlış verilmiş bir rolü düzeltmek, üyeyi silip yeniden davet
        // etmeyi gerektirmemeli (`docs/83`) — yeni rol için de öyle.
        $this->api($owner)
            ->putJson("/api/workspaces/{$workspaceId}/team/members/{$membershipId}/role", ['role' => 'kitchen'])
            ->assertOk()
            ->assertJsonPath('role', 'kitchen');

        $this->assertSame('kitchen', (string) DB::table('workspace_memberships')
            ->where('id', $membershipId)
            ->value('role'));
    }

    // --- KITCHEN-OWNERSHIP-STILL-NOT-INVITED-01 --------------------------

    public function test_ownership_is_still_not_something_you_invite_someone_to(): void
    {
        Mail::fake();

        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'kitchen-ownership-guard');

        // Yeni bir rolün açılması, kapalı olanı açmaz.
        $this->api($owner)
            ->postJson("/api/workspaces/{$workspaceId}/team/invitations", [
                'email' => 'devir@example.test',
                'role' => 'owner',
            ])
            ->assertStatus(422);

        $this->api($owner)
            ->postJson("/api/workspaces/{$workspaceId}/team/invitations", [
                'email' => 'eski@example.test',
                'role' => 'member',
            ])
            ->assertStatus(422);
    }
}
