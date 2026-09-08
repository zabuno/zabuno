<?php

declare(strict_types=1);

namespace Tests\Feature\DataRights;

use App\Domain\DataRights\TenantDataScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * KAPSAM ŞEMAYA KARŞI ÖLÇÜLÜR — FF-226 (`docs/138` §2).
 *
 * Bir dışa aktarma ya da silme kapsamını yalnız belgeye yazmak yetmez:
 * yarın biri yeni bir tablo ekler, belge güncellenmez ve kapsam sessizce
 * eksilir. En kötü hâli silmede görünür — "sildik" dediğimiz veri durmaya
 * devam eder ve kimse fark etmez.
 *
 * Bu test o sessizliği imkânsız kılar: şemadaki HER tablo ya kiracıya ait
 * olarak sınıflandırılmıştır ya da açık bir sebeple kapsam dışıdır. Yeni
 * bir tablo ekleyen paket, bir karar vermeden yeşile dönemez.
 *
 * Gereksinim: DATA-SCOPE-COVERS-SCHEMA-01, DATA-SCOPE-NO-PHANTOM-02,
 * DATA-SCOPE-TENANT-COLUMN-03, DATA-SCOPE-RETENTION-REASON-04.
 */
final class TenantDataScopeCoversTheSchemaTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> */
    private function schemaTables(): array
    {
        $names = array_map(
            static fn (array $table): string => (string) $table['name'],
            Schema::getTables(),
        );

        sort($names);

        return array_values(array_filter($names, static fn (string $name): bool => $name !== 'sqlite_sequence'));
    }

    public function test_every_table_in_the_schema_is_either_tenant_data_or_explicitly_out_of_scope(): void
    {
        $classified = array_merge(
            array_map(static fn ($table): string => $table->name, TenantDataScope::tables()),
            array_keys(TenantDataScope::outOfScope()),
            // Çalışma alanının kendi satırı: silinmez, MEZAR TAŞI olarak
            // kalır (`docs/138` §4) ve bu yüzden tablo listesinde değil
            // silicinin kendisinde ele alınır.
            ['workspaces'],
        );

        $unclassified = array_values(array_diff($this->schemaTables(), $classified));

        $this->assertSame([], $unclassified, implode("\n", [
            'Şemada sınıflandırılmamış tablo var. Her tablo bir karar ister:',
            'kiracıya aitse `TenantDataScope::tables()`, değilse `outOfScope()`.',
            'Sınıflandırılmayan bir tablo, dışa aktarmadan da silmeden de sessizce düşer.',
        ]));
    }

    public function test_no_classified_table_is_a_phantom(): void
    {
        $schema = $this->schemaTables();

        foreach (TenantDataScope::tables() as $table) {
            $this->assertContains($table->name, $schema, "Kiracı tablosu şemada yok: {$table->name}.");
        }

        foreach (array_keys(TenantDataScope::outOfScope()) as $name) {
            if ($name === 'platform_credentials' || $name === 'sqlite_sequence') {
                // İkisi de şemada bulunabilir ya da bulunmayabilir
                // (sürücüye ve göç sırasına göre); listede durmaları bir
                // karar kaydıdır, bir varlık iddiası değil.
                continue;
            }

            $this->assertContains($name, $schema, "Kapsam dışı tablo şemada yok: {$name}.");
        }
    }

    public function test_direct_tables_really_carry_the_column_the_scope_claims(): void
    {
        foreach (TenantDataScope::tables() as $table) {
            if ($table->workspaceColumn === null) {
                $this->assertNotNull($table->parentTable);
                $this->assertTrue(
                    Schema::hasColumn($table->name, (string) $table->foreignKey),
                    "Çocuk tablo ebeveynine bağlanamıyor: {$table->name}.{$table->foreignKey}",
                );

                continue;
            }

            $this->assertTrue(
                Schema::hasColumn($table->name, $table->workspaceColumn),
                "Kiracı sütunu yok: {$table->name}.{$table->workspaceColumn}",
            );
        }
    }

    public function test_every_table_carrying_a_workspace_column_is_a_decision_not_an_accident(): void
    {
        $withWorkspaceColumn = array_values(array_filter(
            $this->schemaTables(),
            static fn (string $name): bool => Schema::hasColumn($name, 'workspace_id'),
        ));

        $decided = array_merge(
            array_map(static fn ($table): string => $table->name, TenantDataScope::tables()),
            array_keys(TenantDataScope::outOfScope()),
        );

        $this->assertSame([], array_values(array_diff($withWorkspaceColumn, $decided)));
    }

    public function test_retained_tables_all_say_why(): void
    {
        $retained = TenantDataScope::retained();

        $this->assertNotSame([], $retained, 'Saklanan hiçbir şey yoksa ekrandaki dürüstlük cümlesi de yalan olurdu.');

        foreach ($retained as $name => $reason) {
            $this->assertNotSame('', trim($reason), "Saklanan tablo sebebini söylemiyor: {$name}.");
        }
    }
}
