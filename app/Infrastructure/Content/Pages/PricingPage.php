<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;
use App\Domain\Money\MoneyFormatter;
use Database\Seeders\PlanCatalogueSeeder;

/**
 * `/fiyatlandirma/` — plan ve fiyat sayfası (P0).
 *
 * **Bu sayfa rakam YAZMAZ, KATALOĞU OKUR.** Fiyat sayfası, bir sayfanın yalan
 * söylemesinin en pahalı olduğu yerdir: ziyaretçi burada okuduğu rakama göre
 * karar verir ve o rakam kasadakinden farklıysa geri kalan her doğru cümle de
 * değerini kaybeder. Elle yazılmış bir fiyat, sahibin katalogda yaptığı ilk
 * düzenlemede sessizce eskir — ve eskidiğini kimse fark etmez, çünkü iki
 * rakamı karşılaştıran bir şey yoktur. Bu yüzden plan adı, tutar, para birimi
 * ve her planın açtığı haklar `PlanCatalogueSeeder::catalogue()`'dan gelir.
 *
 * **Duyurulmayan hak bir karardır, bir unutma değildir.** `WITHHELD` listesi
 * bunun için var: bir hak katalogda bulunabilir ama misafirin göreceği bir
 * yüzeyi yoksa, parası alındığında masadaki hiçbir şey değişmez. Böyle bir
 * hakkı satmak, çalışmayan bir şeyi satmaktır. Susmak da yazmak kadar açık
 * bir karar olduğu için sebebi burada yazılı ve test onu ölçüyor.
 *
 * BİLEREK YAZILMAYANLAR: kampanya, indirim, deneme süresi, yıllık ödeme
 * indirimi, "en popüler" rozeti, para iade garantisi, kurulum ücreti, koltuk
 * başı ya da şube başı fiyat. Ölçüm 2026-09-06: bunların hiçbiri depoda yok.
 * Bir fiyat sayfasının en kolay uydurduğu şeyler tam olarak bunlardır.
 */
final class PricingPage
{
    /**
     * Fiyat sayfasında ANLATILAN haklar ve İngilizce adları.
     *
     * `Entitlement::label()` burada kullanılamaz: o etiketler Türkçedir ve
     * PANELİN dilidir. Kurumsal sitenin kaynak dili İngilizce (`docs/118` E4)
     * ve bu paket hiçbir çeviri üretmiyor — bu satırlar bir çeviri değil,
     * kurumsal sitenin kendi İngilizce metni.
     *
     * @var array<string, string>
     */
    public const ANNOUNCED = [
        'qr.bulk-generation' => 'codes for a whole room at once',
        'analytics.reporting' => 'the reports',
        'branding.custom' => 'your own look on the guest page',
        'team.invitations' => 'people beside the owner',
        'ordering.basic' => 'orders sent from the table',
    ];

    /**
     * Katalogda bulunabilen ama fiyat sayfasında BİLEREK duyurulmayan haklar.
     *
     * Anahtar burada bir kez yazılır; test hem kapsamayı hem de bu adların
     * sayfanın hiçbir yerinde geçmediğini ölçer.
     *
     * @var array<string, string>
     */
    public const WITHHELD = [
        'menu.rich-media' => 'Hakkın misafir yüzeyi yok: parası alınsa bile masadaki misafirin gördüğü sayfa değişmez. Yüzey açıldığı gün bu satır ANNOUNCED\'a taşınır; o güne kadar susmak, satılamayacak bir şeyi satmamaktır.',
    ];

    /** Katalogdaki tutarların para birimi (`PlanCatalogueSeeder`). */
    private const CURRENCY = 'TRY';

    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'fiyatlandirma',
            locale: 'en',
            metadata: new PageMetadata(
                seoTitle: 'Zabuno plans and prices',
                metaDescription: 'What each plan opens, what stays open without one, and how a plan is actually started. Read the plans before you decide.',
                h1: 'Plans and prices',
                breadcrumbTitle: 'Pricing',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        /*
                            Bu cümlenin kuyruğu da KATALOGDAN geliyor. Elle
                            yazılsaydı bugün doğru olurdu ve katalogda açılan
                            ilk yeni hakla birlikte sessizce eksilirdi —
                            yanlış değil, EKSİK: cevap sistemlerinin
                            alıntıladığı tek cümlede eksik olmak, orada hiç
                            olmamakla aynı kapıya çıkar.
                        */
                        text: 'Writing a menu, publishing it and printing codes for the tables works without paying anything. A plan adds rights on top of that: '
                            .self::joined(self::announcedAcrossCatalogue()).'.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'Most pricing pages hide the important half', [
                    new BlockEntry(
                        text: 'A table of ticks tells you what the expensive column has. It rarely tells you what happens on the day you stop paying, or which part of the product you were using all along without a plan.',
                    ),
                    new BlockEntry(
                        text: 'That silence is expensive later. An owner who believes the menu itself is rented behaves differently from one who knows the menu is his.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'The base journey is never for sale', [
                    new BlockEntry(
                        text: 'Signing up, writing the menu, publishing it, printing a code and the guest reading it are the base journey, and no plan closes them. A test in this repository freezes that promise, so it cannot be quietly withdrawn on a slow month.',
                    ),
                    new BlockEntry(
                        text: 'What a plan opens is listed below, and it is the same list the product reads when it decides what your workspace may do. There is no second list for the marketing page.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'How a plan works', [
                    new BlockEntry(
                        term: 'One catalogue, read by this page',
                        text: 'The names, the amounts and the rights on this page are read from the plan catalogue the product itself installs. When the catalogue changes, this page changes with it.',
                        source: 'database/seeders/PlanCatalogueSeeder.php',
                    ),
                    new BlockEntry(
                        term: 'A plan is a set of rights, not a set of screens',
                        text: 'Every right is a named value in code. A misspelled right grants nothing rather than quietly granting everything.',
                        source: 'app/Domain/Entitlement/Entitlement.php',
                    ),
                    new BlockEntry(
                        term: 'The right is checked where it is used',
                        text: 'The workspace carries the set of rights its plan gives, and an unknown key is dropped on the way in. Nothing is granted because a screen forgot to ask.',
                        source: 'app/Domain/Entitlement/EntitlementSet.php',
                    ),
                    new BlockEntry(
                        term: 'A payment is recorded, with the document behind it',
                        text: 'A plan is opened by writing down the payment that was received: which plan, which document, and the date the plan runs to. The same payment recorded twice stays one payment.',
                        source: 'app/Http/Controllers/PlatformAdmin/StoreManualPaymentController.php',
                    ),
                    new BlockEntry(
                        term: 'The guest sees the plan the menu was published with',
                        text: 'The rights are frozen into the publication. If a plan lapses, the printed code on the table keeps showing the page it was printed for, and the change lands on the next publication.',
                        source: 'database/migrations/2026_09_06_000700_add_ordering_switch_and_frozen_plan.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'The plans', self::planEntries()),

                new ContentBlock(BlockType::Requirements, 'What you need', [
                    new BlockEntry(
                        term: 'A workspace',
                        text: 'A plan belongs to a workspace, not to a person. One workspace carries one plan.',
                        source: 'database/migrations/2026_08_23_000013_create_subscriptions_and_manual_payments_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Permission to see the billing side',
                        text: 'Inside the workspace, the plan and the current subscription are shown to members who carry the billing permission. To others the page is not empty, it is not there.',
                        source: 'app/Http/Controllers/Billing/ShowSubscriptionController.php',
                    ),
                    new BlockEntry(
                        term: 'To talk to us to start one',
                        text: 'Starting a paid plan goes through us: the payment is received, then recorded against your workspace with its document reference.',
                        source: 'app/Http/Requests/Billing/StoreManualPaymentRequest.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'What this page does not offer', [
                    new BlockEntry(
                        term: 'No card checkout on this site',
                        text: 'You cannot type a card number here and be on a plan a moment later. The card integration exists only against the provider\'s sandbox, which is a test environment and not a till.',
                        source: 'app/Infrastructure/Billing/Provider/IyzipaySandboxGateway.php',
                    ),
                    new BlockEntry(
                        term: 'No automatic renewal',
                        text: 'A plan runs to a date that is written down when the payment is recorded. Nothing renews itself and nothing is charged again without being asked for.',
                        source: 'app/Application/Billing/UseCase/ManageSubscriptions.php',
                    ),
                    new BlockEntry(
                        term: 'No trial, no discount, no campaign',
                        text: 'The catalogue holds a name, an amount and a set of rights. There is no trial period, no introductory rate and no annual discount to claim.',
                        source: 'database/migrations/2026_08_23_000011_create_plans_table.php',
                    ),
                    new BlockEntry(
                        term: 'One price, not a price per branch or per person',
                        text: 'A plan carries a single amount for the workspace. Adding a branch or another team member does not change what the plan costs.',
                        source: 'app/Application/Billing/Dto/PlanSummary.php',
                    ),
                    new BlockEntry(
                        term: 'One currency',
                        text: 'The catalogue prices are in Turkish lira. There is no second currency and no conversion at checkout.',
                        source: 'database/seeders/PlanCatalogueSeeder.php',
                    ),
                    new BlockEntry(
                        term: 'No invoice archive to browse here',
                        text: 'The workspace shows the plan it is on and the date it runs to. Past payments are held as records on our side, not as a download.',
                        source: 'app/Application/Billing/Dto/SubscriptionSummary.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'Questions before paying', [
                    new BlockEntry(
                        term: 'What happens to my menu if I stop paying?',
                        text: 'The menu, the codes and the guest page keep working: they never needed a plan. What closes is the rights the plan opened, and only on the next publication for the guests already holding a printed code.',
                    ),
                    new BlockEntry(
                        term: 'Can I pay by card right now?',
                        text: 'Not on this site. The card integration runs against the provider\'s sandbox, which is a test environment. A plan is started by talking to us and having the payment recorded.',
                    ),
                    new BlockEntry(
                        term: 'Will it renew on its own?',
                        text: 'No. A plan runs to a date that was written down when the payment was recorded, and it stops there.',
                    ),
                    new BlockEntry(
                        term: 'Is there a free trial?',
                        text: 'There is something better and it is permanent: the base journey costs nothing. You can write the whole menu, publish it and print codes before deciding anything.',
                    ),
                    new BlockEntry(
                        term: 'Does a second branch cost more?',
                        text: 'No. The plan carries one amount for the workspace, whatever it holds.',
                    ),
                    new BlockEntry(
                        term: 'Why is the cheapest plan free?',
                        text: 'Because it already is. Calling the existing behaviour a plan is naming a fact; pretending it costs something would be charging for what the product does anyway.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'Start a plan, or start without one', [
                    new BlockEntry(
                        text: 'Tell us which plan fits and we will record it against your workspace.',
                        href: '/contact',
                        term: 'Talk to us',
                    ),
                ]),

                new ContentBlock(BlockType::Related, 'Related pages', [
                    new BlockEntry(text: 'Analytics', pageKey: 'urun.analitik'),
                    new BlockEntry(text: 'Solutions', pageKey: 'cozumler'),
                ]),
            ],
        );
    }

    /**
     * Plan satırları — KATALOGDAN, elle değil.
     *
     * Sıra da katalogdan gelir: `sort_order` orada üretiliyor ve iki ayrı
     * sıralama, ziyaretçiye panelde gördüğünden başka bir merdiven gösterirdi.
     *
     * @return list<BlockEntry>
     */
    private static function planEntries(): array
    {
        $entries = [];

        foreach (PlanCatalogueSeeder::catalogue() as $plan) {
            $entries[] = new BlockEntry(
                term: $plan['name'],
                text: MoneyFormatter::format($plan['amount_minor'], self::CURRENCY, 'en')
                    .'. '.self::opens($plan['entitlements']),
                source: 'database/seeders/PlanCatalogueSeeder.php',
            );
        }

        return $entries;
    }

    /**
     * Bir planın açtıklarını cümleye çevirir.
     *
     * Duyurulmayan haklar buradan DÜŞER ve bu, listenin sessizce boşalmasına
     * yol açabilir; o hâlde bile cümle dürüsttür, çünkü söylediği şey
     * "bu plan, anlatılabilir bir şey açmıyor"dur.
     *
     * @param  list<string>  $entitlements
     */
    private static function opens(array $entitlements): string
    {
        $named = self::announced($entitlements);

        if ($named === []) {
            return 'Everything the product does without a plan: the menu, the publication, the printed codes and the page the guest reads.';
        }

        return 'Adds '.self::joined($named).'.';
    }

    /**
     * Katalogdaki BÜTÜN planların açtığı, anlatılan haklar — bir kez, sırayla.
     *
     * @return list<string>
     */
    private static function announcedAcrossCatalogue(): array
    {
        $keys = [];

        foreach (PlanCatalogueSeeder::catalogue() as $plan) {
            foreach ($plan['entitlements'] as $key) {
                $keys[] = $key;
            }
        }

        return self::announced(array_values(array_unique($keys)));
    }

    /**
     * Anahtarların İngilizce adları; duyurulmayanlar düşer.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    private static function announced(array $keys): array
    {
        $named = [];

        foreach ($keys as $key) {
            if (isset(self::ANNOUNCED[$key])) {
                $named[] = self::ANNOUNCED[$key];
            }
        }

        return $named;
    }

    /**
     * Virgülle ayrılmış liste, sonunda "and".
     *
     * @param  list<string>  $named
     */
    private static function joined(array $named): string
    {
        $last = array_pop($named);

        if ($last === null) {
            return '';
        }

        return $named === [] ? $last : implode(', ', $named).' and '.$last;
    }
}
