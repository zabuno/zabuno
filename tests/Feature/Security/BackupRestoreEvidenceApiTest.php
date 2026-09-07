<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Domain\Security\BackupRestoreDriver;
use App\Domain\Security\BackupRestoreEvidenceRecord;
use App\Domain\Security\MediaBackupRestoreEvidenceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * S1-WP07 — read-only GET /api/workspaces/{workspace}/security/
 * evidence/backup-restore: Owner-only via the existing
 * SecurityEvidenceView permission, latest-record-only response,
 * enumeration-safe 404 for editor/member/outsider/wrong-workspace/
 * no-record, and no mutation verb registered on the same URI (405, not
 * 404, once GET exists).
 *
 * FF-199 (docs/124): the response now names the DRIVER that produced
 * the latest database record (`sqlite`/`pgsql`), carries the measured
 * backup size and phase durations, and adds a sibling `media` object —
 * the latest media drill record, or `null` when none was ever
 * recorded. A tampered media row fails closed exactly like a tampered
 * database row.
 *
 * Requirement IDs: SEC-BR-API-OWNER-ONLY-01, SEC-BR-API-LATEST-01,
 * SEC-BR-API-ENUM-SAFE-01, SEC-BR-API-NO-MUTATION-01,
 * SEC-BR-API-TRUTH-BOUNDARY-01, SEC-BR-API-NO-SECRETS-01,
 * SEC-BR-API-DRIVER-01, SEC-BR-API-MEDIA-01.
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
     * domain contract (BackupRestoreEvidenceRecord::fromRun()) for the
     * integrity digest and claim, rather than hand-typing an arbitrary
     * integrity_sha256 that would never match its own canonical fields.
     * Only the tamper tests corrupt a field after this insert.
     */
    private function insertPassedEvidenceRow(?string $ranAt = null, BackupRestoreDriver $driver = BackupRestoreDriver::Sqlite): int
    {
        $record = BackupRestoreEvidenceRecord::fromRun(
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
            driver: $driver,
            backupBytes: 40960,
            backupMs: 300,
            restoreMs: 200,
        );

        return (int) DB::table('backup_restore_evidence')->insertGetId([
            'key' => $record->key(),
            'status' => $record->status(),
            'scope' => $record->scope(),
            'runner' => $record->runner(),
            'driver' => $record->driver(),
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
            'backup_bytes' => $record->backupBytes(),
            'backup_ms' => $record->backupMs(),
            'restore_ms' => $record->restoreMs(),
            'output_sha256' => $record->outputSha256(),
            'integrity_sha256' => $record->integritySha256(),
            'claim' => $record->claim(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertPassedMediaEvidenceRow(?string $ranAt = null): int
    {
        $record = MediaBackupRestoreEvidenceRecord::fromRun(
            status: 'passed',
            durationMs: 1200,
            exitCode: 0,
            gitSha: str_repeat('a', 40),
            gitDirty: false,
            sourceSnapshotSha256: str_repeat('b', 64),
            suiteManifestSha256: str_repeat('c', 64),
            archiveSha256: str_repeat('1', 64),
            archiveBytes: 51200,
            sourceManifestSha256: str_repeat('2', 64),
            restoredManifestSha256: str_repeat('2', 64),
            sourceFileCount: 14,
            restoredFileCount: 14,
            sourceBytes: 48000,
            restoredBytes: 48000,
            outputSha256: str_repeat('3', 64),
            ranAt: $ranAt ?? now()->toIso8601String(),
        );

        return (int) DB::table('media_backup_restore_evidence')->insertGetId([
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
            'archive_sha256' => $record->archiveSha256(),
            'archive_bytes' => $record->archiveBytes(),
            'source_manifest_sha256' => $record->sourceManifestSha256(),
            'restored_manifest_sha256' => $record->restoredManifestSha256(),
            'source_file_count' => $record->sourceFileCount(),
            'restored_file_count' => $record->restoredFileCount(),
            'source_bytes' => $record->sourceBytes(),
            'restored_bytes' => $record->restoredBytes(),
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
        $response->assertJsonPath('data.driver', 'sqlite');
        $response->assertJsonPath('data.backup_bytes', 40960);
        $response->assertJsonPath('data.backup_ms', 300);
        $response->assertJsonPath('data.restore_ms', 200);
        $response->assertJsonPath('media', null);
        $response->assertJsonStructure([
            'data' => [
                'id', 'key', 'status', 'scope', 'runner', 'driver', 'ran_at', 'duration_ms',
                'exit_code', 'git_sha', 'git_dirty', 'source_snapshot_sha256',
                'suite_manifest_sha256', 'backup_sha256', 'restored_db_sha256',
                'source_row_count', 'restored_row_count', 'backup_bytes', 'backup_ms',
                'restore_ms', 'output_sha256', 'integrity_sha256', 'claim',
            ],
        ]);
    }

    /**
     * The driver is read from the record, not from the current
     * connection: a row produced by the PostgreSQL runner says `pgsql`
     * even when this test suite happens to run on SQLite.
     */
    public function test_response_names_the_driver_that_produced_the_latest_record(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceWithMember($owner, 'br-driver');
        $this->insertPassedEvidenceRow(driver: BackupRestoreDriver::Pgsql);

        $response = $this->actingAs($owner)->getJson($this->endpoint($workspaceId), $this->jsonHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.driver', 'pgsql');
        $response->assertJsonPath('data.scope', 'postgres_pg_dump_isolated_database_restore_drill');
        $response->assertJsonPath('data.runner', 'pg_dump_custom_pg_restore');
    }

    public function test_owner_receives_the_latest_media_record_beside_the_database_record(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceWithMember($owner, 'br-media');
        $this->insertPassedEvidenceRow();
        $this->insertPassedMediaEvidenceRow(now()->subHour()->toIso8601String());
        $latestMediaId = $this->insertPassedMediaEvidenceRow(now()->toIso8601String());

        $response = $this->actingAs($owner)->getJson($this->endpoint($workspaceId), $this->jsonHeaders());

        $response->assertOk();
        $response->assertJsonPath('media.id', $latestMediaId);
        $response->assertJsonPath('media.key', 'media_backup_restore');
        $response->assertJsonPath('media.status', 'passed');
        $response->assertJsonPath('media.scope', 'local_media_root_tar_isolated_restore_drill');
        $response->assertJsonPath('media.runner', 'tar_sha256_manifest');
        $response->assertJsonPath('media.source_file_count', 14);
        $response->assertJsonPath('media.restored_file_count', 14);
        $response->assertJsonPath('media.source_bytes', 48000);
        $response->assertJsonPath('media.archive_bytes', 51200);
        $response->assertJsonStructure([
            'media' => [
                'id', 'key', 'status', 'scope', 'runner', 'ran_at', 'duration_ms', 'exit_code',
                'git_sha', 'git_dirty', 'source_snapshot_sha256', 'suite_manifest_sha256',
                'archive_sha256', 'archive_bytes', 'source_manifest_sha256',
                'restored_manifest_sha256', 'source_file_count', 'restored_file_count',
                'source_bytes', 'restored_bytes', 'output_sha256', 'integrity_sha256', 'claim',
            ],
        ]);
        $this->assertStringContainsString('tar archive', $response->json('media.claim'));
    }

    /**
     * A media record alone is not "backup evidence": the database
     * record is the primary and its absence is still a 404.
     */
    public function test_media_record_without_a_database_record_is_still_not_found(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceWithMember($owner, 'br-media-only');
        $this->insertPassedMediaEvidenceRow();

        $response = $this->actingAs($owner)->getJson($this->endpoint($workspaceId), $this->jsonHeaders());

        $response->assertNotFound();
        $response->assertJsonMissing(['media']);
    }

    public function test_response_never_carries_raw_output_paths_or_hidden_identifiers(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceWithMember($owner, 'br-secret');
        $this->insertPassedEvidenceRow();
        $this->insertPassedMediaEvidenceRow();

        $response = $this->actingAs($owner)->getJson($this->endpoint($workspaceId), $this->jsonHeaders());

        $response->assertOk();
        foreach (['data', 'media'] as $section) {
            $response->assertJsonMissingPath($section.'.output');
            $response->assertJsonMissingPath($section.'.raw_output');
            $response->assertJsonMissingPath($section.'.secrets');
            $response->assertJsonMissingPath($section.'.absolute_path');
            $response->assertJsonMissingPath($section.'.source_path');
            $response->assertJsonMissingPath($section.'.backup_path');
            $response->assertJsonMissingPath($section.'.restored_path');
            $response->assertJsonMissingPath($section.'.media_root');
            $response->assertJsonMissingPath($section.'.temp_dir');
            $response->assertJsonMissingPath($section.'.tmp_dir');
            $response->assertJsonMissingPath($section.'.connection');
            $response->assertJsonMissingPath($section.'.pdo');
            $response->assertJsonMissingPath($section.'.dsn');
            $response->assertJsonMissingPath($section.'.password');
            $response->assertJsonMissingPath($section.'.drill_uuid');
            $response->assertJsonMissingPath($section.'.uuid');
        }
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
     * A tampered DRIVER (say, a SQLite drill relabelled as the
     * production PostgreSQL one) is exactly the lie the digest exists
     * to catch.
     */
    public function test_tampered_driver_is_never_served_as_valid(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceWithMember($owner, 'br-driver-tamper');
        $this->insertPassedEvidenceRow();

        DB::table('backup_restore_evidence')->update(['driver' => 'pgsql']);

        $response = $this->actingAs($owner)->getJson($this->endpoint($workspaceId), $this->jsonHeaders());

        $response->assertStatus(500);
        $response->assertJsonMissing(['data']);
    }

    public function test_tampered_media_row_is_never_served_as_valid(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceWithMember($owner, 'br-media-tamper');
        $this->insertPassedEvidenceRow();
        $this->insertPassedMediaEvidenceRow();

        DB::table('media_backup_restore_evidence')->update(['restored_file_count' => 13, 'status' => 'passed']);

        $response = $this->actingAs($owner)->getJson($this->endpoint($workspaceId), $this->jsonHeaders());

        $response->assertStatus(500);
        $response->assertJsonMissing(['data']);
        $response->assertJsonMissing(['media']);
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
