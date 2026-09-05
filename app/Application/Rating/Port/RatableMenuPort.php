<?php

declare(strict_types=1);

namespace App\Application\Rating\Port;

/**
 * "BU MENÜ SATIRI GERÇEKTEN BU MENÜDE Mİ, VE ARKASINDA HANGİ TABAK VAR?"
 *
 * ═══ NEDEN DOĞRULANMAK ZORUNDA ═══
 *
 * `menuItemId` gövdeden gelir, yani misafirin telefonundan. Doğrulanmasaydı,
 * bir masadan okutulan karekodla BAŞKA BİR RESTORANIN ürününe oy
 * verilebilirdi — gövdeye yabancı bir sayı yazmak yeterdi. Sipariş ucunda
 * aynı kural yazılı ve aynı sebeple: kimliği istemciden alan her alan
 * sunucuda yeniden bulunur.
 *
 * ═══ NEDEN ÜRÜN KİMLİĞİ DÖNER ═══
 *
 * Puan tabağa yazılır, menü satırına değil (`RatingSubject`). Fiyatı
 * değiştiği için yeniden kurulan bir menü satırı ürünün puanını
 * sıfırlayamamalı.
 */
interface RatableMenuPort
{
    /**
     * Menü satırının arkasındaki ürün — satır bu menüye ait değilse `null`.
     */
    public function productForMenuItem(int $workspaceId, int $menuId, int $menuItemId): ?int;

    /**
     * Bir menünün bütün satırları: menü satırı kimliği => ürün kimliği.
     *
     * Gösterim yolu bunu okur: yayın anlık görüntüsü menü satırlarını
     * taşıyor, puanlar ise ürünlere yazılmış durumda; ikisi arasındaki
     * köprü tek bir sorguda kurulur, satır başına bir sorguyla değil.
     *
     * @return array<int, int>
     */
    public function productsForMenu(int $workspaceId, int $menuId): array;
}
