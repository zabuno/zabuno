<?php

declare(strict_types=1);

namespace Tests\Feature\PlatformAdmin;

use App\Domain\Platform\PlatformRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * RED test for the standalone authenticated Platform Admin shell entry
 * point (S1-WP01A follow-up). At authoring time there is no GET /engineering
 * route in routes/web.php and no resources/js/engineering.tsx Vite entry, so
 * every request below is expected to fail RED — either from the router
 * (unmatched route -> 404 with no view content) or, once routed, from the
 * still-missing platform admin shell view/asset wiring.
 *
 * Frozen contract:
 * - GET /engineering sits behind the same guard convention as GET /app:
 *   guest -> redirect/401 (never a bare unauthenticated 404-with-no-route),
 *   authenticated-but-unverified -> redirect/403 (existing "verified"
 *   convention), never a route-missing crash for either.
 * - A verified authenticated tenant Owner (a real workspace_memberships
 *   Owner row, no platform_role_assignments row) is denied
 *   enumeration-safely: exactly 404, indistinguishable from "route does not
 *   exist" to an outside observer probing for the platform surface.
 * - A verified authenticated super_admin (a real platform_role_assignments
 *   row, role=super_admin) receives 200 HTML containing
 *   id="engineering-app" and a reference to the resources/js/engineering.tsx
 *   Vite entry, with no plan records or other sensitive data rendered
 *   server-side into that HTML (the page is a client shell; the real data
 *   fetch happens client-side against the existing /api/admin/plans API).
 */
final class EngineeringShellJourneyTest extends TestCase
{
    use RefreshDatabase;

    private const PLATFORM_URI = '/engineering';

    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    private function verifiedUser(string $name, string $email): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'email_verified_at' => now(),
        ]);
    }

    private function grantSuperAdmin(User $user): void
    {
        DB::table('platform_role_assignments')->insert([
            'user_id' => $user->getKey(),
            'role' => PlatformRole::SuperAdmin->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function grantTenantOwnerMembership(User $user): void
    {
        $workspaceId = DB::table('workspaces')->insertGetId([
            'name' => 'Platform Shell Test Workspace',
            'slug' => 'engineering-shell-test-workspace-'.$user->getKey(),
            'state' => 'active',
            'created_by' => $user->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $user->getKey(),
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // --- guarded entry ---------------------------------------------------

    public function test_guest_is_rejected_from_platform_route_not_with_a_bare_404(): void
    {
        $response = $this->withHeaders($this->jsonHeaders())->get(self::PLATFORM_URI);

        self::assertNotSame(404, $response->getStatusCode(), 'GET /engineering: rota var olmalı; 404 kabul edilmez.');
        self::assertContains($response->getStatusCode(), [302, 401], 'GET /engineering: kimliksiz istek 302 (login redirect) veya 401 ile reddedilmeli.');
    }

    public function test_unverified_authenticated_user_is_rejected_from_platform_route(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->withHeaders($this->jsonHeaders())->get(self::PLATFORM_URI);

        self::assertNotSame(404, $response->getStatusCode(), 'GET /engineering: rota var olmalı; 404 kabul edilmez.');
        self::assertContains($response->getStatusCode(), [302, 403], 'GET /engineering: doğrulanmamış kullanıcı 302 (verify-notice redirect) veya 403 ile reddedilmeli.');
    }

    // --- authorization boundary -------------------------------------------

    public function test_verified_tenant_owner_without_platform_role_is_denied_enumeration_safely(): void
    {
        $owner = $this->verifiedUser('Ada Owner', 'ada-owner@example.com');
        $this->grantTenantOwnerMembership($owner);

        $response = $this->actingAs($owner)->get(self::PLATFORM_URI);

        $response->assertNotFound();
    }

    // --- authorized shell response ----------------------------------------

    public function test_verified_super_admin_receives_the_platform_admin_shell(): void
    {
        $admin = $this->verifiedUser('Super Admin', 'super-admin@example.com');
        $this->grantSuperAdmin($admin);

        $response = $this->actingAs($admin)->get(self::PLATFORM_URI);

        $response->assertSuccessful();
        $response->assertSee('id="engineering-app"', false);
        $response->assertSee('engineering.tsx', false);

        self::assertStringNotContainsString('amount_minor', $response->getContent() ?: '', 'Platform shell HTML plan verisini server-side sızdırmamalı.');
    }
}
