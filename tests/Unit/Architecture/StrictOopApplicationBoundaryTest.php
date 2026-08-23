<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

/**
 * Encodes docs/03 ADR-L03 (strict OOP): every app/ class file declares
 * declare(strict_types=1), and every concrete application class is final.
 * Abstract classes (e.g. App\Http\Controllers\Controller as a deliberate
 * extension point) are exempt from the final requirement but not from
 * strict_types. Declaration kind (class vs interface/trait/enum) is
 * determined with token_get_all so that `Foo::class` constant references
 * are never mistaken for a class declaration, and so interface/trait/enum
 * files are never flagged for the final-class rule (false-positive-free).
 */
final class StrictOopApplicationBoundaryTest extends TestCase
{
    private const APP_DIR = __DIR__.'/../../../app';

    public function test_every_app_class_file_declares_strict_types(): void
    {
        foreach ($this->appClassFiles() as $file) {
            $contents = (string) file_get_contents($file->getPathname());

            self::assertMatchesRegularExpression(
                '/^\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;/m',
                $contents,
                "{$file->getPathname()} declare(strict_types=1) eksik (ADR-L03)."
            );
        }
    }

    public function test_every_concrete_app_class_is_final(): void
    {
        foreach ($this->concreteAppClassNames() as $fqcn) {
            $reflection = new ReflectionClass($fqcn);

            self::assertTrue(
                $reflection->isFinal(),
                "{$fqcn} concrete bir sınıf ama final değil (ADR-L03: sınıflar varsayılan olarak final; "
                .'bilinçli abstract extension point\'ler final zorunluluğundan muaftır, ama bu sınıf abstract değil).'
            );
        }
    }

    public function test_scan_is_non_vacuous(): void
    {
        self::assertNotEmpty(
            $this->appClassFiles(),
            'app/ hiçbir PHP class dosyası içermiyor; strict_types taraması vacuous olurdu.'
        );
        self::assertNotEmpty(
            $this->concreteAppClassNames(),
            'app/ hiçbir concrete class içermiyor; final taraması vacuous olurdu.'
        );
    }

    /**
     * @return list<SplFileInfo>
     */
    private function appClassFiles(): array
    {
        self::assertDirectoryExists(self::APP_DIR, 'app/ dizini mevcut değil.');

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(self::APP_DIR, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileInfo) {
            if (! $fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($fileInfo->getPathname());

            if ($this->declarationKind($contents) === 'class') {
                $files[] = $fileInfo;
            }
        }

        return $files;
    }

    /**
     * @return list<class-string>
     */
    private function concreteAppClassNames(): array
    {
        $names = [];

        foreach ($this->appClassFiles() as $file) {
            $fqcn = $this->fqcnFromPath((string) $file->getRealPath());

            if (! class_exists($fqcn)) {
                continue;
            }

            $reflection = new ReflectionClass($fqcn);

            if ($reflection->isAbstract()) {
                continue;
            }

            $names[] = $fqcn;
        }

        return $names;
    }

    /**
     * Determines whether the given PHP source declares a class, interface,
     * trait or enum (as opposed to merely referencing `Foo::class`).
     * Returns null when the file declares none of those.
     */
    private function declarationKind(string $contents): ?string
    {
        $tokens = token_get_all($contents);
        $previousSignificant = null;

        foreach ($tokens as $token) {
            if (is_array($token)) {
                $id = $token[0];

                if ($id === T_WHITESPACE || $id === T_COMMENT || $id === T_DOC_COMMENT) {
                    continue;
                }

                if (in_array($id, [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                    $isClassConstantReference = is_array($previousSignificant) && $previousSignificant[0] === T_DOUBLE_COLON;

                    if (! $isClassConstantReference) {
                        return match ($id) {
                            T_CLASS => 'class',
                            T_INTERFACE => 'interface',
                            T_TRAIT => 'trait',
                            default => 'enum',
                        };
                    }
                }

                $previousSignificant = $token;
            } else {
                $previousSignificant = $token;
            }
        }

        return null;
    }

    private function fqcnFromPath(string $path): string
    {
        $appRoot = (string) realpath(self::APP_DIR);
        $relative = substr(str_replace('\\', '/', $path), strlen(str_replace('\\', '/', $appRoot)) + 1);
        $withoutExtension = substr($relative, 0, -4);

        return 'App\\'.str_replace('/', '\\', $withoutExtension);
    }
}
