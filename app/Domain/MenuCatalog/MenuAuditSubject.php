<?php

declare(strict_types=1);

namespace App\Domain\MenuCatalog;

/**
 * Denetim kaydının KONUSU — FF-154.
 *
 * Üç düzey var ve kimlikler kendi tablolarında birbirinden bağımsız artar:
 * 7 numaralı menü, 7 numaralı kategori ve 7 numaralı satır aynı anda var
 * olabilir. Tür olmadan `subject_id` tek başına hiçbir şey adreslemez.
 */
enum MenuAuditSubject: string
{
    case Menu = 'menu';

    case Category = 'category';

    /**
     * Menü SATIRI (`menu_items`), ürün (`products`) değil.
     *
     * Ad ve alerjen ürüne aittir ama sahip menüye bakar: aynı ürün iki
     * kategoride duruyorsa bile sorduğu şey "menüdeki şu satır"dır. Kayıt
     * bu yüzden satıra bağlanır.
     */
    case MenuItem = 'menu_item';
}
