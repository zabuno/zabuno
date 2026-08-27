<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Domain\Authorization\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Blind RED test candidate for CORE-03 RBAC baseline (frozen scope per task
 * instruction: native Laravel/OOP RBAC only, Permission enum
 * workspace.view/workspace.manage; Owner gets both, Member gets view only;
 * deny missing membership/wrong workspace; no dependency, migration, UI,
 * ABAC, ReBAC, OpenFGA, invitation, audit, other-module permissions).
 *
 * Exercises the expected application/delivery Policy Decision Point through
 * its OOP port contract rather than through any HTTP endpoint (none exists
 * in the frozen scope), tenant-scoped to workspace_id:
 *   App\Application\Authorization\Port\AuthorizationPort::can(
 *       int $userId, Permission $permission, int $workspaceId
 *   ): bool
 *
 * App\Application\Authorization\Port\AuthorizationPort has no bound
 * implementation yet (no AuthServiceProvider binding, no PDP class), so
 * resolving it from the container is expected to fail RED with an
 * Illuminate\Contracts\Container\BindingResolutionException, not from a
 * syntax or bootstrap defect in this suite. workspaces / workspace_memberships
 * tables already exist (2026_08_19_000000_create_workspaces_and_workspace_
 * memberships_tables migration), so this suite writes membership rows
 * directly via the query builder rather than through any create/invite flow.
 */
final class WorkspaceAuthorizationJourneyTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    /**
     * `created_by` sabit `1` yazılıydı ve o kullanıcının var olduğu
     * varsayılıyordu. Varsayım PostgreSQL'de tutmadı: yabancı anahtar
     * kısıtı, olmayan bir kullanıcıya bağlanan çalışma alanını reddediyor.
     * Test verisi, kendi bağımlılığını kendisi kurmalı — sıraya güvenmemeli.
     */
    private function workspace(string $name, string $slug, ?int $createdBy = null): int
    {
        $createdBy ??= $this->verifiedUser()->id;

        return (int) DB::table('workspaces')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'state' => 'active',
            'created_by' => $createdBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function membership(int $workspaceId, int $userId, string $role): void
    {
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $userId,
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function port(): AuthorizationPort
    {
        return $this->app->make(AuthorizationPort::class);
    }

    // --- CORE03-PDP-OWNER-01 ------------------------------------------------

    public function test_owner_is_granted_both_view_and_manage_on_their_own_workspace(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspace('Zeytin Restoranları', 'zeytin-restoranlari');
        $this->membership($workspaceId, $owner->id, 'owner');

        $pdp = $this->port();

        self::assertTrue(
            $pdp->can($owner->id, Permission::WorkspaceView, $workspaceId),
            'PDP-OWNER-01: Owner kendi workspace\'inde workspace.view alabilmeli.'
        );
        self::assertTrue(
            $pdp->can($owner->id, Permission::WorkspaceManage, $workspaceId),
            'PDP-OWNER-01: Owner kendi workspace\'inde workspace.manage alabilmeli.'
        );
    }

    // --- CORE03-PDP-MEMBER-01 -----------------------------------------------

    public function test_member_is_granted_view_but_denied_manage_on_their_own_workspace(): void
    {
        $member = $this->verifiedUser();
        $workspaceId = $this->workspace('Deniz Kebap', 'deniz-kebap');
        $this->membership($workspaceId, $member->id, 'member');

        $pdp = $this->port();

        self::assertTrue(
            $pdp->can($member->id, Permission::WorkspaceView, $workspaceId),
            'PDP-MEMBER-01: Member kendi workspace\'inde workspace.view alabilmeli.'
        );
        self::assertFalse(
            $pdp->can($member->id, Permission::WorkspaceManage, $workspaceId),
            'PDP-MEMBER-01: Member kendi workspace\'inde workspace.manage ALAMAMALI (deny-by-default).'
        );
    }

    // --- CORE03-PDP-NO-MEMBERSHIP-01 -----------------------------------------

    public function test_user_with_no_membership_at_all_is_denied_both_permissions(): void
    {
        $stranger = $this->verifiedUser();
        $workspaceId = $this->workspace('Danışmanlık Zinciri', 'danismanlik-zinciri');
        // Deliberately no membership row inserted for $stranger.

        $pdp = $this->port();

        self::assertFalse(
            $pdp->can($stranger->id, Permission::WorkspaceView, $workspaceId),
            'PDP-NO-MEMBERSHIP-01: üyeliği olmayan kullanıcı workspace.view ALAMAMALI.'
        );
        self::assertFalse(
            $pdp->can($stranger->id, Permission::WorkspaceManage, $workspaceId),
            'PDP-NO-MEMBERSHIP-01: üyeliği olmayan kullanıcı workspace.manage ALAMAMALI.'
        );
    }

    // --- CORE03-PDP-WRONG-WORKSPACE-01 ---------------------------------------

    public function test_owner_of_one_workspace_is_denied_both_permissions_on_a_foreign_workspace(): void
    {
        $ayse = $this->verifiedUser();
        $ayseWorkspaceId = $this->workspace('Zeytin Restoranları', 'zeytin-restoranlari-2');
        $this->membership($ayseWorkspaceId, $ayse->id, 'owner');

        $foreignWorkspaceId = $this->workspace('Deniz Kebap', 'deniz-kebap-2');
        // $ayse has no membership row on $foreignWorkspaceId.

        $pdp = $this->port();

        self::assertFalse(
            $pdp->can($ayse->id, Permission::WorkspaceView, $foreignWorkspaceId),
            'PDP-WRONG-WORKSPACE-01: Owner rolü başka bir workspace\'e taşınmaz — yabancı workspace\'te view ALAMAMALI.'
        );
        self::assertFalse(
            $pdp->can($ayse->id, Permission::WorkspaceManage, $foreignWorkspaceId),
            'PDP-WRONG-WORKSPACE-01: Owner rolü başka bir workspace\'e taşınmaz — yabancı workspace\'te manage ALAMAMALI.'
        );
    }

    // --- CORE03-PDP-NONEXISTENT-WORKSPACE-01 --------------------------------

    public function test_permission_check_against_a_nonexistent_workspace_id_is_denied(): void
    {
        $user = $this->verifiedUser();

        $pdp = $this->port();

        self::assertFalse(
            $pdp->can($user->id, Permission::WorkspaceView, 999999999),
            'PDP-NONEXISTENT-WORKSPACE-01: var olmayan workspace_id için karar her zaman deny olmalı (deny-by-default, `docs/05` §2).'
        );
    }
}
