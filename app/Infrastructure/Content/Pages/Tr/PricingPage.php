<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages\Tr;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;
use App\Domain\Money\MoneyFormatter;
use Database\Seeders\PlanCatalogueSeeder;

/**
 * `/tr/fiyatlandirma/` — plan ve fiyat sayfasının Türkçesi (P0).
 *
 * Türkçe sayfa da rakam YAZMAZ, KATALOĞU OKUR. Plan adı, tutar ve her planın
 * açtığı haklar `PlanCatalogueSeeder::catalogue()`'dan gelir; tek fark,
 * tutarın Türkçe biçimlendirilmesi (`MoneyFormatter::format(..., 'tr')`) ve
 * hakların Türkçe adlarıdır. Elle yazılmış bir fiyat, iki dilde iki ayrı
 * yalan üretirdi.
 *
 * `WITHHELD` burada da vardır ve İngilizce aslıyla AYNI anahtarları taşımak
 * zorundadır: bir hakkın bir dilde duyurulup öteki dilde susulması, iki
 * farklı ürün satmak olurdu.
 *
 * BİLEREK YAZILMAYANLAR (İngilizce aslıyla aynı): kampanya, indirim, deneme
 * süresi, yıllık ödeme indirimi, "en popüler" rozeti, para iade garantisi,
 * kurulum ücreti, koltuk ya da şube başı fiyat.
 */
final class PricingPage
{
    /**
     * Fiyat sayfasında ANLATILAN haklar ve Türkçe adları.
     *
     * Anahtar kümesi `App\Infrastructure\Content\Pages\PricingPage::ANNOUNCED`
     * ile birebir aynıdır ve test bunu ölçer.
     *
     * @var array<string, string>
     */
    public const ANNOUNCED = [
        'qr.bulk-generation' => 'bütün bir salonun kodlarını tek seferde üretmek',
        'analytics.reporting' => 'raporlar',
        'branding.custom' => 'misafir sayfasında kendi görünümünüz',
        'team.invitations' => 'sahibin yanındaki kişiler',
        'ordering.basic' => 'masadan gönderilen siparişler',
        'menu.rich-media' => 'misafir menüsünde ürün fotoğrafları',
    ];

    /**
     * Katalogda bulunabilen ama BİLEREK duyurulmayan haklar.
     *
     * İngilizce aslı gibi bugün BOŞ ve bu bir sonuçtur, bir kural değil.
     * Mekanizma duruyor: satılabilen ama misafir yüzeyi olmayan bir sonraki
     * hak buraya, gerekçesiyle birlikte yazılır.
     *
     * @var array<string, string>
     */
    public const WITHHELD = [];

    /** Katalogdaki tutarların para birimi (`PlanCatalogueSeeder`). */
    private const CURRENCY = 'TRY';

    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'fiyatlandirma',
            locale: 'tr',
            metadata: new PageMetadata(
                seoTitle: 'Zabuno planları ve fiyatları',
                metaDescription: 'Hangi plan neyi açar, plansız ne açık kalır ve bir plan gerçekte nasıl başlatılır. Karar vermeden önce planları okuyun.',
                h1: 'Planlar ve fiyatlar',
                breadcrumbTitle: 'Fiyatlandırma',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'Menü yazmak, yayınlamak ve masalar için kod bastırmak hiçbir ödeme yapmadan çalışır. Plan bunun üstüne haklar ekler: '
                            .self::joined(self::announcedAcrossCatalogue()).'.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'Çoğu fiyat sayfası önemli yarıyı saklar', [
                    new BlockEntry(
                        text: 'Onay işaretlerinden oluşan bir tablo size pahalı sütunun neye sahip olduğunu söyler. Ödemeyi bıraktığınız gün ne olacağını ya da ürünün hangi kısmını en baştan beri plansız kullandığınızı ise nadiren söyler.',
                    ),
                    new BlockEntry(
                        text: 'O sessizliğin bedeli sonra ödenir. Menünün kendisinin kiralık olduğunu sanan bir işletmeci, menünün kendisine ait olduğunu bilenden başka türlü davranır.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'Temel yolculuk asla satılık değildir', [
                    new BlockEntry(
                        text: 'Kaydolmak, menüyü yazmak, yayınlamak, kod bastırmak ve misafirin onu okuması temel yolculuktur ve hiçbir plan bunları kapatmaz. Bu depodaki bir test o sözü dondurur; böylece durgun bir ayda sessizce geri alınamaz.',
                    ),
                    new BlockEntry(
                        text: 'Bir planın neyi açtığı aşağıda yazılı ve bu, ürünün çalışma alanınızın ne yapabileceğine karar verirken okuduğu listenin ta kendisi. Tanıtım sayfası için ikinci bir liste yoktur.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'Bir plan nasıl çalışır', [
                    new BlockEntry(
                        term: 'Tek katalog, bu sayfa tarafından okunur',
                        text: 'Bu sayfadaki adlar, tutarlar ve haklar, ürünün kendisinin kurduğu plan kataloğundan okunur. Katalog değiştiğinde bu sayfa da onunla birlikte değişir.',
                        source: 'database/seeders/PlanCatalogueSeeder.php',
                    ),
                    new BlockEntry(
                        term: 'Plan bir ekran kümesi değil, bir haklar kümesidir',
                        text: 'Her hak kodda adlandırılmış bir değerdir. Yanlış yazılmış bir hak, sessizce her şeyi vermek yerine hiçbir şey vermez.',
                        source: 'app/Domain/Entitlement/Entitlement.php',
                    ),
                    new BlockEntry(
                        term: 'Hak, kullanıldığı yerde denetlenir',
                        text: 'Çalışma alanı, planının verdiği hak kümesini taşır ve tanınmayan bir anahtar girişte düşer. Bir ekran sormayı unuttuğu için hiçbir şey verilmez.',
                        source: 'app/Domain/Entitlement/EntitlementSet.php',
                    ),
                    new BlockEntry(
                        term: 'Ödeme, arkasındaki belgeyle birlikte kaydedilir',
                        text: 'Plan, alınan ödemenin yazılmasıyla açılır: hangi plan, hangi belge ve planın hangi tarihe kadar sürdüğü. İki kez kaydedilen aynı ödeme tek bir ödeme olarak kalır.',
                        source: 'app/Http/Controllers/PlatformAdmin/StoreManualPaymentController.php',
                    ),
                    new BlockEntry(
                        term: 'Misafir, menünün yayınlandığı planı görür',
                        text: 'Haklar yayının içine dondurulur. Bir plan sona ererse masadaki basılı kod, basıldığı sayfayı göstermeye devam eder ve değişiklik bir sonraki yayında yerine oturur.',
                        source: 'database/migrations/2026_09_06_000700_add_ordering_switch_and_frozen_plan.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'Planlar', self::planEntries()),

                new ContentBlock(BlockType::Requirements, 'Neye ihtiyacınız var', [
                    new BlockEntry(
                        term: 'Bir çalışma alanı',
                        text: 'Plan bir kişiye değil bir çalışma alanına aittir. Bir çalışma alanı tek bir plan taşır.',
                        source: 'database/migrations/2026_08_23_000013_create_subscriptions_and_manual_payments_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Faturalama tarafını görme yetkisi',
                        text: 'Çalışma alanının içinde plan ve mevcut abonelik, faturalama yetkisi taşıyan üyelere gösterilir. Ötekiler için sayfa boş değildir, hiç yoktur.',
                        source: 'app/Http/Controllers/Billing/ShowSubscriptionController.php',
                    ),
                    new BlockEntry(
                        term: 'Başlatmak için bizimle konuşmak',
                        text: 'Ücretli bir planı başlatmak bizden geçer: ödeme alınır, sonra belge referansıyla birlikte çalışma alanınıza kaydedilir.',
                        source: 'app/Http/Requests/Billing/StoreManualPaymentRequest.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'Bu sayfanın sunmadıkları', [
                    new BlockEntry(
                        term: 'Bu sitede kartla ödeme yok',
                        text: 'Buraya bir kart numarası yazıp bir dakika sonra planda olamazsınız. Kart entegrasyonu yalnız sağlayıcının kum havuzuna karşı vardır; orası bir test ortamıdır, yazarkasa değil.',
                        source: 'app/Infrastructure/Billing/Provider/IyzipaySandboxGateway.php',
                    ),
                    new BlockEntry(
                        term: 'Otomatik yenileme yok',
                        text: 'Plan, ödeme kaydedilirken yazılan bir tarihe kadar sürer. Hiçbir şey kendini yenilemez ve istenmeden yeniden tahsil edilmez.',
                        source: 'app/Application/Billing/UseCase/ManageSubscriptions.php',
                    ),
                    new BlockEntry(
                        term: 'Deneme yok, indirim yok, kampanya yok',
                        text: 'Katalog bir ad, bir tutar ve bir haklar kümesi tutar. Deneme süresi, tanıtım fiyatı ve talep edilecek yıllık indirim yoktur.',
                        source: 'database/migrations/2026_08_23_000011_create_plans_table.php',
                    ),
                    new BlockEntry(
                        term: 'Şube ya da kişi başı değil, tek fiyat',
                        text: 'Plan, çalışma alanı için tek bir tutar taşır. Şube ya da ekip üyesi eklemek planın fiyatını değiştirmez.',
                        source: 'app/Application/Billing/Dto/PlanSummary.php',
                    ),
                    new BlockEntry(
                        term: 'Tek para birimi',
                        text: 'Katalog fiyatları Türk lirasıdır. İkinci bir para birimi ve ödeme sırasında çevrim yoktur.',
                        source: 'database/seeders/PlanCatalogueSeeder.php',
                    ),
                    new BlockEntry(
                        term: 'Burada gezilecek bir fatura arşivi yok',
                        text: 'Çalışma alanı, üzerinde olduğu planı ve hangi tarihe kadar sürdüğünü gösterir. Geçmiş ödemeler bizim tarafımızda kayıt olarak tutulur, indirilecek dosya olarak değil.',
                        source: 'app/Application/Billing/Dto/SubscriptionSummary.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'Ödemeden önceki sorular', [
                    new BlockEntry(
                        term: 'Ödemeyi bırakırsam menüme ne olur?',
                        text: 'Menü, kodlar ve misafir sayfası çalışmaya devam eder: onların zaten hiçbir zaman plana ihtiyacı olmadı. Kapanan şey planın açtığı haklardır ve elinde basılı kod olan misafirler için ancak bir sonraki yayında.',
                    ),
                    new BlockEntry(
                        term: 'Şu anda kartla ödeyebilir miyim?',
                        text: 'Bu sitede hayır. Kart entegrasyonu sağlayıcının kum havuzuna karşı çalışır ve orası bir test ortamıdır. Plan, bizimle konuşup ödemeyi kaydettirerek başlatılır.',
                    ),
                    new BlockEntry(
                        term: 'Kendiliğinden yenilenir mi?',
                        text: 'Hayır. Plan, ödeme kaydedilirken yazılan bir tarihe kadar sürer ve orada durur.',
                    ),
                    new BlockEntry(
                        term: 'Ücretsiz deneme var mı?',
                        text: 'Daha iyisi var ve kalıcı: temel yolculuk hiçbir şeye mal olmaz. Hiçbir karar vermeden önce bütün menüyü yazabilir, yayınlayabilir ve kod bastırabilirsiniz.',
                    ),
                    new BlockEntry(
                        term: 'İkinci bir şube daha fazlaya mı mal olur?',
                        text: 'Hayır. Plan, içinde ne olursa olsun çalışma alanı için tek bir tutar taşır.',
                    ),
                    new BlockEntry(
                        term: 'En ucuz plan neden ücretsiz?',
                        text: 'Çünkü zaten öyle. Var olan davranışa plan demek bir olguyu adlandırmaktır; bir bedeli varmış gibi yapmak ise ürünün nasılsa yaptığı şey için para almak olurdu.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'Bir planla ya da plansız başlayın', [
                    new BlockEntry(
                        text: 'Hangi planın size uyduğunu söyleyin, biz onu çalışma alanınıza kaydedelim.',
                        href: '/contact',
                        term: 'Bizimle konuşun',
                    ),
                ]),

                new ContentBlock(BlockType::Related, 'İlgili sayfalar', [
                    new BlockEntry(text: 'Analitik', pageKey: 'urun.analitik'),
                    new BlockEntry(text: 'Çözümler', pageKey: 'cozumler'),
                ]),
            ],
        );
    }

    /**
     * Plan satırları — KATALOGDAN, elle değil.
     *
     * @return list<BlockEntry>
     */
    private static function planEntries(): array
    {
        $entries = [];

        foreach (PlanCatalogueSeeder::catalogue() as $plan) {
            $entries[] = new BlockEntry(
                term: $plan['name'],
                text: MoneyFormatter::format($plan['amount_minor'], self::CURRENCY, 'tr')
                    .'. '.self::opens($plan['entitlements']),
                source: 'database/seeders/PlanCatalogueSeeder.php',
            );
        }

        return $entries;
    }

    /**
     * Bir planın açtıklarını cümleye çevirir.
     *
     * @param  list<string>  $entitlements
     */
    private static function opens(array $entitlements): string
    {
        $named = self::announced($entitlements);

        if ($named === []) {
            return 'Ürünün plansız yaptığı her şey: menü, yayın, basılı kodlar ve misafirin okuduğu sayfa.';
        }

        return 'Şunları ekler: '.self::joined($named).'.';
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
     * Anahtarların Türkçe adları; duyurulmayanlar düşer.
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
     * Virgülle ayrılmış liste, sonunda "ve".
     *
     * @param  list<string>  $named
     */
    private static function joined(array $named): string
    {
        $last = array_pop($named);

        if ($last === null) {
            return '';
        }

        return $named === [] ? $last : implode(', ', $named).' ve '.$last;
    }
}
