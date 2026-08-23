<?php

declare(strict_types=1);

namespace Tests\Unit\Modules;

use PHPUnit\Framework\TestCase;

/**
 * Encodes docs/04 §2/§5 (finite, numbered CORE-01..16 kernel) and docs/32
 * (deterministic_baseline=required + ai_posture mandatory on every
 * manifest). Reads the config array directly rather than booting a
 * container, mirroring CORE-05's "data-only registry bootstrap" scope for
 * S1-WP01A (no lifecycle engine yet).
 */
final class CoreKernelManifestTest extends TestCase
{
    private const REGISTRY_PATH = __DIR__.'/../../../config/core-modules.php';

    public function test_registry_declares_exactly_the_sixteen_core_kernel_codes(): void
    {
        $manifests = $this->loadRegistry();

        $expected = array_map(static fn (int $n): string => sprintf('CORE-%02d', $n), range(1, 16));

        self::assertSame($expected, array_keys($manifests), 'CORE-01..CORE-16 tam ve sıralı olmalı.');
    }

    public function test_every_core_manifest_has_a_required_deterministic_baseline(): void
    {
        $manifests = $this->loadRegistry();

        foreach ($manifests as $code => $manifest) {
            self::assertArrayHasKey('deterministic_baseline', $manifest, "{$code} deterministic_baseline eksik.");
            self::assertSame('required', $manifest['deterministic_baseline'], "{$code} deterministic_baseline 'required' olmalı (docs/32).");
        }
    }

    public function test_every_core_manifest_declares_an_ai_posture(): void
    {
        $manifests = $this->loadRegistry();

        foreach ($manifests as $code => $manifest) {
            self::assertArrayHasKey('ai_posture', $manifest, "{$code} ai_posture eksik.");
            self::assertNotSame('', $manifest['ai_posture'], "{$code} ai_posture boş olamaz.");
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadRegistry(): array
    {
        self::assertFileExists(self::REGISTRY_PATH, 'config/core-modules.php henüz kurulmadı (CORE-05).');

        /** @var mixed $manifests */
        $manifests = require self::REGISTRY_PATH;
        self::assertIsArray($manifests);

        return $manifests;
    }
}
