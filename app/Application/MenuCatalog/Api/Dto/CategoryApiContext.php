<?php

declare(strict_types=1);

namespace App\Application\MenuCatalog\Api\Dto;

/**
 * Bir kategorinin, HTTP katmanının karar vermek için ihtiyaç duyduğu bağlamı.
 *
 * FF-154 ile menü kimliği ve KATEGORİNİN O ANKİ ADI eklendi: kategori
 * silmek içindeki her satırı da götüren en yıkıcı menü işlemidir ve o
 * kaydın "Yaz Menüsü silindi" diyebilmesi için adın silinmeden ÖNCE
 * okunmuş olması gerekir.
 */
final class CategoryApiContext
{
    public function __construct(
        public readonly int $workspaceId,
        public readonly string $brandCurrencyCode,
        public readonly int $menuId,
        public readonly string $name,
    ) {}
}
