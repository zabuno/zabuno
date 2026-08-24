<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Security;

use PHPUnit\Framework\TestCase;

/**
 * S1-WP07 RED — unit contract for the tenant-isolation evidence domain
 * record: the frozen six-file suite manifest, the immutable field set on
 * a recorded evidence row, and the deterministic integrity digest that
 * must be verified before serialization. Nothing under
 * App\Domain\Security exists yet, so every test below fails RED on class
 * resolution, not on assertion logic.
 *
 * Requirement IDs: SEC-EVID-MANIFEST-01, SEC-EVID-FIELDS-01,
 * SEC-EVID-INTEGRITY-01, SEC-EVID-CLAIM-01.
 */
final class TenantIsolationEvidenceRecordTest extends TestCase
{
    /**
     * Frozen contract: the operator-only evidence command executes
     * exactly these six existing tenant-isolation test files, in this
     * order, through the injected runner port. No other file is in
     * scope.
     */
    public function test_suite_manifest_is_frozen_to_exactly_six_named_files(): void
    {
        $manifestClass = 'App\\Domain\\Security\\TenantIsolationSuiteManifest';

        $this->assertTrue(
            class_exists($manifestClass),
            'App\\Domain\\Security\\TenantIsolationSuiteManifest must exist to freeze the six-file suite scope.'
        );

        /** @var list<string> $paths */
        $paths = $manifestClass::paths();

        $this->assertSame([
            'tests/Feature/Media/MediaTenantIsolationTest.php',
            'tests/Feature/MenuCatalog/MenuApiTenantEscapeTest.php',
            'tests/Feature/MenuCatalog/MenuCatalogTenantEscapeTest.php',
            'tests/Feature/Publication/PublicationTenantIsolationTest.php',
            'tests/Feature/QrDestination/BulkQrTenantIsolationTest.php',
            'tests/Feature/QrDestination/QrDestinationTenantIsolationTest.php',
        ], $paths);
    }

    public function test_manifest_exposes_no_way_to_add_or_override_a_suite_file(): void
    {
        $manifestClass = 'App\\Domain\\Security\\TenantIsolationSuiteManifest';
        $this->assertTrue(class_exists($manifestClass));

        $this->assertFalse(
            method_exists($manifestClass, 'withPath'),
            'The manifest must not expose a mutation hook for adding suite files.'
        );
        $this->assertFalse(
            method_exists($manifestClass, 'add'),
            'The manifest must not expose a mutation hook for adding suite files.'
        );
    }

    /**
     * A recorded evidence row must carry every immutable field the
     * frozen contract requires, with the documented value shapes.
     */
    public function test_evidence_record_exposes_required_immutable_fields_for_a_passed_run(): void
    {
        $recordClass = 'App\\Domain\\Security\\TenantIsolationEvidenceRecord';
        $this->assertTrue(class_exists($recordClass), 'App\\Domain\\Security\\TenantIsolationEvidenceRecord must exist.');

        $record = $recordClass::fromRun(
            status: 'passed',
            durationMs: 1234,
            exitCode: 0,
            gitSha: str_repeat('a', 40),
            gitDirty: false,
            sourceSnapshotSha256: str_repeat('b', 64),
            suiteManifestSha256: str_repeat('c', 64),
            outputSha256: str_repeat('d', 64),
            ranAt: '2026-08-23T10:00:00+00:00',
        );

        $this->assertSame('tenant_isolation', $record->key());
        $this->assertSame('passed', $record->status());
        $this->assertSame('automated_local_feature_tests', $record->scope());
        $this->assertSame('phpunit', $record->runner());
        $this->assertSame('2026-08-23T10:00:00+00:00', $record->ranAt());
        $this->assertSame(1234, $record->durationMs());
        $this->assertGreaterThanOrEqual(0, $record->durationMs());
        $this->assertSame(0, $record->exitCode());
        $this->assertSame(str_repeat('a', 40), $record->gitSha());
        $this->assertFalse($record->gitDirty());
        $this->assertSame(str_repeat('b', 64), $record->sourceSnapshotSha256());
        $this->assertSame(str_repeat('c', 64), $record->suiteManifestSha256());
        $this->assertSame(str_repeat('d', 64), $record->outputSha256());
        $this->assertNotSame('', $record->integritySha256());
        $this->assertSame(64, strlen($record->integritySha256()));

        $this->assertStringContainsString('ASVS', $record->claim());
        $this->assertStringContainsString('selected automated', mb_strtolower($record->claim()));
        $this->assertMatchesRegularExpression('/not.*(an\s+ASVS\s+audit|pentest|production proof)/i', $record->claim());
    }

    public function test_evidence_record_honestly_represents_a_failed_run_with_nonzero_exit(): void
    {
        $recordClass = 'App\\Domain\\Security\\TenantIsolationEvidenceRecord';
        $this->assertTrue(class_exists($recordClass));

        $record = $recordClass::fromRun(
            status: 'failed',
            durationMs: 500,
            exitCode: 1,
            gitSha: str_repeat('e', 40),
            gitDirty: true,
            sourceSnapshotSha256: str_repeat('f', 64),
            suiteManifestSha256: str_repeat('1', 64),
            outputSha256: str_repeat('2', 64),
            ranAt: '2026-08-23T11:00:00+00:00',
        );

        $this->assertSame('failed', $record->status());
        $this->assertNotSame(0, $record->exitCode());
        $this->assertTrue($record->gitDirty());
    }

    /**
     * The integrity digest is computed deterministically over the
     * canonical evidence fields (SHA-256), so two records built from the
     * exact same field values produce the exact same digest, and any
     * single differing field changes it.
     */
    public function test_integrity_digest_is_deterministic_over_canonical_fields(): void
    {
        $recordClass = 'App\\Domain\\Security\\TenantIsolationEvidenceRecord';
        $this->assertTrue(class_exists($recordClass));

        $args = [
            'status' => 'passed',
            'durationMs' => 42,
            'exitCode' => 0,
            'gitSha' => str_repeat('9', 40),
            'gitDirty' => false,
            'sourceSnapshotSha256' => str_repeat('9', 64),
            'suiteManifestSha256' => str_repeat('9', 64),
            'outputSha256' => str_repeat('9', 64),
            'ranAt' => '2026-08-23T12:00:00+00:00',
        ];

        $recordA = $recordClass::fromRun(...$args);
        $recordB = $recordClass::fromRun(...$args);

        $this->assertSame($recordA->integritySha256(), $recordB->integritySha256());

        $tampered = $recordClass::fromRun(...array_merge($args, ['status' => 'failed']));
        $this->assertNotSame($recordA->integritySha256(), $tampered->integritySha256());
    }

    /**
     * Verification must fail closed on tamper: a record whose stored
     * integrity digest no longer matches its recomputed digest over the
     * canonical fields must never verify as passed/valid.
     */
    public function test_verification_fails_closed_when_integrity_digest_does_not_match(): void
    {
        $recordClass = 'App\\Domain\\Security\\TenantIsolationEvidenceRecord';
        $this->assertTrue(class_exists($recordClass));

        $record = $recordClass::fromRun(
            status: 'passed',
            durationMs: 10,
            exitCode: 0,
            gitSha: str_repeat('7', 40),
            gitDirty: false,
            sourceSnapshotSha256: str_repeat('7', 64),
            suiteManifestSha256: str_repeat('7', 64),
            outputSha256: str_repeat('7', 64),
            ranAt: '2026-08-23T13:00:00+00:00',
        );

        $this->assertTrue($record->verifiesIntegrity());

        $tampered = $recordClass::reconstitute(
            id: 1,
            key: $record->key(),
            status: $record->status(),
            scope: $record->scope(),
            runner: $record->runner(),
            ranAt: $record->ranAt(),
            durationMs: $record->durationMs(),
            exitCode: $record->exitCode(),
            gitSha: $record->gitSha(),
            gitDirty: $record->gitDirty(),
            sourceSnapshotSha256: $record->sourceSnapshotSha256(),
            suiteManifestSha256: $record->suiteManifestSha256(),
            outputSha256: $record->outputSha256(),
            integritySha256: str_repeat('0', 64),
            claim: $record->claim(),
        );

        $this->assertFalse($tampered->verifiesIntegrity());
    }
}
