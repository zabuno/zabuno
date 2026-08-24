<?php

declare(strict_types=1);

namespace Tests\Architecture\Tenancy;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

/**
 * Blind RED test candidate for S1-WP02B (docs/34 §"Mimari sınır",
 * RED→GREEN S1WP02B-ONION-01). Extends the WP01A-wide OnionBoundaryTest/
 * ADR-L03 discipline (tests/Unit/Architecture/OnionBoundaryTest.php) and
 * mirrors tests/Architecture/Identity/IdentityOnionBoundaryTest.php for the
 * S1-WP02B Tenancy vertical slice: App\Domain\Tenancy must stay
 * framework-free (Illuminate never leaks in), every class declares
 * strict_types=1, and every class is final. app/Domain/Tenancy does not
 * exist yet in this snapshot, so this fails RED via assertDirectoryExists
 * until S1-WP02B Domain classes are added.
 */
final class TenancyOnionBoundaryTest extends TestCase
{
    private const TENANCY_DOMAIN_DIR = __DIR__.'/../../../app/Domain/Tenancy';

    public function test_tenancy_domain_layer_never_imports_illuminate_namespace(): void
    {
        foreach ($this->tenancyDomainPhpFiles() as $file) {
            $contents = (string) file_get_contents($file->getPathname());

            self::assertStringNotContainsString(
                'Illuminate\\',
                $contents,
                "{$file->getPathname()} sızdırıyor: S1-WP02B Tenancy Domain katmanı Laravel'e bağımlı olamaz (docs/34 §Mimari sınır)."
            );
        }
    }

    public function test_every_tenancy_domain_class_declares_strict_types(): void
    {
        foreach ($this->tenancyDomainPhpFiles() as $file) {
            $contents = (string) file_get_contents($file->getPathname());

            self::assertMatchesRegularExpression(
                '/^\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;/m',
                $contents,
                "{$file->getPathname()} declare(strict_types=1) eksik (ADR-L03)."
            );
        }
    }

    public function test_every_tenancy_domain_class_is_final(): void
    {
        foreach ($this->tenancyDomainFullyQualifiedClassNames() as $fqcn) {
            self::assertTrue(
                class_exists($fqcn) || interface_exists($fqcn) || enum_exists($fqcn),
                "{$fqcn} otomatik yüklenemedi."
            );

            if (! class_exists($fqcn)) {
                continue; // interface/enum: final zorunluluğu yalnız class'lar için (ADR-L03).
            }

            $reflection = new ReflectionClass($fqcn);

            self::assertTrue(
                $reflection->isFinal(),
                "{$fqcn} final olmalı (ADR-L03: Domain value object/aggregate sınıfları varsayılan final, docs/34 §Mimari sınır)."
            );
        }
    }

    /**
     * @return list<SplFileInfo>
     */
    private function tenancyDomainPhpFiles(): array
    {
        self::assertDirectoryExists(
            self::TENANCY_DOMAIN_DIR,
            'app/Domain/Tenancy henüz kurulmadı (S1-WP02B implementasyonu başlamadı, docs/34 entry gate).'
        );

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(self::TENANCY_DOMAIN_DIR, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile() && $fileInfo->getExtension() === 'php') {
                $files[] = $fileInfo;
            }
        }

        self::assertNotEmpty(
            $files,
            'app/Domain/Tenancy hiçbir PHP dosyası içermiyor; Illuminate-bağımsızlık, strict_types ve final testleri '
            .'instantiate edilemiyor (boş dizinde vacuous GREEN önlenir).'
        );

        return $files;
    }

    /**
     * @return list<class-string>
     */
    private function tenancyDomainFullyQualifiedClassNames(): array
    {
        $classes = [];
        foreach ($this->tenancyDomainPhpFiles() as $file) {
            $relative = str_replace(
                [realpath(self::TENANCY_DOMAIN_DIR).DIRECTORY_SEPARATOR, '.php'],
                ['', ''],
                (string) $file->getRealPath()
            );
            $classes[] = 'App\\Domain\\Tenancy\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $relative);
        }

        return $classes;
    }
}
