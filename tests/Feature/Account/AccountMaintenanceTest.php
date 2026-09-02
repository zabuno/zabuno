<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * P1-07 RED — kullanıcı kendi hesabını kendi onarır (`docs/83`).
 *
 * MÜŞTERİ SORUNU. Self-service bir üründe kullanıcı kendi hesabını kendi
 * onarır. Bugün: adını değiştiremez, OTURUMU AÇIKKEN şifresini
 * değiştiremez, davet ettiği kişinin rolünü sonradan düzeltemez. Yanlış rol
 * verdiyse tek çare üyeyi silip yeniden davet etmektir.
 *
 * KARAR (kriter 1): şifre değişince DİĞER OTURUMLAR SONLANDIRILIR. İnsanların
 * şifre değiştirmesinin en yaygın nedeni, birinin onu ele geçirdiğinden
 * şüphelenmektir; diğer oturumları açık bırakmak işlemin amacını boşa
 * çıkarırdı.
 *
 * Requirement IDs: ACCOUNT-NAME-01, ACCOUNT-PASSWORD-01,
 * ACCOUNT-PASSWORD-WRONG-CURRENT-01, ACCOUNT-PASSWORD-OTHER-SESSIONS-01,
 * TEAM-ROLE-CHANGE-01, TEAM-ROLE-IMMEDIATE-01, TEAM-ROLE-LAST-OWNER-01.
 */
final class AccountMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private function api(User $user)
    {
        return $this->actingAs($user)->withHeaders(['Accept' => 'application/json']);
    }

    /** @return array{0:User,1:int} [owner, workspaceId] */
    private function ownerWorkspace(string $slug): array
    {
        $owner = User::factory()->create([
            'email_verified_at' => now(),
            'name' => 'Ismail',
            'password' => Hash::make('eski-parola-123'),
        ]);

        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => $slug, 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId, 'user_id' => $owner->id, 'role' => 'owner',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$owner, $workspaceId];
    }

    // --- ACCOUNT-NAME-01 --------------------------------------------------

    public function test_a_user_can_correct_their_own_name(): void
    {
        [$owner] = $this->ownerWorkspace('acct-name');

        $this->api($owner)->putJson('/api/user/profile', ['name' => 'İsmail Karaca'])
            ->assertOk()
            ->assertJsonPath('name', 'İsmail Karaca');

        self::assertSame('İsmail Karaca', (string) $owner->fresh()->name);
    }

    public function test_a_blank_name_is_refused_rather_than_silently_kept(): void
    {
        [$owner] = $this->ownerWorkspace('acct-name-blank');

        // Boş bir ad bir NİYETTİR: kullanıcı Kaydet'e bastı. Sessizce eski
        // adı korumak, düğmeye basılıp hiçbir şey olmaması demektir.
        $this->api($owner)->putJson('/api/user/profile', ['name' => '   '])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        self::assertSame('Ismail', (string) $owner->fresh()->name);
    }

    // --- ACCOUNT-PASSWORD-01 ----------------------------------------------

    public function test_a_signed_in_user_sets_a_new_password_by_proving_the_current_one(): void
    {
        [$owner] = $this->ownerWorkspace('acct-pass');

        $this->api($owner)->putJson('/api/user/password', [
            'currentPassword' => 'eski-parola-123',
            'password' => 'yeni-parola-456',
            'password_confirmation' => 'yeni-parola-456',
        ])->assertOk();

        self::assertTrue(
            Hash::check('yeni-parola-456', (string) $owner->fresh()->password),
            'ACCOUNT-PASSWORD-01: yeni şifre gerçekten yazılmalı.'
        );
    }

    // --- ACCOUNT-PASSWORD-WRONG-CURRENT-01 --------------------------------

    public function test_the_current_password_must_be_proven(): void
    {
        [$owner] = $this->ownerWorkspace('acct-pass-wrong');

        // Mevcut şifre sorulmadan değiştirme, açık bırakılmış bir bilgisayarı
        // hesabın tamamına dönüştürürdü.
        $this->api($owner)->putJson('/api/user/password', [
            'currentPassword' => 'yanlis-parola',
            'password' => 'yeni-parola-456',
            'password_confirmation' => 'yeni-parola-456',
        ])->assertStatus(422)->assertJsonValidationErrors('currentPassword');

        self::assertTrue(Hash::check('eski-parola-123', (string) $owner->fresh()->password));
    }

    public function test_a_weak_or_unconfirmed_password_is_refused(): void
    {
        [$owner] = $this->ownerWorkspace('acct-pass-weak');

        $this->api($owner)->putJson('/api/user/password', [
            'currentPassword' => 'eski-parola-123',
            'password' => 'kisa',
            'password_confirmation' => 'kisa',
        ])->assertStatus(422)->assertJsonValidationErrors('password');

        $this->api($owner)->putJson('/api/user/password', [
            'currentPassword' => 'eski-parola-123',
            'password' => 'yeni-parola-456',
            'password_confirmation' => 'baska-bir-sey',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    // --- ACCOUNT-PASSWORD-OTHER-SESSIONS-01 -------------------------------

    public function test_changing_the_password_ends_every_other_session(): void
    {
        [$owner] = $this->ownerWorkspace('acct-pass-sessions');

        foreach (['telefon', 'tablet'] as $index => $device) {
            DB::table('sessions')->insert([
                'id' => 'oturum-'.$device,
                'user_id' => $owner->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => $device,
                'payload' => base64_encode(serialize([])),
                'last_activity' => time() - $index,
            ]);
        }

        // Başka bir kullanıcının oturumuna DOKUNULMAMALI.
        $stranger = User::factory()->create(['email_verified_at' => now()]);
        DB::table('sessions')->insert([
            'id' => 'oturum-yabanci',
            'user_id' => $stranger->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'baskasi',
            'payload' => base64_encode(serialize([])),
            'last_activity' => time(),
        ]);

        $this->api($owner)->putJson('/api/user/password', [
            'currentPassword' => 'eski-parola-123',
            'password' => 'yeni-parola-456',
            'password_confirmation' => 'yeni-parola-456',
        ])->assertOk();

        self::assertSame(
            0,
            DB::table('sessions')->where('user_id', $owner->id)->count(),
            'ACCOUNT-PASSWORD-OTHER-SESSIONS-01: şifre değişince kullanıcının diğer oturumları kapanmalı.'
        );

        self::assertSame(
            1,
            DB::table('sessions')->where('user_id', $stranger->id)->count(),
            'ACCOUNT-PASSWORD-OTHER-SESSIONS-01: başkasının oturumuna dokunulmamalı.'
        );
    }

    // --- TEAM-ROLE-CHANGE-01 / TEAM-ROLE-IMMEDIATE-01 ---------------------

    public function test_the_owner_fixes_a_wrong_role_without_deleting_the_person(): void
    {
        [$owner, $workspaceId] = $this->ownerWorkspace('acct-role');

        $teammate = User::factory()->create(['email_verified_at' => now()]);
        $membershipId = (int) DB::table('workspace_memberships')->insertGetId([
            'workspace_id' => $workspaceId, 'user_id' => $teammate->id, 'role' => 'manager',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->api($teammate)
            ->getJson("/api/workspaces/{$workspaceId}/team/members")
            ->assertOk();

        $this->api($owner)->putJson(
            "/api/workspaces/{$workspaceId}/team/members/{$membershipId}/role",
            ['role' => 'editor']
        )->assertOk()->assertJsonPath('role', 'editor');

        self::assertSame(
            'editor',
            (string) DB::table('workspace_memberships')->where('id', $membershipId)->value('role'),
        );

        // Rol değişikliği ANINDA etkilidir: iznin YOKLUĞU ölçülür.
        $this->api($teammate)
            ->getJson("/api/workspaces/{$workspaceId}/team/members")
            ->assertStatus(404);
    }

    public function test_only_a_role_the_owner_may_hand_out_is_accepted(): void
    {
        [$owner, $workspaceId] = $this->ownerWorkspace('acct-role-invalid');

        $teammate = User::factory()->create(['email_verified_at' => now()]);
        $membershipId = (int) DB::table('workspace_memberships')->insertGetId([
            'workspace_id' => $workspaceId, 'user_id' => $teammate->id, 'role' => 'editor',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Sahiplik rol düzenlemeyle verilmez; DEVREDİLİR.
        $this->api($owner)->putJson(
            "/api/workspaces/{$workspaceId}/team/members/{$membershipId}/role",
            ['role' => 'owner']
        )->assertStatus(422);

        self::assertSame(
            'editor',
            (string) DB::table('workspace_memberships')->where('id', $membershipId)->value('role'),
        );
    }

    // --- TEAM-ROLE-LAST-OWNER-01 ------------------------------------------

    public function test_the_owner_cannot_demote_themselves_and_strand_the_workspace(): void
    {
        [$owner, $workspaceId] = $this->ownerWorkspace('acct-role-self');

        $ownMembershipId = (int) DB::table('workspace_memberships')
            ->where('workspace_id', $workspaceId)->where('user_id', $owner->id)->value('id');

        $this->api($owner)->putJson(
            "/api/workspaces/{$workspaceId}/team/members/{$ownMembershipId}/role",
            ['role' => 'editor']
        )->assertStatus(422);

        self::assertSame(
            'owner',
            (string) DB::table('workspace_memberships')->where('id', $ownMembershipId)->value('role'),
            'TEAM-ROLE-LAST-OWNER-01: sahipsiz kalan bir çalışma alanı kimse tarafından onarılamaz.'
        );
    }

    public function test_a_manager_cannot_hand_out_roles(): void
    {
        [, $workspaceId] = $this->ownerWorkspace('acct-role-authz');

        $manager = User::factory()->create(['email_verified_at' => now()]);
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId, 'user_id' => $manager->id, 'role' => 'manager',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $victim = User::factory()->create(['email_verified_at' => now()]);
        $victimId = (int) DB::table('workspace_memberships')->insertGetId([
            'workspace_id' => $workspaceId, 'user_id' => $victim->id, 'role' => 'editor',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        /*
            403, 404 DEĞİL.

            Manager çalışma alanını GÖRÜYOR; varlığını gizlemenin anlamı yok.
            Deponun iki aşamalı kapı dili tam olarak bunu söylüyor: göremiyorsa
            404, görüyor ama yetkisi yoksa 403 — ve kullanıcının çıkış yolu
            farklıdır (sahipten istemek).
        */
        $this->api($manager)->putJson(
            "/api/workspaces/{$workspaceId}/team/members/{$victimId}/role",
            ['role' => 'manager']
        )->assertStatus(403);

        self::assertSame(
            'editor',
            (string) DB::table('workspace_memberships')->where('id', $victimId)->value('role'),
        );
    }
}
