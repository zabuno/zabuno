<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Encodes the onion-boundary rule for the Media application slice: files
 * under app/Application/Media must not depend on the Illuminate framework
 * namespace (ports/DTOs stay framework-agnostic) and must declare
 * strict_types. Currently RED: MediaRepositoryPort imports
 * Illuminate\Http\UploadedFile.
 */
final class MediaApplicationBoundaryTest extends TestCase
{
    private const MEDIA_APPLICATION_DIR = __DIR__.'/../../../app/Application/Media';

    public function test_scan_is_non_vacuous(): void
    {
        self::assertNotEmpty(
            $this->mediaApplicationPhpFiles(),
            'app/Application/Media hiçbir PHP dosyası içermiyor; tarama vacuous olurdu.'
        );
    }

    public function test_media_application_files_have_no_illuminate_dependency(): void
    {
        foreach ($this->mediaApplicationPhpFiles() as $file) {
            $contents = (string) file_get_contents($file->getPathname());

            self::assertDoesNotMatchRegularExpression(
                '/\bIlluminate\\\\/',
                $contents,
                "{$file->getPathname()} Illuminate namespace'ine bağımlı (Application katmanı framework-agnostic olmalı)."
            );
        }
    }

    public function test_media_application_files_declare_strict_types(): void
    {
        foreach ($this->mediaApplicationPhpFiles() as $file) {
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
    private function mediaApplicationPhpFiles(): array
    {
        self::assertDirectoryExists(self::MEDIA_APPLICATION_DIR, 'app/Application/Media dizini mevcut değil.');

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(self::MEDIA_APPLICATION_DIR, FilesystemIterator::SKIP_DOTS)
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
