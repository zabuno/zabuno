<?php

declare(strict_types=1);

namespace App\Application\Publication\UseCase;

use App\Application\MenuCatalog\Dto\MenuDraftTree;
use App\Application\Publication\Exception\UnreadyDraftException;
use App\Domain\Publication\MenuIdentity;

final class BuildPublicationSnapshot
{
    /**
     * Builds an immutable publish snapshot solely from the server-held
     * draft tree, validating that the draft is ready to publish. The
     * request-supplied snapshot is never trusted.
     *
     * Restoran kimliği de snapshot'a YAZILIR (`docs/75`): misafirin
     * gördüğü ad, adres ve telefon yayın anında donar. Canlı sorguyla
     * çekilseydi, şubenin adı değiştiği gün geçmiş bir yayın da sessizce
     * değişirdi.
     *
     * @return array{identity?: array{brandName:string,locationName:string,addressLine:string|null,phone:string|null}, categories: list<array{name:string,menuItems:list<array{productName:string,priceMinorAmount:int,currencyCode:string,allergens:list<string>}>}>}
     *
     * @throws UnreadyDraftException
     */
    /**
     * @param  array<int, array<string,mixed>>  $itemImages  menü satırı kimliği → görsel bloğu
     * @param  array<string,mixed>|null  $logo
     */
    public static function fromDraftTree(
        MenuDraftTree $tree,
        ?MenuIdentity $identity = null,
        array $itemImages = [],
        ?array $logo = null,
    ): array {
        if ($tree->categories === []) {
            throw UnreadyDraftException::noCategory();
        }

        $categories = [];
        $hasVisibleItem = false;

        foreach ($tree->categories as $category) {
            if (trim($category['name']) === '') {
                throw UnreadyDraftException::blankCategoryName();
            }

            $menuItems = [];

            foreach ($category['items'] as $item) {
                if (! $item['isVisible']) {
                    continue;
                }

                if (trim($item['productName']) === '') {
                    throw UnreadyDraftException::blankVisibleProductName();
                }

                if ($item['priceMinorAmount'] <= 0) {
                    throw UnreadyDraftException::nonPositiveVisiblePrice();
                }

                if (trim($item['currencyCode']) === '') {
                    throw UnreadyDraftException::blankVisibleCurrency();
                }

                $hasVisibleItem = true;

                $entry = [
                    // Menü satırı kimliği snapshot'a YAZILIR: "bugün
                    // tükendi" donmuş menünün üstüne konan canlı bir
                    // nottur ve hangi satıra ait olduğunu bilmesi gerekir
                    // (`docs/82`).
                    'menuItemId' => $item['id'],
                    'productName' => $item['productName'],
                    // Açıklama ve görsel DE donar: yayınlanmış menü,
                    // sonradan düzenlenen bir fotoğrafı ya da metni
                    // habersiz göstermez (`docs/77`).
                    'description' => $item['description'] ?? null,
                    'priceMinorAmount' => $item['priceMinorAmount'],
                    'currencyCode' => $item['currencyCode'],
                    'allergens' => $item['allergens'],
                ];

                $image = $itemImages[$item['id']] ?? null;

                if ($image !== null) {
                    $entry['image'] = $image;
                }

                $menuItems[] = $entry;
            }

            if ($menuItems === []) {
                continue;
            }

            $categories[] = [
                'name' => $category['name'],
                'menuItems' => $menuItems,
            ];
        }

        if (! $hasVisibleItem) {
            throw UnreadyDraftException::noVisibleItem();
        }

        $snapshot = ['categories' => $categories];

        // Kimlik BAŞA yazılır: snapshot bir insanın da okuyabileceği
        // sıradadır ve "bu hangi restoranın menüsü" ilk satırdadır.
        if ($identity === null) {
            return $snapshot;
        }

        $identityBlock = $identity->toSnapshot();

        if ($logo !== null) {
            $identityBlock['logo'] = $logo;
        }

        return ['identity' => $identityBlock] + $snapshot;
    }
}
