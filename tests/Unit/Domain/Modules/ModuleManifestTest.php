<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Modules;

use App\Domain\Modules\ModuleManifest;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Encodes the invariants of the Domain-layer ModuleManifest value object
 * named in modules/core-module-registry.md ("Public contracts / events —
 * name, version, dependencies, permissions, routes, migrations, frontend
 * entry, settings, entitlements, events, jobs, health checks", docs/03
 * ADR-L04) and docs/03 ADR-L03 (Strict OOP: value objects are immutable and
 * reject invalid state at construction, not later — "kazara
 * genişletilmesini/override edilmesini önler"). Placed under
 * App\Domain\Modules to stay the innermost Onion layer (ADR-L02): no
 * Illuminate import, consistent with OnionBoundaryTest's enforcement over
 * app/Domain.
 */
final class ModuleManifestTest extends TestCase
{
    /**
     * @return array{code: string, name: string, moduleClass: string, version: string, dependencies: array<int, string>, deterministicBaseline: string, aiPosture: string}
     */
    private function validAttributes(): array
    {
        return [
            'code' => 'CORE-05',
            'name' => 'Module Registry',
            'moduleClass' => 'core',
            'version' => '1.0.0',
            'dependencies' => [],
            'deterministicBaseline' => 'required',
            'aiPosture' => 'advisory',
        ];
    }

    public function test_it_constructs_from_a_valid_manifest_array(): void
    {
        $manifest = ModuleManifest::fromArray($this->validAttributes());

        self::assertSame('CORE-05', $manifest->code());
        self::assertSame('1.0.0', $manifest->version());
        self::assertSame('required', $manifest->deterministicBaseline());
        self::assertSame('advisory', $manifest->aiPosture());
    }

    public function test_it_rejects_a_version_that_is_not_semantic(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ModuleManifest::fromArray(['version' => '1.0'] + $this->validAttributes());
    }

    public function test_it_rejects_a_core_class_code_outside_the_finite_core_01_16_range(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ModuleManifest::fromArray(['code' => 'CORE-17', 'moduleClass' => 'core'] + $this->validAttributes());
    }

    public function test_it_rejects_a_deterministic_baseline_that_is_not_required(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ModuleManifest::fromArray(['deterministicBaseline' => 'optional'] + $this->validAttributes());
    }

    public function test_it_rejects_an_empty_deterministic_baseline(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ModuleManifest::fromArray(['deterministicBaseline' => ''] + $this->validAttributes());
    }

    public function test_it_rejects_an_unknown_ai_posture(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ModuleManifest::fromArray(['aiPosture' => 'omniscient'] + $this->validAttributes());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function validAiPostureProvider(): iterable
    {
        yield 'advisory' => ['advisory'];
        yield 'assistive' => ['assistive'];
        yield 'automated_guarded' => ['automated_guarded'];
        yield 'agentic_guarded' => ['agentic_guarded'];
        yield 'none' => ['none'];
    }

    #[DataProvider('validAiPostureProvider')]
    public function test_it_accepts_every_ai_posture_defined_in_the_capability_matrix(string $posture): void
    {
        $manifest = ModuleManifest::fromArray(['aiPosture' => $posture] + $this->validAttributes());

        self::assertSame($posture, $manifest->aiPosture());
    }
}
