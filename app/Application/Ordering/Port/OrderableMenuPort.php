<?php

declare(strict_types=1);

namespace App\Application\Ordering\Port;

use App\Application\Ordering\Dto\OrderableLine;

interface OrderableMenuPort
{
    /**
     * O ANDA SERVİS EDİLEN menünün sipariş edilebilir satırları.
     *
     * Kaynak taslak değil, YAYINLANMIŞ menünün dayandığı katalogdur ve
     * yalnız GÖRÜNÜR satırlar döner: gizli bir ürün menüde yoktur,
     * dolayısıyla sipariş de edilemez. "Bugün bitti" ise ayrı bir eksendir
     * ve satırın kendisinde işaretlenir — düşürülmez, çünkü misafire
     * "bu vardı ama bugün bitti" demek "bu yok" demekten farklıdır.
     *
     * Anahtar `menu_items.id`'dir: misafirin gönderdiği tek kimlik odur.
     *
     * @return array<int, OrderableLine>
     */
    public function linesForMenu(int $workspaceId, int $menuId): array;
}
