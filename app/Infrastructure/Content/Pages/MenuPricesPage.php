<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;

/**
 * `/urun/menu-yonetimi/urun-fiyatlari/` — menü fiyatları (P0).
 *
 * Fiyat, restoranın misafire verdiği sözdür ve bu sayfanın sorusu o sözün
 * nasıl yazıldığı, ne zaman masaya ulaştığı ve kimin değiştirdiğidir.
 * Rakam YAZILMAZ: sayfada tek bir fiyat örneği yok, çünkü örnek bir fiyat
 * yarın birinin gerçek sandığı fiyattır.
 *
 * BİLEREK YAZILMAYANLAR: boyut/porsiyon fiyatı, indirim, happy hour,
 * kampanya fiyatı, KDV satırı, döviz çevrimi, panelde toplu fiyat
 * değişikliği, misafire fiyat geçmişi, ödeme. Ölçüm 2026-09-06: `menu_items`
 * tek `price_minor_amount` taşıyor; indirim/vergi sütunu ve ucu yok;
 * fiyat ucu satır başına.
 */
final class MenuPricesPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'urun.menu-yonetimi.urun-fiyatlari',
            locale: 'en',
            metadata: new PageMetadata(
                seoTitle: 'Menu prices',
                metaDescription: 'One price per dish in the currency you charge, changed in a draft, released by a publish you can schedule, and recorded with who changed it and when.',
                h1: 'Prices',
                breadcrumbTitle: 'Prices',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'Every dish carries one price in the currency of your business. You change it in the draft, it reaches guests when you publish, a publish can be scheduled for a chosen minute, and every price change is recorded with who made it and when.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'The price on the table is a promise', [
                    new BlockEntry(
                        text: 'A guest orders at the price they read. If the card shows last month\'s number, the difference is argued at the till, and the argument is lost by whoever is standing there.',
                    ),
                    new BlockEntry(
                        text: 'Changing prices on paper means a reprint, so prices are changed rarely and all at once, usually at the wrong moment of the evening.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'Change in private, release on purpose', [
                    new BlockEntry(
                        text: 'Prices are edited in a draft nobody at a table can see. When you publish, every guest who scans from then on reads the new price; the version they were already reading does not change under them.',
                    ),
                    new BlockEntry(
                        text: 'A publish can be scheduled, so tonight\'s edits go live at six in the morning without anybody being awake for it.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'How it works', [
                    new BlockEntry(
                        term: 'Type the price',
                        text: 'Enter a decimal amount. It is checked against the decimals of your currency and stored as a whole number of minor units, so nothing is rounded twice.',
                        source: 'app/Domain/Money/Money.php',
                    ),
                    new BlockEntry(
                        term: 'It is checked against your currency',
                        text: 'A price in a currency other than the brand\'s is refused, not converted.',
                        source: 'app/Http/Controllers/MenuCatalog/UpdateMenuItemPriceController.php',
                    ),
                    new BlockEntry(
                        term: 'Preview on a phone',
                        text: 'A signed, expiring preview link shows the draft with its new prices on a real phone before anyone else sees it.',
                        source: 'app/Http/Controllers/Publication/CreateDraftPreviewLinkController.php',
                    ),
                    new BlockEntry(
                        term: 'Publish, now or at a set time',
                        text: 'Publish immediately, or schedule the publish; the server carries it out on the minute, in the branch\'s time zone.',
                        source: 'app/Http/Controllers/Publication/StorePublicationScheduleController.php',
                    ),
                    new BlockEntry(
                        term: 'Undo a mistake',
                        text: 'If the new prices were wrong, restore an earlier published version.',
                        source: 'app/Http/Controllers/Publication/RestorePublicationController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'What it does', [
                    new BlockEntry(
                        term: 'Formatted by the currency, not by a habit',
                        text: 'Two decimals for lira and euros, none for yen, three for dinar. The decimal count comes from the currency itself.',
                        source: 'app/Domain/Money/MoneyFormatter.php',
                    ),
                    new BlockEntry(
                        term: 'Written the way the guest reads numbers',
                        text: 'The separator between thousands and decimals follows the guest\'s interface language.',
                        source: 'app/Support/Money/PriceLabel.php',
                    ),
                    new BlockEntry(
                        term: 'No price rather than a wrong price',
                        text: 'A price that cannot be formatted is left out of the page instead of shown wrong.',
                        source: 'app/Support/Money/PriceLabel.php',
                    ),
                    new BlockEntry(
                        term: 'Prices frozen in every publication',
                        text: 'A published version carries its prices inside it. A price edited today does not change what yesterday\'s version shows.',
                        source: 'app/Application/Publication/UseCase/BuildPublicationSnapshot.php',
                    ),
                    new BlockEntry(
                        term: 'Who changed the kebab',
                        text: 'Every price change is written to the audit trail with the person and the time. A spreadsheet import that changes many prices is recorded as one summarised entry.',
                        source: 'app/Domain/MenuCatalog/MenuAuditAction.php',
                    ),
                    new BlockEntry(
                        term: 'All prices at once, through a spreadsheet',
                        text: 'Export the menu, change the price column, import it back. The import writes to the draft, never straight to the tables.',
                        source: 'app/Http/Controllers/MenuCatalog/ImportMenuCsvController.php',
                    ),
                    new BlockEntry(
                        term: 'Different prices per branch',
                        text: 'Menus belong to a branch, so a branch\'s prices are its own. The same dish can cost differently at two addresses.',
                        source: 'database/migrations/2026_09_05_000400_allow_many_menus_per_location.php',
                    ),
                    new BlockEntry(
                        term: 'The guest can filter by price',
                        text: 'On the menu page a guest can narrow the list to a price range, when scripts run.',
                        source: 'resources/views/public-menu.blade.php',
                    ),
                    new BlockEntry(
                        term: 'Orders carry the server\'s price',
                        text: 'Where ordering is on, the price on an order line comes from the server, not from what the phone sent.',
                        source: 'app/Application/Ordering/UseCase/BuildOrderLines.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'What you need', [
                    new BlockEntry(
                        term: 'A price above zero on every visible dish',
                        text: 'Publishing refuses a visible dish with no price.',
                        source: 'app/Application/Publication/UseCase/BuildPublicationSnapshot.php',
                    ),
                    new BlockEntry(
                        term: 'One currency on the business',
                        text: 'The currency is set on the brand from the real list of currencies, and every menu of the business is priced in it.',
                        source: 'app/Http/Requests/Tenancy/UpdateBrandRequest.php',
                    ),
                    new BlockEntry(
                        term: 'Permission to manage the menu',
                        text: 'Owners, managers and editors change prices. The kitchen role is refused on the price endpoint even if it tries.',
                        source: 'tests/Feature/MenuCatalog/KitchenRoleMenuBoundaryTest.php',
                    ),
                    new BlockEntry(
                        term: 'No plan',
                        text: 'Setting, changing, scheduling and restoring prices are on the free plan.',
                        source: 'database/seeders/PlanCatalogueSeeder.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'What it does not do', [
                    new BlockEntry(
                        term: 'One price per dish',
                        text: 'No size prices, no portion prices, no option surcharges.',
                        source: 'database/migrations/2026_08_20_000002_create_menu_catalog_tables.php',
                    ),
                    new BlockEntry(
                        term: 'No discounts, no happy hour',
                        text: 'There is no discount field, no time-based price and no promotional price. A cheaper afternoon list is a second menu that hands over by the hour.',
                        source: 'app/Http/Controllers/MenuCatalog/UpdateMenuServiceWindowController.php',
                    ),
                    new BlockEntry(
                        term: 'No tax line',
                        text: 'A price is the price. There is no separate tax amount, and the guest page says nothing about tax.',
                        source: 'app/Support/Money/PriceLabel.php',
                    ),
                    new BlockEntry(
                        term: 'No currency conversion',
                        text: 'Prices are shown in your currency only. A guest cannot switch them to another one.',
                        source: 'app/Domain/Money/MoneyFormatter.php',
                    ),
                    new BlockEntry(
                        term: 'No bulk repricing in the panel',
                        text: 'Prices are changed one dish at a time in the panel. Changing many at once is done through the spreadsheet.',
                        source: 'routes/api/menu-catalog.php',
                    ),
                    new BlockEntry(
                        term: 'No price history for the guest',
                        text: 'A guest sees the current published price only. Earlier prices are visible to you in the versions and the audit trail, not to them.',
                        source: 'app/Http/Controllers/Publication/ListPublicationsController.php',
                    ),
                    new BlockEntry(
                        term: 'No payment',
                        text: 'The price is read, not charged. Nobody pays through the menu.',
                        source: 'routes/web.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'Questions owners ask', [
                    new BlockEntry(
                        term: 'When does a new price reach the tables?',
                        text: 'When you publish. Until then it is in the draft only.',
                    ),
                    new BlockEntry(
                        term: 'What happens to a guest who is reading the menu while I publish?',
                        text: 'They keep reading the version they opened. The new price is read by the next scan.',
                    ),
                    new BlockEntry(
                        term: 'Why does the price show two decimals for lira but none for yen?',
                        text: 'Because the currency decides. The decimal count comes from the currency, not from a setting.',
                    ),
                    new BlockEntry(
                        term: 'Can I add a happy hour price?',
                        text: 'No. There is no time-based price. A cheaper afternoon list is a second menu that takes over by the hour.',
                    ),
                    new BlockEntry(
                        term: 'Does an import from a spreadsheet change prices immediately?',
                        text: 'No. It writes to the draft. Guests see the new prices after you publish.',
                    ),
                    new BlockEntry(
                        term: 'What if I type a price with the wrong number of decimals?',
                        text: 'It is refused with a message. The decimals must match your currency.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'Prices are free to change', [
                    new BlockEntry(
                        text: 'Setting, scheduling and restoring prices are on the free plan.',
                        href: '/pricing',
                        term: 'See the plans',
                    ),
                ]),

                new ContentBlock(BlockType::Related, 'Related pages', [
                    new BlockEntry(text: 'Menu management', pageKey: 'urun.menu-yonetimi'),
                    new BlockEntry(text: 'Languages and currency', pageKey: 'urun.coklu-dil-ve-para-birimi'),
                    new BlockEntry(text: 'Multiple branches', pageKey: 'urun.coklu-sube'),
                ]),
            ],
        );
    }
}
