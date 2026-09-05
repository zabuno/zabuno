<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;

/**
 * `/cozumler/` — çözümler girişi (P0). Ürün sayfası DEĞİL.
 *
 * **Bu sayfanın tek işi, olmayan bir ayrımı satmamaktır.** Site haritası bu
 * başlığın altına on bir işletme türü yazmış: kafe, bar, otel, food truck,
 * food hall, stadyum… Ölçüm 2026-09-06: üründe TEK işletme türü var
 * (`BusinessType::Restaurant`) ve ürün bir kafeyi bir restorandan ayırt
 * etmiyor. Yönerge §13.4 tam olarak bunu yasaklıyor: "yalnızca sektör adı
 * değiştirilmiş kopya metin yayınlama" ve "boş, az veri içeren ya da yalnızca
 * şehir/anahtar kelime değiştirilmiş sayfa yayınlanmamalıdır".
 *
 * Dürüst cevap, on bir kopya sayfa değil: aynı ürünün farklı mutfaklarda
 * farklı işe yaradığını, ayrımın ÜRÜNDE değil MENÜDE olduğunu söylemektir.
 * Bir kafeyi kafe yapan şey, üründe bir "kafe kipi" değil, yazdığı menüdür.
 *
 * BİLEREK YAZILMAYANLAR: sektöre özel kip, hazır sektör menüsü, müşteri
 * hikâyesi, sonuç metriği ("%30 daha hızlı servis"), kasa/POS entegrasyonu,
 * rezervasyon, paket servis, sadakat programı. Ölçüm: hiçbiri depoda yok ve
 * bir çözüm sayfasının en kolay uydurduğu şeyler tam olarak bunlardır.
 */
final class SolutionsPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'cozumler',
            locale: 'en',
            metadata: new PageMetadata(
                seoTitle: 'Who Zabuno is built for',
                metaDescription: 'One product for places that serve food and drink. What changes between a cafe and a dining room is the menu you write, not a mode you switch on.',
                h1: 'Solutions',
                breadcrumbTitle: 'Solutions',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'Zabuno is one product for places that serve food and drink, from a single cafe to a business with several branches. There is no separate edition per kind of venue: what differs between them is the menu you write and the hours you keep.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'Software sold by sector is usually the same software', [
                    new BlockEntry(
                        text: 'Most of this industry\'s websites carry one page per venue type, and behind eleven pages sits one product with the noun swapped. The owner of a bakery reads a page written for a bakery and buys something that was never built for one.',
                    ),
                    new BlockEntry(
                        text: 'The cost lands later, when the promised difference turns out not to exist and the setting that was implied cannot be found.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'One product, and the differences named honestly', [
                    new BlockEntry(
                        text: 'Zabuno knows one kind of business and says so in its own code. A cafe and a fine dining room get the same menu editor, the same codes on the table and the same reports.',
                    ),
                    new BlockEntry(
                        text: 'The real differences are still real, and they are handled by things that already exist: several menus that hand over by the hour, prices set per branch, opening hours per day, and ordering that a kitchen switches on when it is ready.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'How one product fits different rooms', [
                    new BlockEntry(
                        term: 'One kind of business, on purpose',
                        text: 'The product recognises food and drink businesses and nothing else. A distinction that does not change behaviour is not written into the data.',
                        source: 'app/Domain/Publication/BusinessType.php',
                    ),
                    new BlockEntry(
                        term: 'Breakfast, lunch and the late menu',
                        text: 'A branch can hold several menus that take over from each other by the hour, in that branch\'s own time zone. This is what a cafe and a bar actually need from each other.',
                        source: 'database/migrations/2026_09_05_000400_allow_many_menus_per_location.php',
                    ),
                    new BlockEntry(
                        term: 'The room decides the codes',
                        text: 'Codes are made per table or per area, so a twelve-table dining room and a counter with one code are the same product used differently.',
                        source: 'database/migrations/2026_08_22_000005_create_qr_destination_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Ordering only where a kitchen is watching',
                        text: 'Sending an order from the table is switched on per branch and starts switched off, because a quiet counter should never receive an order nobody is looking at.',
                        source: 'database/migrations/2026_09_06_000700_add_ordering_switch_and_frozen_plan.php',
                    ),
                    new BlockEntry(
                        term: 'Your language and your money',
                        text: 'The business sets the language its menu is written in and the currency it charges, and the guest page follows both.',
                        source: 'database/migrations/2026_08_19_000001_create_brands_and_locations_tables.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'What every venue gets, whatever it is called', [
                    new BlockEntry(
                        term: 'A menu the guest opens in their own browser',
                        text: 'A code on the table, a page that opens without an app, and a menu you can correct in the middle of service.',
                        source: 'app/Http/Controllers/QrDestination/ShowPublicMenuController.php',
                    ),
                    new BlockEntry(
                        term: 'A published version, not a live document',
                        text: 'Guests read a version you published. Editing the draft during service does not change what somebody is reading at that moment.',
                        source: 'database/migrations/2026_08_22_000004_create_menu_publications_table.php',
                    ),
                    new BlockEntry(
                        term: 'As many branches as the business has',
                        text: 'One account holds one brand and any number of branches, each with its own menus, prices and hours.',
                        source: 'database/migrations/2026_08_19_000001_create_brands_and_locations_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Photographs sized for a phone on a bad connection',
                        text: 'Upload the picture your phone took; the guest gets a small copy made for the size it is shown at.',
                        source: 'app/Infrastructure/Media/Processing/GdMediaAssetProcessor.php',
                    ),
                    new BlockEntry(
                        term: 'What guests looked at, and what they searched for in vain',
                        text: 'Counts of scans, opens, dish views and searches that found nothing - the demand a paper menu can never show you.',
                        source: 'app/Domain/Analytics/AnalyticsEventType.php',
                    ),
                    new BlockEntry(
                        term: 'Orders from the table, where it makes sense',
                        text: 'Guests can build a basket and send it to the kitchen in branches where the owner has switched that on.',
                        source: 'app/Http/Controllers/Ordering/UpdateOrderingSwitchController.php',
                    ),
                    new BlockEntry(
                        term: 'A team with real roles',
                        text: 'Managers, editors and a kitchen role that sees allergens and what has sold out rather than the whole business.',
                        source: 'app/Domain/Tenancy/MembershipRole.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'What you need', [
                    new BlockEntry(
                        term: 'A business and at least one branch',
                        text: 'A brand with a name, and one branch with an address and a time zone. A single-site cafe is a business with one branch.',
                        source: 'app/Http/Controllers/Tenancy/StoreLocationController.php',
                    ),
                    new BlockEntry(
                        term: 'A menu you are willing to publish',
                        text: 'Dishes with names and prices in your currency. Publishing is a deliberate act, not an autosave.',
                        source: 'app/Http/Controllers/Publication/StorePublicationController.php',
                    ),
                    new BlockEntry(
                        term: 'A way to put the code in front of the guest',
                        text: 'A printed card on the table, a sticker, or a code by the counter. Zabuno makes the printable sheet; the paper is yours.',
                        source: 'app/Http/Controllers/QrDestination/ExportQrCardController.php',
                    ),
                    new BlockEntry(
                        term: 'No plan, to begin',
                        text: 'Writing the menu, publishing it and printing codes work without paying. Plans open the reports, the branding and the team.',
                        source: 'database/seeders/PlanCatalogueSeeder.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'What it is not', [
                    new BlockEntry(
                        term: 'Not a till and not a point of sale',
                        text: 'Zabuno does not take payment from a guest, does not print a bill and does not know what your day took.',
                        source: 'app/Application/Ordering/UseCase/BuildOrderLines.php',
                    ),
                    new BlockEntry(
                        term: 'No booking, no delivery, no loyalty',
                        text: 'There is no table reservation, no delivery or courier integration and no points card. These are not switched off; they are absent.',
                        source: 'app/Domain/Authorization/Permission.php',
                    ),
                    new BlockEntry(
                        term: 'No sector mode and no ready-made menu',
                        text: 'There is no cafe setting, no bakery template and no starter menu to import for your kind of venue. Your menu is yours to write.',
                        source: 'app/Domain/Publication/BusinessType.php',
                    ),
                    new BlockEntry(
                        term: 'No customer stories or figures on this page',
                        text: 'You will not read a percentage here about faster service or a bigger bill. No such measurement exists, and quoting one would be inventing it.',
                        source: 'app/Support/Seo/CorporatePageStructuredData.php',
                    ),
                    new BlockEntry(
                        term: 'Nothing about money in the reports',
                        text: 'The reports count attention, not turnover. A venue that needs revenue reporting needs it from somewhere else.',
                        source: 'app/Application/Analytics/Port/AnalyticsRepositoryPort.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'Questions owners ask', [
                    new BlockEntry(
                        term: 'Is there a version for cafes?',
                        text: 'No, and that is the honest answer rather than a shortcoming. A cafe uses the same product; what makes it a cafe is the menu it writes and the hours it keeps.',
                    ),
                    new BlockEntry(
                        term: 'Does it work for a bar with a late menu?',
                        text: 'Yes. A branch can run several menus that hand over by the hour in its own time zone, so the late list appears on its own.',
                    ),
                    new BlockEntry(
                        term: 'Can it replace my till?',
                        text: 'No. Zabuno shows the menu and can carry an order to the kitchen. It does not take payment and does not report your takings.',
                    ),
                    new BlockEntry(
                        term: 'I have one small place. Is this too much?',
                        text: 'A single site is a business with one branch, and the base journey costs nothing. The parts built for several branches simply stay out of your way.',
                    ),
                    new BlockEntry(
                        term: 'Do you have a menu template for my kind of venue?',
                        text: 'No. There is no ready-made menu to import for a sector.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'See what it costs, and what it does not', [
                    new BlockEntry(
                        text: 'The plans, what each opens, and the part that never needed one.',
                        href: '/pricing',
                        term: 'See the plans',
                    ),
                ]),

                new ContentBlock(BlockType::Related, 'Related pages', [
                    new BlockEntry(text: 'QR menu', pageKey: 'urun.qr-menu'),
                    new BlockEntry(text: 'Multiple branches', pageKey: 'urun.coklu-sube'),
                    new BlockEntry(text: 'Pricing', pageKey: 'fiyatlandirma'),
                ]),
            ],
        );
    }
}
