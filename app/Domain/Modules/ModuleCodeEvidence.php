<?php

declare(strict_types=1);

namespace App\Domain\Modules;

use InvalidArgumentException;

/**
 * Bir modül tanımının KODDA ölçülen karşılığı (`docs/111` §3.3, §4.1).
 *
 * Bu nesne bir iddia taşımaz, bir SAYIM taşır: kaç bağlam dizini, hangi rota
 * dosyaları, hangi tablolar, kaç test dosyası. Rozet bu sayımdan türetilir
 * (`ModuleCodePresence`) ve ekranda rozetin YANINDA bu sayım yazılır.
 *
 * Ayrımı burada bir kez daha yazıyorum çünkü bu deponun tekrar eden kusuru
 * tam olarak burada doğuyor (`docs/109` §8.7): sayım "bu kodun deposunda
 * karşılığı var" der. "Bu kurulumda üretimde çalışıyor" DEMEZ. Çalışan bir
 * dizin, kayıtlı bir rota ve geçen bir test, ürünün müşteride ayakta olduğunu
 * kanıtlamaz — yalnız yeteneğin yazılmış olduğunu kanıtlar.
 */
final class ModuleCodeEvidence
{
    /**
     * @param  list<string>  $directories
     * @param  list<string>  $routeFiles
     * @param  list<string>  $tables
     */
    private function __construct(
        private readonly bool $measurable,
        private readonly array $directories,
        private readonly array $routeFiles,
        private readonly array $tables,
        private readonly int $testFiles,
    ) {}

    /**
     * Eşleme kurulamadı: modül tanımı hangi kod bağlamına karşılık geldiğini
     * SÖYLEMİYOR. Bu bir "yok" değildir ve öyle sayılmaz — ölçüm yapılmadıysa
     * sonuç "geçti" değil "bilinmiyor"dur.
     */
    public static function notMeasurable(): self
    {
        return new self(false, [], [], [], 0);
    }

    /**
     * @param  list<string>  $directories
     * @param  list<string>  $routeFiles
     * @param  list<string>  $tables
     */
    public static function measured(array $directories, array $routeFiles, array $tables, int $testFiles): self
    {
        if ($testFiles < 0) {
            throw new InvalidArgumentException('ModuleCodeEvidence test sayısı negatif olamaz.');
        }

        return new self(true, array_values($directories), array_values($routeFiles), array_values($tables), $testFiles);
    }

    public function isMeasurable(): bool
    {
        return $this->measurable;
    }

    /**
     * @return list<string>
     */
    public function directories(): array
    {
        return $this->directories;
    }

    /**
     * @return list<string>
     */
    public function routeFiles(): array
    {
        return $this->routeFiles;
    }

    /**
     * @return list<string>
     */
    public function tables(): array
    {
        return $this->tables;
    }

    public function testFiles(): int
    {
        return $this->testFiles;
    }
}
