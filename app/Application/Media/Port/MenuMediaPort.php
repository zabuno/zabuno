<?php

declare(strict_types=1);

namespace App\Application\Media\Port;

/**
 * Görselin bir ürüne ya da markaya BAĞLANMASI (`docs/77`, P0-04).
 *
 * Bağ her zaman bir SÜRÜME yapılır, varlığa değil. Sahip fotoğrafı
 * sonradan düzenlerse yeni bir sürüm doğar; yayınlanmış menü hâlâ
 * onayladığı sürümü gösterir.
 */
interface MenuMediaPort
{
    /**
     * @return bool Bağ kuruldu mu (varlık bulunamadı ya da hazır değilse false)
     */
    public function bindMenuItemImage(int $workspaceId, int $menuItemId, ?int $mediaAssetId): bool;

    public function bindBrandLogo(int $workspaceId, int $brandId, ?int $mediaAssetId): bool;

    /**
     * TASLAKTA bağlı varlığın kimliği — ekranın "şu an hangi logo seçili"
     * sorusuna cevabı (`docs/98` FF-64). Yayına yazılmış satırlara bakmaz.
     */
    public function draftAssetId(int $workspaceId, string $entityType, int $entityId): ?int;

    /**
     * Yayına yazılacak görsel bloğu: sürüm kimliği, alternatif metin,
     * ölçüler ve her genişlik için değişmez adres.
     *
     * @return array{versionId:int,altText:string,width:int,height:int,sources:list<array{width:int,url:string}>}|null
     */
    public function snapshotImage(int $workspaceId, string $entityType, int $entityId): ?array;

    /**
     * Panelin "bu satırda hangi görsel bağlı" sorusu — TEK sorguda.
     *
     * Satır başına ayrı sorgu, kırk ürünlük bir menüde kırk gidiş dönüş
     * demekti ve menü ekranını gözle görülür biçimde yavaşlatırdı.
     *
     * @param  list<int>  $menuItemIds
     * @return array<int, int> menü satırı kimliği → medya varlığı kimliği
     */
    public function attachedAssetIds(int $workspaceId, array $menuItemIds): array;

    /**
     * Yayın anında kullanım kaydı açar.
     *
     * Bu satırlar olmadan "bu görsel yayında mı" sorusu cevapsızdır ve
     * paneldeki bir silme, misafirin gözü önünde menüyü bozar (`docs/76`).
     *
     * @param  list<int>  $menuItemIds
     */
    public function recordPublicationUsages(int $workspaceId, int $publicationId, array $menuItemIds, ?int $brandId): void;
}
