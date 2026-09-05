<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;

/**
 * `/urun/coklu-dil-ve-para-birimi/` — dil ve para birimi sayfası (P0).
 *
 * **BU SAYFANIN EN KOLAY YALANI: "menünüz her dilde".** Site haritası bu
 * başlığın altına "Menü çevirisi `[P0]`" satırını yazmış ve o satır bir
 * SIRADIR, bir söz değil: ölçüm 2026-09-06, ürün yemek adlarını ÇEVİRMİYOR.
 * `products.name` tek bir sütundur ve hiçbir göçte dil boyutu yoktur. Sayfa
 * bu yüzden çevirinin nerede bittiğini açıkça yazıyor — ve ürünün kendisi de
 * misafire aynı şeyi söylüyor: menü dili ile arayüz dili ayrıştığında misafir
 * "yemek adları restoranın kendi dilinde" uyarısını okur.
 *
 * BİLEREK YAZILMAYANLAR: otomatik/makine çevirisi, menü içeriği çevirisi,
 * dile göre fiyat, döviz çevrimi, sağdan sola yazılan bir misafir menüsü,
 * panelde dil değiştirici. Hiçbiri depoda yok. `docs/120`deki dokuz dillik
 * tablo bir ALTYAPI kabiliyetidir (adres uzayı ve hreflang); yayınlanmış bir
 * ürün dili değildir ve öyleymiş gibi yazılmadı.
 */
final class LanguagesAndCurrencyPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'urun.coklu-dil-ve-para-birimi',
            locale: 'en',
            metadata: new PageMetadata(
                seoTitle: 'Guest menu language and currency',
                metaDescription: 'A visiting guest reads the menu page in their own language, dish names stay in yours, and prices are correct in the currency you actually charge.',
                h1: 'Languages and currency',
                breadcrumbTitle: 'Languages and currency',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'The guest menu speaks Turkish or English, chosen by the guest and remembered. Dish names stay in your own language, and the page says so rather than pretending otherwise. Prices are shown in your currency, with that currency\'s own decimals.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'A tourist and a price are two different problems', [
                    new BlockEntry(
                        text: 'A guest from abroad can usually work out what a dish is. What they cannot work out is the button that opens allergens, the word for the search box, or whether the kitchen is taking orders.',
                    ),
                    new BlockEntry(
                        text: 'The price is the sharper problem. A menu that divides by a hundred everywhere is wrong in Japan and wrong in Kuwait, and a price that is wrong on the table is not a formatting bug, it is a promise you did not make.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'Translate the page, not the kitchen', [
                    new BlockEntry(
                        text: 'The parts of the guest page that Zabuno writes are translated and switchable. The parts you write - dish names, descriptions, the notes about your own kitchen - stay exactly as you wrote them.',
                    ),
                    new BlockEntry(
                        text: 'That line is drawn out loud. When the guest is reading in a language other than yours, the page tells them the dish names are in the restaurant\'s language, instead of letting them assume a translation that never happened.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'How the language and the money are chosen', [
                    new BlockEntry(
                        term: 'The guest chooses, in a link',
                        text: 'The language switch on the guest menu is an ordinary link, so it works before any script has run and on a phone that is barely connected.',
                        source: 'app/Support/Localization/GuestLocale.php',
                    ),
                    new BlockEntry(
                        term: 'The browser does not choose for them',
                        text: 'The guest\'s browser language is deliberately ignored. The same code shows the same page to everyone at the table until someone picks otherwise.',
                        source: 'app/Support/Localization/GuestLocale.php',
                    ),
                    new BlockEntry(
                        term: 'The choice is remembered',
                        text: 'A guest who switched language once is not asked again on the next visit; the choice is kept in their browser for a year.',
                        source: 'app/Http/Controllers/QrDestination/ShowPublicMenuController.php',
                    ),
                    new BlockEntry(
                        term: 'Your own language is a setting on the business',
                        text: 'The business carries the language its menu is written in, chosen from a list rather than typed. It is what the page falls back to and what screen readers are told.',
                        source: 'app/Domain/Tenancy/ValueObject/LocaleCode.php',
                    ),
                    new BlockEntry(
                        term: 'Money is stored as whole units, never as a decimal',
                        text: 'A price is an integer plus a currency code. Nothing is stored as a floating point number, so nothing drifts by a hundredth over time.',
                        source: 'database/migrations/2026_08_20_000002_create_menu_catalog_tables.php',
                    ),
                    new BlockEntry(
                        term: 'The currency decides its own decimals',
                        text: 'The number of decimal places comes from the currency itself, not from a fixed division by a hundred. Yen has none, dinar has three, and both come out right.',
                        source: 'app/Domain/Money/MoneyFormatter.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'What it does today', [
                    new BlockEntry(
                        term: 'A guest menu in Turkish or English',
                        text: 'Everything Zabuno writes on the guest page - buttons, labels, the allergen wording, the search box - is translated into both, and each is a real translation rather than English with a different heading.',
                        source: 'resources/js/i18n/guest.ts',
                    ),
                    new BlockEntry(
                        term: 'The dish names keep their own language, and say so',
                        text: 'Menu content is marked with the language it is written in, so a screen reader pronounces a Turkish dish name as Turkish even inside an English page.',
                        source: 'resources/views/public-menu.blade.php',
                    ),
                    new BlockEntry(
                        term: 'One currency per business, chosen from the real list',
                        text: 'The currency is set on the business from the full standard list with its symbol and its decimals, at sign-up and afterwards.',
                        source: 'app/Infrastructure/Reference/IcuMarketReference.php',
                    ),
                    new BlockEntry(
                        term: 'A price in the wrong currency is refused, not converted',
                        text: 'A dish priced in a currency other than the business\'s is rejected before it is saved. Silently converting it would invent a number nobody agreed to.',
                        source: 'app/Domain/Money/Money.php',
                    ),
                    new BlockEntry(
                        term: 'No price rather than a wrong price',
                        text: 'If a currency cannot be resolved on the guest page, the dish is shown without a price. A wrong price is worse than a missing one.',
                        source: 'app/Support/Money/PriceLabel.php',
                    ),
                    new BlockEntry(
                        term: 'The browser is told how to write the money',
                        text: 'The server measures its own formatting - symbol, separators, digits - and hands that to the page, so the price does not change shape between the server and the phone.',
                        source: 'app/Support/Money/MoneyFormatContract.php',
                    ),
                    new BlockEntry(
                        term: 'One basket cannot mix currencies',
                        text: 'An order refuses lines in a second currency instead of adding them up into a total that means nothing.',
                        source: 'app/Application/Ordering/UseCase/BuildOrderLines.php',
                    ),
                    new BlockEntry(
                        term: 'Addresses that carry their language',
                        text: 'On this website, the language is part of the address rather than a guess about your browser, and pages declare their counterparts to search engines only where a real counterpart is published.',
                        source: 'app/Application/Content/UseCase/ResolveLocaleAlternates.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'What you need', [
                    new BlockEntry(
                        term: 'A language and a currency on the business',
                        text: 'Both are asked for when the business is created, because a menu cannot be published without knowing what language it is in and what it charges.',
                        source: 'resources/js/components/workspace/BrandOnboardingForm.tsx',
                    ),
                    new BlockEntry(
                        term: 'Prices written in that currency',
                        text: 'Every dish is priced in the business\'s currency, with no more decimal places than the currency has.',
                        source: 'app/Http/Controllers/MenuCatalog/StoreMenuItemController.php',
                    ),
                    new BlockEntry(
                        term: 'Nothing else, and no plan',
                        text: 'Language and currency are not sold. There is no right to buy here; the behaviour is the same on the free plan.',
                        source: 'app/Domain/Entitlement/Entitlement.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'What it does not do', [
                    new BlockEntry(
                        term: 'Dish names are not translated',
                        text: 'A dish has one name and one description, in your language. There is no second language for menu content, and none is generated.',
                        source: 'database/migrations/2026_08_20_000002_create_menu_catalog_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Nothing is machine translated',
                        text: 'Translation generation is switched off in code, not behind a setting. No text in this product was produced by a machine translator.',
                        source: 'app/Domain/Localization/TranslationGenerationLock.php',
                    ),
                    new BlockEntry(
                        term: 'Two languages on the guest page, not nine',
                        text: 'The guest can read the page in Turkish or English. Other languages exist as addresses and as groundwork, and the switch does not offer them.',
                        source: 'app/Support/Localization/GuestLocale.php',
                    ),
                    new BlockEntry(
                        term: 'The panel is in English only',
                        text: 'The screens you work in ship in one language. Other catalogues are written and kept, and a language enters only when every single string in it is done.',
                        source: 'config/i18n.php',
                    ),
                    new BlockEntry(
                        term: 'No currency conversion',
                        text: 'There is no exchange rate anywhere in the product and no "show this in euros". A guest sees the currency you charge in.',
                        source: 'app/Domain/Money/Money.php',
                    ),
                    new BlockEntry(
                        term: 'One currency per business, not per menu',
                        text: 'A branch cannot price one menu in a second currency. The currency belongs to the business.',
                        source: 'database/migrations/2026_08_19_000001_create_brands_and_locations_tables.php',
                    ),
                    new BlockEntry(
                        term: 'No price that changes with the language',
                        text: 'A price has no language dimension. The same dish costs the same whichever way the guest is reading the page.',
                        source: 'database/migrations/2026_08_20_000002_create_menu_catalog_tables.php',
                    ),
                    new BlockEntry(
                        term: 'No right-to-left guest menu',
                        text: 'Text direction is handled by the groundwork and tested, but no right-to-left language is offered to a guest today.',
                        source: 'app/Support/Localization/DocumentLocale.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'Questions owners ask', [
                    new BlockEntry(
                        term: 'Will my menu be translated for tourists?',
                        text: 'The page around your menu will be, into Turkish or English. The dish names will not, and the page tells the guest that instead of letting them assume it.',
                    ),
                    new BlockEntry(
                        term: 'Can I write the English version of a dish myself?',
                        text: 'Not as a separate language today. A dish carries one name and one description; some owners put both languages inside that description, which is honest but it is not a translated menu.',
                    ),
                    new BlockEntry(
                        term: 'Can a guest see prices in euros?',
                        text: 'No. There is no conversion in the product, and inventing a rate on the table would be inventing a price.',
                    ),
                    new BlockEntry(
                        term: 'Does the menu follow my guest\'s phone language?',
                        text: 'No, on purpose. Everyone scanning the same code sees the same page until a guest chooses otherwise, and that choice is then remembered.',
                    ),
                    new BlockEntry(
                        term: 'My currency has no decimals. Will the prices be a hundred times wrong?',
                        text: 'No. The decimals come from the currency itself, so yen shows as yen and dinar keeps its three places.',
                    ),
                    new BlockEntry(
                        term: 'Is any of this on a paid plan?',
                        text: 'No. Language and currency behave the same on every plan, including the free one.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'Language and currency cost nothing extra', [
                    new BlockEntry(
                        text: 'See what the plans do change, and what stays open without one.',
                        href: '/pricing',
                        term: 'See the plans',
                    ),
                ]),

                new ContentBlock(BlockType::Related, 'Related pages', [
                    new BlockEntry(text: 'QR menu', pageKey: 'urun.qr-menu'),
                    new BlockEntry(text: 'Menu management', pageKey: 'urun.menu-yonetimi'),
                ]),
            ],
        );
    }
}
