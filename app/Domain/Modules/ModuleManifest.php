<?php

declare(strict_types=1);

namespace App\Domain\Modules;

use InvalidArgumentException;

/**
 * Immutable value object for a module manifest (docs/03 ADR-L04, ADR-L03).
 * Framework-free by design (ADR-L02 Onion boundary): no Illuminate import.
 * Stage 1 (CORE-05) scope is data/config registry only — lifecycle state
 * (install/enable/disable) is not modeled here yet.
 */
final class ModuleManifest
{
    private const VALID_AI_POSTURES = [
        'advisory',
        'assistive',
        'automated_guarded',
        'agentic_guarded',
        'none',
    ];

    private const REQUIRED_DETERMINISTIC_BASELINE = 'required';

    /**
     * @param  array<int, string>  $dependencies
     */
    private function __construct(
        private readonly string $code,
        private readonly string $name,
        private readonly string $moduleClass,
        private readonly string $version,
        private readonly array $dependencies,
        private readonly string $deterministicBaseline,
        private readonly string $aiPosture,
    ) {}

    /**
     * @param  array{code: string, name: string, moduleClass: string, version: string, dependencies: array<int, string>, deterministicBaseline: string, aiPosture: string}  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        $code = self::requireString($attributes, 'code');
        $name = self::requireString($attributes, 'name');
        $moduleClass = self::requireString($attributes, 'moduleClass');
        $version = self::requireString($attributes, 'version');
        $dependencies = self::requireDependencies($attributes);
        $deterministicBaseline = self::requireString($attributes, 'deterministicBaseline');
        $aiPosture = self::requireString($attributes, 'aiPosture');

        self::assertSemanticVersion($version);
        self::assertValidCodeForClass($code, $moduleClass);
        self::assertRequiredDeterministicBaseline($deterministicBaseline);
        self::assertValidAiPosture($aiPosture);

        return new self($code, $name, $moduleClass, $version, $dependencies, $deterministicBaseline, $aiPosture);
    }

    public function code(): string
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function moduleClass(): string
    {
        return $this->moduleClass;
    }

    public function version(): string
    {
        return $this->version;
    }

    /**
     * @return array<int, string>
     */
    public function dependencies(): array
    {
        return $this->dependencies;
    }

    public function deterministicBaseline(): string
    {
        return $this->deterministicBaseline;
    }

    public function aiPosture(): string
    {
        return $this->aiPosture;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function requireString(array $attributes, string $key): string
    {
        $value = $attributes[$key] ?? null;
        if (! is_string($value)) {
            throw new InvalidArgumentException("ModuleManifest '{$key}' bir string olmalı.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<int, string>
     */
    private static function requireDependencies(array $attributes): array
    {
        $dependencies = $attributes['dependencies'] ?? null;
        if (! is_array($dependencies)) {
            throw new InvalidArgumentException("ModuleManifest 'dependencies' bir array olmalı.");
        }
        foreach ($dependencies as $dependency) {
            if (! is_string($dependency) || $dependency === '') {
                throw new InvalidArgumentException("ModuleManifest 'dependencies' yalnız boş olmayan string kodlar içerebilir.");
            }
        }

        return array_values($dependencies);
    }

    private static function assertSemanticVersion(string $version): void
    {
        if (preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/', $version) !== 1) {
            throw new InvalidArgumentException(
                "ModuleManifest 'version' semantic (major.minor.patch) olmalı, bulunan: '{$version}'."
            );
        }
    }

    private static function assertValidCodeForClass(string $code, string $moduleClass): void
    {
        if ($moduleClass === 'core' && preg_match('/^CORE-(0[1-9]|1[0-6])$/', $code) !== 1) {
            throw new InvalidArgumentException(
                "ModuleManifest 'code' Core sınıfı için CORE-01..CORE-16 sonlu aralığı dışında: '{$code}' (docs/04 §5)."
            );
        }
    }

    private static function assertRequiredDeterministicBaseline(string $deterministicBaseline): void
    {
        if ($deterministicBaseline !== self::REQUIRED_DETERMINISTIC_BASELINE) {
            throw new InvalidArgumentException(
                "ModuleManifest 'deterministicBaseline' yalnız 'required' olabilir (docs/32), bulunan: '{$deterministicBaseline}'."
            );
        }
    }

    private static function assertValidAiPosture(string $aiPosture): void
    {
        if (! in_array($aiPosture, self::VALID_AI_POSTURES, true)) {
            throw new InvalidArgumentException(
                "ModuleManifest 'aiPosture' bilinen kümede değil (docs/32): '{$aiPosture}'."
            );
        }
    }
}
