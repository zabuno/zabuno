<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;

/**
 * `/urun/qr-menu/` — QR menü sayfası (P0).
 *
 * Her satır depoda bir kanıt taşır. Ölçüm 2026-09-05'te yapıldı; iddia
 * ederken tahmin edilmedi, dosya açıldı.
 *
 * BİLEREK YAZILMAYANLAR (ürün bunları bugün yapmıyor): porsiyon/varyant,
 * ekstra ve seçenek grupları, besin değeri/kalori, KDV satırı, misafirin
 * menüden ödeme yapması, menü İÇERİĞİNİN çevrilmesi. Bunları "yakında" diye
 * yazmak da yasak (yönerge §1 madde 18): okuyan kişi onu bugün var sanır ve
 * satın alma kararını onun üzerine kurar.
 */
final class QrMenuPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'urun.qr-menu',
            locale: 'en',
            metadata: new PageMetadata(
                seoTitle: 'QR menu for restaurants',
                metaDescription: 'Guests scan the code on the table and read your menu in the browser. No app, no download, and the page still works when scripts do not.',
                h1: 'QR menu',
                breadcrumbTitle: 'QR menu',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'A Zabuno QR menu is a web page your guests open by scanning the code on their table. It opens in the browser they already have, needs no app and no download, and shows the photo, description, price and declared allergens of every dish you publish.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'The problem with a paper menu', [
                    new BlockEntry(
                        text: 'A printed menu freezes the day it goes to the printer. The kebab that ran out at seven is still on the card at nine, last month\'s price is still the price a guest reads, and the new dessert is a handwritten line on the back page.',
                    ),
                    new BlockEntry(
                        text: 'Every correction is a reprint, and between two reprints the menu quietly lies to the guest. The waiter absorbs the difference, one table at a time.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'What Zabuno changes', [
                    new BlockEntry(
                        text: 'The printed card stops being the menu and becomes a doorway. What the guest reads lives on your side and changes when you change it, so a sold-out dish is marked sold out on the next scan instead of on the next print run.',
                    ),
                    new BlockEntry(
                        text: 'The address behind the code never changes, so the cards you already printed keep working after you rename a dish, raise a price, or point that table at a different menu.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'How it works', [
                    new BlockEntry(
                        term: 'Build the menu',
                        text: 'Create categories and dishes, set prices, attach photos and mark declared allergens.',
                        source: 'app/Http/Controllers/MenuCatalog/StoreMenuEntryController.php',
                    ),
                    new BlockEntry(
                        term: 'Publish a version',
                        text: 'Publishing takes a numbered snapshot. Guests always read a published version, never your working draft.',
                        source: 'database/migrations/2026_08_22_000004_create_menu_publications_table.php',
                    ),
                    new BlockEntry(
                        term: 'Print the code',
                        text: 'Each code carries a 43-character token that cannot be guessed, so a code is not found by typing a neighbouring address.',
                        source: 'app/Domain/QrDestination/QrToken.php',
                    ),
                    new BlockEntry(
                        term: 'The guest scans',
                        text: 'The scan resolves to the menu page and the server renders the HTML before it is sent. There is nothing to install and nothing to sign up for.',
                        source: 'app/Http/Controllers/QrDestination/RedirectQrTokenController.php',
                    ),
                    new BlockEntry(
                        term: 'Change without reprinting',
                        text: 'Mark a dish sold out, publish a new version, or point the same code at another menu. The paper on the table stays the same paper.',
                        source: 'app/Http/Controllers/QrDestination/RetargetQrCodeController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'What the guest page does', [
                    new BlockEntry(
                        term: 'Readable without JavaScript',
                        text: 'Categories are ordinary anchors and dishes are ordinary links, so browsing, prices, allergens and sold-out marks survive a browser that runs no scripts.',
                        source: 'resources/views/public-menu.blade.php',
                    ),
                    new BlockEntry(
                        term: 'A dish has its own page',
                        text: 'Photo, description, price, declared allergens and sold-out state, at an address a guest can send to a friend.',
                        source: 'resources/views/public-menu-item.blade.php',
                    ),
                    new BlockEntry(
                        term: 'A permanent, sayable address',
                        text: 'Besides the scan, every menu has a lasting address that reads like a name rather than a code, so it can go on a card, a window or a social profile.',
                        source: 'routes/web.php',
                    ),
                    new BlockEntry(
                        term: 'Sold out says sold out',
                        text: 'Out-of-stock and allergen states are written in words, not signalled by colour alone, so they still reach a guest who cannot tell the colours apart.',
                        source: 'resources/views/public-menu-item.blade.php',
                    ),
                    new BlockEntry(
                        term: 'Prices in your currency, correctly',
                        text: 'The decimal places follow the currency itself, and a price that cannot be resolved is hidden rather than shown wrong.',
                        source: 'app/Support/Money/PriceLabel.php',
                    ),
                    new BlockEntry(
                        term: 'Built for a thumb',
                        text: 'Tap targets stay at least 44 pixels, focus is visible for keyboard users, and motion is dropped when the phone asks for less of it.',
                        source: 'resources/views/partials/guest-surface-style.blade.php',
                    ),
                    new BlockEntry(
                        term: 'Interface language, guest\'s choice',
                        text: 'The surrounding interface can be switched between Turkish and English with a plain link that works without scripts.',
                        source: 'app/Support/Localization/GuestLocale.php',
                    ),
                    new BlockEntry(
                        term: 'Only dishes worth a page get one',
                        text: 'A dish with no description, no photo and no allergen information is left out of search results and is not linked from the menu, because a link that leads nowhere is a lie.',
                        source: 'app/Http/Controllers/QrDestination/ShowPublicMenuItemController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'What you need', [
                    new BlockEntry(
                        term: 'On the guest side',
                        text: 'A phone with a camera and a browser. No app, no account, no download.',
                        source: 'resources/views/public-menu.blade.php',
                    ),
                    new BlockEntry(
                        term: 'On your side',
                        text: 'A Zabuno account, one published menu and one printed code. Registering, building a menu, publishing it and printing a code work on the free plan.',
                        source: 'database/seeders/PlanCatalogueSeeder.php',
                    ),
                    new BlockEntry(
                        term: 'For generating codes table by table',
                        text: 'Producing codes for a whole floor in one pass belongs to a paid plan; producing them one at a time does not.',
                        source: 'app/Http/Controllers/QrDestination/StoreBulkQrCodesController.php',
                    ),
                    new BlockEntry(
                        term: 'For your own colours on the guest page',
                        text: 'Carrying your brand colour into the guest menu belongs to a paid plan. Without it the menu is shown in the neutral default.',
                        source: 'app/Domain/Entitlement/Entitlement.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'What it does not do', [
                    new BlockEntry(
                        term: 'No sizes, portions or extras',
                        text: 'A dish carries one price. Half portions, size options and paid extras are not part of the menu today.',
                        source: 'database/migrations/2026_08_20_000002_create_menu_catalog_tables.php',
                    ),
                    new BlockEntry(
                        term: 'No calories or nutrition values',
                        text: 'Allergens are shown because you declare them. Energy and nutrition figures are not stored and are not displayed.',
                        source: 'database/migrations/2026_08_28_000400_add_description_to_products.php',
                    ),
                    new BlockEntry(
                        term: 'Allergens are declared, not certified',
                        text: 'The page states which allergens you declared. It never claims a dish is free of anything, because a kitchen is not a laboratory.',
                        source: 'resources/views/public-menu-item.blade.php',
                    ),
                    new BlockEntry(
                        term: 'Dish names are not translated',
                        text: 'Switching the interface language does not translate your menu. Dish names and descriptions stay in the language you wrote them in, and the page tells the guest so.',
                        source: 'resources/views/public-menu.blade.php',
                    ),
                    new BlockEntry(
                        term: 'Guests do not pay here',
                        text: 'There is no payment step on the guest menu. Nobody enters card details on this page.',
                        source: 'routes/web.php',
                    ),
                    new BlockEntry(
                        term: 'Offline replay is limited',
                        text: 'A menu opened from a scanned code can be shown again on a weak connection. The shareable permanent address has no offline behaviour.',
                        source: 'public/public-diner-sw.js',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'Questions guests and owners ask', [
                    new BlockEntry(
                        term: 'Does the guest need to install an app?',
                        text: 'No. The code opens a web page in the browser that is already on the phone. There is no download and no account.',
                    ),
                    new BlockEntry(
                        term: 'Do I have to reprint the codes when I change the menu?',
                        text: 'No. The printed code points at an address that does not change. Edit a dish, publish a new version, or point the code at a different menu, and the same card keeps working.',
                    ),
                    new BlockEntry(
                        term: 'What does a guest see when a dish has run out?',
                        text: 'The dish is marked sold out in words on the menu and on its own page, from the moment you mark it. You do not have to publish a new version for that.',
                    ),
                    new BlockEntry(
                        term: 'Can a guest send one dish to a friend?',
                        text: 'Yes. A dish that has something to show has its own address, so the link opens on that dish rather than at the top of the menu.',
                    ),
                    new BlockEntry(
                        term: 'Is the menu readable if scripts are blocked?',
                        text: 'Yes. Categories, dishes, prices, allergens and sold-out marks are rendered by the server. Search and filters need scripts and are simply not shown without them.',
                    ),
                    new BlockEntry(
                        term: 'Does it cost anything to start?',
                        text: 'Registering, building a menu, publishing it, printing a code and serving it to guests are on the free plan. Paid plans add capability on top; they do not switch the basics on.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'See what a plan includes', [
                    new BlockEntry(
                        text: 'Plans, what each one adds and what the free one already covers.',
                        href: '/pricing',
                        term: 'Compare plans',
                    ),
                ]),

                new ContentBlock(BlockType::Related, 'Related pages', [
                    new BlockEntry(text: 'Menu management', pageKey: 'urun.menu-yonetimi'),
                    new BlockEntry(text: 'Tables and QR codes', pageKey: 'urun.masa-ve-qr-yonetimi'),
                ]),
            ],
        );
    }
}
