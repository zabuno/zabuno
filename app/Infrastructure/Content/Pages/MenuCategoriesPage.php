<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;

/**
 * `/urun/menu-yonetimi/kategoriler/` — menü kategorileri (P0). İlk üç
 * kademeli sayfa.
 *
 * Ebeveyn (`MenuManagementPage`) kategorilere tek adımda değiniyor:
 * "oluştur, yeniden adlandır, sil, sırala". Bu sayfanın ayrı var olma
 * sebebi, ebeveynin geçtiği şeyin kendi sorularının olması: kategori
 * silinince içindeki yemeklere ne olur (silinir — `cascade`), misafir
 * sırayı nasıl görür (başlık + çıpa), aynı yemek iki bölümde durabilir mi
 * (evet). Aynı soru iki sayfada sorulmaz; `ProductPageLibraryTest` ölçüyor.
 *
 * BİLEREK YAZILMAYANLAR: alt kategori, gizli kategori, kategori görseli ya
 * da açıklaması, saate bağlı bölüm, çevrilmiş başlık. Ölçüm 2026-09-06:
 * `menu_categories` tablosu yalnız `menu_id`, `name`, `position` taşıyor;
 * kategori için görünürlük ucu yok.
 */
final class MenuCategoriesPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'urun.menu-yonetimi.kategoriler',
            locale: 'en',
            metadata: new PageMetadata(
                seoTitle: 'Menu categories',
                metaDescription: 'The sections of your menu the guest jumps between. Create, rename, reorder and remove them, and know that removing one takes its dishes with it.',
                h1: 'Categories',
                breadcrumbTitle: 'Categories',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'A category is a section of your menu: Starters, Grills, Desserts. You create, rename, reorder and remove categories in the draft, and the order you set is the order the guest reads and jumps between at the top of the menu page.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'Forty dishes in one long list', [
                    new BlockEntry(
                        text: 'A menu without sections is a scroll. The guest looking for dessert passes every grill on the way, and the one looking for a soup gives up before the soup.',
                    ),
                    new BlockEntry(
                        text: 'Paper solves this with headings, and then freezes them: a seasonal section printed in June is still on the card in October.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'Sections that move with the season', [
                    new BlockEntry(
                        text: 'Categories are the headings of your menu and they are yours to change between publications. Add a summer section in May, move it to the top, and remove it in September; the dishes inside it go with it.',
                    ),
                    new BlockEntry(
                        text: 'The guest gets the same headings as jump links at the top of the page, so a phone screen behaves like a table of contents instead of a scroll.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'How it works', [
                    new BlockEntry(
                        term: 'Create a category',
                        text: 'Give it a name. It joins the end of the menu and can be moved.',
                        source: 'app/Http/Controllers/MenuCatalog/StoreCategoryController.php',
                    ),
                    new BlockEntry(
                        term: 'Put dishes in it',
                        text: 'A dish is added to a category in one step: name, price, currency and declared allergens together.',
                        source: 'app/Http/Controllers/MenuCatalog/StoreMenuEntryController.php',
                    ),
                    new BlockEntry(
                        term: 'Set the order',
                        text: 'Move categories into the order you want. The position is stored per menu, and no two categories can hold the same slot.',
                        source: 'app/Http/Controllers/MenuCatalog/ReorderCategoriesController.php',
                    ),
                    new BlockEntry(
                        term: 'Rename without losing anything',
                        text: 'Renaming a category keeps its dishes and its position.',
                        source: 'app/Http/Controllers/MenuCatalog/RenameCategoryController.php',
                    ),
                    new BlockEntry(
                        term: 'Remove a section',
                        text: 'Deleting a category deletes every dish row inside it. The dishes are not moved elsewhere, so move them first if you want to keep them.',
                        source: 'app/Http/Controllers/MenuCatalog/DeleteCategoryController.php',
                    ),
                    new BlockEntry(
                        term: 'Publish',
                        text: 'Guests see the new headings and order with the next published version, not while you are still arranging them.',
                        source: 'app/Http/Controllers/Publication/StorePublicationController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'What it does', [
                    new BlockEntry(
                        term: 'Jump links for the guest',
                        text: 'Every category becomes a link at the top of the guest menu that scrolls to its section. The links are plain anchors and work without scripts.',
                        source: 'resources/views/public-menu.blade.php',
                    ),
                    new BlockEntry(
                        term: 'One order, kept exactly',
                        text: 'Category positions are unique within a menu, so the order you saved is the order that is published; there is no tie to resolve.',
                        source: 'database/migrations/2026_08_20_000002_create_menu_catalog_tables.php',
                    ),
                    new BlockEntry(
                        term: 'The same dish in two sections',
                        text: 'A dish that belongs in Breakfast and in All Day can be listed in both. Its name, description and allergens are written once and shared.',
                        source: 'app/Http/Controllers/MenuCatalog/StoreMenuItemController.php',
                    ),
                    new BlockEntry(
                        term: 'An empty section says so',
                        text: 'A category with no visible dish shows a short note to the guest instead of a blank heading.',
                        source: 'resources/views/public-menu.blade.php',
                    ),
                    new BlockEntry(
                        term: 'Categories in the spreadsheet',
                        text: 'The export writes the category name on every dish row, and the import rebuilds the sections from that column.',
                        source: 'app/Application/MenuCatalog/Csv/MenuCsv.php',
                    ),
                    new BlockEntry(
                        term: 'A record of adding, renaming and removing',
                        text: 'Creating, renaming and removing a category is written to the menu audit trail with who did it and when.',
                        source: 'app/Domain/MenuCatalog/MenuAuditAction.php',
                    ),
                    new BlockEntry(
                        term: 'Sections read from a photo',
                        text: 'When a menu is read from a photograph, the draft you approve arrives with its sections already grouped.',
                        source: 'app/Application/Ai/UseCase/ApplyMenuArtifact.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'What you need', [
                    new BlockEntry(
                        term: 'At least one category to publish',
                        text: 'A menu cannot be published with no category, and publishing refuses a category with a blank name.',
                        source: 'app/Application/Publication/UseCase/BuildPublicationSnapshot.php',
                    ),
                    new BlockEntry(
                        term: 'Permission to manage the menu',
                        text: 'Owners, managers and editors arrange categories. The kitchen role cannot.',
                        source: 'app/Domain/Authorization/RolePermissions.php',
                    ),
                    new BlockEntry(
                        term: 'Nothing else, and no plan',
                        text: 'Categories are part of the free journey.',
                        source: 'database/seeders/PlanCatalogueSeeder.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'What it does not do', [
                    new BlockEntry(
                        term: 'No sub-categories',
                        text: 'A category holds dishes, not other categories. Grills cannot contain Lamb and Chicken as nested sections.',
                        source: 'database/migrations/2026_08_20_000002_create_menu_catalog_tables.php',
                    ),
                    new BlockEntry(
                        term: 'No hidden category',
                        text: 'A dish can be hidden; a category cannot. To keep a section off the menu, hide its dishes or remove the section.',
                        source: 'routes/api/menu-catalog.php',
                    ),
                    new BlockEntry(
                        term: 'No picture or description on a category',
                        text: 'A category is a name. It has no photo and no text of its own.',
                        source: 'database/migrations/2026_08_20_000002_create_menu_catalog_tables.php',
                    ),
                    new BlockEntry(
                        term: 'No timed sections',
                        text: 'A category cannot switch itself on at breakfast time. That job belongs to menus: a branch can hold several menus that hand over by the hour.',
                        source: 'app/Http/Controllers/MenuCatalog/UpdateMenuServiceWindowController.php',
                    ),
                    new BlockEntry(
                        term: 'Order changes are not recorded',
                        text: 'Moving a category up or down is not written to the audit trail. It changes dozens of times while a menu is arranged, and nobody asks who did it.',
                        source: 'app/Domain/MenuCatalog/MenuAuditAction.php',
                    ),
                    new BlockEntry(
                        term: 'Category names are not translated',
                        text: 'A heading is written once, in your language, and the guest sees it as written.',
                        source: 'database/migrations/2026_08_20_000002_create_menu_catalog_tables.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'Questions owners ask', [
                    new BlockEntry(
                        term: 'What happens to the dishes when I delete a category?',
                        text: 'They are deleted with it. Move them to another category first if you want to keep them.',
                    ),
                    new BlockEntry(
                        term: 'Can I hide a whole section for the winter?',
                        text: 'Not as a section. Hide the dishes inside it, or remove the section and bring it back from a spreadsheet later.',
                    ),
                    new BlockEntry(
                        term: 'Can one dish be in two categories?',
                        text: 'Yes. It is listed in both and edited once.',
                    ),
                    new BlockEntry(
                        term: 'Does the guest see the categories in my order?',
                        text: 'Yes, both as headings and as jump links at the top of the page.',
                    ),
                    new BlockEntry(
                        term: 'Can a category have its own photo?',
                        text: 'No. Photos belong to dishes.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'Sections cost nothing', [
                    new BlockEntry(
                        text: 'Categories, dishes and publishing are part of the free plan.',
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
