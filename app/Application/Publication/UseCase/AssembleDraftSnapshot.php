<?php

declare(strict_types=1);

namespace App\Application\Publication\UseCase;

use App\Application\Media\Port\MenuMediaPort;
use App\Application\MenuCatalog\Port\MenuCatalogRepositoryPort;
use App\Application\Publication\Exception\UnreadyDraftException;
use App\Application\Publication\Port\MenuIdentityPort;

/**
 * Taslaktan yayınlanabilir bir anlık görüntü kurmanın TEK yeri.
 *
 * Bu sınıf var, çünkü aynı montaj artık üç yerden isteniyor: hemen
 * yayınlamak, ileri bir zamana planlamak ve telefonda önizlemek. Üç kopya
 * olsaydı, biri bir gün görselleri unuturdu — ve sahip önizlemede gördüğü
 * fotoğrafın yayında olmadığını ancak misafir sorduğunda anlardı.
 */
final class AssembleDraftSnapshot
{
    public function __construct(
        private readonly MenuCatalogRepositoryPort $menuCatalog,
        private readonly MenuIdentityPort $identities,
        private readonly MenuMediaPort $menuMedia,
    ) {}

    /**
     * @return array{snapshot: array<string,mixed>, visibleItemIds: list<int>, brandId: ?int, locationId: int}|null
     *                                                                                                              Menü yoksa `null`.
     *
     * @throws UnreadyDraftException taslak yayınlanabilir durumda değilse
     */
    public function forMenu(int $workspaceId, int $menuId): ?array
    {
        $tree = $this->menuCatalog->getDraftTree($workspaceId, $menuId);

        if ($tree === null) {
            return null;
        }

        $itemImages = [];
        $visibleItemIds = [];

        foreach ($tree->categories as $category) {
            foreach ($category['items'] as $item) {
                if (! $item['isVisible']) {
                    continue;
                }

                $visibleItemIds[] = (int) $item['id'];
                $image = $this->menuMedia->snapshotImage($workspaceId, 'menu_item', (int) $item['id']);

                if ($image !== null) {
                    $itemImages[(int) $item['id']] = $image;
                }
            }
        }

        $brandId = $this->identities->brandIdForMenu($workspaceId, $menuId);
        $logo = $brandId === null ? null : $this->menuMedia->snapshotImage($workspaceId, 'brand', $brandId);

        $snapshot = BuildPublicationSnapshot::fromDraftTree(
            $tree,
            $this->identities->forMenu($workspaceId, $menuId),
            $itemImages,
            $logo,
        );

        return [
            'snapshot' => $snapshot,
            'visibleItemIds' => array_values($visibleItemIds),
            'brandId' => $brandId,
            'locationId' => $tree->locationId,
        ];
    }
}
