<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * S1-WP04b1 RED — onion-boundary rule for the QR Destination application
 * slice, mirroring PublicationApplicationBoundaryTest: files under
 * app/Application/QrDestination must not depend on the Illuminate framework
 * namespace (ports/DTOs stay framework-agnostic) and must declare
 * strict_types. Today app/Application/QrDestination does not exist at all,
 * so the non-vacuous scan assertion fails RED first.
 */
final class QrDestinationApplicationBoundaryTest extends TestCase
{
    private const QR_DESTINATION_APPLICATION_DIR = __DIR__.'/../../../app/Application/QrDestination';

    public function test_scan_is_non_vacuous(): void
    {
        self::assertNotEmpty(
            $this->qrDestinationApplicationPhpFiles(),
            'app/Application/QrDestination hiçbir PHP dosyası içermiyor; tarama vacuous olurdu.'
        );
    }

    public function test_qr_destination_application_files_have_no_illuminate_dependency(): void
    {
        foreach ($this->qrDestinationApplicationPhpFiles() as $file) {
            $contents = (string) file_get_contents($file->getPathname());

            self::assertDoesNotMatchRegularExpression(
                '/\bIlluminate\\\\/',
                $contents,
                "{$file->getPathname()} Illuminate namespace'ine bağımlı (Application katmanı framework-agnostic olmalı)."
            );
        }
    }

    public function test_qr_destination_application_files_declare_strict_types(): void
    {
        foreach ($this->qrDestinationApplicationPhpFiles() as $file) {
            $contents = (string) file_get_contents($file->getPathname());

            self::assertMatchesRegularExpression(
                '/^\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;/m',
                $contents,
                "{$file->getPathname()} declare(strict_types=1) eksik."
            );
        }
    }

    /**
     * @return list<SplFileInfo>
     */
    private function qrDestinationApplicationPhpFiles(): array
    {
        self::assertDirectoryExists(self::QR_DESTINATION_APPLICATION_DIR, 'app/Application/QrDestination dizini mevcut değil.');

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(self::QR_DESTINATION_APPLICATION_DIR, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileInfo) {
            if (! $fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
                continue;
            }

            $files[] = $fileInfo;
        }

        return $files;
    }
}
