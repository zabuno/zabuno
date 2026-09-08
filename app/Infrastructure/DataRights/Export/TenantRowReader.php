<?php

declare(strict_types=1);

namespace App\Infrastructure\DataRights\Export;

use App\Domain\DataRights\TenantDataScope;
use App\Domain\DataRights\TenantTable;
use Illuminate\Support\Facades\DB;

/**
 * "Bu çalışma alanının satırları hangileri?" — tek yerde, tek kez (FF-226).
 *
 * Dışa aktarma ile silme AYNI kimlik kümesini kullanır ve bu bilinçli: iki
 * ayrı sorgu yazılsaydı, bir gün biri bir tabloyu dâhil eder diğeri etmez
 * ve kullanıcı indirdiğinde gördüğü veri, sildirdiğinde yok olan veriden
 * farklı olurdu. O fark hiçbir ekranda görünmez ve yalnız mahkemede
 * anlaşılırdı.
 *
 * ÇOCUK TABLOLAR EBEVEYNDEN TÜRER. `menu_items` üzerinde `workspace_id`
 * yoktur ve olmaması bir kusur değil: kategori zaten menüye, menü de
 * çalışma alanına bağlıdır. Sütun eklemek yerine bağı takip etmek, şemayı
 * bu paketin ihtiyacına göre bükmemek demektir.
 */
final class TenantRowReader
{
    /** @var array<string, list<int>> */
    private array $ids = [];

    public function __construct(private readonly int $workspaceId) {}

    /**
     * Bir tablonun bu çalışma alanına ait satır kimlikleri.
     *
     * @return list<int>
     */
    public function idsFor(TenantTable $table): array
    {
        if (array_key_exists($table->name, $this->ids)) {
            return $this->ids[$table->name];
        }

        if ($table->workspaceColumn !== null) {
            $ids = DB::table($table->name)
                ->where($table->workspaceColumn, $this->workspaceId)
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            return $this->ids[$table->name] = $ids;
        }

        $parent = self::tableNamed((string) $table->parentTable);
        $parentIds = $this->idsFor($parent);

        if ($parentIds === []) {
            return $this->ids[$table->name] = [];
        }

        /*
            PARÇALI SORGU: `whereIn` binlerce kimlikle çağrıldığında
            SQLite'ın değişken sınırına (varsayılan 999) çarpar ve sorgu
            "too many SQL variables" ile düşer. Yüz binlik bir misafir
            olayı tablosunda bu kaçınılmazdır; parçalamak bir iyileştirme
            değil, çalışmanın şartıdır.
        */
        $ids = [];

        foreach (array_chunk($parentIds, 500) as $chunk) {
            $ids = [
                ...$ids,
                ...DB::table($table->name)
                    ->whereIn((string) $table->foreignKey, $chunk)
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(static fn ($id): int => (int) $id)
                    ->all(),
            ];
        }

        return $this->ids[$table->name] = $ids;
    }

    /**
     * Satırları parça parça okur — bütün tablo belleğe alınmaz.
     *
     * @param  callable(list<array<string, mixed>>): void  $onChunk
     */
    public function eachRow(TenantTable $table, callable $onChunk, int $chunkSize = 500): void
    {
        foreach (array_chunk($this->idsFor($table), $chunkSize) as $chunk) {
            $rows = DB::table($table->name)
                ->whereIn('id', $chunk)
                ->orderBy('id')
                ->get()
                ->map(static fn (object $row): array => (array) $row)
                ->all();

            if ($rows !== []) {
                $onChunk($rows);
            }
        }
    }

    private static function tableNamed(string $name): TenantTable
    {
        foreach (TenantDataScope::tables() as $table) {
            if ($table->name === $name) {
                return $table;
            }
        }

        throw new \RuntimeException("Kapsamda olmayan ebeveyn tablo: {$name}.");
    }
}
