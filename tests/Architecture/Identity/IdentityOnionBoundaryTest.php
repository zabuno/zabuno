<?php

declare(strict_types=1);

namespace Tests\Architecture\Identity;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

/**
 * Blind RED test candidate for S1-WP02A CORE-01 (docs/33 §4, §11 "Onion
 * strict/final"). Extends the WP01A-wide OnionBoundaryTest/ADR-L03 discipline
 * (tests/Unit/Architecture/OnionBoundaryTest.php) to the CORE-01 Identity
 * vertical slice specifically: App\Domain\Identity must stay framework-free
 * (Illuminate never leaks in), every class declares strict_types=1, and every
 * class is final (ADR-L03: "kazara genişletilmesini/override edilmesini
 * önler"). app/Domain/Identity does not exist yet in this snapshot, so this
 * fails RED via assertDirectoryExists until CORE-01 Domain classes are added.
 */
final class IdentityOnionBoundaryTest extends TestCase
{
    private const IDENTITY_DOMAIN_DIR = __DIR__.'/../../../app/Domain/Identity';

    public function test_identity_domain_layer_never_imports_illuminate_namespace(): void
    {
        foreach ($this->identityDomainPhpFiles() as $file) {
            $contents = (string) file_get_contents($file->getPathname());

            self::assertStringNotContainsString(
                'Illuminate\\',
                $contents,
                "{$file->getPathname()} sızdırıyor: CORE-01 Identity Domain katmanı Laravel'e bağımlı olamaz (docs/33 §4, ADR-L02)."
            );
        }
    }

    public function test_every_identity_domain_class_declares_strict_types(): void
    {
        foreach ($this->identityDomainPhpFiles() as $file) {
            $contents = (string) file_get_contents($file->getPathname());

            self::assertMatchesRegularExpression(
                '/^\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;/m',
                $contents,
                "{$file->getPathname()} declare(strict_types=1) eksik (ADR-L03)."
            );
        }
    }

    public function test_every_identity_domain_class_is_final(): void
    {
        foreach ($this->identityDomainFullyQualifiedClassNames() as $fqcn) {
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
                "{$fqcn} final olmalı (ADR-L03: Domain value object/aggregate sınıfları varsayılan final, docs/33 §4)."
            );
        }
    }

    /**
     * @return list<SplFileInfo>
     */
    private function identityDomainPhpFiles(): array
    {
        self::assertDirectoryExists(
            self::IDENTITY_DOMAIN_DIR,
            'app/Domain/Identity henüz kurulmadı (S1-WP02A CORE-01 implementasyonu başlamadı, docs/33 §2 entry gate).'
        );

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(self::IDENTITY_DOMAIN_DIR, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile() && $fileInfo->getExtension() === 'php') {
                $files[] = $fileInfo;
            }
        }

        self::assertNotEmpty(
            $files,
            'app/Domain/Identity hiçbir PHP dosyası içermiyor; Illuminate-bağımsızlık, strict_types ve final testleri '
            .'instantiate edilemiyor (boş dizinde vacuous GREEN önlenir).'
        );

        return $files;
    }

    /**
     * @return list<class-string>
     */
    private function identityDomainFullyQualifiedClassNames(): array
    {
        $classes = [];
        foreach ($this->identityDomainPhpFiles() as $file) {
            $relative = str_replace(
                [realpath(self::IDENTITY_DOMAIN_DIR).DIRECTORY_SEPARATOR, '.php'],
                ['', ''],
                (string) $file->getRealPath()
            );
            $classes[] = 'App\\Domain\\Identity\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $relative);
        }

        return $classes;
    }
}
