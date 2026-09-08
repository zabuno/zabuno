<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;
use App\Domain\Entitlement\Entitlement;
use Database\Seeders\PlanCatalogueSeeder;

/**
 * `/urun/siparis/` — masadan sipariş (P1). FF-229.
 *
 * ═══ NEDEN BU SAYFA VAR ═══
 *
 * `docs/137` §5 bunu ölçtü ve adını koydu: **satılan ama anlatılmayan
 * yetenek.** Sipariş hattı uçtan uca çalışıyor — misafir gönderir, garson
 * onaylar, mutfak görür — ve `ordering.basic` hakkı plan kataloğunda bir
 * kademede SATILIYOR. Buna karşılık sayfa kütüğünde tek bir satırı, yazılmış
 * on altı sayfada tek bir cümlesi yoktu. Parası alınan bir yeteneğin ürün
 * sayfası olmaması, satın alan restoranın onu ancak ilk akşam keşfetmesi
 * demektir; ya da hiç keşfetmemesi.
 *
 * ═══ NEDEN HUB'IN KENDİSİ, ALT SAYFA DEĞİL ═══
 *
 * Kütükte `urun.siparis` (Dijital sipariş) ve altında altı kardeş var:
 * masaya sipariş, gel-al, paket servis, ön sipariş, çoklu satıcı, açık
 * hesap. Depoda bunların YALNIZ BİRİ var. `urun.siparis.masaya-siparis`
 * yazmak, üstüne bir de ata hub'ı yazmayı gerektirirdi
 * (`ProductPageLibraryTest::test_every_written_page_has_its_parent_hub_written`)
 * ve o hub anlatacak başka bir şey bulamayıp çocuğunun kopyası olurdu —
 * yönergenin doorway saydığı şeyin ta kendisi. Bu yüzden hub'ın kendisi
 * masadan siparişi anlatır ve kardeşlerin YOKLUĞUNU açıkça yazar.
 *
 * ═══ BİLEREK YAZILMAYANLAR ═══
 *
 * Masada ödeme, kasa/POS bağlantısı, paket servis, gel-al, ön sipariş,
 * rezervasyon, açık hesap ve hesap bölme, sipariş notu ("soğansız"), mutfak
 * fişi yazıcısı, sesli/anlık bildirim, misafirin siparişini takip ettiği
 * ekran. Ölçüm 2026-09-08: hiçbirinin depoda karşılığı yok. Yokluğu YAZMAK
 * bu sayfanın işinin yarısıdır: masadan sipariş satın alan bir restoran,
 * ödemenin de geleceğini varsayarsa ilk gün hayal kırıklığına uğrar.
 */
final class OrderingPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'urun.siparis',
            locale: 'en',
            metadata: new PageMetadata(
                seoTitle: 'Ordering from the table with a QR code',
                metaDescription: 'A guest scans the code on the table and sends an order; a waiter confirms it before the kitchen sees it. What that covers, and what it does not.',
                h1: 'Ordering from the table',
                breadcrumbTitle: 'Ordering',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'Where you switch it on, a guest scans the code on their own table, builds a basket in the browser and sends it. A waiter confirms or refuses it, and only a confirmed order reaches the kitchen screen. Zabuno carries the order; it does not take the money.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'The order is taken twice, and the second time is from memory', [
                    new BlockEntry(
                        text: 'At a full table the waiter writes on a pad, walks to the kitchen and says it out loud. Between the pad and the pass, a dish changes, a portion is forgotten, and an allergy that was mentioned at the table is not mentioned again.',
                    ),
                    new BlockEntry(
                        text: 'The busiest hour is also the one where the pad is hardest to read. Nobody records what went wrong, so the same evening repeats itself next week.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'The guest writes it, a person still decides it', [
                    new BlockEntry(
                        text: 'The guest picks from the menu already open on their phone, so the dish names, the prices and the declared allergens are the ones you published. Nothing is retyped and nothing is remembered.',
                    ),
                    new BlockEntry(
                        text: 'What the guest sends is a request, not a job. A waiter confirms it, and only then does it appear in the kitchen. Somebody who is not sitting at that table cannot open work on your stove.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'How it works', [
                    new BlockEntry(
                        term: 'You switch it on for the branch',
                        text: 'Ordering is off until you turn it on, and it is turned on one branch at a time. Anyone who can see the orders knows whether the branch is taking them; only the owner can flip the switch.',
                        source: 'app/Http/Controllers/Ordering/UpdateOrderingSwitchController.php',
                    ),
                    new BlockEntry(
                        term: 'The guest scans the code on the table',
                        text: 'The cart appears only when the scanned code belongs to a table, your plan carries the right, the branch is taking orders, and the published menu is priced in one currency.',
                        source: 'app/Http/Controllers/QrDestination/ShowPublicMenuController.php',
                    ),
                    new BlockEntry(
                        term: 'The basket stays on the phone',
                        text: 'Nothing is written on the server until the guest sends the order. There is no account to open and no application to install.',
                        source: 'database/migrations/2026_09_06_000600_create_orders_tables.php',
                    ),
                    new BlockEntry(
                        term: 'The server writes the prices, not the phone',
                        text: 'Only a dish and a quantity are accepted from the guest. The name, the price and the declared allergens are copied from the menu you published, so the guest cannot set what a dish costs.',
                        source: 'app/Application/Ordering/UseCase/BuildOrderLines.php',
                    ),
                    new BlockEntry(
                        term: 'A waiter confirms it, or refuses it with a reason',
                        text: 'Sent orders wait in a queue, oldest first. Confirming and refusing are the same door, and a refusal cannot be saved without a reason written on it.',
                        source: 'app/Http/Controllers/Ordering/ChangeOrderStatusController.php',
                    ),
                    new BlockEntry(
                        term: 'Only then does the kitchen see it',
                        text: 'A waiting order is invisible to the kitchen board. Confirmed, preparing and ready are the only states a cook ever sees.',
                        source: 'app/Domain/Ordering/OrderStatus.php',
                    ),
                    new BlockEntry(
                        term: 'The kitchen moves it, service closes it',
                        text: 'The kitchen marks an order as being prepared and then ready. Marking it delivered belongs to whoever carries the plate, not to the stove.',
                        source: 'app/Http/Controllers/Ordering/ListKitchenOrdersController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'What you can do', [
                    new BlockEntry(
                        term: 'The table is read, never typed',
                        text: 'The order lands on the table whose code was scanned. A guest cannot pick a table, so an order on the wrong table is not a mistake this product can make.',
                        source: 'database/migrations/2026_08_22_000006_create_dining_areas_and_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Nothing to install, nothing to sign up for',
                        text: 'The guest orders from the same web page that shows the menu, without an account and without giving a name, an email address or a phone number.',
                        source: 'routes/web.php',
                    ),
                    new BlockEntry(
                        term: 'The order keeps the price it was sent at',
                        text: 'Names, prices and allergens are copied into the order line. Renaming a dish or changing a price tonight does not rewrite this evening\'s tickets.',
                        source: 'database/migrations/2026_09_06_000600_create_orders_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Declared allergens travel with the line',
                        text: 'What you declared for a dish is carried onto the kitchen board with the order, so it is read where the food is made and not only where it was ordered.',
                        source: 'app/Application/Ordering/Dto/OrderLineSummary.php',
                    ),
                    new BlockEntry(
                        term: 'Two boards, two different permissions',
                        text: 'The service queue and the kitchen board are separate. A kitchen account sees confirmed work, allergens and sold-out marks, and cannot confirm an order or read anything else about the business.',
                        source: 'app/Domain/Authorization/RolePermissions.php',
                    ),
                    new BlockEntry(
                        term: 'Two people cannot confirm the same order',
                        text: 'A status change is written on the condition that the order is still where it was. The second waiter is told what the order is now, instead of being told the confirmation worked.',
                        source: 'app/Infrastructure/Ordering/Persistence/EloquentOrderRepository.php',
                    ),
                    new BlockEntry(
                        term: 'A history that is not deleted',
                        text: 'Every order stays, with what was in it, what happened to it, when, and the reason if it was refused. There is no way to remove an evening from the record.',
                        source: 'app/Http/Controllers/Ordering/ListOrderHistoryController.php',
                    ),
                    new BlockEntry(
                        term: 'A ceiling on open orders per table',
                        text: 'One table can hold five orders that are still open. Closed orders do not count, so a table eating all evening can keep ordering.',
                        source: 'app/Http/Controllers/Ordering/StoreGuestOrderController.php',
                    ),
                    new BlockEntry(
                        term: 'The boards say when they last updated',
                        text: 'The queue and the kitchen board refresh every ten seconds and print the moment of the last successful refresh, using the server\'s clock rather than the screen\'s.',
                        source: 'resources/js/components/workspace/pages/orders/useOrderFeed.ts',
                    ),
                    new BlockEntry(
                        term: 'Orders sent are counted in the report',
                        text: 'Sending an order is recorded as an event beside scans and menu opens, so the menu report can show it. What was in the order is not copied into the reporting table.',
                        source: 'app/Domain/Analytics/AnalyticsEventType.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'What you need', [
                    new BlockEntry(
                        term: 'A code that belongs to a table',
                        text: 'A poster, a door code or a business card cannot carry an order, because there is no table behind it. Such a scan is refused and the guest is asked to scan the code on their own table.',
                        source: 'app/Http/Controllers/Ordering/StoreGuestOrderController.php',
                    ),
                    new BlockEntry(
                        term: 'A plan that carries the right',
                        text: self::planSentence(),
                        source: 'database/seeders/PlanCatalogueSeeder.php',
                    ),
                    new BlockEntry(
                        term: 'The switch, turned on by you',
                        text: 'A branch does not take orders until somebody turns it on. This is deliberate: a restaurant that never opens the panel should never have work appear on its stove.',
                        source: 'database/migrations/2026_09_06_000700_add_ordering_switch_and_frozen_plan.php',
                    ),
                    new BlockEntry(
                        term: 'One currency in the published menu',
                        text: 'The cart shows a total. If the published menu mixes currencies, no total can be honest, so the cart is not drawn at all.',
                        source: 'app/Http/Controllers/QrDestination/ShowPublicMenuController.php',
                    ),
                    new BlockEntry(
                        term: 'Somebody watching a screen',
                        text: 'An order waits until a person confirms it. On the plan that opens ordering, the owner already carries every ordering permission, so no team invitation is needed to start.',
                        source: 'app/Domain/Authorization/RolePermissions.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'What it does not do', [
                    new BlockEntry(
                        term: 'It does not take payment',
                        text: 'No guest pays through Zabuno. An order has no paid state, no card step and no bill; the money is still handled at the table, exactly as it was before.',
                        source: 'app/Domain/Ordering/OrderStatus.php',
                    ),
                    new BlockEntry(
                        term: 'It does not talk to your till',
                        text: 'There is no connection to a point-of-sale system or an accounting package. An order lives in Zabuno and is read on a Zabuno screen.',
                        source: 'routes/api/ordering.php',
                    ),
                    new BlockEntry(
                        term: 'Only from a table in the room',
                        text: 'There is no takeaway, no delivery, no collection and no ordering ahead of arriving. The one way to send an order is to scan the code on a table.',
                        source: 'routes/web.php',
                    ),
                    new BlockEntry(
                        term: 'No table booking',
                        text: 'Reserving a table is not part of this product. Ordering starts with a guest who is already sitting down.',
                        source: 'app/Domain/Authorization/Permission.php',
                    ),
                    new BlockEntry(
                        term: 'The guest has no screen after sending',
                        text: 'The guest is told the order was received, and that is where the phone\'s part ends. There is no page that follows the order, no way to cancel it from the phone, and a refusal reason is read out at the table rather than pushed to it.',
                        source: 'resources/js/i18n/guest.ts',
                    ),
                    new BlockEntry(
                        term: 'No notes and no options on a dish',
                        text: 'An order line is a dish and a quantity. "No onions", "well done" and a choice of sauce cannot be sent, because a dish has no options in the menu either.',
                        source: 'database/migrations/2026_09_06_000600_create_orders_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Nothing prints and nothing beeps',
                        text: 'There is no kitchen ticket printer, no sound and no push notification. Orders appear on a screen that refreshes itself, so the screen has to be visible to somebody.',
                        source: 'resources/js/components/workspace/kitchen/KitchenMonitor.tsx',
                    ),
                    new BlockEntry(
                        term: 'No running tab and no split bill',
                        text: 'Each order carries its own total. The product does not add a table\'s orders into one bill and does not divide one between guests.',
                        source: 'app/Application/Ordering/Dto/OrderSummary.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'Questions owners ask about ordering', [
                    new BlockEntry(
                        term: 'Can my guests pay through the menu?',
                        text: 'No. Ordering carries the request to your kitchen; the payment is taken at the table the way it is today. There is no card step anywhere in this flow.',
                    ),
                    new BlockEntry(
                        term: 'Does an order go straight to the kitchen?',
                        text: 'No, and that is the point. It waits in the service queue until a waiter confirms it. The kitchen board never shows an unconfirmed order.',
                    ),
                    new BlockEntry(
                        term: 'What happens if I never switch ordering on?',
                        text: 'Nothing changes. Guests read the menu as before and no cart is drawn on their phone, so nobody is invited to send an order that would sit unread.',
                    ),
                    new BlockEntry(
                        term: 'Can a guest order from the poster by the door?',
                        text: 'No. A code that is not tied to a table is refused, because there would be nowhere to send the food. The guest is asked to scan the code on their own table.',
                    ),
                    new BlockEntry(
                        term: 'Can a guest change or cancel an order after sending it?',
                        text: 'Not from the phone. The guest asks the staff, and the waiter refuses the order with a reason, which stays on the record.',
                    ),
                    new BlockEntry(
                        term: 'Does the kitchen screen make a sound when an order arrives?',
                        text: 'No. It refreshes every ten seconds and shows when it last updated, so a frozen screen can be told apart from a quiet evening.',
                    ),
                    new BlockEntry(
                        term: 'Can a guest ask for a dish without onions?',
                        text: 'No. An order line is a dish and a quantity; there is no note field and no option list. Those requests are still made out loud.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'See which plan opens ordering', [
                    new BlockEntry(
                        text: 'Reading the menu costs nothing; sending an order from the table is part of a paid plan.',
                        href: '/pricing',
                        term: 'See the plans',
                    ),
                ]),

                new ContentBlock(BlockType::Related, 'Related pages', [
                    new BlockEntry(text: 'Tables and QR codes', pageKey: 'urun.masa-ve-qr-yonetimi'),
                    new BlockEntry(text: 'QR menu', pageKey: 'urun.qr-menu'),
                    new BlockEntry(text: 'Analytics', pageKey: 'urun.analitik'),
                ]),
            ],
        );
    }

    /**
     * Sipariş hakkını taşıyan planlar — KATALOGDAN, elle değil.
     *
     * Plan adını buraya yazmak bugün doğru olurdu ve katalogda yapılan ilk
     * düzenlemede sessizce eskirdi: sayfa "Restaurant" derken ürün başka bir
     * kademede satıyor olurdu, ve bunu ancak parasını ödeyip sipariş
     * gönderemeyen bir restoran fark ederdi.
     *
     * Boş liste de bir cevaptır ve uydurulmaz: hakkı hiçbir plan taşımıyorsa
     * cümle bunu söyler.
     */
    private static function planSentence(): string
    {
        $names = [];

        foreach (PlanCatalogueSeeder::catalogue() as $plan) {
            if (in_array(Entitlement::OrderingBasic->value, $plan['entitlements'], true)) {
                $names[] = $plan['name'];
            }
        }

        if ($names === []) {
            return 'No plan in the catalogue opens ordering from the table today, so the cart is not drawn for any guest.';
        }

        return 'Ordering from the table is opened by these plans: '.self::joined($names)
            .'. Without one, the cart is never drawn, and an order sent by hand is refused with the name of the missing right.';
    }

    /**
     * Virgülle ayrılmış liste, sonunda "and".
     *
     * @param  list<string>  $names
     */
    private static function joined(array $names): string
    {
        $last = array_pop($names);

        if ($last === null) {
            return '';
        }

        return $names === [] ? $last : implode(', ', $names).' and '.$last;
    }
}
