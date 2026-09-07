<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;

/**
 * `/urun/tasarim-ve-marka/` — tasarım ve marka sayfası (P0, `docs/119` §21
 * Faz 6 madde 5; dalga 1–2'de yazılmamış tek üst başlık).
 *
 * **Bu sayfanın en kolay yalanı "menünüz baştan aşağı sizin renginizde".**
 * Ölçüm 2026-09-06: misafir menüsünde marka rengi yalnız DEKORASYONDUR —
 * üstte 4 px'lik bir şerit ve kategori başlığının altında 2 px'lik bir
 * çizgi (`public-menu.blade.php`). Kontrast rampası (`BrandSkin`) türetilir,
 * ölçülür ve yayına DONAR, ama misafir yüzeyi henüz ondan çizmiyor; bunu
 * ürünün kendi stil kökü yazıyor. Sayfa bu yüzden rengin nerede bittiğini
 * adıyla söylüyor.
 *
 * BİLEREK YAZILMAYANLAR: biçim varyantı (altı seçenek veritabanında ve
 * yayında var, panelde seçici YOK ve misafir yüzeyi okumuyor — misafir
 * yüzeyi olmayan bir hak satılmaz, `PricingPage::WITHHELD` ilkesi), yazı
 * tipi seçimi, düzen/tema editörü, özel alan adı, kapak görseli, kendi tab
 * ikonu. Ölçüm: hiçbiri depoda yok.
 */
final class DesignAndBrandingPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'urun.tasarim-ve-marka',
            locale: 'en',
            metadata: new PageMetadata(
                seoTitle: 'Your logo and colours on the guest menu',
                metaDescription: 'Add a logo, a primary and a secondary colour, and they appear on the guest menu and the printed table cards. Frozen with each publication, checked before printing.',
                h1: 'Design and branding',
                breadcrumbTitle: 'Design and branding',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'Design and branding is where your restaurant\'s logo, primary colour and secondary colour are set once and carried onto the guest menu and the printed table cards. Your colour is used as decoration, never as text, so a pale brand colour cannot make a price unreadable.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'A menu that looks like the software, not the restaurant', [
                    new BlockEntry(
                        text: 'A guest who scans a code lands on a page that is, technically, somebody else\'s website. If it carries no trace of the restaurant, the guest checks the address bar before trusting a price.',
                    ),
                    new BlockEntry(
                        text: 'The opposite failure is worse: software that lets an owner paint the whole page in a brand yellow, and then shows white prices on that yellow to a guest under a dim lamp.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'Your identity on the page, readability kept by the product', [
                    new BlockEntry(
                        text: 'You give a logo and two colours. Zabuno places the logo beside the restaurant name, draws a strip in your primary colour across the top of the menu and a line in your secondary colour under each category, and leaves text and backgrounds in its own measured ink.',
                    ),
                    new BlockEntry(
                        text: 'What the guest sees is frozen with each published version. Changing a colour tomorrow does not repaint the menu somebody opened from yesterday\'s publication.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'How it works', [
                    new BlockEntry(
                        term: 'Upload the logo',
                        text: 'The logo is a file in your media library, scanned and resized like any photo, and bound to the brand once it has been processed.',
                        source: 'app/Http/Controllers/Tenancy/BindBrandLogoController.php',
                    ),
                    new BlockEntry(
                        term: 'Pick two colours',
                        text: 'A colour box and a six-digit code field side by side, so a brand code written in a document can be typed exactly.',
                        source: 'resources/js/components/workspace/pages/profile/BrandColorsRegion.tsx',
                    ),
                    new BlockEntry(
                        term: 'The code is validated',
                        text: 'Only a full six-digit colour is stored. Short forms and colour names are refused, so the menu template never receives a value it cannot draw.',
                        source: 'app/Http/Requests/Tenancy/UpdateBrandRequest.php',
                    ),
                    new BlockEntry(
                        term: 'Publish',
                        text: 'Logo and colours are written into the publication snapshot together with the restaurant name, address and phone. Guests read the snapshot.',
                        source: 'app/Domain/Publication/MenuIdentity.php',
                    ),
                    new BlockEntry(
                        term: 'Print the cards',
                        text: 'The same primary colour goes onto the table cards as a frame, strip or background. The code itself is always printed dark on light.',
                        source: 'app/Domain/QrDestination/CardTheme.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'What it does', [
                    new BlockEntry(
                        term: 'Logo beside the name',
                        text: 'A small logo sits in the menu header next to your restaurant name, served in the right size for the screen, with the alternative text you wrote for it.',
                        source: 'resources/views/public-menu.blade.php',
                    ),
                    new BlockEntry(
                        term: 'A strip in your colour',
                        text: 'The primary colour appears as a strip across the top of the guest menu and the secondary colour as a line under each category heading. A restaurant that chose no colour shows no strip.',
                        source: 'resources/views/public-menu.blade.php',
                    ),
                    new BlockEntry(
                        term: 'Colours and logo on the printed cards',
                        text: 'Every card design uses the brand colour on the card, and the restaurant name where the design shows one. The logo is embedded in the card file itself, so a print shop cannot lose it.',
                        source: 'app/Support/QrDestination/QrCardSvg.php',
                    ),
                    new BlockEntry(
                        term: 'A colour that would break the code is refused',
                        text: 'A branded code style uses your colour only when the contrast is enough to scan. Otherwise the classic dark code is printed and the fallback is reported.',
                        source: 'app/Domain/QrDestination/QrContrast.php',
                    ),
                    new BlockEntry(
                        term: 'Readability measured, not hoped for',
                        text: 'From your colour, Zabuno derives shades for text, fills and lines against both grounds the guest page can use, and measures each against the WCAG 2.2 thresholds. The measured result is stored with each publication.',
                        source: 'app/Domain/Branding/BrandRamp.php',
                    ),
                    new BlockEntry(
                        term: 'The name, address and phone travel with it',
                        text: 'The restaurant name, branch name, address and a tappable phone number appear on the menu and are frozen at publish time like the colours.',
                        source: 'app/Application/Publication/UseCase/BuildPublicationSnapshot.php',
                    ),
                    new BlockEntry(
                        term: 'Name changes are never blocked by the plan',
                        text: 'Correcting the restaurant name, time zone or currency works on every plan. Only the appearance fields are behind the branding entitlement.',
                        source: 'app/Http/Controllers/Tenancy/UpdateBrandController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'What you need', [
                    new BlockEntry(
                        term: 'A plan with branding',
                        text: 'Carrying your colours onto the guest menu belongs to a paid plan. Without it the guest page stays in the neutral default, and the menu is published and printed all the same.',
                        source: 'app/Domain/Entitlement/Entitlement.php',
                    ),
                    new BlockEntry(
                        term: 'Permission to manage the business',
                        text: 'Logo and colours are set by the owner or a manager, not by an editor or the kitchen.',
                        source: 'app/Domain/Authorization/RolePermissions.php',
                    ),
                    new BlockEntry(
                        term: 'A logo file the media library accepts',
                        text: 'The logo goes through the same malware scan and size limits as a dish photo, and is bound only once it has been processed.',
                        source: 'app/Http/Controllers/Tenancy/BindBrandLogoController.php',
                    ),
                    new BlockEntry(
                        term: 'A publish after the change',
                        text: 'Guests read published versions. A new colour reaches the table with the next publication, not before.',
                        source: 'app/Domain/Publication/MenuIdentity.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'What it does not do', [
                    new BlockEntry(
                        term: 'Your colour is decoration, not the page',
                        text: 'Buttons, prices and links on the guest menu use the product\'s own ink, not your colour. The measured shades exist, but the guest page does not draw its text from them.',
                        source: 'resources/views/partials/guest-surface-style.blade.php',
                    ),
                    new BlockEntry(
                        term: 'No fonts',
                        text: 'The guest menu uses the phone\'s own system font. There is no typeface choice and no font upload.',
                        source: 'resources/views/partials/guest-surface-style.blade.php',
                    ),
                    new BlockEntry(
                        term: 'No layout or theme editor',
                        text: 'There is no editor for spacing, corners, shadows or section order. The guest page has one layout, tuned for a phone held in one hand.',
                        source: 'resources/views/partials/guest-surface-style.blade.php',
                    ),
                    new BlockEntry(
                        term: 'No custom domain',
                        text: 'The menu lives at a Zabuno address. It cannot be served from your own domain.',
                        source: 'routes/web.php',
                    ),
                    new BlockEntry(
                        term: 'The tab icon is Zabuno\'s',
                        text: 'The small icon in the browser tab is the product\'s, not your logo.',
                        source: 'resources/views/public-menu.blade.php',
                    ),
                    new BlockEntry(
                        term: 'No cover photo',
                        text: 'There is no picture behind the header and no cover image for the menu. Photos belong to dishes.',
                        source: 'resources/views/public-menu.blade.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'Questions owners ask', [
                    new BlockEntry(
                        term: 'Can I put my logo on the menu?',
                        text: 'Yes. Upload it to the media library, bind it to the brand, and publish. It appears beside the restaurant name on the menu and on the printed cards.',
                    ),
                    new BlockEntry(
                        term: 'Can I change the colour of the buttons?',
                        text: 'No. Your colour is used as a strip and a line; buttons, prices and links stay in the product\'s own ink so they remain readable on every phone.',
                    ),
                    new BlockEntry(
                        term: 'Why did my brand colour not appear on the code?',
                        text: 'Because the contrast was not enough to scan. The classic dark code was printed instead and the fallback was reported. The card around it still carries your colour.',
                    ),
                    new BlockEntry(
                        term: 'I changed the colour. Why does the menu look the same?',
                        text: 'The guest reads a published version. Publish again and the new colour is in the next version.',
                    ),
                    new BlockEntry(
                        term: 'Can I use my own font?',
                        text: 'No. The menu uses the system font of the guest\'s phone.',
                    ),
                    new BlockEntry(
                        term: 'Is branding on the free plan?',
                        text: 'No. The free plan publishes the menu in the neutral look. Your colours on the guest page are part of a paid plan.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'See which plan carries your colours', [
                    new BlockEntry(
                        text: 'Publishing and printing are free; your colours on the guest page are not.',
                        href: '/pricing',
                        term: 'See the plans',
                    ),
                ]),

                new ContentBlock(BlockType::Related, 'Related pages', [
                    new BlockEntry(text: 'QR menu', pageKey: 'urun.qr-menu'),
                    new BlockEntry(text: 'Tables and QR codes', pageKey: 'urun.masa-ve-qr-yonetimi'),
                    new BlockEntry(text: 'Images and media', pageKey: 'urun.gorsel-ve-medya'),
                ]),
            ],
        );
    }
}
