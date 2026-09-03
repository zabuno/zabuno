<?php

declare(strict_types=1);

namespace App\Application\Ai\UseCase;

use App\Application\MenuCatalog\Exception\MenuCatalogTenantMismatchException;
use App\Application\MenuCatalog\Port\MenuCatalogRepositoryPort;
use Illuminate\Support\Facades\DB;

/**
 * İnsan onayını ÜRÜNE yazar — `docs/96` (Faz 2, `opt-23`).
 *
 * `renameMenuItemProduct`'ın AYNI yolunu kullanır (mevcut ürün adını
 * KORUYARAK, yalnız açıklamayı değiştirerek) — bu depoda açıklama yazmanın
 * tek test edilmiş yolu budur; ikinci bir yazma yolu icat edilmez.
 *
 * Hedef menü öğesi `subject_id`'den okunur, çağıranın verdiği bir
 * parametreden DEĞİL — bir istemci hatası yanlış ürünün üstüne yazamaz.
 *
 * İKİ KEZ UYGULANMAZ — aynı disiplin: `ApplyMenuArtifact`.
 */
final class ApplyProductDescriptionDraft
{
    public function __construct(private readonly MenuCatalogRepositoryPort $menuCatalog) {}

    /**
     * @return array{applied: bool, alreadyApplied: bool, reason: ?string}
     */
    public function handle(int $workspaceId, int $artifactId): array
    {
        $artifact = DB::table('ai_artifacts')
            ->where('id', $artifactId)
            ->where('workspace_id', $workspaceId)
            ->where('capability', 'product.description')
            ->first();

        if ($artifact === null) {
            return ['applied' => false, 'alreadyApplied' => false, 'reason' => 'not-found'];
        }

        if ($artifact->applied_at !== null) {
            return ['applied' => false, 'alreadyApplied' => true, 'reason' => null];
        }

        $description = $this->readDescription($artifact);

        if ($description === null) {
            return ['applied' => false, 'alreadyApplied' => false, 'reason' => 'empty-draft'];
        }

        $menuItemId = (int) $artifact->subject_id;

        $current = DB::table('menu_items')
            ->join('products', 'products.id', '=', 'menu_items.product_id')
            ->where('menu_items.id', $menuItemId)
            ->where('products.workspace_id', $workspaceId)
            ->select('products.name as product_name')
            ->first();

        if ($current === null) {
            return ['applied' => false, 'alreadyApplied' => false, 'reason' => 'not-found'];
        }

        try {
            $this->menuCatalog->renameMenuItemProduct(
                $workspaceId,
                $menuItemId,
                (string) $current->product_name,
                $description,
                touchDescription: true,
            );
        } catch (MenuCatalogTenantMismatchException) {
            return ['applied' => false, 'alreadyApplied' => false, 'reason' => 'not-found'];
        }

        DB::table('ai_artifacts')->where('id', $artifactId)->update([
            'reviewed_at' => now(),
            'applied_at' => now(),
            'updated_at' => now(),
        ]);

        return ['applied' => true, 'alreadyApplied' => false, 'reason' => null];
    }

    private function readDescription(object $artifact): ?string
    {
        $fields = (array) json_decode((string) $artifact->fields, true);

        foreach ($fields as $field) {
            if (($field['name'] ?? null) !== 'description') {
                continue;
            }

            $value = trim((string) ($field['value'] ?? ''));

            return $value === '' ? null : $value;
        }

        return null;
    }
}
