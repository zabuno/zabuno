<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Execution;

use App\Application\Security\Port\MediaBackupRestoreDrillRunnerPort;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Medya tatbikatı — `storage/app` altındaki medya kökü (docs/124).
 *
 * Fotoğraflar veritabanında değil diskte yaşar; veritabanı tatbikatı
 * onlara dokunmaz. Bu koşucu medya kökünü `tar -cf` ile arşivler, arşivi
 * izole bir dizine `tar -xf` ile açar ve kopyayı aslıyla üç ölçüyle
 * karşılaştırır: dosya sayısı, toplam bayt ve dosya başına SHA-256'dan
 * türeyen manifest özeti. Kaynağa yazılmaz; arşiv ve kopya sonunda silinir.
 *
 * `tar` neden PHP'nin PharData'sı değil: PharData arşivi bellekte kurar,
 * bir restoranın gigabaytlık fotoğraf kökünde bu bir bellek tavanına
 * çarpar; `tar` akıtır. `tar` her Linux/macOS/Alpine'de vardır; yoksa
 * sonuç BİLİNMİYOR'dur (127), "geçti" değil.
 *
 * Çağrı biçimi sözleşmedir: `tar -cf <arşiv> -C <kök> .` ve
 * `tar -xf <arşiv> -C <dizin>`; testteki bozma sarmalayıcısı bu sıraya
 * dayanır.
 */
final class TarMediaBackupRestoreDrillRunner implements MediaBackupRestoreDrillRunnerPort
{
    private const DEFAULT_WORK_ROOT_NAME = 'zabuno-backup-restore-drill';

    private const TEMP_CHILD_PREFIX = 'media-drill-';

    private const PROCESS_TIMEOUT_SECONDS = 3600;

    private const EXIT_TOOL_MISSING = 127;

    private const EXIT_PRECONDITION = 126;

    public function __construct(
        private readonly string $mediaRoot,
        private readonly ?string $workRoot = null,
        private readonly ?string $tarBinary = null,
    ) {}

    /**
     * @return array{passed: bool, exit_code: int, duration_ms: int, output: string, measured: bool, archive_sha256: string, archive_bytes: int, source_manifest_sha256: string, restored_manifest_sha256: string, source_file_count: int, restored_file_count: int, source_bytes: int, restored_bytes: int}
     */
    public function run(): array
    {
        $startedAt = hrtime(true);
        $tempChild = null;

        try {
            $tar = $this->locate();
            if ($tar === null) {
                return $this->unmeasured(self::EXIT_TOOL_MISSING, 'tar not found on PATH; media drill result unknown', $startedAt);
            }

            if (! is_dir($this->mediaRoot) || ! is_readable($this->mediaRoot)) {
                return $this->unmeasured(self::EXIT_PRECONDITION, 'media root is missing or unreadable; media drill result unknown', $startedAt);
            }

            $workRoot = $this->resolveWorkRoot();
            if (! is_dir($workRoot) && ! @mkdir($workRoot, 0700, true) && ! is_dir($workRoot)) {
                throw new RuntimeException('Unable to prepare the media drill work root.');
            }

            if ($this->isInside($workRoot, $this->mediaRoot)) {
                return $this->unmeasured(self::EXIT_PRECONDITION, 'the drill work root lies inside the media root; the archive would swallow itself, media drill result unknown', $startedAt);
            }

            $tempChild = $workRoot.DIRECTORY_SEPARATOR.self::TEMP_CHILD_PREFIX.bin2hex(random_bytes(16));
            $restoreDir = $tempChild.DIRECTORY_SEPARATOR.'restored';
            if (! mkdir($restoreDir, 0700, true)) {
                throw new RuntimeException('Unable to create a guarded temp child for the media drill.');
            }

            $archivePath = $tempChild.DIRECTORY_SEPARATOR.'media.tar';

            [$sourceManifest, $sourceFileCount, $sourceBytes] = $this->manifest($this->mediaRoot);

            $this->runProcess([$tar, '-cf', $archivePath, '-C', $this->mediaRoot, '.']);

            $archiveBytes = filesize($archivePath);
            $archiveSha256 = hash_file('sha256', $archivePath);
            if ($archiveBytes === false || $archiveSha256 === false) {
                throw new RuntimeException('Unable to measure the media archive.');
            }

            $this->runProcess([$tar, '-xf', $archivePath, '-C', $restoreDir]);

            [$restoredManifest, $restoredFileCount, $restoredBytes] = $this->manifest($restoreDir);

            $passed = $sourceFileCount === $restoredFileCount
                && $sourceBytes === $restoredBytes
                && hash_equals($sourceManifest, $restoredManifest);

            return [
                'passed' => $passed,
                'exit_code' => $passed ? 0 : 1,
                'duration_ms' => $this->elapsedMs($startedAt),
                'output' => $passed
                    ? sprintf('media drill passed (%d files, %d bytes)', $sourceFileCount, $sourceBytes)
                    : sprintf('media drill failed manifest verification (%d/%d files, %d/%d bytes)', $sourceFileCount, $restoredFileCount, $sourceBytes, $restoredBytes),
                'measured' => true,
                'archive_sha256' => $archiveSha256,
                'archive_bytes' => $archiveBytes,
                'source_manifest_sha256' => $sourceManifest,
                'restored_manifest_sha256' => $restoredManifest,
                'source_file_count' => $sourceFileCount,
                'restored_file_count' => $restoredFileCount,
                'source_bytes' => $sourceBytes,
                'restored_bytes' => $restoredBytes,
            ];
        } catch (Throwable $e) {
            return [
                'passed' => false,
                'exit_code' => 1,
                'duration_ms' => $this->elapsedMs($startedAt),
                'output' => 'media drill failed: '.str_replace($this->mediaRoot, '[media root]', $e->getMessage()),
                'measured' => true,
                'archive_sha256' => str_repeat('0', 64),
                'archive_bytes' => 0,
                'source_manifest_sha256' => str_repeat('0', 64),
                'restored_manifest_sha256' => str_repeat('0', 64),
                'source_file_count' => 0,
                'restored_file_count' => 0,
                'source_bytes' => 0,
                'restored_bytes' => 0,
            ];
        } finally {
            $this->cleanupTempChild($tempChild);
        }
    }

    /**
     * @return array{passed: bool, exit_code: int, duration_ms: int, output: string, measured: bool, archive_sha256: string, archive_bytes: int, source_manifest_sha256: string, restored_manifest_sha256: string, source_file_count: int, restored_file_count: int, source_bytes: int, restored_bytes: int}
     */
    private function unmeasured(int $exitCode, string $output, int|float $startedAt): array
    {
        return [
            'passed' => false,
            'exit_code' => $exitCode,
            'duration_ms' => $this->elapsedMs($startedAt),
            'output' => $output,
            'measured' => false,
            'archive_sha256' => str_repeat('0', 64),
            'archive_bytes' => 0,
            'source_manifest_sha256' => str_repeat('0', 64),
            'restored_manifest_sha256' => str_repeat('0', 64),
            'source_file_count' => 0,
            'restored_file_count' => 0,
            'source_bytes' => 0,
            'restored_bytes' => 0,
        ];
    }

    private function locate(): ?string
    {
        if ($this->tarBinary !== null) {
            return is_file($this->tarBinary) && is_executable($this->tarBinary) ? $this->tarBinary : null;
        }

        return (new ExecutableFinder)->find('tar');
    }

    /**
     * Dosya başına `göreli yol \0 boyut \0 sha256`, göreli yola göre
     * sıralı; özet bu listenin SHA-256'sıdır. Sembolik bağlar ve dizinler
     * sayılmaz — tar ikisini de taşır ama tatbikatın ölçtüğü şey içeriktir.
     *
     * @return array{0: string, 1: int, 2: int} manifest sha256, file count, total bytes
     */
    private function manifest(string $root): array
    {
        $entries = [];
        $bytes = 0;
        $prefixLength = strlen(rtrim($root, DIRECTORY_SEPARATOR)) + 1;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isLink() || ! $file->isFile()) {
                continue;
            }

            $path = $file->getPathname();
            $size = $file->getSize();
            $sha256 = hash_file('sha256', $path);

            if ($sha256 === false) {
                throw new RuntimeException('Unable to hash a media file.');
            }

            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, $prefixLength));
            $entries[$relative] = $relative."\0".$size."\0".$sha256;
            $bytes += $size;
        }

        ksort($entries, SORT_STRING);

        return [hash('sha256', implode("\n", $entries)), count($entries), $bytes];
    }

    /**
     * @param  list<string>  $command
     */
    private function runProcess(array $command): void
    {
        $process = new Process($command);
        $process->setTimeout(self::PROCESS_TIMEOUT_SECONDS);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(sprintf(
                'tar exited with code %d: %s',
                (int) $process->getExitCode(),
                trim($process->getErrorOutput()),
            ));
        }
    }

    private function isInside(string $candidate, string $root): bool
    {
        $candidateReal = realpath($candidate);
        $rootReal = realpath($root);

        if ($candidateReal === false || $rootReal === false) {
            return false;
        }

        return $candidateReal === $rootReal
            || str_starts_with($candidateReal, rtrim($rootReal, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
    }

    private function resolveWorkRoot(): string
    {
        if ($this->workRoot !== null) {
            return $this->workRoot;
        }

        return sys_get_temp_dir().DIRECTORY_SEPARATOR.self::DEFAULT_WORK_ROOT_NAME;
    }

    private function cleanupTempChild(?string $tempChild): void
    {
        if ($tempChild === null) {
            return;
        }

        $workRoot = $this->resolveWorkRoot();

        if (! str_starts_with($tempChild, rtrim($workRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
            return;
        }

        if (! str_starts_with(basename($tempChild), self::TEMP_CHILD_PREFIX) || ! is_dir($tempChild)) {
            return;
        }

        $this->removeTree($tempChild);
    }

    /**
     * Yalnız korumalı geçici dizinin altını siler; sembolik bağın
     * arkasına geçmez.
     */
    private function removeTree(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path) && ! is_link($path)) {
                $this->removeTree($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

    private function elapsedMs(int|float $startedAt): int
    {
        return (int) ((hrtime(true) - $startedAt) / 1_000_000);
    }
}
