<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Infrastructure\Security\Source\GitSecurityEvidenceSnapshot;
use RuntimeException;
use Tests\TestCase;

/**
 * SEC-EVIDENCE-NO-GIT-01 — kanıt komutları üretimde `git`siz ÇALIŞMALI.
 *
 * 2026-09-08'de sunucuda ölçüldü: `security:evidence:tenant-isolation` ve
 * `security:evidence:backup-restore` konteynerde "git: not found" ile
 * çöküyordu. Üretim imajında ne `git` ikilisi ne `.git` dizini var — ikisi
 * de bilinçli: derleme araçları çalışan sunucuya girmez (`docker/Dockerfile`
 * §1) ve `.dockerignore` `.git`'i eler. Sonuç: gece 03:40'taki yedek
 * tatbikatı üretimde HİÇ koşmamıştı ve koşamazdı. Kanıt üretmesi gereken
 * komut, kanıt yerine bir yığın izi bırakıyordu.
 *
 * Commit kimliği başka bir yerden zaten geliyor: deploy `ZABUNO_BUILD_REVISION`
 * ile konteynere verir, `config('build.revision')` onu taşır ve `/up/build`
 * onu yayımlar (`docs/87`). Snapshot, `git` yokken o kimliğe düşer.
 * `git_dirty` o durumda `false`tur — imaj değişmezdir, kirli olamaz.
 *
 * Kural fail-closed kalır: ne `git` ne derleme kimliği varsa kanıt
 * UYDURULMAZ; komut yine durur, ama sebebi iki kaynağı da adıyla söyler.
 */
final class SecurityEvidenceSnapshotWithoutGitTest extends TestCase
{
    private const REVISION = '40109db865903ad75963c3eb538c08a0f1b2269e';

    private string $emptyPath;

    private string|false $originalPath;

    protected function setUp(): void
    {
        parent::setUp();

        // `git` bulunamasın: PATH yalnız boş bir dizini gösterir. Symfony
        // Process ve ExecutableFinder ikisi de `getenv('PATH')`'e bakar.
        $this->emptyPath = sys_get_temp_dir().'/no-git-'.uniqid();
        mkdir($this->emptyPath, 0700, true);
        $this->originalPath = getenv('PATH');
        putenv('PATH='.$this->emptyPath);
    }

    protected function tearDown(): void
    {
        putenv('PATH='.($this->originalPath === false ? '' : $this->originalPath));
        @rmdir($this->emptyPath);

        parent::tearDown();
    }

    public function test_without_git_the_snapshot_falls_back_to_the_build_revision(): void
    {
        config(['build.revision' => self::REVISION]);

        $snapshot = (new GitSecurityEvidenceSnapshot)->collect(['composer.json']);

        self::assertSame(self::REVISION, $snapshot['git_sha']);
        self::assertFalse($snapshot['git_dirty'], 'Değişmez bir imaj kirli olamaz.');
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $snapshot['source_snapshot_sha256']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $snapshot['suite_manifest_sha256']);
    }

    public function test_without_git_and_without_a_build_revision_the_snapshot_refuses_and_names_both_sources(): void
    {
        config(['build.revision' => null]);

        try {
            (new GitSecurityEvidenceSnapshot)->collect(['composer.json']);
            self::fail('Kanıt uydurulmamalı: ne git ne derleme kimliği varken snapshot durmalı.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('git', $exception->getMessage());
            self::assertStringContainsString('ZABUNO_BUILD_REVISION', $exception->getMessage());
        }
    }

    public function test_a_malformed_build_revision_is_not_accepted_as_evidence(): void
    {
        config(['build.revision' => 'not-a-sha']);

        $this->expectException(RuntimeException::class);

        (new GitSecurityEvidenceSnapshot)->collect(['composer.json']);
    }
}
