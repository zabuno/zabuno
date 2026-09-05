<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Security\Execution;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;

/**
 * FF-199 RED (docs/124) — the media drill: archive the media root with
 * `tar`, extract into an isolated directory, and prove the copy is the
 * original by file count, total bytes and a per-file SHA-256 manifest.
 *
 * This runs the REAL `tar` on a media root the test seeds itself; the
 * runner never sees `storage/app`. The corruption case uses a wrapper
 * around the real `tar` that flips one byte after extraction — so the
 * failure is caught by the runner's own verification, not injected
 * through a test seam in production code. That wrapper relies on the
 * runner invoking extraction as `tar -xf <archive> -C <dir>`; the
 * argument order is part of this contract.
 *
 * Requirement IDs: SEC-BR-MEDIA-ROUNDTRIP-01, SEC-BR-MEDIA-CORRUPT-01,
 * SEC-BR-MEDIA-NO-MUTATE-01, SEC-BR-MEDIA-CLEANUP-01,
 * SEC-BR-MEDIA-TOOLS-UNKNOWN-01, SEC-BR-MEDIA-NO-SECRETS-01.
 */
final class TarMediaBackupRestoreDrillRunnerTest extends TestCase
{
    private const RUNNER_CLASS = 'App\\Infrastructure\\Security\\Execution\\TarMediaBackupRestoreDrillRunner';

    private const PORT = 'App\\Application\\Security\\Port\\MediaBackupRestoreDrillRunnerPort';

    /** @var list<string> */
    private array $tempDirsToClean = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirsToClean as $dir) {
            $this->removeDirectoryRecursively($dir);
        }
        $this->tempDirsToClean = [];

        parent::tearDown();
    }

    private function removeDirectoryRecursively(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.DIRECTORY_SEPARATOR.$item;
            is_dir($path) && ! is_link($path) ? $this->removeDirectoryRecursively($path) : @unlink($path);
        }

        @rmdir($dir);
    }

    private function makeUniqueTempDir(): string
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'zabuno-media-drill-'.bin2hex(random_bytes(8));
        $this->assertTrue(mkdir($dir, 0700, true));
        $this->tempDirsToClean[] = $dir;

        return $dir;
    }

    /**
     * A media root shaped like the real one: `quarantine/{workspace}/…`
     * originals and `renditions/{workspace}/{asset}/…` derivatives, plus
     * a dotfile, with random bytes so hashes are not trivially equal.
     *
     * @return array{0: string, 1: int, 2: int} root, file count, total bytes
     */
    private function seedMediaRoot(string $dir): array
    {
        $root = $dir.DIRECTORY_SEPARATOR.'media';
        $files = [
            'quarantine/1/a1b2.jpg' => 3 * 1024,
            'quarantine/1/c3d4.pdf' => 5 * 1024 + 17,
            'quarantine/2/e5f6.png' => 1024,
            'renditions/1/10/thumb-abc.webp' => 700,
            'renditions/1/10/card-def.webp' => 1300,
            'renditions/2/11/thumb-ghi.webp' => 640,
            '.gitignore' => 12,
        ];

        $bytes = 0;
        foreach ($files as $relative => $size) {
            $path = $root.DIRECTORY_SEPARATOR.$relative;
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0700, true);
            }
            file_put_contents($path, random_bytes($size));
            $bytes += $size;
        }

        return [$root, count($files), $bytes];
    }

    /**
     * Wraps the real `tar` and corrupts the first extracted file — the
     * archive and the extraction both succeed, so only the runner's own
     * manifest comparison can notice.
     */
    private function makeCorruptingTarWrapper(string $dir): string
    {
        $real = (new ExecutableFinder)->find('tar');
        $this->assertNotNull($real, 'A real tar binary is required for this test.');

        $script = $dir.DIRECTORY_SEPARATOR.'tar-corrupting';
        $body = "#!/bin/sh\n"
            ."\"{$real}\" \"\$@\"\n"
            ."status=\$?\n"
            ."if [ \"\$1\" = \"-xf\" ]; then\n"
            ."  first=\$(find \"\$4\" -type f | LC_ALL=C sort | head -n 1)\n"
            ."  printf 'X' >> \"\$first\"\n"
            ."fi\n"
            ."exit \$status\n";
        file_put_contents($script, $body);
        chmod($script, 0700);

        return $script;
    }

    private function hashTree(string $root): string
    {
        $entries = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $entries[] = substr($file->getPathname(), strlen($root)).':'.hash_file('sha256', $file->getPathname());
            }
        }
        sort($entries);

        return hash('sha256', implode("\n", $entries));
    }

    public function test_runner_class_implements_the_media_port(): void
    {
        $this->assertTrue(class_exists(self::RUNNER_CLASS), self::RUNNER_CLASS.' must exist.');
        $this->assertTrue(interface_exists(self::PORT), self::PORT.' must exist.');
        $this->assertContains(self::PORT, class_implements(self::RUNNER_CLASS) ?: []);
    }

    public function test_a_successful_drill_reports_matching_counts_bytes_and_manifests(): void
    {
        $dir = $this->makeUniqueTempDir();
        [$root, $fileCount, $bytes] = $this->seedMediaRoot($dir);
        $workRoot = $this->makeUniqueTempDir();

        $runner = new (self::RUNNER_CLASS)($root, $workRoot);
        $result = $runner->run();

        $this->assertTrue($result['passed'], $result['output']);
        $this->assertTrue($result['measured']);
        $this->assertSame(0, $result['exit_code']);
        $this->assertGreaterThanOrEqual(0, $result['duration_ms']);
        $this->assertSame($fileCount, $result['source_file_count']);
        $this->assertSame($fileCount, $result['restored_file_count']);
        $this->assertSame($bytes, $result['source_bytes']);
        $this->assertSame($bytes, $result['restored_bytes']);
        $this->assertSame(64, strlen($result['source_manifest_sha256']));
        $this->assertSame($result['source_manifest_sha256'], $result['restored_manifest_sha256']);
        $this->assertSame(64, strlen($result['archive_sha256']));
        $this->assertGreaterThan($bytes, $result['archive_bytes'], 'A tar archive carries headers on top of the payload.');
    }

    /**
     * The manifest is a real per-file digest: two roots with the same
     * file names and sizes but different bytes must not share it.
     */
    public function test_manifest_digest_depends_on_file_contents_not_only_names_and_sizes(): void
    {
        $dirA = $this->makeUniqueTempDir();
        $dirB = $this->makeUniqueTempDir();
        [$rootA] = $this->seedMediaRoot($dirA);
        [$rootB] = $this->seedMediaRoot($dirB);

        $resultA = (new (self::RUNNER_CLASS)($rootA, $this->makeUniqueTempDir()))->run();
        $resultB = (new (self::RUNNER_CLASS)($rootB, $this->makeUniqueTempDir()))->run();

        $this->assertTrue($resultA['passed']);
        $this->assertTrue($resultB['passed']);
        $this->assertSame($resultA['source_bytes'], $resultB['source_bytes']);
        $this->assertNotSame($resultA['source_manifest_sha256'], $resultB['source_manifest_sha256']);
    }

    /**
     * One extra byte in one restored file: the drill fails, says it was
     * measured, and the two manifests disagree. File counts alone would
     * not have caught this — they still match — so the manifest
     * comparison is asserted on its own.
     */
    public function test_a_corrupted_restored_file_fails_the_drill(): void
    {
        $dir = $this->makeUniqueTempDir();
        [$root, $fileCount] = $this->seedMediaRoot($dir);
        $workRoot = $this->makeUniqueTempDir();
        $wrapper = $this->makeCorruptingTarWrapper($this->makeUniqueTempDir());

        $runner = new (self::RUNNER_CLASS)($root, $workRoot, $wrapper);
        $result = $runner->run();

        $this->assertFalse($result['passed']);
        $this->assertTrue($result['measured'], 'A drill that ran and caught corruption WAS measured.');
        $this->assertNotSame(0, $result['exit_code']);
        $this->assertSame($fileCount, $result['source_file_count']);
        $this->assertSame($fileCount, $result['restored_file_count']);
        $this->assertNotSame($result['source_manifest_sha256'], $result['restored_manifest_sha256']);
    }

    public function test_source_media_root_is_never_modified_by_the_drill(): void
    {
        $dir = $this->makeUniqueTempDir();
        [$root] = $this->seedMediaRoot($dir);
        $before = $this->hashTree($root);

        (new (self::RUNNER_CLASS)($root, $this->makeUniqueTempDir()))->run();

        $this->assertSame($before, $this->hashTree($root));
    }

    public function test_temp_artifacts_are_cleaned_up_after_success_and_after_corruption(): void
    {
        $dir = $this->makeUniqueTempDir();
        [$root] = $this->seedMediaRoot($dir);

        $workRootOk = $this->makeUniqueTempDir();
        (new (self::RUNNER_CLASS)($root, $workRootOk))->run();
        clearstatcache();
        $this->assertSame([], array_values(array_diff(scandir($workRootOk) ?: [], ['.', '..'])), 'No residue after a passed drill.');

        $workRootBad = $this->makeUniqueTempDir();
        $wrapper = $this->makeCorruptingTarWrapper($this->makeUniqueTempDir());
        (new (self::RUNNER_CLASS)($root, $workRootBad, $wrapper))->run();
        clearstatcache();
        $this->assertSame([], array_values(array_diff(scandir($workRootBad) ?: [], ['.', '..'])), 'No residue after a failed drill either.');
    }

    /**
     * An empty media root is a legitimate state (fresh install): nothing
     * to lose, nothing lost — passed with zero files. A MISSING root is
     * not: the drill cannot say anything about it, so it is unknown.
     */
    public function test_an_empty_media_root_passes_with_zero_files_but_a_missing_root_is_unknown(): void
    {
        $empty = $this->makeUniqueTempDir();
        $result = (new (self::RUNNER_CLASS)($empty, $this->makeUniqueTempDir()))->run();
        $this->assertTrue($result['passed'], $result['output']);
        $this->assertSame(0, $result['source_file_count']);
        $this->assertSame(0, $result['restored_file_count']);

        $missing = $this->makeUniqueTempDir().DIRECTORY_SEPARATOR.'does-not-exist';
        $result = (new (self::RUNNER_CLASS)($missing, $this->makeUniqueTempDir()))->run();
        $this->assertFalse($result['passed']);
        $this->assertFalse($result['measured']);
        $this->assertNotSame(0, $result['exit_code']);
    }

    public function test_missing_tar_binary_is_reported_as_unknown_not_as_a_verdict(): void
    {
        $dir = $this->makeUniqueTempDir();
        [$root] = $this->seedMediaRoot($dir);

        $result = (new (self::RUNNER_CLASS)($root, $this->makeUniqueTempDir(), '/nonexistent/zabuno/tar'))->run();

        $this->assertFalse($result['passed']);
        $this->assertFalse($result['measured']);
        $this->assertSame(127, $result['exit_code']);
        $this->assertStringContainsString('tar', $result['output']);
    }

    public function test_result_never_exposes_a_path_uuid_or_connection_key(): void
    {
        $dir = $this->makeUniqueTempDir();
        [$root] = $this->seedMediaRoot($dir);

        $result = (new (self::RUNNER_CLASS)($root, $this->makeUniqueTempDir()))->run();

        foreach (array_keys($result) as $key) {
            $this->assertDoesNotMatchRegularExpression('/path|uuid|pdo|connection|dsn/i', $key);
        }
        $this->assertStringNotContainsString($root, $result['output'], 'The media root path must not leak into the output.');
    }
}
