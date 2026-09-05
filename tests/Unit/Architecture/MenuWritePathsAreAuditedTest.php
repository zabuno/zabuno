<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * DELİĞİN BİR DAHA AÇILMAMASI — FF-156.
 *
 * FF-154 menü denetim izini kurdu; FF-156 onun bıraktığı deliği kapattı
 * (AI onayı menüye yazıyor, ize yazmıyordu). Delik bir dikkatsizlik
 * değildi: menüye yazan yeni bir yol eklemek KOLAY, o yolu ize bağlamayı
 * hatırlamak ZORDUR — ve unutulduğunda hiçbir test kırılmaz, hiçbir ekran
 * bozulmaz. Eksik bir denetim izi TAM görünür; sahip aradığı değişikliği
 * bulamayınca "kimse dokunmamış" der.
 *
 * BU TEST O SESSİZLİĞİ BOZAR. Menü verisine yazan her dosyayı sayar ve her
 * birinin ya denetim izine bağlı olmasını ya da BURADA gerekçesiyle
 * listelenmiş olmasını ister. Yeni bir yazma yolu eklendiğinde test kırılır
 * ve kırılma mesajı ne yapılacağını söyler.
 *
 * NE OLMADIĞI DA ÖNEMLİ: bu bir "her yazma kaydedilmeli" kuralı DEĞİLDİR.
 * FF-154 sıralamayı, "bugün bitti"yi ve servis aralığını bilerek dışarıda
 * bıraktı ve gerekçelerini `MenuAuditAction` docblock'una yazdı. Test o
 * kararı korur; istediği tek şey kararın VERİLMİŞ olmasıdır.
 *
 * SINIRI: tarama metinseldir ve çağrı adlarına bakar. Altyapı katmanı
 * (`app/Infrastructure`) kapsam dışıdır — yazmayı O uygular, kaydı ise
 * üstündeki katman verir. Bir gün alışılmadık bir yazma yolu (ham SQL,
 * dinamik tablo adı) eklenirse tarama onu göremez; o yüzden bu test bir
 * güvenlik ağıdır, kanıt değil.
 */
final class MenuWritePathsAreAuditedTest extends TestCase
{
    /**
     * Denetim izinin ÜSTÜNDE durduğu katmanlar.
     *
     * Kaydı burası verir: hangi olayın ize değdiği bir uygulama kararıdır
     * ve fail ("kim") yalnız burada bilinir.
     *
     * @var list<string>
     */
    private const SCANNED_DIRS = [
        __DIR__.'/../../../app/Http/Controllers',
        __DIR__.'/../../../app/Application',
        __DIR__.'/../../../app/Jobs',
        __DIR__.'/../../../app/Console',
    ];

    /**
     * Menü verisini DEĞİŞTİREN depo çağrıları.
     *
     * Adlar tekildir; `createProduct` gibi menüye değil ÜRÜN kataloğuna
     * yazan çağrılar bilerek yok: bu izin konusu menüdür.
     *
     * @var list<string>
     */
    private const WRITE_CALLS = [
        '->createDraftMenu(',
        '->addCategory(',
        '->addMenuItem(',
        '->addMenuEntry(',
        '->updateMenuItemPrice(',
        '->updateMenuItemVisibility(',
        '->deleteMenuItem(',
        '->deleteCategory(',
        '->renameCategory(',
        '->renameMenuItemProduct(',
        '->importDraftRows(',
        '->reorderMenuItems(',
        '->reorderCategories(',
        '->replaceProductAllergens(',
        '->setServiceWindow(',
        '->clearServiceWindow(',
        // `rename`/`delete` tek başına her yerde geçer; menü takvimine
        // yapılan çağrı özellik adıyla ayrılır.
        'schedule->rename(',
        'schedule->delete(',
    ];

    /** Depoyu atlayıp doğrudan tabloya yazan yollar. */
    private const DIRECT_WRITE_PATTERN =
        '/DB::table\(\'(menus|menu_categories|menu_items)\'\)[^;]*->(update|insert|insertGetId|delete)\(/';

    /** Denetim izine bağlı bir dosyanın taşıdığı bağımlılık. */
    private const AUDIT_MARKER = 'MenuAuditPort';

    /**
     * BİLEREK KAYDEDİLMEYEN yollar ve sebepleri.
     *
     * Gerekçelerin tamamı `MenuAuditAction` docblock'undadır; buradaki
     * cümle onun kısası. Bu listeye bir dosya eklemek bir KARARDIR: "sahip
     * bunu sorar mı?" sorusuna hayır cevabı verilmiş demektir.
     *
     * @var array<string, string>
     */
    private const DELIBERATELY_NOT_AUDITED = [
        // Sıra misafire verilmiş bir söz değildir ve menü düzenlenirken
        // onlarca kez değişir.
        'app/Http/Controllers/MenuCatalog/ReorderCategoriesController.php' => 'sıralama',
        'app/Http/Controllers/MenuCatalog/ReorderMenuItemsController.php' => 'sıralama',
        // "Bugün bitti" servis sırasında atılan, ertesi gün kendiliğinden
        // silinen bir tebeşir notudur ve sistemdeki EN SIK mutasyondur.
        'app/Http/Controllers/MenuCatalog/UpdateMenuItemStockController.php' => 'bugün bitti',
        'app/Http/Controllers/MenuCatalog/UpdateMenuStockController.php' => 'bugün bitti',
        // Menünün günün hangi saatinde açılacağı bir yerleşim kararıdır,
        // menünün İÇERİĞİ değil.
        'app/Http/Controllers/MenuCatalog/UpdateMenuServiceWindowController.php' => 'servis aralığı',
        'app/Http/Controllers/MenuCatalog/DeleteMenuServiceWindowController.php' => 'servis aralığı',
        // Açıklama pazarlama metnidir; elle düzenleme yolu da (
        // `RenameMenuItemController`) onu kaydetmez. AI yolunu ayrı tutmak
        // izi TERS yönde yanıltırdı.
        'app/Application/Ai/UseCase/ApplyProductDescriptionDraft.php' => 'ürün açıklaması',
    ];

    public function test_the_scan_actually_finds_menu_write_paths(): void
    {
        self::assertGreaterThan(
            10,
            count($this->menuWritePaths()),
            'Tarama menü yazma yolu bulamadı; kural sessizce vacuous olurdu.'
        );
    }

    /**
     * Menüye yazan her yol ya ize bağlıdır ya da gerekçesiyle listelidir.
     */
    public function test_every_menu_write_path_is_audited_or_deliberately_listed(): void
    {
        foreach ($this->menuWritePaths() as $relativePath => $contents) {
            if (isset(self::DELIBERATELY_NOT_AUDITED[$relativePath])) {
                continue;
            }

            /*
                `assertStringContainsString` DEĞİL: başarısızlıkta dosyanın
                TAMAMINI mesaja basar ve asıl talimat kaybolur. Kırılan
                testin işi ne yapılacağını söylemektir.
            */
            self::assertTrue(
                str_contains($contents, self::AUDIT_MARKER),
                "{$relativePath} menü verisine yazıyor ama denetim izine (".self::AUDIT_MARKER.') '
                ."bağlı değil.\n"
                ."Menüye yazan her yol ya ize yazmalı ya da bir KARARLA dışarıda kalmalıdır.\n"
                ."Yapılacak: `MenuAuditAction` docblock'undaki listeyi oku. Olay ize değiyorsa "
                .'FF-154 desenini uygula (aynı port, aynı DTO, öncesi/sonrası); değmiyorsa '
                ."gerekçesini O docblock'a ve bu testteki DELIBERATELY_NOT_AUDITED listesine yaz."
            );
        }
    }

    /**
     * Liste ÇÜRÜMEZ: artık var olmayan ya da artık yazmayan bir dosya
     * listede kalırsa, bir sonraki okuyucu onu geçerli bir karar sanır.
     */
    public function test_the_exception_list_has_no_stale_entries(): void
    {
        $writePaths = $this->menuWritePaths();

        foreach (array_keys(self::DELIBERATELY_NOT_AUDITED) as $relativePath) {
            self::assertArrayHasKey(
                $relativePath,
                $writePaths,
                "{$relativePath} artık menü verisine yazmıyor (ya da yok); DELIBERATELY_NOT_AUDITED "
                .'listesinden çıkarılmalı.'
            );
        }
    }

    /**
     * Menü verisine yazan dosyalar: yol => içerik.
     *
     * @return array<string, string>
     */
    private function menuWritePaths(): array
    {
        $found = [];

        foreach (self::SCANNED_DIRS as $dir) {
            self::assertDirectoryExists($dir, "Taranacak dizin yok: {$dir}");

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
            );

            /** @var SplFileInfo $fileInfo */
            foreach ($iterator as $fileInfo) {
                if (! $fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
                    continue;
                }

                $contents = (string) file_get_contents($fileInfo->getPathname());

                if (! $this->writesMenuData($contents)) {
                    continue;
                }

                $found[$this->relativePath($fileInfo)] = $contents;
            }
        }

        ksort($found);

        return $found;
    }

    private function writesMenuData(string $contents): bool
    {
        foreach (self::WRITE_CALLS as $call) {
            if (str_contains($contents, $call)) {
                return true;
            }
        }

        return preg_match(self::DIRECT_WRITE_PATTERN, $contents) === 1;
    }

    private function relativePath(SplFileInfo $fileInfo): string
    {
        $root = (string) realpath(__DIR__.'/../../../');
        $path = (string) $fileInfo->getRealPath();

        return ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);
    }
}
