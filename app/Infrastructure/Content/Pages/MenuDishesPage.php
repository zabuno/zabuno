<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;

/**
 * `/urun/menu-yonetimi/urunler/` — yemekler (P0).
 *
 * Sayfanın sorusu "bir yemek NE TAŞIR": ad, açıklama, bir fotoğraf, bir
 * fiyat, beyan edilmiş alerjenler; ve anlatacak şeyi olduğunda kendi
 * sayfası. Ebeveyn bunu yayın akışı içinde tek satırda geçiyor; burası
 * yemeğin kendisi.
 *
 * BİLEREK YAZILMAYANLAR: boyut/porsiyon, ekstra ve seçenek, kalori ve besin
 * değeri, diyet etiketi (vegan, glutensiz), galeri, çeviri, yemek başına
 * karekod. Ölçüm 2026-09-06: `products` yalnız `name` ve `description`
 * taşıyor, `TaxonomyTerm` yalnız `allergen` türünü kabul ediyor, bir menü
 * satırına tek görsel bağlanıyor.
 */
final class MenuDishesPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'urun.menu-yonetimi.urunler',
            locale: 'en',
            metadata: new PageMetadata(
                seoTitle: 'Dishes on the menu',
                metaDescription: 'What a dish carries: a name, a description, a photo, a price and declared allergens, on the menu and on its own page. And what it cannot carry today.',
                h1: 'Dishes',
                breadcrumbTitle: 'Dishes',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'A dish is one item on your menu with a name, a description, one photo, one price in your currency and the allergens you declare. It can be hidden without being deleted, marked sold out for the day, and, when it has something to show, it gets a page of its own that a guest can share.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'A line on a card, and a question for the waiter', [
                    new BlockEntry(
                        text: '"Adana kebab" and a number is a line. It does not say what is in it, how it looks, or whether the pistachio in the sauce matters to the guest at table six. The waiter answers the same three questions all evening.',
                    ),
                    new BlockEntry(
                        text: 'On paper, adding those answers costs space. On a phone, not adding them costs the sale.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'A card for every dish, written once', [
                    new BlockEntry(
                        text: 'Each dish carries what a guest asks about: a description, a photograph and the allergens you declare. A dish with something to show gets its own address, so a guest can send it to a friend and the friend lands on that dish.',
                    ),
                    new BlockEntry(
                        text: 'The dish is written once. List it in two sections and the description and allergens are shared, so a correction is made in one place.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'How it works', [
                    new BlockEntry(
                        term: 'Add a dish in one step',
                        text: 'Name, price, currency and declared allergens are written together in one transaction. A half-written dish never exists.',
                        source: 'app/Http/Controllers/MenuCatalog/StoreMenuEntryController.php',
                    ),
                    new BlockEntry(
                        term: 'Describe it',
                        text: 'The description is free text on the dish itself. Edit it by hand, or ask for a first draft and apply it if you like it.',
                        source: 'app/Http/Controllers/MenuCatalog/RenameMenuItemController.php',
                    ),
                    new BlockEntry(
                        term: 'Attach a photo',
                        text: 'Pick a photo from the media library. The dish is bound to that photo at a specific version, so a later edit of the picture does not change a published menu.',
                        source: 'app/Http/Controllers/MenuCatalog/BindMenuItemImageController.php',
                    ),
                    new BlockEntry(
                        term: 'Declare the allergens',
                        text: 'Mark the allergens you declare. They are shown to the guest as your declaration, and changing them is recorded with a name and a time.',
                        source: 'app/Http/Controllers/MenuCatalog/UpdateMenuItemAllergensController.php',
                    ),
                    new BlockEntry(
                        term: 'Hide it, or take it off',
                        text: 'A seasonal dish is hidden and kept; a wrong one is deleted. A published version is untouched by either until you publish again.',
                        source: 'app/Http/Controllers/MenuCatalog/DeleteMenuItemController.php',
                    ),
                    new BlockEntry(
                        term: 'Publish',
                        text: 'A new dish reaches the guest with the next published version. Only the sold-out mark skips that step.',
                        source: 'app/Http/Controllers/Publication/StorePublicationController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'What a dish can do', [
                    new BlockEntry(
                        term: 'Its own page, when it has earned one',
                        text: 'A dish with a description, a photo or declared allergens gets a shareable address showing all of them. A dish with only a name and a price does not, so search engines are not fed hundreds of copies of a line.',
                        source: 'app/Http/Controllers/QrDestination/ShowPublicMenuItemController.php',
                    ),
                    new BlockEntry(
                        term: 'Visible on the day it is added',
                        text: 'A new dish is visible by default. There is no switch to find before the first publish.',
                        source: 'database/migrations/2026_08_28_000300_menu_items_default_to_visible.php',
                    ),
                    new BlockEntry(
                        term: 'Sold out for today, not forever',
                        text: 'A dish marked sold out is shown as sold out until the end of the branch\'s day, then comes back on its own.',
                        source: 'app/Domain/MenuCatalog/StockState.php',
                    ),
                    new BlockEntry(
                        term: 'A first draft of the description',
                        text: 'Ask for a description draft, read it, and apply it or discard it. Nothing is written to the dish without your approval.',
                        source: 'app/Http/Controllers/Ai/ApplyProductDescriptionDraftController.php',
                    ),
                    new BlockEntry(
                        term: 'The same dish typed twice is found',
                        text: 'Zabuno can point out dishes whose names look like duplicates. It points; it does not merge.',
                        source: 'app/Http/Controllers/Ai/ShowDuplicateProductCandidatesController.php',
                    ),
                    new BlockEntry(
                        term: 'Changes are recorded',
                        text: 'Adding, renaming, repricing, hiding and deleting a dish, and changing its allergens, go to the audit trail with who and when.',
                        source: 'app/Domain/MenuCatalog/MenuAuditAction.php',
                    ),
                    new BlockEntry(
                        term: 'A photo sized for the screen',
                        text: 'The guest page asks for the copy that fits the screen, not the original your phone took.',
                        source: 'resources/views/public-menu-item.blade.php',
                    ),
                    new BlockEntry(
                        term: 'Searchable and filterable by the guest',
                        text: 'On the menu page a guest can search dishes by name and narrow the list by declared allergen or price range, when scripts run.',
                        source: 'resources/views/public-menu.blade.php',
                    ),
                    new BlockEntry(
                        term: 'In and out as a spreadsheet',
                        text: 'Every dish exports as a row with category, name, price, currency, allergens, description and visibility, and imports the same way.',
                        source: 'app/Application/MenuCatalog/Csv/MenuCsv.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'What you need', [
                    new BlockEntry(
                        term: 'A name and a price',
                        text: 'A visible dish needs a non-empty name and a price above zero before the menu can be published.',
                        source: 'app/Application/Publication/UseCase/BuildPublicationSnapshot.php',
                    ),
                    new BlockEntry(
                        term: 'A price in the brand\'s currency',
                        text: 'The price is typed as a decimal in the currency of your business, and the decimals must match that currency.',
                        source: 'app/Domain/Money/Money.php',
                    ),
                    new BlockEntry(
                        term: 'Permission to manage the menu',
                        text: 'Owners, managers and editors add and edit dishes. The kitchen role only marks allergens and sold-out state.',
                        source: 'app/Domain/Authorization/RolePermissions.php',
                    ),
                    new BlockEntry(
                        term: 'No plan',
                        text: 'Everything on this page is on the free plan.',
                        source: 'database/seeders/PlanCatalogueSeeder.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'What a dish cannot carry', [
                    new BlockEntry(
                        term: 'One price, no sizes or portions',
                        text: 'Small and large are two dishes today, not one dish with two prices.',
                        source: 'database/migrations/2026_08_20_000002_create_menu_catalog_tables.php',
                    ),
                    new BlockEntry(
                        term: 'No extras or choices',
                        text: 'Extra cheese, a sauce choice and a cooking preference are not part of a dish.',
                        source: 'database/migrations/2026_08_20_000002_create_menu_catalog_tables.php',
                    ),
                    new BlockEntry(
                        term: 'No calories, no nutrition, no diet labels',
                        text: 'Only the allergens you declare are stored. There is no vegan, vegetarian or gluten-free tag and no energy figure.',
                        source: 'app/Domain/MenuCatalog/Product.php',
                    ),
                    new BlockEntry(
                        term: 'One photo',
                        text: 'A dish carries one photo, not a gallery.',
                        source: 'app/Application/Media/Port/MenuMediaPort.php',
                    ),
                    new BlockEntry(
                        term: 'Names and descriptions are not translated',
                        text: 'A dish is written in one language. Switching the guest interface language does not translate it.',
                        source: 'database/migrations/2026_08_28_000400_add_description_to_products.php',
                    ),
                    new BlockEntry(
                        term: 'A declaration, not a guarantee',
                        text: 'The page states which allergens you declared. It never claims a dish is free of anything.',
                        source: 'resources/views/public-menu-item.blade.php',
                    ),
                    new BlockEntry(
                        term: 'No per-dish code',
                        text: 'A dish has a web address, but not its own printable QR code.',
                        source: 'routes/api/qr-destination.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'Questions owners ask', [
                    new BlockEntry(
                        term: 'Can I write a description and add a photo to a dish?',
                        text: 'Yes. Both live on the dish and appear on the menu and on the dish\'s own page.',
                    ),
                    new BlockEntry(
                        term: 'Why does one dish have its own page and another not?',
                        text: 'A page is made only for a dish with a description, a photo or declared allergens. A name and a price alone would be a copy of the menu line.',
                    ),
                    new BlockEntry(
                        term: 'Can I offer a small and a large size?',
                        text: 'Not on one dish. Add two dishes today.',
                    ),
                    new BlockEntry(
                        term: 'Does Zabuno write my descriptions?',
                        text: 'It can offer a first draft. You read it and apply it, or not; nothing is written without you.',
                    ),
                    new BlockEntry(
                        term: 'Can I mark a dish vegan or gluten-free?',
                        text: 'No. Only declared allergens are stored today. You can say it in the description.',
                    ),
                    new BlockEntry(
                        term: 'I hid a dish. Is it gone from the menu on the tables?',
                        text: 'Not until you publish. The published version stays as it was.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'Dishes cost nothing to add', [
                    new BlockEntry(
                        text: 'Adding, describing and photographing a dish are on the free plan.',
                        href: '/pricing',
                        term: 'See the plans',
                    ),
                ]),

                new ContentBlock(BlockType::Related, 'Related pages', [
                    new BlockEntry(text: 'Menu management', pageKey: 'urun.menu-yonetimi'),
                    new BlockEntry(text: 'Categories', pageKey: 'urun.menu-yonetimi.kategoriler'),
                    new BlockEntry(text: 'Images and media', pageKey: 'urun.gorsel-ve-medya'),
                    new BlockEntry(text: 'Zabuno AI', pageKey: 'urun.zabuno-ai'),
                ]),
            ],
        );
    }
}
