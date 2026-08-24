<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * S1-WP07 RED — read-only GET /api/workspaces/{workspace}/security/
 * evidence/tenant-isolation: Owner-only via a new SecurityEvidenceView
 * permission, latest-record-only response, enumeration-safe 404 for
 * editor/member/outsider/wrong-workspace/no-record, and no mutation verb
 * registered on the same URI (405, not 404, once GET exists). The route
 * isn't registered yet, so every request below fails RED with a 404
 * route-not-found response before any RBAC/tenant-guard logic runs.
 *
 * Requirement IDs: SEC-EVID-API-OWNER-ONLY-01, SEC-EVID-API-LATEST-01,
 * SEC-EVID-API-ENUM-SAFE-01, SEC-EVID-API-NO-MUTATION-01,
 * SEC-EVID-API-TRUTH-BOUNDARY-01.
 */
final class TenantIsolationEvidenceApiTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    private function endpoint(int $workspaceId): string
    {
        return "/api/workspaces/{$workspaceId}/security/evidence/tenant-isolation";
    }

    private function workspaceWithMember(User $owner, string $slugSeed, string $role = 'owner', ?User $member = null): int
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Restoran '.$slugSeed,
            'slug' => $slugSeed,
            'state' => 'active',
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => ($member ?? $owner)->id,
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $workspaceId;
    }

    /**
     * Builds a row that verifies as valid by going through the public
     * domain contract (App\Domain\Security\TenantIsolationEvidenceRecord
     * ::fromRun()) for the integrity digest and claim, rather than
     * hand-typing an arbitrary integrity_sha256 that would never match
     * its own canonical fields. Only the tamper test corrupts a field
     * after this insert.
     */
    private function insertPassedEvidenceRow(?string $ranAt = null): int
    {
        $recordClass = 'App\\Domain\\Security\\TenantIsolationEvidenceRecord';

        $record = $recordClass::fromRun(
            status: 'passed',
            durationMs: 999,
            exitCode: 0,
            gitSha: str_repeat('a', 40),
            gitDirty: false,
            sourceSnapshotSha256: str_repeat('b', 64),
            suiteManifestSha256: str_repeat('c', 64),
            outputSha256: str_repeat('d', 64),
            ranAt: $ranAt ?? now()->toIso8601String(),
        );

        return (int) DB::table('tenant_isolation_evidence')->insertGetId([
            'key' => $record->key(),
            'status' => $record->status(),
            'scope' => $record->scope(),
            'runner' => $record->runner(),
            'ran_at' => $record->ranAt(),
            'duration_ms' => $record->durationMs(),
            'exit_code' => $record->exitCode(),
            'git_sha' => $record->gitSha(),
            'git_dirty' => $record->gitDirty(),
            'source_snapshot_sha256' => $record->sourceSnapshotSha256(),
            'suite_manifest_sha256' => $record->suiteManifestSha256(),
            'output_sha256' => $record->outputSha256(),
            'integrity_sha256' => $record->integritySha256(),
            'claim' => $record->claim(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_owner_receives_the_latest_evidence_record(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceWithMember($owner, 'evid-owner');

        $this->insertPassedEvidenceRow(now()->subHour()->toIso8601String());
        $latestId = $this->insertPassedEvidenceRow(now()->toIso8601String());

        $response = $this->actingAs($owner)->getJson($this->endpoint($workspaceId), $this->jsonHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.id', $latestId);
        $response->assertJsonPath('data.key', 'tenant_isolation');
        $response->assertJsonPath('data.status', 'passed');
        $response->assertJsonPath('data.scope', 'automated_local_feature_tests');
        $response->assertJsonPath('data.runner', 'phpunit');
        $response->assertJsonStructure([
            'data' => [
                'id', 'key', 'status', 'scope', 'runner', 'ran_at', 'duration_ms',
                'exit_code', 'git_sha', 'git_dirty', 'source_snapshot_sha256',
                'suite_manifest_sha256', 'output_sha256', 'integrity_sha256', 'claim',
            ],
        ]);
    }

    public function test_response_never_carries_raw_test_output_or_secrets(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceWithMember($owner, 'evid-secret');
        $this->insertPassedEvidenceRow();

        $response = $this->actingAs($owner)->getJson($this->endpoint($workspaceId), $this->jsonHeaders());

        $response->assertOk();
        $response->assertJsonMissingPath('data.output');
        $response->assertJsonMissingPath('data.raw_output');
        $response->assertJsonMissingPath('data.secrets');
    }

    public function test_claim_field_explicitly_denies_audit_pentest_or_production_proof(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceWithMember($owner, 'evid-claim');
        $this->insertPassedEvidenceRow();

        $response = $this->actingAs($owner)->getJson($this->endpoint($workspaceId), $this->jsonHeaders());

        $response->assertOk();
        $claim = $response->json('data.claim');
        $this->assertIsString($claim);
        $this->assertStringContainsString('ASVS', $claim);
        $this->assertMatchesRegularExpression('/not.*(an\s+ASVS\s+audit|pentest|production proof)/i', $claim);
    }

    public function test_editor_gets_enumeration_safe_404(): void
    {
        $owner = $this->verifiedUser();
        $editor = $this->verifiedUser();
        $workspaceId = $this->workspaceWithMember($owner, 'evid-editor');
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $editor->id,
            'role' => 'editor',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->insertPassedEvidenceRow();

        $response = $this->actingAs($editor)->getJson($this->endpoint($workspaceId), $this->jsonHeaders());

        $response->assertNotFound();
        $response->assertJsonMissing(['data']);
    }

    public function test_member_gets_enumeration_safe_404(): void
    {
        $owner = $this->verifiedUser();
        $member = $this->verifiedUser();
        $workspaceId = $this->workspaceWithMember($owner, 'evid-member', 'member', $owner);
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $member->id,
            'role' => 'member',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->insertPassedEvidenceRow();

        $response = $this->actingAs($member)->getJson($this->endpoint($workspaceId), $this->jsonHeaders());

        $response->assertNotFound();
    }

    public function test_non_member_outsider_gets_enumeration_safe_404(): void
    {
        $owner = $this->verifiedUser();
        $outsider = $this->verifiedUser();
        $workspaceId = $this->workspaceWithMember($owner, 'evid-outsider');
        $this->insertPassedEvidenceRow();

        $response = $this->actingAs($outsider)->getJson($this->endpoint($workspaceId), $this->jsonHeaders());

        $response->assertNotFound();
    }

    public function test_owner_of_a_different_workspace_gets_enumeration_safe_404(): void
    {
        $ownerA = $this->verifiedUser();
        $ownerB = $this->verifiedUser();
        $workspaceIdA = $this->workspaceWithMember($ownerA, 'evid-wsa');
        $this->workspaceWithMember($ownerB, 'evid-wsb');
        $this->insertPassedEvidenceRow();

        $response = $this->actingAs($ownerB)->getJson($this->endpoint($workspaceIdA), $this->jsonHeaders());

        $response->assertNotFound();
    }

    public function test_unauthenticated_caller_is_rejected(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceWithMember($owner, 'evid-unauth');
        $this->insertPassedEvidenceRow();

        $response = $this->getJson($this->endpoint($workspaceId), $this->jsonHeaders());

        $response->assertStatus(401);
    }

    public function test_owner_with_no_recorded_evidence_gets_enumeration_safe_404(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceWithMember($owner, 'evid-none');

        $response = $this->actingAs($owner)->getJson($this->endpoint($workspaceId), $this->jsonHeaders());

        $response->assertNotFound();
    }

    /**
     * A tampered row (integrity digest no longer matches the canonical
     * fields) must never be served as passed/valid evidence — the API
     * fails closed rather than trusting a mismatched digest.
     */
    public function test_tampered_evidence_row_is_never_served_as_valid(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceWithMember($owner, 'evid-tamper');
        $this->insertPassedEvidenceRow();

        DB::table('tenant_isolation_evidence')->update(['status' => 'passed', 'exit_code' => 0, 'git_sha' => str_repeat('9', 40)]);

        $response = $this->actingAs($owner)->getJson($this->endpoint($workspaceId), $this->jsonHeaders());

        $response->assertStatus(500);
        $response->assertJsonMissingPath('data.status');
    }

    /**
     * The GET route exists (Owner-authorized), but no mutation verb is
     * registered on the same URI — a matched route that rejects the verb
     * responds 405 Method Not Allowed, never 404. No mutation ever
     * occurs regardless of status code.
     */
    public function test_no_post_put_patch_or_delete_mutation_route_exists(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceWithMember($owner, 'evid-nomutate');

        $endpoint = $this->endpoint($workspaceId);
        $countBefore = DB::table('tenant_isolation_evidence')->count();

        $this->actingAs($owner)->postJson($endpoint, [], $this->jsonHeaders())->assertStatus(405);
        $this->actingAs($owner)->putJson($endpoint, [], $this->jsonHeaders())->assertStatus(405);
        $this->actingAs($owner)->patchJson($endpoint, [], $this->jsonHeaders())->assertStatus(405);
        $this->actingAs($owner)->deleteJson($endpoint, [], $this->jsonHeaders())->assertStatus(405);

        $this->assertSame($countBefore, DB::table('tenant_isolation_evidence')->count());
    }

    /**
     * SecurityEvidenceView is a new Owner-only permission — it must not
     * be granted to Member or Editor role sets (truth boundary: even a
     * privileged non-owner role must not see global evidence through a
     * per-workspace route).
     */
    public function test_security_evidence_view_permission_is_owner_only(): void
    {
        $this->assertTrue(enum_exists('App\\Domain\\Authorization\\Permission'));

        $permissionClass = 'App\\Domain\\Authorization\\Permission';
        $rolePermissionsClass = 'App\\Domain\\Authorization\\RolePermissions';
        $membershipRoleClass = 'App\\Domain\\Tenancy\\MembershipRole';

        $this->assertTrue(defined($permissionClass.'::SecurityEvidenceView'));

        $ownerPermissions = $rolePermissionsClass::for($membershipRoleClass::Owner);
        $this->assertContains($permissionClass::SecurityEvidenceView, $ownerPermissions);

        $memberPermissions = $rolePermissionsClass::for($membershipRoleClass::Member);
        $this->assertNotContains($permissionClass::SecurityEvidenceView, $memberPermissions);

        $editorPermissions = $rolePermissionsClass::for($membershipRoleClass::Editor);
        $this->assertNotContains($permissionClass::SecurityEvidenceView, $editorPermissions);
    }
}
