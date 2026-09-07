<?php

declare(strict_types=1);

namespace Tests\Unit\Modules;

use App\Domain\Modules\ModuleContextDeclaration;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `modules/` EŞLEMESİ — `docs/111` §4.2 B ve adım 4.
 *
 * İki isim uzayı vardı ve aralarındaki bağ hiçbir yerde yazılı değildi:
 * tanım dosyaları `menu-catalog`, kod bağlamları `MenuCatalog`. §4.2 iki yol
 * saydı. A — eşlemeyi ekran koduna gömmek — reddedildi, tek cümleyle:
 * "yanlış olduğunda kimse fark etmez, hiçbir test kırılmaz". Bu dosya, B'nin
 * o cümleye verdiği cevaptır: eşleme veridir ve yanlış olduğunda BU test
 * kırılır.
 *
 * Üç şey donuyor:
 *  1. Altmış iki tanımın her biri bir `contexts:` alanı taşır — eksik bir
 *     alan, sessizce "kod karşılığı yok" sayılmaz.
 *  2. Beyan edilen her bağlam adı `app/` altında GERÇEKTEN vardır. Yazım
 *     hatası ya da bayatlamış bir isim, ekranda "yalnız tanım" diye görünüp
 *     bir modülü haksız yere yok sayardı.
 *  3. "belirsiz" diyen her tanım sebebini de yazar.
 */
final class ModuleSpecMappingTest extends TestCase
{
    private const REPOSITORY_ROOT = __DIR__.'/../../..';

    private const LAYERS = ['Domain', 'Application', 'Infrastructure'];

    #[Test]
    public function every_module_spec_declares_exactly_one_contexts_field(): void
    {
        $specs = $this->specFiles();

        self::assertNotEmpty($specs, 'modules/ boş; eşleme testi boşlukta yeşil vermemeli.');

        foreach ($specs as $path) {
            $source = (string) file_get_contents($path);
            $matched = preg_match_all('/^contexts:\s*(\S.*)$/m', $source, $matches);

            self::assertSame(
                1,
                $matched,
                basename($path).': tam olarak bir `contexts:` satırı olmalı (bulunan: '.$matched.').'
            );
        }
    }

    #[Test]
    public function every_declared_context_exists_in_the_code(): void
    {
        $declared = [];

        foreach ($this->specFiles() as $path) {
            $declaration = ModuleContextDeclaration::parse($this->contextsValue($path));

            foreach ($declaration->contexts() as $context) {
                $declared[$context] = true;

                $directories = array_filter(
                    self::LAYERS,
                    fn (string $layer): bool => is_dir(self::REPOSITORY_ROOT.'/app/'.$layer.'/'.$context)
                );

                self::assertNotEmpty(
                    $directories,
                    basename($path).": beyan edilen `{$context}` bağlamının app/ altında hiçbir dizini yok."
                );
            }
        }

        self::assertNotEmpty($declared, 'Hiçbir tanım bir bağlam sahiplenmiyor; eşleme kurulmamış demektir.');
    }

    #[Test]
    public function every_unknown_declaration_says_why_it_cannot_be_measured(): void
    {
        foreach ($this->specFiles() as $path) {
            $declaration = ModuleContextDeclaration::parse($this->contextsValue($path));

            if (! $declaration->isUnknown()) {
                continue;
            }

            self::assertNotSame(
                '',
                $declaration->note(),
                basename($path).': gerekçesiz bir "belirsiz", sessizce "yok" demektir.'
            );
        }
    }

    #[Test]
    public function the_mapping_never_invents_a_context_name(): void
    {
        $known = [];

        foreach (self::LAYERS as $layer) {
            foreach (glob(self::REPOSITORY_ROOT.'/app/'.$layer.'/*', GLOB_ONLYDIR) ?: [] as $directory) {
                $known[basename($directory)] = true;
            }
        }

        foreach ($this->specFiles() as $path) {
            foreach (ModuleContextDeclaration::parse($this->contextsValue($path))->contexts() as $context) {
                self::assertArrayHasKey(
                    $context,
                    $known,
                    basename($path).": `{$context}` app/ altındaki bağlam adlarından biri değil."
                );
            }
        }
    }

    private function contextsValue(string $path): string
    {
        $source = (string) file_get_contents($path);

        self::assertSame(
            1,
            preg_match('/^contexts:\s*(.+)$/m', $source, $match),
            basename($path).': `contexts:` alanı okunamadı.'
        );

        return $match[1];
    }

    /**
     * @return list<string>
     */
    private function specFiles(): array
    {
        $paths = glob(self::REPOSITORY_ROOT.'/modules/*.md');

        return $paths === false ? [] : array_values($paths);
    }
}
