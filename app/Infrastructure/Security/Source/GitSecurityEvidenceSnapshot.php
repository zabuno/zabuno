<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Source;

use App\Application\Security\Port\SecurityEvidenceSnapshotPort;
use RuntimeException;
use Symfony\Component\Process\Process;

final class GitSecurityEvidenceSnapshot implements SecurityEvidenceSnapshotPort
{
    private const TIMEOUT_SECONDS = 30;

    /**
     * @param  list<string>  $suitePaths
     * @return array{git_sha: string, git_dirty: bool, source_snapshot_sha256: string, suite_manifest_sha256: string}
     */
    public function collect(array $suitePaths): array
    {
        return [
            'git_sha' => $this->currentGitSha(),
            'git_dirty' => $this->currentGitDirty(),
            'source_snapshot_sha256' => $this->sourceSnapshotSha256($suitePaths),
            'suite_manifest_sha256' => $this->suiteManifestSha256($suitePaths),
        ];
    }

    private function currentGitSha(): string
    {
        $output = $this->runGit(['rev-parse', 'HEAD']);
        $sha = trim($output);

        if ($sha === '' || preg_match('/^[0-9a-f]{40}$/', $sha) !== 1) {
            throw new RuntimeException('Unable to determine the current git SHA.');
        }

        return $sha;
    }

    private function currentGitDirty(): bool
    {
        $output = $this->runGit(['status', '--porcelain']);

        return trim($output) !== '';
    }

    private function runGit(array $arguments): string
    {
        $process = new Process(['git', ...$arguments], base_path());
        $process->setTimeout(self::TIMEOUT_SECONDS);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Unable to collect git security-evidence metadata: '.$process->getErrorOutput());
        }

        return $process->getOutput();
    }

    /**
     * @param  list<string>  $suitePaths
     */
    private function sourceSnapshotSha256(array $suitePaths): string
    {
        $sorted = $suitePaths;
        sort($sorted);

        return $this->hashPathsWithContents($sorted);
    }

    /**
     * @param  list<string>  $suitePaths
     */
    private function suiteManifestSha256(array $suitePaths): string
    {
        return $this->hashPathsWithContents($suitePaths);
    }

    /**
     * @param  list<string>  $paths
     */
    private function hashPathsWithContents(array $paths): string
    {
        $parts = [];

        foreach ($paths as $path) {
            $fullPath = base_path($path);

            if (! is_file($fullPath) || ! is_readable($fullPath)) {
                throw new RuntimeException("Unable to collect security-evidence metadata: missing or unreadable file [{$path}].");
            }

            $contents = file_get_contents($fullPath);

            if ($contents === false) {
                throw new RuntimeException("Unable to collect security-evidence metadata: could not read file [{$path}].");
            }

            $parts[] = $path."\0".hash('sha256', $contents);
        }

        return hash('sha256', implode("\0", $parts));
    }
}
