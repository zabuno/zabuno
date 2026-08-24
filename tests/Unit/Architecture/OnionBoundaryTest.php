<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Encodes docs/03 ADR-L02 (Onion dependency direction) and ADR-L03
 * (strict_types baseline). This does not extend Laravel's TestCase because
 * the Domain layer itself must stay framework-free — the point being
 * tested.
 */
final class OnionBoundaryTest extends TestCase
{
    private const DOMAIN_DIR = __DIR__.'/../../../app/Domain';

    public function test_domain_layer_never_imports_illuminate_namespace(): void
    {
        foreach ($this->domainPhpFiles() as $file) {
            $contents = (string) file_get_contents($file->getPathname());

            self::assertStringNotContainsString(
                'Illuminate\\',
                $contents,
                "{$file->getPathname()} sızdırıyor: Domain katmanı Laravel'e bağımlı olamaz (ADR-L02)."
            );
        }
    }

    public function test_every_domain_class_declares_strict_types(): void
    {
        foreach ($this->domainPhpFiles() as $file) {
            $contents = (string) file_get_contents($file->getPathname());

            self::assertMatchesRegularExpression(
                '/^\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;/m',
                $contents,
                "{$file->getPathname()} declare(strict_types=1) eksik (ADR-L03)."
            );
        }
    }

    /**
     * @return list<SplFileInfo>
     */
    private function domainPhpFiles(): array
    {
        self::assertDirectoryExists(self::DOMAIN_DIR, 'app/Domain henüz kurulmadı.');

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(self::DOMAIN_DIR, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile() && $fileInfo->getExtension() === 'php') {
                $files[] = $fileInfo;
            }
        }

        self::assertNotEmpty(
            $files,
            'app/Domain hiçbir PHP dosyası içermiyor; Illuminate-bağımsızlık ve strict_types '
            .'testleri instantiate edilemiyor (boş dizinde vacuous GREEN önlenir).'
        );

        return $files;
    }
}
