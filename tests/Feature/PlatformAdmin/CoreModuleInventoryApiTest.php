<?php

declare(strict_types=1);

namespace Tests\Feature\PlatformAdmin;

use App\Domain\Platform\PlatformRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * MODÜL ENVANTERİ OKUNUR — `docs/111` adım 1, kabul ölçütleri §8.
 *
 * Sorunun sahibi tek cümle sormuştu: "mevcutta hangi modüller var?".
 * Bugün bu soruyu depoyu elle tarayarak cevaplıyoruz. Bu uç o taramanın
 * **doğrulanmış** parçasını yayınlar: 16 CORE modülü ve bağlamlar arası
 * gözlenmiş bağımlılık kenarları.
 *
 * Bu testlerin donduruduğu asıl söz, uçta NE OLMADIĞIDIR:
 * `modules/*.md` durum iddiası (62'si de "PLANNING ONLY" der ve en az
 * 18'inde bu yanlıştır), açma/kapama anahtarı, uydurma sürüm, kanıtsız
 * kenar. Bir envanter, taşıdığı yanlış tek satır yüzünden envanter olmaktan
 * çıkar.
 */
final class CoreModuleInventoryApiTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/admin/modules';

    private function superAdmin(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        DB::table('platform_role_assignments')->insert([
            'user_id' => $user->getKey(),
            'role' => PlatformRole::SuperAdmin->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    // --- §8.2 yetki --------------------------------------------------------

    #[Test]
    public function a_guest_never_sees_the_inventory(): void
    {
        $this->getJson(self::URI)->assertUnauthorized();
    }

    #[Test]
    public function a_verified_user_without_the_platform_role_gets_a_plain_404(): void
    {
        // Enumeration-safe: "yetkin yok" demek, yüzeyin VAR olduğunu
        // söylemektir. Mevcut kapı (EnsurePlatformSuperAdmin) düz 404 verir
        // ve bu uç için ikinci bir izin kavramı türetilmez (`docs/111` §2).
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->getJson(self::URI)->assertNotFound();
    }

    // --- §8.1 uç yalnız gerçek kaynağı okur --------------------------------

    #[Test]
    public function the_sixteen_core_modules_are_served_from_the_registry_file(): void
    {
        $response = $this->actingAs($this->superAdmin())->getJson(self::URI)->assertOk();

        $codes = array_column($response->json('modules'), 'code');
        self::assertSame(
            array_map(static fn (int $n): string => sprintf('CORE-%02d', $n), range(1, 16)),
            $codes,
            'CORE-01..CORE-16 tam ve dosyadaki sırayla gelmeli.'
        );

        $identity = $response->json('modules.0');
        self::assertSame('Identity & Sessions', $identity['name']);
        self::assertSame('1.0.0', $identity['version']);
        self::assertSame('core', $identity['moduleClass']);
        self::assertSame('required', $identity['deterministicBaseline']);
        self::assertSame('advisory', $identity['aiPosture']);
        self::assertSame([], $identity['dependencies']);

        $tenancy = collect($response->json('modules'))->firstWhere('code', 'CORE-02');
        self::assertSame(['CORE-01'], $tenancy['dependencies']);
    }

    #[Test]
    public function a_registry_row_that_is_removed_disappears_from_the_endpoint(): void
    {
        // §8.1'in ikinci yarısı: kaynak GERÇEKTEN kaynaksa, ondan çıkarılan
        // satır uçtan da düşer. Uçta ikinci bir kopya (sabit dizi, önbellek,
        // migration) olsaydı bu test onu yakalardı.
        config(['core-modules' => [
            'CORE-01' => [
                'name' => 'Identity & Sessions',
                'version' => '2.3.4',
                'module_class' => 'core',
                'dependencies' => [],
                'deterministic_baseline' => 'required',
                'ai_posture' => 'assistive',
            ],
        ]]);

        $response = $this->actingAs($this->superAdmin())->getJson(self::URI)->assertOk();

        self::assertSame(['CORE-01'], array_column($response->json('modules'), 'code'));
        self::assertSame('2.3.4', $response->json('modules.0.version'));
        self::assertSame('assistive', $response->json('modules.0.aiPosture'));
    }

    #[Test]
    public function a_registry_row_that_violates_the_manifest_contract_is_not_served_half_valid(): void
    {
        // `ModuleManifest` sınırda reddediyor (`docs/111` §3.1). Uç o sınırı
        // ATLAYIP ham diziyi yayınlasaydı, doğrulanmış bir kaynak sanılan
        // şey aslında doğrulanmamış olurdu.
        config(['core-modules' => [
            'CORE-01' => [
                'name' => 'Identity & Sessions',
                'version' => 'bir-nokta-sıfır',
                'module_class' => 'core',
                'dependencies' => [],
                'deterministic_baseline' => 'required',
                'ai_posture' => 'advisory',
            ],
        ]]);

        $this->actingAs($this->superAdmin())->getJson(self::URI)->assertStatus(500);
    }

    // --- §8.5 bağımlılık kanıtı taşınır ------------------------------------

    #[Test]
    public function every_dependency_edge_carries_the_file_that_proves_it(): void
    {
        $response = $this->actingAs($this->superAdmin())->getJson(self::URI)->assertOk();

        $nodes = $response->json('contextGraph.nodes');
        self::assertContains('MenuCatalog', $nodes);
        self::assertContains('Publication', $nodes);

        $edges = $response->json('contextGraph.edges');
        self::assertNotSame([], $edges, 'Gözlenmiş kenarlar var; boş bir grafik yanlış olurdu.');

        foreach ($edges as $edge) {
            self::assertArrayHasKey('evidencePath', $edge);
            self::assertNotSame('', $edge['evidencePath'], 'Kanıtsız kenar çizilmez (`docs/111` §8.5).');
            self::assertFileExists(base_path($edge['evidencePath']), "Kanıt yolu depoda yok: {$edge['evidencePath']}");
        }

        $publication = collect($edges)->firstWhere('from', 'Publication');
        self::assertSame('MenuCatalog', $publication['to']);
        self::assertSame(
            'app/Application/Publication/UseCase/BuildPublicationSnapshot.php',
            $publication['evidencePath']
        );
    }

    // --- §8.3, §5.1, §6: çizilmeyecekler uçta da YOK -----------------------

    #[Test]
    public function the_payload_carries_no_lifecycle_switch_and_no_invented_field(): void
    {
        $response = $this->actingAs($this->superAdmin())->getJson(self::URI)->assertOk();

        // Anahtar yok, çünkü kodda karşılığı yok (`docs/111` §5.1). Devre
        // dışı bir anahtar bile tutulmayacak bir söz olurdu (`docs/109` §8.4)
        // ve ucun taşıdığı bir alan er ya da geç ekrana çıkar.
        foreach ($response->json('modules') as $module) {
            self::assertSame(
                ['aiPosture', 'code', 'dependencies', 'deterministicBaseline', 'moduleClass', 'name', 'version'],
                collect(array_keys($module))->sort()->values()->all(),
                'Uç yalnız kaynağı olan alanı taşır; enabled/status/health uydurmaz.'
            );
        }

        $body = $response->getContent() ?: '';
        self::assertStringNotContainsString('PLANNING ONLY', $body);
        self::assertStringNotContainsString('bilinmiyor', $body);
    }

    // --- §8.7 spec dosyalarının DURUM iddiası kaynak DEĞİLDİR --------------

    #[Test]
    public function neither_the_endpoint_nor_the_screen_reaches_into_the_module_spec_files(): void
    {
        /*
            KURAL DARALDI, GEVŞEMEDİ (FF-210). Adım 4 `modules/*.md` içine tek
            satırlık bir `contexts:` alanı koydu ve o alan bir ADRESTİR, bir
            durum değil (`docs/111` §4.2 B). Onu okuyan TEK yer
            `RepositoryModuleInventory`'dir; uç ve ekran o klasöre hâlâ hiç
            uzanmaz — okuma yeri tek olmazsa ikinci bir okuyucu er ya da geç
            başka bir satırı da ayrıştırır.
        */
        $surfaces = [
            'app/Http/Controllers/PlatformAdmin/ListCoreModulesController.php',
            'resources/js/components/admin/pages/ModulesPage.tsx',
            'resources/js/components/admin/pages/ModuleInventory.tsx',
        ];

        foreach ($surfaces as $surface) {
            $source = file_get_contents(base_path($surface));
            self::assertIsString($source, "{$surface} okunamadı.");

            /*
                Yasaklanan şey o klasörden BAHSETMEK değil — bu dosyaların
                yorumları `modules/*.md`'nin neden okunmadığını anlatıyor ve
                anlatmalı da. Yasaklanan, oraya uzanan bir YOL: bir dize
                içinde geçen `modules/` her zaman bir dosya okumasıdır
                (`base_path('modules/…')`, `glob`, `import`).
            */
            self::assertDoesNotMatchRegularExpression(
                '#([\'"])[^\'"\n]*modules/[^\'"\n]*\1#i',
                $source,
                "{$surface}: `modules/` bir kaynak değildir (`docs/111` §3.4)."
            );
        }
    }

    #[Test]
    public function the_only_reader_of_the_spec_files_parses_nothing_but_the_heading_and_the_mapping(): void
    {
        /*
            Kaynak taraması yetmezdi, o yüzden DAVRANIŞ ölçülüyor: gövdesinde
            "PLANNING ONLY" yazan bir tanım dosyası bile rozete etki etmiyor
            (`RepositoryModuleInventoryTest`). Burada donan şey, o okuyucunun
            tekliği: uçtan çıkan her satırın kaynağı ya `config/` ya da
            ölçümün kendisidir.
        */
        $reader = (string) file_get_contents(base_path('app/Infrastructure/Modules/RepositoryModuleInventory.php'));
        $reader = preg_replace('#/\*.*?\*/#s', '', $reader) ?? $reader;

        foreach (['PLANNING', 'Durum', 'status'] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $reader,
                "Tanım dosyalarının okuyucusu bir DURUM cümlesi ayrıştırmaya başlamış: {$forbidden}."
            );
        }
    }

    // --- §4.1 rozet + §4.2 eşleme ------------------------------------------

    #[Test]
    public function every_spec_module_carries_the_observation_that_produced_its_badge(): void
    {
        $response = $this->actingAs($this->superAdmin())->getJson(self::URI)->assertOk();

        $specs = $response->json('specModules');
        self::assertNotSame([], $specs, 'Depoda modül tanımı var; boş bir liste yanlış olurdu.');

        foreach ($specs as $spec) {
            self::assertContains(
                $spec['presence'],
                ['implemented', 'partial', 'definition-only', 'unknown'],
                "{$spec['slug']}: rozet bilinen dört değerden biri olmalı."
            );

            /*
                `docs/111` §8.4: rozet gözlemsiz çizilmez. Gözlem UÇTA
                taşınmazsa ekran onu çizemez; bu yüzden kural burada donar.
            */
            self::assertArrayHasKey('observation', $spec);
            self::assertArrayHasKey('directories', $spec['observation']);
            self::assertArrayHasKey('routeFiles', $spec['observation']);
            self::assertArrayHasKey('tables', $spec['observation']);
            self::assertArrayHasKey('testFiles', $spec['observation']);

            foreach ($spec['observation']['directories'] as $directory) {
                self::assertDirectoryExists(base_path($directory), "{$spec['slug']}: ölçülen dizin depoda yok.");
            }

            foreach ($spec['observation']['routeFiles'] as $routeFile) {
                self::assertFileExists(base_path($routeFile), "{$spec['slug']}: ölçülen rota dosyası depoda yok.");
            }

            if ($spec['presence'] === 'unknown') {
                // Bilinmiyor bir cevaptır ama gerekçesiz bir cevap değildir.
                self::assertTrue(
                    $spec['mapping'] === 'undeclared' || $spec['mappingNote'] !== '',
                    "{$spec['slug']}: \"bilinmiyor\" sebebini de yazmalı."
                );
            }
        }
    }

    #[Test]
    public function a_module_measured_in_the_code_is_never_reported_as_definition_only(): void
    {
        // Menü kataloğu bu ürünün en yoğun bağlamı — `docs/111` §0 bu belgeyi
        // yazdıran örnek olarak onu seçmişti: tanım dosyası "çalıştırılamaz"
        // diyordu, kod 9 domain sınıfı ve 21 Feature testi taşıyordu.
        $response = $this->actingAs($this->superAdmin())->getJson(self::URI)->assertOk();

        $menuCatalog = collect($response->json('specModules'))->firstWhere('slug', 'menu-catalog');

        self::assertSame('implemented', $menuCatalog['presence']);
        self::assertSame(['MenuCatalog'], $menuCatalog['contexts']);
        self::assertNotSame([], $menuCatalog['observation']['tables']);
        self::assertGreaterThan(0, $menuCatalog['observation']['testFiles']);
    }

    #[Test]
    public function code_contexts_without_a_spec_are_listed_on_their_own(): void
    {
        /*
            Eşlemenin ikinci yarısı (`docs/111` §4.2). Bunları modül listesine
            karıştırmak iki soruyu birden bulanıklaştırırdı: "bu tanımın kodu
            var mı" ile "bu kodun tanımı var mı" aynı soru değildir.
        */
        $response = $this->actingAs($this->superAdmin())->getJson(self::URI)->assertOk();

        $unmapped = $response->json('unmappedContexts');
        $claimed = collect($response->json('specModules'))->pluck('contexts')->flatten()->all();

        foreach ($unmapped as $context) {
            self::assertNotContains($context, $claimed, "{$context} hem eşleşmiş hem eşleşmemiş olamaz.");

            $directories = array_filter(
                ['Domain', 'Application', 'Infrastructure'],
                static fn (string $layer): bool => is_dir(base_path('app/'.$layer.'/'.$context))
            );

            self::assertNotEmpty($directories, "{$context} bir kod bağlamı değil; listede olmamalı.");
        }
    }
}
