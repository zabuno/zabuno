<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;

/**
 * `/urun/masa-ve-qr-yonetimi/` — masa ve QR yönetimi sayfası (P0).
 *
 * BİLEREK YAZILMAYANLAR: ürün bazlı QR kartları, menü dışı QR hedefleri
 * (serbest adres, Wi-Fi, kampanya), kalibrasyon cetveli ve "test taraması
 * kaydedildi" durumu. `docs/08` bunları PLANLAMA olarak yazıyor; depoda
 * karşılıkları yok ve bir planlama belgesi bir ürün vaadi değildir.
 */
final class TablesAndQrPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'urun.masa-ve-qr-yonetimi',
            locale: 'en',
            metadata: new PageMetadata(
                seoTitle: 'Table and QR code management',
                metaDescription: 'Generate a code per table, print it as a card, a poster or a cut-and-share sheet, and point it somewhere else later without reprinting.',
                h1: 'Tables and QR codes',
                breadcrumbTitle: 'Tables and QR codes',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'This is where a table becomes an address. You generate a code for each table, export it as a card, a poster or a sheet you cut up, and change what the code points at later without printing anything again.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'A printed code is a promise you cannot take back', [
                    new BlockEntry(
                        text: 'Paper is cheap to print once and expensive to print twice. A code that stops working, or one that scans on the designer\'s screen but not under the lamp above table nine, costs a whole reprint and an evening of apologies.',
                    ),
                    new BlockEntry(
                        text: 'The failure is also silent: nobody reports a card that will not scan. The guest gives up and asks the waiter, and the owner never hears about it.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'The code is checked before it reaches the printer', [
                    new BlockEntry(
                        text: 'Every code is drawn by the server and then read back by a decoder before it is handed to you. A code that cannot be read does not become a file.',
                    ),
                    new BlockEntry(
                        text: 'Contrast is checked the same way. A brand colour too pale to scan is refused and the classic black code is used instead, and you are told that it happened rather than finding out from a guest.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'How it works', [
                    new BlockEntry(
                        term: 'Lay out the floor',
                        text: 'Describe how many areas and how many tables you have, with a numbering prefix and seat counts, and the tables and their codes are created together.',
                        source: 'app/Http/Controllers/QrDestination/StoreBulkQrCodesController.php',
                    ),
                    new BlockEntry(
                        term: 'Or add a single code',
                        text: 'A code can also be created on its own, without describing a floor first.',
                        source: 'app/Http/Controllers/QrDestination/StoreQrCodeController.php',
                    ),
                    new BlockEntry(
                        term: 'Choose how it looks',
                        text: 'Pick a code style and a card style, a size and an orientation, and add a short line of your own text.',
                        source: 'app/Domain/QrDestination/CardSize.php',
                    ),
                    new BlockEntry(
                        term: 'The server proves it scans',
                        text: 'Before export, the generated code is decoded again on the server. Preview and print come from the same drawing, so what you approve is what is printed.',
                        source: 'app/Infrastructure/QrDestination/Rendering/EndroidQrCodeImageExportAdapter.php',
                    ),
                    new BlockEntry(
                        term: 'Print it',
                        text: 'Export one card, a poster, a whole floor as one archive for the print shop, or an A4 sheet of twelve to cut up.',
                        source: 'app/Http/Controllers/QrDestination/ExportQrCardsZipController.php',
                    ),
                    new BlockEntry(
                        term: 'Change it later',
                        text: 'Point an existing code at a different published menu. The token, and therefore the paper on the table, does not change.',
                        source: 'app/Http/Controllers/QrDestination/RetargetQrCodeController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'What you can do', [
                    new BlockEntry(
                        term: 'A code per table',
                        text: 'Tables carry a name, a seat count and an area, and each one gets its own code, so a scan is tied to a place and not just to the restaurant.',
                        source: 'database/migrations/2026_08_22_000006_create_dining_areas_and_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Codes that cannot be guessed',
                        text: 'Each code carries a 43-character token, so nobody finds your menu by typing an address next to somebody else\'s.',
                        source: 'app/Domain/QrDestination/QrToken.php',
                    ),
                    new BlockEntry(
                        term: 'Six code styles, six card styles',
                        text: 'Classic, minimal, bold, rounded, branded and high-contrast codes, on cards that carry your logo and your primary colour.',
                        source: 'app/Domain/QrDestination/QrTheme.php',
                    ),
                    new BlockEntry(
                        term: 'Real paper sizes',
                        text: 'Cards in ISO sizes and common screen ratios, portrait or landscape, plus posters down to A7.',
                        source: 'app/Http/Controllers/QrDestination/ExportQrCodePdfController.php',
                    ),
                    new BlockEntry(
                        term: 'Vector files for the printer',
                        text: 'Cards export as SVG and PDF, so a print shop scales them without the code turning to mush. Raster is offered for the plain code, not for cards.',
                        source: 'app/Http/Controllers/QrDestination/ExportQrCardController.php',
                    ),
                    new BlockEntry(
                        term: 'One archive for the whole floor',
                        text: 'Export every card at once, filtered by area, with each file named after its table so the print shop does not have to guess.',
                        source: 'app/Http/Controllers/QrDestination/ExportQrCardsZipController.php',
                    ),
                    new BlockEntry(
                        term: 'A sheet you cut up',
                        text: 'Twelve codes on one A4 page for the tables you want to cover today without a trip to the printer.',
                        source: 'app/Domain/QrDestination/QrPrintSheet.php',
                    ),
                    new BlockEntry(
                        term: 'Contrast checked, not assumed',
                        text: 'A brand colour that would not scan reliably is refused and the classic code is used instead, and the fallback is reported rather than hidden.',
                        source: 'app/Domain/QrDestination/QrContrast.php',
                    ),
                    new BlockEntry(
                        term: 'Switch a code off',
                        text: 'A code can be disabled and enabled again, so a lost or stolen card stops working without every other table being affected.',
                        source: 'app/Http/Controllers/QrDestination/DisableQrCodeController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'What you need', [
                    new BlockEntry(
                        term: 'A published menu',
                        text: 'A code points at a published menu. Without one there is nothing for the code to open.',
                        source: 'app/Http/Controllers/QrDestination/RetargetQrCodeController.php',
                    ),
                    new BlockEntry(
                        term: 'For one code at a time',
                        text: 'Creating codes one by one, and exporting them, are on the free plan.',
                        source: 'database/seeders/PlanCatalogueSeeder.php',
                    ),
                    new BlockEntry(
                        term: 'For a whole floor in one pass',
                        text: 'Describing an entire floor and generating its codes together belongs to a paid plan.',
                        source: 'app/Http/Controllers/QrDestination/StoreBulkQrCodesController.php',
                    ),
                    new BlockEntry(
                        term: 'For your colours on the card',
                        text: 'Your brand colour on the card and the code belongs to a paid plan; without it the neutral default is used.',
                        source: 'app/Domain/Entitlement/Entitlement.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'What it does not do', [
                    new BlockEntry(
                        term: 'A code opens a menu, nothing else',
                        text: 'A code cannot be pointed at an arbitrary web address, a Wi-Fi password or a campaign page. Its only destination is a published menu.',
                        source: 'app/Infrastructure/QrDestination/Persistence/EloquentQrCodeRepository.php',
                    ),
                    new BlockEntry(
                        term: 'No per-dish codes',
                        text: 'There is no code that opens a single dish. A dish has its own web address, but not its own printable code.',
                        source: 'routes/api/qr-destination.php',
                    ),
                    new BlockEntry(
                        term: 'Tables are created by the floor wizard',
                        text: 'Tables and areas are created together when you describe the floor. There is no screen for adding, renaming or deleting a single table afterwards; an area can be renamed.',
                        source: 'app/Http/Controllers/QrDestination/RenameDiningAreaController.php',
                    ),
                    new BlockEntry(
                        term: 'No print calibration tools',
                        text: 'There is no ruler to check printed size and no place to record that a card was test-scanned. Scan the first card yourself before printing the rest.',
                        source: 'app/Http/Controllers/QrDestination/ExportQrCardController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'Questions owners ask', [
                    new BlockEntry(
                        term: 'Do I have to reprint when the menu changes?',
                        text: 'No. The code points at an address that does not change. You can even point it at a different menu and the same card keeps working.',
                    ),
                    new BlockEntry(
                        term: 'What if my brand colour makes the code hard to scan?',
                        text: 'It is refused. Contrast is checked, the classic black code is used instead, and you are told that the fallback happened.',
                    ),
                    new BlockEntry(
                        term: 'Will the code still scan after the print shop resizes it?',
                        text: 'Cards are exported as vector files, so scaling does not soften the code. Each generated code is also decoded again on the server before you get the file.',
                    ),
                    new BlockEntry(
                        term: 'Can I get every table\'s card in one download?',
                        text: 'Yes. One archive holds a file per table, named after the table, and can be filtered to a single area.',
                    ),
                    new BlockEntry(
                        term: 'Someone walked off with a card. What now?',
                        text: 'Disable that code. The other tables are untouched, and it can be enabled again if the card turns up.',
                    ),
                    new BlockEntry(
                        term: 'Can I print just a few codes myself?',
                        text: 'Yes. An A4 sheet holds twelve codes to cut up, without ordering anything.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'See which plan covers your floor', [
                    new BlockEntry(
                        text: 'Single codes are free; generating a whole floor at once is not.',
                        href: '/pricing',
                        term: 'See the plans',
                    ),
                ]),

                new ContentBlock(BlockType::Related, 'Related pages', [
                    new BlockEntry(text: 'QR menu', pageKey: 'urun.qr-menu'),
                    new BlockEntry(text: 'Analytics', pageKey: 'urun.analitik'),
                ]),
            ],
        );
    }
}
