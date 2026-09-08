<?php

declare(strict_types=1);

namespace Tests\Feature\DataRights;

use App\Domain\DataRights\TenantDataScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * SİLME SIRASI ŞEMADAN ÖLÇÜLÜR — FF-226 (`docs/138` §4).
 *
 * Kırk dört tablo tek bir işlemde siliniyor ve sıra elle yazılı. Elle
 * yazılan bir sıra bir gün bozulur: biri kapsam listesine yeni bir tablo
 * ekler, ebeveyninin ÜSTÜNE koyar ve silme ilk yabancı anahtar kısıtında
 * düşer. Bunu bir kiracının gerçek silme gününde öğrenmek, en pahalı
 * öğrenme biçimidir.
 *
 * Kırk dört tabloya birer satır yazan bir donanım da yazılabilirdi;
 * ama o donanım her yeni sütun kısıtında bakım ister ve bakılmayan bir
 * donanım bir gün sessizce eksik kalır. Bu test bunun yerine ŞEMANIN
 * KENDİSİNİ okuyor: her yabancı anahtar için, işaret edilen tablo işaret
 * eden tablodan SONRA silinmeli.
 *
 * `set null` ve `cascade` istisnadır ve olması gerekir: ebeveyn silinince
 * çocuk zaten kendi başının çaresine bakıyor (`media_assets.media_folder_id`
 * `nullOnDelete`, `media_folders.parent_id` `cascadeOnDelete`).
 *
 * Gereksinim: DATA-ERASE-ORDER-TOPOLOGICAL-01.
 */
final class ErasureOrderRespectsForeignKeysTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_parent_is_deleted_before_a_child_that_points_at_it(): void
    {
        $order = array_map(
            static fn ($table): string => $table->name,
            TenantDataScope::erasedTablesInDeletionOrder(),
        );

        $position = array_flip($order);
        $problems = [];

        foreach ($order as $child) {
            foreach (Schema::getForeignKeys($child) as $key) {
                $parent = (string) $key['foreign_table'];
                $onDelete = strtolower((string) ($key['on_delete'] ?? ''));

                // Ebeveyn silinince çocuk kendi başının çaresine bakıyor.
                if ($onDelete === 'set null' || $onDelete === 'cascade') {
                    continue;
                }

                // Silinmeyen bir tabloya (fatura, onay defteri) ya da
                // kapsam dışı bir tabloya (kullanıcı, plan) işaret eden
                // anahtarın sırayla işi yok: o satır zaten kalıyor.
                if ($parent === $child || ! array_key_exists($parent, $position)) {
                    continue;
                }

                if ($position[$parent] < $position[$child]) {
                    $problems[] = sprintf(
                        '%s (%d) → %s (%d): ebeveyn önce siliniyor, kısıt düşer.',
                        $child,
                        $position[$child],
                        $parent,
                        $position[$parent],
                    );
                }
            }
        }

        $this->assertSame([], $problems, implode("\n", array_merge(
            ['Silme sırası yabancı anahtarlarla çelişiyor:'],
            $problems,
            ['Kapsam listesi EBEVEYNDEN ÇOCUĞA yazılır; silme onun tersine yürür.'],
        )));
    }

    public function test_the_workspace_row_is_never_in_the_deletion_order(): void
    {
        $order = array_map(
            static fn ($table): string => $table->name,
            TenantDataScope::erasedTablesInDeletionOrder(),
        );

        // Mezar taşı silinmez: kesilmiş faturanın sahibi belirsiz kalırdı.
        $this->assertNotContains('workspaces', $order);
    }
}
