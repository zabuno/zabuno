<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * S1-WP07 RED — read-only GET /api/workspaces/{workspace}/security/
 * evidence/backup-restore: Owner-only via the existing
 * SecurityEvidenceView permission, latest-record-only response,
 * enumeration-safe 404 for editor/member/outsider/wrong-workspace/
 * no-record, and no mutation verb registered on the same URI (405, not
 * 404, once GET exists). The route isn't registered yet, so every
 * request below fails RED with a 404 route-not-found response before
 * any RBAC/tenant-guard logic runs.
 *
 * Requirement IDs: SEC-BR-API-OWNER-ONLY-01, SEC-BR-API-LATEST-01,
 * SEC-BR-API-ENUM-SAFE-01, SEC-BR-API-NO-MUTATION-01,
 * SEC-BR-API-TRUTH-BOUNDARY-01, SEC-BR-API-NO-SECRETS-01.
 */
final class BackupRestoreEvidenceApiTest extends TestCase
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
        return "/api/workspaces/{$workspaceId}/security/evidence/backup-restore";
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
     * domain contract (App\Domain\Security\BackupRestoreEvidenceRecord
     * ::fromRun()) for the integrity digest and claim, rather than
     * hand-typing an arbitrary integrity_sha256 that would never match
     * its own canonical fields. Only the tamper test corrupts a field
     * after this insert.
     */
    private function insertPassedEvidenceRow(?string $ranAt = null): int
    {
        $recordClass = 'App\\Domain\\Security\\BackupRestoreEvidenceRecord';

        $record = $recordClass::fromRun(
            status: 'passed',
            durationMs: 999,
            exitCode: 0,
            gitSha: str_repeat('a', 40),
            gitDirty: false,
            sourceSnapshotSha256: str_repeat('b', 64),
            suiteManifestSha256: str_repeat('c', 64),
            backupSha256: str_repeat('d', 64),
            restoredDbSha256: str_repeat('d', 64),
            sourceRowCount: 9,
            restoredRowCount: 9,
            outputSha256: str_repeat('e', 64),
            ranAt: $ranAt ?? now()->toIso8601String(),
        );

        return (int) DB::table('backup_restore_evidence')->insertGetId([
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
            'backup_sha256' => $record->backupSha256(),
            'restored_db_sha256' => $record->restoredDbSha256(),
            'source_row_count' => $record->sourceRowCount(),
            'restored_row_count' => $record->restoredRowCount(),
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
        $workspaceId = $this->workspaceWithMember($owner, 'br-owner');

        $this->insertPassedEvidenceRow(now()->subHour()->toIso8601String());
        $latestId = $this->insertPassedEvidenceRow(now()->toIso8601String());

        $response = $this->actingAs($owner)->getJson($this->endpoint($workspaceId), $this->jsonHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.id', $latestId);
        $response->assertJsonPath('data.key', 'backup_restore');
        $response->assertJsonPath('data.status', 'passed');
        $response->assertJsonPath('data.scope', 'local_sqlite_online_backup_restore_drill');
        $response->assertJsonPath('data.runner', 'sqlite3_online_backup');
        $response->assertJsonStructure([
            'data' => [
                'id', 'key', 'status', 'scope', 'runner', 'ran_at', 'duration_ms',
                'exit_code', 'git_sha', 'git_dirty', 'source_snapshot_sha256',
                'suite_manifest_sha256', 'backup_sha256', 'restored_db_sha256',
                'source_row_count', 'restored_row_count', 'output_sha256',
                'integrity_sha256', 'claim',
            ],
        ]);
    }

    public function test_response_never_carries_raw_output_paths_or_hidden_identifiers(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceWithMember($owner, 'br-secret');
        $this->insertPassedEvidenceRow();

        $response = $this->actingAs($owner)->getJson($this->endpoint($workspaceId), $this->jsonHeaders());

        $response->assertOk();
        $response->assertJsonMissingPath('data.output');
        $response->assertJsonMissingPath('data.raw_output');
        $response->assertJsonMissingPath('data.secrets');
        $response->assertJsonMissingPath('data.absolute_path');
        $response->assertJsonMissingPath('data.source_path');
        $response->assertJsonMissingPath('data.backup_path');
        $response->assertJsonMissingPath('data.restored_path');
        $response->assertJsonMissingPath('data.temp_dir');
        $response->assertJsonMissingPath('data.tmp_dir');
        $response->assertJsonMissingPath('data.connection');
        $response->assertJsonMissingPath('data.pdo');
        $response->assertJsonMissingPath('data.dsn');
        $response->assertJsonMissingPath('data.drill_uuid');
        $response->assertJsonMissingPath('data.uuid');
    }

    public function test_claim_field_explicitly_denies_rpo_rto_and_production_dr_proof(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceWithMember($owner, 'br-claim');
        $this->insertPassedEvidenceRow();

        $response = $this->actingAs($owner)->getJson($this->endpoint($workspaceId), $this->jsonHeaders());

        $response->assertOk();
        $claim = $response->json('data.claim');
        $this->assertSame(
            'This evidence reflects one local SQLite online-backup and isolated file-copy restore drill against a frozen table manifest. It is not an RPO/RTO proof, not a production DR drill, and does not test cross-host or point-in-time recovery.',
            $claim,
        );
    }

    public function test_editor_gets_enumeration_safe_404(): void
    {
        $owner = $this->verifiedUser();
        $editor = $this->verifiedUser();
        $workspaceId = $this->workspaceWithMember($owner, 'br-editor');
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
        $workspaceId = $this->workspaceWithMember($owner, 'br-member', 'member', $owner);
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
        $workspaceId = $this->workspaceWithMember($owner, 'br-outsider');
        $this->insertPassedEvidenceRow();

        $response = $this->actingAs($outsider)->getJson($this->endpoint($workspaceId), $this->jsonHeaders());

        $response->assertNotFound();
    }

    public function test_owner_of_a_different_workspace_gets_enumeration_safe_404(): void
    {
        $ownerA = $this->verifiedUser();
        $ownerB = $this->verifiedUser();
        $workspaceIdA = $this->workspaceWithMember($ownerA, 'br-wsa');
        $this->workspaceWithMember($ownerB, 'br-wsb');
        $this->insertPassedEvidenceRow();

        $response = $this->actingAs($ownerB)->getJson($this->endpoint($workspaceIdA), $this->jsonHeaders());

        $response->assertNotFound();
    }

    public function test_unauthenticated_caller_is_rejected(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceWithMember($owner, 'br-unauth');
        $this->insertPassedEvidenceRow();

        $response = $this->getJson($this->endpoint($workspaceId), $this->jsonHeaders());

        $response->assertStatus(401);
    }

    public function test_owner_with_no_recorded_evidence_gets_enumeration_safe_404(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceWithMember($owner, 'br-none');

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
        $workspaceId = $this->workspaceWithMember($owner, 'br-tamper');
        $this->insertPassedEvidenceRow();

        DB::table('backup_restore_evidence')->update(['status' => 'passed', 'exit_code' => 0, 'git_sha' => str_repeat('9', 40)]);

        $response = $this->actingAs($owner)->getJson($this->endpoint($workspaceId), $this->jsonHeaders());

        $response->assertStatus(500);
        $response->assertJsonMissingPath('data.status');
    }

    /**
     * Tampering only the stored exact claim text (leaving status,
     * exit_code, and every other field untouched) must also fail closed
     * with 500 and no data.status — integrity covers the claim, not just
     * the run outcome fields.
     */
    public function test_tampered_claim_is_never_served_as_valid(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceWithMember($owner, 'br-claim-tamper');
        $this->insertPassedEvidenceRow();

        DB::table('backup_restore_evidence')->update(['claim' => 'This is not the frozen claim text.']);

        $response = $this->actingAs($owner)->getJson($this->endpoint($workspaceId), $this->jsonHeaders());

        $response->assertStatus(500);
        $response->assertJsonMissingPath('data.status');
        $response->assertJsonMissing(['data']);
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
        $workspaceId = $this->workspaceWithMember($owner, 'br-nomutate');

        $endpoint = $this->endpoint($workspaceId);
        $countBefore = DB::table('backup_restore_evidence')->count();

        $this->actingAs($owner)->postJson($endpoint, [], $this->jsonHeaders())->assertStatus(405);
        $this->actingAs($owner)->putJson($endpoint, [], $this->jsonHeaders())->assertStatus(405);
        $this->actingAs($owner)->patchJson($endpoint, [], $this->jsonHeaders())->assertStatus(405);
        $this->actingAs($owner)->deleteJson($endpoint, [], $this->jsonHeaders())->assertStatus(405);

        $this->assertSame($countBefore, DB::table('backup_restore_evidence')->count());
    }

    /**
     * SecurityEvidenceView is the existing Owner-only permission reused
     * from the tenant-isolation evidence endpoint — it must not be
     * granted to Member or Editor role sets on this endpoint either.
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
