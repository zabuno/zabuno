<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;

/**
 * `/urun/menu-yonetimi/stok-durumu/` — "bugün tükendi" (P0).
 *
 * Site haritasındaki başlık "Tükendi ve stok durumu"; ürünün yaptığı şey
 * bunun İLK yarısıdır. Stok sayımı, adet, otomatik düşüm yok — ölçüm
 * 2026-09-06: `menu_items.out_of_stock_since` tek bir zaman damgasıdır ve
 * `StockState` onu şubenin gününe göre okur. Sayfa bu yüzden "stok" kelimesini
 * satmıyor; "bugün tükendi" işaretinin ne yaptığını, ne zaman düştüğünü ve
 * kimin koyabildiğini anlatıyor.
 *
 * BİLEREK YAZILMAYANLAR: adet/envanter, siparişten otomatik tükenme, "saat
 * altıda geri gelir", çok günlük işaret, bildirim, işaretin denetim izi.
 */
final class StockStatusPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'urun.menu-yonetimi.stok-durumu',
            locale: 'en',
            metadata: new PageMetadata(
                seoTitle: 'Sold out for today',
                metaDescription: 'Mark a dish sold out and the guest sees it on the next scan, without a publish. The mark lifts itself at the end of your day, and the dish never leaves the menu.',
                h1: 'Stock status',
                breadcrumbTitle: 'Stock status',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'Stock status is the "sold out for today" mark. Mark a dish, or a whole list of dishes, as sold out and guests read it in words on the next scan, without a publish. The mark expires at the end of your branch\'s day on its own, and the dish stays on the menu the whole time.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'The fish is finished, and the menu does not know', [
                    new BlockEntry(
                        text: 'At nine the sea bass is gone. The card on table four still lists it, the guest orders it, and the waiter carries the bad news back. Hiding the dish is the wrong tool: a hidden dish is gone from the menu, and a guest who came for fish reads a menu that never had fish.',
                    ),
                    new BlockEntry(
                        text: 'The next morning the fish is back, and somebody has to remember to un-hide six dishes, one at a time, before the first guest sits down.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'A chalk note, not a menu change', [
                    new BlockEntry(
                        text: 'Sold out is a mark on the dish, separate from whether it is on the menu. The dish stays listed, in its place, with its price, and a short line says it is sold out. The guest who came for fish knows you have fish, just not tonight.',
                    ),
                    new BlockEntry(
                        text: 'The mark is a time, not a switch. It is true for the rest of the branch\'s day and false the next morning, without anyone touching it and without a scheduled job that could fail to run.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'How it works', [
                    new BlockEntry(
                        term: 'Mark one dish',
                        text: 'One request marks a dish sold out or back in stock.',
                        source: 'app/Http/Controllers/MenuCatalog/UpdateMenuItemStockController.php',
                    ),
                    new BlockEntry(
                        term: 'Or mark the list',
                        text: 'Several dishes are marked in one request: the ones that ran out and the ones that came back, together.',
                        source: 'app/Http/Controllers/MenuCatalog/UpdateMenuStockController.php',
                    ),
                    new BlockEntry(
                        term: 'The guest sees it on the next scan',
                        text: 'No publish is needed. The published version is unchanged; the mark is read live on top of it.',
                        source: 'app/Http/Controllers/QrDestination/ShowPublicMenuController.php',
                    ),
                    new BlockEntry(
                        term: 'It lifts itself at midnight, your midnight',
                        text: 'The mark carries the time it was set and counts as sold out only for that same day in the branch\'s time zone.',
                        source: 'app/Domain/MenuCatalog/StockState.php',
                    ),
                    new BlockEntry(
                        term: 'Or lift it yourself',
                        text: 'If a delivery arrives, mark the dish back in stock and it is back on the next scan.',
                        source: 'app/Http/Controllers/MenuCatalog/UpdateMenuItemStockController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'What it does', [
                    new BlockEntry(
                        term: 'Written in words on the guest page',
                        text: 'A sold-out dish stays in its section with its name and price and a short sold-out line, so the state does not depend on colour alone.',
                        source: 'resources/views/public-menu.blade.php',
                    ),
                    new BlockEntry(
                        term: 'On the dish page too',
                        text: 'A dish\'s own page shows the same sold-out line.',
                        source: 'resources/views/public-menu-item.blade.php',
                    ),
                    new BlockEntry(
                        term: 'The kitchen can do it',
                        text: 'The kitchen role marks allergens and sold-out state and nothing else. The cook can say the fish is gone without being able to change a price.',
                        source: 'app/Domain/Tenancy/MembershipRole.php',
                    ),
                    new BlockEntry(
                        term: 'The boundary is on the server',
                        text: 'A kitchen account that calls the price endpoint is refused. Hiding a button is not a permission; the server is.',
                        source: 'tests/Feature/MenuCatalog/KitchenRoleMenuBoundaryTest.php',
                    ),
                    new BlockEntry(
                        term: 'Sold out is not hidden',
                        text: 'Hidden and sold out are two separate states. A hidden dish is absent; a sold-out dish is present and unavailable.',
                        source: 'database/migrations/2026_08_28_000500_add_out_of_stock_to_menu_items.php',
                    ),
                    new BlockEntry(
                        term: 'Ordering refuses it',
                        text: 'Where ordering from the table is on, a sold-out dish cannot be put in the basket, and an order that names one is rejected with the reason.',
                        source: 'app/Application/Ordering/UseCase/BuildOrderLines.php',
                    ),
                    new BlockEntry(
                        term: 'Kept out of the audit trail, on purpose',
                        text: 'Sold-out marks are the most frequent change in the system and lift themselves. Recording them would bury the price question under an evening of service.',
                        source: 'app/Domain/MenuCatalog/MenuAuditAction.php',
                    ),
                    new BlockEntry(
                        term: 'Shown correctly in the panel',
                        text: 'The menu editor shows the sold-out state computed for today in the branch\'s time zone, separately from visibility.',
                        source: 'app/Http/Controllers/MenuCatalog/Support/MenuTreePayload.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'What you need', [
                    new BlockEntry(
                        term: 'A published menu',
                        text: 'The mark is read on top of the published version. A dish that is not published has nowhere to be shown as sold out.',
                        source: 'app/Http/Controllers/QrDestination/ShowPublicMenuController.php',
                    ),
                    new BlockEntry(
                        term: 'Permission to manage stock',
                        text: 'Owners, managers, editors and the kitchen role can mark stock.',
                        source: 'app/Domain/Authorization/RolePermissions.php',
                    ),
                    new BlockEntry(
                        term: 'A time zone on the branch',
                        text: 'The day ends in the branch\'s own time zone, so the branch carries one; it always does.',
                        source: 'database/migrations/2026_08_28_000100_move_timezone_ownership_to_locations.php',
                    ),
                    new BlockEntry(
                        term: 'No plan',
                        text: 'Marking a dish sold out is on the free plan.',
                        source: 'database/seeders/PlanCatalogueSeeder.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'What it does not do', [
                    new BlockEntry(
                        term: 'No counts',
                        text: 'There is no quantity. Zabuno does not know you have four portions left; it knows only sold out or not.',
                        source: 'database/migrations/2026_08_28_000500_add_out_of_stock_to_menu_items.php',
                    ),
                    new BlockEntry(
                        term: 'No automatic sold out from orders',
                        text: 'Orders sent from the table do not reduce a stock figure, because there is none. Somebody marks the dish.',
                        source: 'app/Application/Ordering/UseCase/BuildOrderLines.php',
                    ),
                    new BlockEntry(
                        term: 'No "back at six"',
                        text: 'A dish cannot be marked sold out until a chosen time. It is sold out until the end of the day or until somebody lifts it.',
                        source: 'app/Domain/MenuCatalog/StockState.php',
                    ),
                    new BlockEntry(
                        term: 'No multi-day mark',
                        text: 'A dish that will be gone all week has to be marked again each day, or hidden.',
                        source: 'app/Domain/MenuCatalog/StockState.php',
                    ),
                    new BlockEntry(
                        term: 'No notification',
                        text: 'Nobody is emailed or messaged when a dish is marked sold out.',
                        source: 'routes/api/menu-catalog.php',
                    ),
                    new BlockEntry(
                        term: 'No record of who marked it',
                        text: 'Sold-out marks are not in the audit trail.',
                        source: 'app/Domain/MenuCatalog/MenuAuditAction.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'Questions owners ask', [
                    new BlockEntry(
                        term: 'Do I need to publish after marking a dish sold out?',
                        text: 'No. The mark is read live on top of the published version, and guests see it on the next scan.',
                    ),
                    new BlockEntry(
                        term: 'Will the dish come back tomorrow on its own?',
                        text: 'Yes. The mark counts only for the day it was set, in your branch\'s time zone.',
                    ),
                    new BlockEntry(
                        term: 'Can my cook mark a dish sold out without seeing prices?',
                        text: 'Yes. The kitchen role marks allergens and sold-out state and nothing else, and the limit is enforced on the server.',
                    ),
                    new BlockEntry(
                        term: 'Should I hide a dish or mark it sold out?',
                        text: 'Sold out, if it will be back. A hidden dish disappears from the menu; a sold-out dish stays listed with a note.',
                    ),
                    new BlockEntry(
                        term: 'Can I say "back at six"?',
                        text: 'No. The mark lasts until the end of the day or until you lift it.',
                    ),
                    new BlockEntry(
                        term: 'Can a guest order a sold-out dish?',
                        text: 'No. Where ordering is on, a sold-out dish cannot be added to the basket, and an order naming it is refused.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'Sold out is free', [
                    new BlockEntry(
                        text: 'Marking a dish sold out is part of the free plan.',
                        href: '/pricing',
                        term: 'See the plans',
                    ),
                ]),

                new ContentBlock(BlockType::Related, 'Related pages', [
                    new BlockEntry(text: 'Menu management', pageKey: 'urun.menu-yonetimi'),
                    new BlockEntry(text: 'Dishes', pageKey: 'urun.menu-yonetimi.urunler'),
                    new BlockEntry(text: 'QR menu', pageKey: 'urun.qr-menu'),
                ]),
            ],
        );
    }
}
