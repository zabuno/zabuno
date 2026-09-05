<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;

/**
 * `/urun/menu-yonetimi/` — menü yönetimi sayfası (P0).
 *
 * BİLEREK YAZILMAYANLAR: varyant/porsiyon, ekstra ve seçenek grupları, menü
 * kopyalama, toplu fiyat/görünürlük/silme işlemleri. Ölçüm 2026-09-05: bu
 * dördü için depoda ne tablo, ne sütun, ne uç var.
 */
final class MenuManagementPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'urun.menu-yonetimi',
            locale: 'en',
            metadata: new PageMetadata(
                seoTitle: 'Menu management',
                metaDescription: 'Build categories and dishes, set prices, mark a dish sold out and publish a numbered version your guests can read a second later.',
                h1: 'Menu management',
                breadcrumbTitle: 'Menu management',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'Menu management is where you build the menu your guests read: categories, dishes, prices, photos and declared allergens. Nothing you type reaches a guest until you publish, and every publish is a numbered version you can go back to.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'Editing is not the hard part', [
                    new BlockEntry(
                        text: 'Changing a price is easy anywhere. The hard part is changing it without breaking the menu a guest is reading at that exact moment, and being able to say tomorrow who changed it and what it was before.',
                    ),
                    new BlockEntry(
                        text: 'Most kitchens solve this by not editing at all. The menu drifts, and the correction is made out loud by whoever is carrying the plates.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'Draft and published are two different things', [
                    new BlockEntry(
                        text: 'You work on a draft. Guests read a published version. Between the two there is a deliberate step, so a half-finished breakfast menu is never on a table.',
                    ),
                    new BlockEntry(
                        text: 'One state deliberately skips that step: marking a dish sold out takes effect immediately, because a dish that ran out has already run out and waiting for a publish would be waiting for nothing.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'How it works', [
                    new BlockEntry(
                        term: 'Categories first',
                        text: 'Create, rename, delete and reorder categories. The order you set is the order the guest reads.',
                        source: 'app/Http/Controllers/MenuCatalog/ReorderCategoriesController.php',
                    ),
                    new BlockEntry(
                        term: 'Then the dishes',
                        text: 'A dish, its menu row and its allergens are written in one transaction, so a half-written dish never appears.',
                        source: 'app/Http/Controllers/MenuCatalog/StoreMenuEntryController.php',
                    ),
                    new BlockEntry(
                        term: 'Check it on a phone first',
                        text: 'A signed preview link shows the draft on a real phone and expires by itself, so a draft cannot leak by being forwarded.',
                        source: 'app/Http/Controllers/Publication/CreateDraftPreviewLinkController.php',
                    ),
                    new BlockEntry(
                        term: 'Publish a numbered version',
                        text: 'Publishing freezes a snapshot and gives it a version number. Guests read that snapshot.',
                        source: 'database/migrations/2026_08_22_000004_create_menu_publications_table.php',
                    ),
                    new BlockEntry(
                        term: 'Go back if it was wrong',
                        text: 'Any earlier published version can be restored, so a bad publish is a minute of trouble instead of an evening of it.',
                        source: 'app/Http/Controllers/Publication/RestorePublicationController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'What you can do', [
                    new BlockEntry(
                        term: 'Prices',
                        text: 'Set a dish price in your currency, stored as a whole number of minor units so rounding does not drift.',
                        source: 'app/Http/Controllers/MenuCatalog/UpdateMenuItemPriceController.php',
                    ),
                    new BlockEntry(
                        term: 'Photos',
                        text: 'Attach a photo from the media library; the guest page serves the right size for the screen that asked for it.',
                        source: 'app/Http/Controllers/MenuCatalog/BindMenuItemImageController.php',
                    ),
                    new BlockEntry(
                        term: 'Declared allergens',
                        text: 'Mark the allergens you declare for a dish. They are shown to the guest as declarations, never as a guarantee.',
                        source: 'app/Http/Controllers/MenuCatalog/UpdateMenuItemAllergensController.php',
                    ),
                    new BlockEntry(
                        term: 'Sold out, immediately',
                        text: 'Mark one dish, or a whole list of dishes in one request, as sold out or back in stock. Guests see it on the next scan without a publish.',
                        source: 'app/Http/Controllers/MenuCatalog/UpdateMenuStockController.php',
                    ),
                    new BlockEntry(
                        term: 'Hide without deleting',
                        text: 'A dish can be taken off the published menu and kept in the catalogue, so a seasonal dish does not have to be typed again.',
                        source: 'app/Http/Controllers/MenuCatalog/UpdateMenuItemVisibilityController.php',
                    ),
                    new BlockEntry(
                        term: 'Several menus, one place',
                        text: 'A location can hold more than one menu, and a menu can hand over to another at a set time of day, so breakfast and dinner live behind the same printed code.',
                        source: 'database/migrations/2026_09_05_000400_allow_many_menus_per_location.php',
                    ),
                    new BlockEntry(
                        term: 'Publish at a chosen time',
                        text: 'A publish can be scheduled. It is carried out by the server on the minute, whether or not anyone is logged in.',
                        source: 'app/Console/Commands/PublishScheduledMenusCommand.php',
                    ),
                    new BlockEntry(
                        term: 'Import and export as CSV',
                        text: 'A whole menu can be brought in from a spreadsheet and taken back out as one, so the menu is never locked inside the product.',
                        source: 'app/Http/Controllers/MenuCatalog/ImportMenuCsvController.php',
                    ),
                    new BlockEntry(
                        term: 'Who changed the price',
                        text: 'Menu changes are written to an audit trail, so "why is the kebab 20 lira more" has an answer with a name and a time on it.',
                        source: 'database/migrations/2026_09_06_000200_create_menu_audits_table.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'What you need', [
                    new BlockEntry(
                        term: 'An account and a location',
                        text: 'One workspace and one location are enough to build and publish a menu.',
                        source: 'app/Http/Controllers/MenuCatalog/StoreMenuController.php',
                    ),
                    new BlockEntry(
                        term: 'A plan',
                        text: 'Building, editing, publishing and restoring a menu are on the free plan. No part of this page is behind a paid plan.',
                        source: 'database/seeders/PlanCatalogueSeeder.php',
                    ),
                    new BlockEntry(
                        term: 'One currency per menu',
                        text: 'A menu is priced in the currency of its brand. Mixed currencies inside one menu are not supported.',
                        source: 'app/Domain/Money/MoneyFormatter.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'What it does not do', [
                    new BlockEntry(
                        term: 'No sizes or portions',
                        text: 'A dish has a single price. Small and large, or half and full, cannot be expressed as one dish today.',
                        source: 'database/migrations/2026_08_20_000002_create_menu_catalog_tables.php',
                    ),
                    new BlockEntry(
                        term: 'No extras or option groups',
                        text: 'Paid additions such as extra cheese, or choices such as a sauce, are not part of a dish.',
                        source: 'database/migrations/2026_08_20_000002_create_menu_catalog_tables.php',
                    ),
                    new BlockEntry(
                        term: 'No menu duplication',
                        text: 'A menu cannot be copied into a second one. A second menu is built, or brought in from a spreadsheet.',
                        source: 'routes/api/menu-catalog.php',
                    ),
                    new BlockEntry(
                        term: 'Bulk work is limited to stock',
                        text: 'Sold-out marks can be changed for many dishes at once. Prices, visibility and deletion are changed one dish at a time.',
                        source: 'app/Http/Controllers/MenuCatalog/UpdateMenuStockController.php',
                    ),
                    new BlockEntry(
                        term: 'No tax lines',
                        text: 'A price is shown as the price. There is no separate tax field and the guest page makes no statement about tax.',
                        source: 'app/Support/Money/PriceLabel.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'Questions owners ask', [
                    new BlockEntry(
                        term: 'Does my edit reach the guest straight away?',
                        text: 'Only if it is a sold-out mark. Everything else waits for you to publish, so guests never read a half-finished menu.',
                    ),
                    new BlockEntry(
                        term: 'Can I undo a bad publish?',
                        text: 'Yes. Every publish is a numbered version and an earlier one can be restored.',
                    ),
                    new BlockEntry(
                        term: 'Can I set the new prices tonight and have them start tomorrow?',
                        text: 'Yes. Schedule the publish and the server carries it out at that time on its own.',
                    ),
                    new BlockEntry(
                        term: 'Can breakfast and dinner share one printed code?',
                        text: 'Yes. A location can hold several menus and one can hand over to another at a set time of day.',
                    ),
                    new BlockEntry(
                        term: 'Can I get my menu out again?',
                        text: 'Yes. The whole menu exports to a spreadsheet file, and one can be imported the same way.',
                    ),
                    new BlockEntry(
                        term: 'Can I see who changed a price?',
                        text: 'Yes. Menu changes are recorded with who made them and when.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'Start with the free plan', [
                    new BlockEntry(
                        text: 'Building, publishing and restoring a menu are not behind a paid plan.',
                        href: '/pricing',
                        term: 'See the plans',
                    ),
                ]),

                new ContentBlock(BlockType::Related, 'Related pages', [
                    new BlockEntry(text: 'QR menu', pageKey: 'urun.qr-menu'),
                    new BlockEntry(text: 'Zabuno AI', pageKey: 'urun.zabuno-ai'),
                ]),
            ],
        );
    }
}
