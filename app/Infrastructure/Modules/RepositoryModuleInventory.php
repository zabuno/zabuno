<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules;

use App\Domain\Modules\ModuleCodeEvidence;
use App\Domain\Modules\ModuleCodePresence;
use App\Domain\Modules\ModuleContextDeclaration;
use FilesystemIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Modül tanımlarını KODLA eşleştiren ölçüm (`docs/111` adım 3 + adım 4).
 *
 * ═══ BU SINIF NEYİ OKUR, NEYİ OKUMAZ ═══
 *
 * `modules/*.md` dosyalarından yalnız İKİ ŞEY okunur:
 *   1. H1 başlığı — modülün adı.
 *   2. tek satırlık `contexts:` alanı — modülün hangi kod bağlamına
 *      karşılık geldiği (`docs/111` §4.2 B).
 *
 * Dosyanın geri kalanı — durum cümleleri, "PLANNING ONLY", sürüm, bağımlılık
 * listesi, sınıf — OKUNMAZ ve okunmamalı. Bu dosyaların altmış ikisi de bir
 * zamanlar kendini "şu an çalıştırılamaz" ilan ediyordu ve en az on sekizinde
 * bu yanlıştı; o cümleyi ürünün en görünür yerine taşımak, `docs/109`
 * §8.7'deki kusur ailesine en büyük üyeyi eklemek olurdu.
 *
 * ═══ DURUM NEREDEN GELİR ═══
 *
 * Dört gözlem, dördü de dosya sisteminden ölçülür (`docs/111` §3.3):
 *   - bağlam dizini  → `app/{Domain,Application,Infrastructure}/<Bağlam>`
 *   - yüzey          → `routes/**.php` içinde `App\Http\Controllers\<Bağlam>\`
 *   - veri           → bağlam kaynağının andığı VE bir migration'ın YARATTIĞI
 *                      tablolar (isim benzerliğiyle değil, kesişimle)
 *   - kanıt          → `tests/` altında adı bağlam olan bir yol parçası
 *
 * Migration'ı dosya ADINDAN eşleştirmek kolay olurdu ve yanlış olurdu:
 * `create_workspaces_and_workspace_memberships_tables` hangi bağlamındır?
 * Bu yüzden köprü tablo adıdır — bağlamın kodu `table('x')` diyorsa ve bir
 * migration `Schema::create('x')` yaptıysa, o veri o bağlamındır ve kanıtı
 * gösterilebilir.
 *
 * ═══ ROZET NE İDDİA ETMEZ ═══
 *
 * "Kod karşılığı var" ile "bu kurulumda üretimde çalışıyor" aynı cümle
 * değildir. Bu sınıf yalnız birincisini ölçer; ikincisini ölçen bir sonda
 * bu depoda yok ve olmayan bir sondanın yeşil rozeti yalan olur
 * (`docs/111` §6).
 */
final class RepositoryModuleInventory
{
    private const SPEC_DIRECTORY = 'modules';

    private const LAYERS = ['Domain', 'Application', 'Infrastructure'];

    private const CONTROLLER_NAMESPACE = 'App\\Http\\Controllers\\';

    /** @var array<string, list<string>>|null */
    private ?array $tablesByContext = null;

    /** @var array<string, list<string>>|null */
    private ?array $routeFilesByContext = null;

    /** @var array<string, int>|null */
    private ?array $testFilesByContext = null;

    public function __construct(private readonly string $repositoryRoot) {}

    /**
     * Her modül tanımı için bir satır: adı, sınıfı, beyan ettiği bağlamlar,
     * ölçülen gözlem ve ondan türetilen rozet.
     *
     * @return list<array<string, mixed>>
     */
    public function specModules(): array
    {
        $rows = [];

        foreach ($this->specFiles() as $path) {
            $slug = basename($path, '.md');
            $source = (string) file_get_contents($path);

            $declaration = $this->declarationIn($source, $slug);
            $evidence = $this->evidenceFor($declaration);
            $presence = ModuleCodePresence::deriveFrom($evidence);

            $rows[] = [
                'slug' => $slug,
                'name' => $this->headingIn($source, $slug),
                'specPath' => self::SPEC_DIRECTORY.'/'.$slug.'.md',
                'moduleClass' => $this->classOf($slug),
                'mapping' => $declaration?->kind() ?? 'undeclared',
                'mappingNote' => $declaration?->note() ?? '',
                'contexts' => $declaration?->contexts() ?? [],
                'presence' => $presence->value,
                'observation' => [
                    'directories' => $evidence->directories(),
                    'routeFiles' => $evidence->routeFiles(),
                    'tables' => $evidence->tables(),
                    'testFiles' => $evidence->testFiles(),
                ],
            ];
        }

        return $rows;
    }

    /**
     * Tanımı olmayan kod bağlamları (`docs/111` §4.2).
     *
     * Eşlemenin ikinci yarısı budur ve ayrı listelenir: `app/` altında bir
     * dizini olan ama hiçbir modül tanımının sahiplenmediği bağlam. Bunları
     * modül listesine karıştırmak, ikisini de okunamaz yapardı.
     *
     * @return list<string>
     */
    public function unmappedContexts(): array
    {
        $claimed = [];

        foreach ($this->specModules() as $row) {
            foreach ($row['contexts'] as $context) {
                $claimed[$context] = true;
            }
        }

        $unmapped = [];

        foreach ($this->allContexts() as $context) {
            if (! isset($claimed[$context])) {
                $unmapped[] = $context;
            }
        }

        return $unmapped;
    }

    // --- tanım dosyaları ---------------------------------------------------

    /**
     * @return list<string>
     */
    private function specFiles(): array
    {
        $paths = glob($this->repositoryRoot.'/'.self::SPEC_DIRECTORY.'/*.md');

        if ($paths === false) {
            return [];
        }

        sort($paths);

        return array_values($paths);
    }

    private function headingIn(string $source, string $slug): string
    {
        if (preg_match('/^#\s+(.+)$/m', $source, $match) === 1) {
            return trim($match[1]);
        }

        return $slug;
    }

    /**
     * Tek satır, tek alan. Alan hiç yoksa `null` döner ve rozet "bilinmiyor"
     * olur — sessizce "yok" değil.
     */
    private function declarationIn(string $source, string $slug): ?ModuleContextDeclaration
    {
        if (preg_match('/^'.ModuleContextDeclaration::FIELD.':\s*(.+)$/m', $source, $match) !== 1) {
            return null;
        }

        try {
            return ModuleContextDeclaration::parse($match[1]);
        } catch (InvalidArgumentException) {
            /*
                Okunamayan bir beyan, olmayan bir beyandan daha iyi değildir:
                ikisi de ölçüm yapılamadığı anlamına gelir ve ikisi de
                "bilinmiyor" verir. Burada bir varsayılan uydurmak, sayfanın
                tek işini — dürüst olmayı — bozardı.
            */
            unset($slug);

            return null;
        }
    }

    private function classOf(string $slug): string
    {
        if (str_starts_with($slug, 'core-')) {
            return 'core';
        }

        if (str_starts_with($slug, 'opt-')) {
            return 'optional';
        }

        return 'product';
    }

    // --- ölçüm -------------------------------------------------------------

    private function evidenceFor(?ModuleContextDeclaration $declaration): ModuleCodeEvidence
    {
        if ($declaration === null || $declaration->isUnknown()) {
            return ModuleCodeEvidence::notMeasurable();
        }

        $directories = [];
        $routeFiles = [];
        $tables = [];
        $tests = 0;

        foreach ($declaration->contexts() as $context) {
            foreach (self::LAYERS as $layer) {
                $relative = 'app/'.$layer.'/'.$context;

                if (is_dir($this->repositoryRoot.'/'.$relative)) {
                    $directories[] = $relative;
                }
            }

            $routeFiles = array_merge($routeFiles, $this->routeFilesByContext()[$context] ?? []);
            $tables = array_merge($tables, $this->tablesByContext()[$context] ?? []);
            $tests += $this->testFilesByContext()[$context] ?? 0;
        }

        return ModuleCodeEvidence::measured(
            array_values(array_unique($directories)),
            array_values(array_unique($routeFiles)),
            array_values(array_unique($tables)),
            $tests,
        );
    }

    /**
     * @return array<string, list<string>>
     */
    private function routeFilesByContext(): array
    {
        if ($this->routeFilesByContext !== null) {
            return $this->routeFilesByContext;
        }

        $map = [];

        foreach ($this->phpFilesUnder('routes') as $file) {
            $relative = $this->relativePath($file->getPathname());
            $source = (string) file_get_contents($file->getPathname());

            if (preg_match_all('/'.preg_quote(self::CONTROLLER_NAMESPACE, '/').'([A-Za-z0-9]+)\\\\/', $source, $matches) === false) {
                continue;
            }

            foreach (array_unique($matches[1]) as $context) {
                $map[$context][] = $relative;
            }
        }

        foreach ($map as $context => $files) {
            $map[$context] = array_values(array_unique($files));
        }

        return $this->routeFilesByContext = $map;
    }

    /**
     * Bağlamın kodu bir tablo adını anıyor VE bir migration o tabloyu
     * yaratıyorsa, o veri o bağlamındır. Kesişim; isim benzerliği değil.
     *
     * @return array<string, list<string>>
     */
    private function tablesByContext(): array
    {
        if ($this->tablesByContext !== null) {
            return $this->tablesByContext;
        }

        $created = [];

        foreach ($this->phpFilesUnder('database/migrations') as $file) {
            $source = (string) file_get_contents($file->getPathname());

            if (preg_match_all('/Schema::create\(\s*[\'"]([a-z0-9_]+)[\'"]/', $source, $matches) > 0) {
                foreach ($matches[1] as $table) {
                    $created[$table] = true;
                }
            }
        }

        $map = [];

        foreach (self::LAYERS as $layer) {
            foreach ($this->directoriesIn('app/'.$layer) as $context) {
                $tables = [];

                foreach ($this->phpFilesUnder('app/'.$layer.'/'.$context) as $file) {
                    $source = (string) file_get_contents($file->getPathname());

                    if (preg_match_all('/table\(\s*[\'"]([a-z0-9_]+)[\'"]/', $source, $matches) > 0) {
                        foreach ($matches[1] as $table) {
                            if (isset($created[$table])) {
                                $tables[] = $table;
                            }
                        }
                    }
                }

                if ($tables !== []) {
                    $map[$context] = array_values(array_unique(array_merge($map[$context] ?? [], $tables)));
                }
            }
        }

        foreach ($map as $context => $tables) {
            sort($tables);
            $map[$context] = $tables;
        }

        return $this->tablesByContext = $map;
    }

    /**
     * Test dosyası bir bağlama, YOLUNDA o bağlamın adını taşıyan bir parça
     * varsa sayılır (`tests/Feature/MenuCatalog/…`). Dosya adına gömülü
     * benzerlik ("MediaLibraryTest" → Media) sayılmaz: yakın duran bir isim
     * bir ölçüm değildir.
     *
     * @return array<string, int>
     */
    private function testFilesByContext(): array
    {
        if ($this->testFilesByContext !== null) {
            return $this->testFilesByContext;
        }

        $counts = [];

        foreach ($this->phpFilesUnder('tests') as $file) {
            $segments = explode('/', $this->relativePath($file->getPathname()));
            array_shift($segments);
            $last = array_pop($segments);
            $segments[] = basename((string) $last, '.php');

            foreach (array_unique($segments) as $segment) {
                $counts[$segment] = ($counts[$segment] ?? 0) + 1;
            }
        }

        return $this->testFilesByContext = $counts;
    }

    /**
     * @return list<string>
     */
    private function allContexts(): array
    {
        $contexts = [];

        foreach (self::LAYERS as $layer) {
            foreach ($this->directoriesIn('app/'.$layer) as $context) {
                $contexts[$context] = true;
            }
        }

        $names = array_keys($contexts);
        sort($names);

        return array_values($names);
    }

    // --- dosya sistemi -----------------------------------------------------

    /**
     * @return list<string>
     */
    private function directoriesIn(string $relative): array
    {
        $path = $this->repositoryRoot.'/'.$relative;

        if (! is_dir($path)) {
            return [];
        }

        $names = [];

        foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $entry) {
            if ($entry instanceof SplFileInfo && $entry->isDir()) {
                $names[] = $entry->getFilename();
            }
        }

        sort($names);

        return array_values($names);
    }

    /**
     * @return list<SplFileInfo>
     */
    private function phpFilesUnder(string $relative): array
    {
        $path = $this->repositoryRoot.'/'.$relative;

        if (! is_dir($path)) {
            return [];
        }

        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $entry) {
            if ($entry instanceof SplFileInfo && $entry->isFile() && $entry->getExtension() === 'php') {
                $files[] = $entry;
            }
        }

        return $files;
    }

    private function relativePath(string $absolute): string
    {
        $prefix = $this->repositoryRoot.'/';

        return str_starts_with($absolute, $prefix) ? substr($absolute, strlen($prefix)) : $absolute;
    }
}
