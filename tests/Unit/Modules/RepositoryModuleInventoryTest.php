<?php

declare(strict_types=1);

namespace Tests\Unit\Modules;

use App\Infrastructure\Modules\RepositoryModuleInventory;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * ÖLÇÜM SAHTE BİR DEPO ÜZERİNDE DONAR (`docs/111` adım 3).
 *
 * Gerçek depo üzerinde koşan bir test, bugünkü sayıya bakar ve yarın o sayı
 * meşru bir sebeple değiştiğinde kırılır — yani ölçümü değil, ölçümün o günkü
 * sonucunu dondurur. Burada dondurulan KURALIN kendisidir: hangi girdi hangi
 * rozeti üretir.
 *
 * En kritik davranış, birinci testte: tanım dosyasının GÖVDESİ "PLANNING
 * ONLY — şu an çalıştırılamaz" dese bile rozet bundan etkilenmez. Bu depoda
 * altmış iki dosyanın altmış ikisi bir zamanlar bunu diyordu ve en az on
 * sekizinde yanlıştı; o cümleyi ekrana taşıyan bir okuma, `docs/109`
 * §8.7'deki kusur ailesine en görünür üyeyi eklerdi.
 */
final class RepositoryModuleInventoryTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/zabuno-module-inventory-'.bin2hex(random_bytes(6));
        mkdir($this->root.'/modules', 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->root);

        parent::tearDown();
    }

    #[Test]
    public function a_spec_that_calls_itself_unrunnable_is_still_measured_from_the_code(): void
    {
        $this->spec('menu-catalog', "# Menu Catalog\n\ncontexts: MenuCatalog\n\nPLANNING ONLY. Şu an çalıştırılamaz.\n");
        $this->context('MenuCatalog', 'Domain', "<?php table('menus');");
        $this->migration("<?php Schema::create('menus', fn () => null);");
        $this->route('menu-catalog', '<?php use App\\Http\\Controllers\\MenuCatalog\\ShowMenuController;');
        $this->test('Feature/MenuCatalog', 'ShowMenuTest');

        $row = $this->firstRow();

        self::assertSame('implemented', $row['presence']);
        self::assertSame(['MenuCatalog'], $row['contexts']);
        self::assertSame(['app/Domain/MenuCatalog'], $row['observation']['directories']);
        self::assertSame(['routes/api/menu-catalog.php'], $row['observation']['routeFiles']);
        self::assertSame(['menus'], $row['observation']['tables']);
        self::assertSame(1, $row['observation']['testFiles']);
        self::assertSame('Menu Catalog', $row['name']);
    }

    #[Test]
    public function a_spec_without_the_field_is_unknown_and_not_absent(): void
    {
        // Alan yoksa ölçüm YAPILAMAZ. "Kod karşılığı yok" demek, yapılmamış
        // bir ölçümün sonucunu uydurmak olurdu.
        $this->spec('mystery', "# Mystery\n\nBir açıklama.\n");

        $row = $this->firstRow();

        self::assertSame('unknown', $row['presence']);
        self::assertSame('undeclared', $row['mapping']);
        self::assertSame([], $row['contexts']);
    }

    #[Test]
    public function an_unreadable_declaration_is_unknown_too(): void
    {
        $this->spec('broken', "# Broken\n\ncontexts: menu-catalog\n");

        self::assertSame('unknown', $this->firstRow()['presence']);
    }

    #[Test]
    public function a_slice_inside_another_context_is_unknown_and_keeps_its_reason(): void
    {
        /*
            Zamanlanmış yayınlama Publication'ın İÇİNDE yaşar ve kendi dizini
            yoktur. Onu Publication ile eşleştirmek, o bağlamın bütün kanıtını
            dilime mal etmek olurdu: ekranda "uygulanmış" yazardı ve bu, hiç
            ölçülmemiş bir şey hakkında verilmiş bir söz olurdu.
        */
        $this->spec('opt-11', "# OPT-11\n\ncontexts: belirsiz: Publication bağlamının içinde bir dilim\n");
        $this->context('Publication', 'Domain', '<?php');

        $row = $this->firstRow();

        self::assertSame('unknown', $row['presence']);
        self::assertSame('unknown', $row['mapping']);
        self::assertSame('Publication bağlamının içinde bir dilim', $row['mappingNote']);
        self::assertSame([], $row['observation']['directories']);
    }

    #[Test]
    public function an_explicit_no_code_declaration_is_definition_only(): void
    {
        $this->spec('opt-17-loyalty', "# OPT-17\n\ncontexts: yok\n");

        self::assertSame('definition-only', $this->firstRow()['presence']);
    }

    #[Test]
    public function a_context_with_a_directory_but_no_surface_is_partial(): void
    {
        $this->spec('core-taxonomy', "# CORE-09\n\ncontexts: Taxonomy\n");
        $this->context('Taxonomy', 'Domain', '<?php');

        $row = $this->firstRow();

        self::assertSame('partial', $row['presence']);
        self::assertSame(['app/Domain/Taxonomy'], $row['observation']['directories']);
    }

    #[Test]
    public function a_table_only_counts_when_a_migration_actually_creates_it(): void
    {
        /*
            Köprü tablo ADIDIR, dosya adı benzerliği değil. Bir bağlam
            olmayan bir tabloyu ansaydı ve biz onu saysaydık, ekranda "verisi
            var" yazardı — ölçmeden verilmiş bir "geçti".
        */
        $this->spec('ghost', "# Ghost\n\ncontexts: Ghost\n");
        $this->context('Ghost', 'Infrastructure', "<?php table('never_created');");
        $this->test('Feature/Ghost', 'GhostTest');

        $row = $this->firstRow();

        self::assertSame([], $row['observation']['tables']);
        self::assertSame('partial', $row['presence']);
    }

    #[Test]
    public function contexts_without_a_spec_are_listed_separately(): void
    {
        // `docs/111` §4.2'nin ikinci yarısı: eşleşmeyen tanımlar ve tanımı
        // olmayan kod bağlamları AYRI listelenir; biri diğerinin içinde
        // kaybolmaz.
        $this->spec('menu-catalog', "# Menu Catalog\n\ncontexts: MenuCatalog\n");
        $this->context('MenuCatalog', 'Domain', '<?php');
        $this->context('Rating', 'Domain', '<?php');
        $this->context('Url', 'Domain', '<?php');

        self::assertSame(['Rating', 'Url'], $this->inventory()->unmappedContexts());
    }

    // --- sahte depo --------------------------------------------------------

    private function inventory(): RepositoryModuleInventory
    {
        return new RepositoryModuleInventory($this->root);
    }

    /**
     * @return array<string, mixed>
     */
    private function firstRow(): array
    {
        $rows = $this->inventory()->specModules();

        self::assertCount(1, $rows);

        return $rows[0];
    }

    private function spec(string $slug, string $contents): void
    {
        file_put_contents($this->root.'/modules/'.$slug.'.md', $contents);
    }

    private function context(string $name, string $layer, string $contents): void
    {
        $directory = $this->root.'/app/'.$layer.'/'.$name;
        mkdir($directory, 0o777, true);
        file_put_contents($directory.'/'.$name.'Thing.php', $contents);
    }

    private function migration(string $contents): void
    {
        mkdir($this->root.'/database/migrations', 0o777, true);
        file_put_contents($this->root.'/database/migrations/0001_01_01_000000_create.php', $contents);
    }

    private function route(string $name, string $contents): void
    {
        mkdir($this->root.'/routes/api', 0o777, true);
        file_put_contents($this->root.'/routes/api/'.$name.'.php', $contents);
    }

    private function test(string $directory, string $name): void
    {
        mkdir($this->root.'/tests/'.$directory, 0o777, true);
        file_put_contents($this->root.'/tests/'.$directory.'/'.$name.'.php', '<?php');
    }

    private function deleteTree(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $entry) {
            if ($entry instanceof SplFileInfo) {
                $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
        }

        rmdir($path);
    }
}
