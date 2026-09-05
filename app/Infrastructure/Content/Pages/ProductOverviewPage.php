<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;

/**
 * `/urun/` — ürün genel bakışı (P0). Sekiz ürün sayfasının kırıntı atası.
 *
 * Bu sayfa iki dalga boyunca BOŞ bir yuvaydı: içeriği yazılmış dokuz ürün
 * sayfasının her birinin kırıntısı ilk basamakta tıklanamayan bir "Ürün"
 * etiketine çarpıyordu ve hiçbir sayfa ötekine buradan ulaşamıyordu. Hub
 * olmanın ölçüsü `ProductPageLibraryTest`te: yazılmış her ürün sayfasına
 * buradan bir bağlantı çıkar.
 *
 * Sayfanın işi bir harita çizmek, ana sayfayı tekrar etmek değil: hangi
 * parça var, hangisi ayrı sayfada anlatılıyor ve ürün NE DEĞİL.
 *
 * BİLEREK YAZILMAYANLAR: kasa/POS, misafirden ödeme alma, rezervasyon, paket
 * servis, sadakat programı, üçüncü taraf entegrasyon, mağazada uygulama,
 * sektöre özel sürüm, birden çok marka. Ölçüm 2026-09-06: hiçbiri depoda yok.
 */
final class ProductOverviewPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'urun',
            locale: 'en',
            metadata: new PageMetadata(
                seoTitle: 'What the Zabuno product is made of',
                metaDescription: 'A menu guests open by scanning a code, the editor behind it, printable codes, photos, reports, ordering and a team in one account. What exists, and what does not.',
                h1: 'Product overview',
                breadcrumbTitle: 'Product overview',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'Zabuno is one web product for places that serve food and drink: you write a menu, publish it, print a code for each table, and guests read the menu in their own browser. Photos, reports, ordering from the table, branding and a team are parts of the same account, not separate products.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'Nine features on a poster, and no map', [
                    new BlockEntry(
                        text: 'A restaurant owner comparing menu software reads long lists of features and cannot tell which ones exist, which are switched on by a plan, and which are a sentence about the future. The list is the same length whether the product is finished or not.',
                    ),
                    new BlockEntry(
                        text: 'The cost of guessing wrong is paid at the table: a feature the owner assumed was there is discovered to be missing during service, when there is no time to change anything.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'One product, described part by part', [
                    new BlockEntry(
                        text: 'This page is the map. Each part of Zabuno has its own page, and each of those pages says what the part does today, what it needs and what it does not do, with every claim tied to the product itself.',
                    ),
                    new BlockEntry(
                        text: 'The base journey is the same for everybody and costs nothing: an account, a business, a branch, a menu, a published version, a printed code. Plans add capability on top of that chain; they never switch the chain off.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'The journey, in order', [
                    new BlockEntry(
                        term: 'Create an account and a business',
                        text: 'Register with an email address, then describe your business with its name, language, currency and time zone.',
                        source: 'app/Http/Controllers/Tenancy/StoreBrandController.php',
                    ),
                    new BlockEntry(
                        term: 'Add a branch',
                        text: 'A branch has an address and its own clock. A single restaurant is a business with one branch; a chain adds more under the same account.',
                        source: 'app/Http/Controllers/Tenancy/StoreLocationController.php',
                    ),
                    new BlockEntry(
                        term: 'Write the menu',
                        text: 'Categories, dishes, prices, photos and declared allergens go into a draft that guests cannot see.',
                        source: 'app/Http/Controllers/MenuCatalog/StoreMenuEntryController.php',
                    ),
                    new BlockEntry(
                        term: 'Publish a version',
                        text: 'Publishing freezes a numbered snapshot. Guests always read a published version, and an earlier one can be restored.',
                        source: 'app/Http/Controllers/Publication/StorePublicationController.php',
                    ),
                    new BlockEntry(
                        term: 'Print a code for each table',
                        text: 'Generate a code per table, export it as a card, a poster or a sheet to cut up, and change what it points at later without reprinting.',
                        source: 'app/Http/Controllers/QrDestination/StoreQrCodeController.php',
                    ),
                    new BlockEntry(
                        term: 'Guests scan',
                        text: 'The code opens the menu in the browser already on the phone. No app, no account, and the page is readable without scripts.',
                        source: 'app/Http/Controllers/QrDestination/ShowPublicMenuController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'The parts, and where each one is explained', [
                    new BlockEntry(
                        term: 'QR menu',
                        text: 'The page guests read: photo, description, price, declared allergens and sold-out state of every dish, at a permanent address that fits on a card.',
                        source: 'resources/views/public-menu.blade.php',
                    ),
                    new BlockEntry(
                        term: 'Menu management',
                        text: 'Categories, dishes, prices and stock, edited in a draft and released as numbered versions, with a record of who changed what.',
                        source: 'app/Http/Controllers/MenuCatalog/UpdateMenuItemPriceController.php',
                    ),
                    new BlockEntry(
                        term: 'Tables and QR codes',
                        text: 'A code per table, read back by a decoder before it becomes a file, printed as vector cards, and pointed elsewhere without reprinting.',
                        source: 'app/Http/Controllers/QrDestination/ExportQrCardController.php',
                    ),
                    new BlockEntry(
                        term: 'Design and branding',
                        text: 'A logo, a primary and a secondary colour, frozen into each publication so a published menu never changes under a guest.',
                        source: 'app/Domain/Publication/MenuIdentity.php',
                    ),
                    new BlockEntry(
                        term: 'Images and media',
                        text: 'Photos are scanned, resized once and served small; the original is kept so the copies can be made again.',
                        source: 'app/Infrastructure/Media/Processing/GdMediaAssetProcessor.php',
                    ),
                    new BlockEntry(
                        term: 'Languages and currency',
                        text: 'The guest page is in Turkish or English by the guest\'s choice, dish names stay in yours, and prices follow the decimals of your currency.',
                        source: 'app/Support/Localization/GuestLocale.php',
                    ),
                    new BlockEntry(
                        term: 'Multiple branches',
                        text: 'Any number of branches under one business, each with its own menus, prices, codes and opening hours.',
                        source: 'database/migrations/2026_08_19_000001_create_brands_and_locations_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Analytics',
                        text: 'Scans, menu opens, dish views, searches that found nothing and orders sent, counted by the server and turned into a menu report.',
                        source: 'app/Domain/Analytics/AnalyticsEventType.php',
                    ),
                    new BlockEntry(
                        term: 'Zabuno AI',
                        text: 'A photo of a printed menu becomes a draft you approve, a dish gets a first draft of a description, and dishes typed twice are found.',
                        source: 'app/Http/Controllers/Ai/StoreMenuAiImportController.php',
                    ),
                    new BlockEntry(
                        term: 'Ordering from the table',
                        text: 'Where the owner switches it on, guests build a basket and send it to a kitchen screen; the table is read from the code, never typed.',
                        source: 'app/Http/Controllers/Ordering/StoreGuestOrderController.php',
                    ),
                    new BlockEntry(
                        term: 'A team with roles',
                        text: 'Editors, managers and a kitchen role that marks allergens and sold-out dishes and sees nothing else, invited by the owner.',
                        source: 'app/Domain/Tenancy/MembershipRole.php',
                    ),
                    new BlockEntry(
                        term: 'Ratings from the table',
                        text: 'A guest who scanned a code can rate a dish, and the owner can reply.',
                        source: 'app/Http/Controllers/Rating/StoreGuestRatingController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'What you need', [
                    new BlockEntry(
                        term: 'A phone or a computer with a browser',
                        text: 'The owner\'s side is a web application too. There is nothing to install for you or for your guests.',
                        source: 'routes/web.php',
                    ),
                    new BlockEntry(
                        term: 'An email address you can read',
                        text: 'Registration is confirmed by email before the account can be used.',
                        source: 'app/Http/Controllers/Auth/SendEmailVerificationNotificationController.php',
                    ),
                    new BlockEntry(
                        term: 'A printer, or a print shop',
                        text: 'Zabuno makes the printable files; the paper on the table is yours.',
                        source: 'app/Http/Controllers/QrDestination/ExportQrPrintSheetController.php',
                    ),
                    new BlockEntry(
                        term: 'No plan, to begin',
                        text: 'Registering, building, publishing and printing are on the free plan. Paid plans open bulk code generation, reports, branding, the team and ordering.',
                        source: 'database/seeders/PlanCatalogueSeeder.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'What Zabuno is not', [
                    new BlockEntry(
                        term: 'Not a till and not a payment system',
                        text: 'Guests do not pay through Zabuno, and nothing here prints a bill or reports your takings.',
                        source: 'app/Application/Ordering/UseCase/BuildOrderLines.php',
                    ),
                    new BlockEntry(
                        term: 'No reservations, delivery or loyalty',
                        text: 'There is no table booking, no courier or delivery integration and no points card.',
                        source: 'app/Domain/Authorization/Permission.php',
                    ),
                    new BlockEntry(
                        term: 'No third-party integrations',
                        text: 'Nothing connects to a point-of-sale system, an accounting package or a food marketplace. Your menu leaves as a spreadsheet file and comes back the same way.',
                        source: 'app/Http/Controllers/MenuCatalog/ExportMenuCsvController.php',
                    ),
                    new BlockEntry(
                        term: 'No app in a store',
                        text: 'There is no iPhone or Android app for you or your guests; both sides are web pages.',
                        source: 'resources/views/public-menu.blade.php',
                    ),
                    new BlockEntry(
                        term: 'Dish names are written once',
                        text: 'The interface around the menu speaks two languages; the dishes are written in yours and are not translated.',
                        source: 'database/migrations/2026_08_20_000002_create_menu_catalog_tables.php',
                    ),
                    new BlockEntry(
                        term: 'One brand per account',
                        text: 'An account holds one business with many branches, not several businesses.',
                        source: 'database/migrations/2026_08_19_000001_create_brands_and_locations_tables.php',
                    ),
                    new BlockEntry(
                        term: 'No sector editions',
                        text: 'There is no cafe version, bakery version or hotel version. The product recognises one kind of business: places that serve food and drink.',
                        source: 'app/Domain/Publication/BusinessType.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'Questions owners ask first', [
                    new BlockEntry(
                        term: 'Do I have to buy a plan before I can try it?',
                        text: 'No. The chain from account to printed code works without a plan. Plans add capability on top.',
                    ),
                    new BlockEntry(
                        term: 'Do guests install anything?',
                        text: 'No. The code opens a web page in the browser already on the phone.',
                    ),
                    new BlockEntry(
                        term: 'Is Zabuno a point-of-sale system?',
                        text: 'No. It shows the menu and can carry an order to the kitchen. It does not take payment and does not know your takings.',
                    ),
                    new BlockEntry(
                        term: 'Which part should I read first?',
                        text: 'The QR menu page, because that is what your guests see. Menu management is where you spend your own time.',
                    ),
                    new BlockEntry(
                        term: 'Does it work with my current software?',
                        text: 'Not through an integration; there is none today. The menu can be exported and imported as a spreadsheet file.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'See what each plan adds', [
                    new BlockEntry(
                        text: 'The free plan covers the chain above. Paid plans add on top of it.',
                        href: '/pricing',
                        term: 'See the plans',
                    ),
                ]),

                new ContentBlock(BlockType::Related, 'The parts of the product', [
                    new BlockEntry(text: 'QR menu', pageKey: 'urun.qr-menu'),
                    new BlockEntry(text: 'Menu management', pageKey: 'urun.menu-yonetimi'),
                    new BlockEntry(text: 'Tables and QR codes', pageKey: 'urun.masa-ve-qr-yonetimi'),
                    new BlockEntry(text: 'Design and branding', pageKey: 'urun.tasarim-ve-marka'),
                    new BlockEntry(text: 'Images and media', pageKey: 'urun.gorsel-ve-medya'),
                    new BlockEntry(text: 'Languages and currency', pageKey: 'urun.coklu-dil-ve-para-birimi'),
                    new BlockEntry(text: 'Multiple branches', pageKey: 'urun.coklu-sube'),
                    new BlockEntry(text: 'Analytics', pageKey: 'urun.analitik'),
                    new BlockEntry(text: 'Zabuno AI', pageKey: 'urun.zabuno-ai'),
                    new BlockEntry(text: 'Solutions', pageKey: 'cozumler'),
                    new BlockEntry(text: 'Pricing', pageKey: 'fiyatlandirma'),
                ]),
            ],
        );
    }
}
