<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages\Tr;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;

/**
 * `/tr/cozumler/` — çözümler girişinin Türkçesi (P0). Ürün sayfası DEĞİL.
 *
 * Bu sayfanın tek işi iki dilde de aynı: olmayan bir ayrımı satmamak. Üründe
 * TEK işletme türü var ve ürün bir kafeyi bir restorandan ayırt etmiyor.
 * Sektöre özel kip, hazır sektör menüsü, müşteri hikâyesi, sonuç metriği,
 * kasa entegrasyonu, rezervasyon, paket servis ve sadakat programı burada da
 * yazılmaz.
 */
final class SolutionsPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'cozumler',
            locale: 'tr',
            metadata: new PageMetadata(
                seoTitle: 'Zabuno kimin için yapıldı',
                metaDescription: 'Yeme içme sunan yerler için tek bir ürün. Kafe ile salon arasında değişen şey, açtığınız bir kip değil, yazdığınız menüdür.',
                h1: 'Çözümler',
                breadcrumbTitle: 'Çözümler',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'Zabuno, tek bir kafeden birkaç şubeli bir işletmeye kadar yeme içme sunan yerler için tek bir üründür. Mekân türüne göre ayrı bir sürüm yoktur: aralarındaki fark, yazdığınız menü ve tuttuğunuz saatlerdir.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'Sektöre göre satılan yazılım genellikle aynı yazılımdır', [
                    new BlockEntry(
                        text: 'Bu sektörün web sitelerinin çoğu mekân türü başına bir sayfa taşır ve on bir sayfanın arkasında yalnızca adı değiştirilmiş tek bir ürün durur. Bir fırının sahibi fırın için yazılmış bir sayfayı okur ve fırın için hiç yapılmamış bir şeyi satın alır.',
                    ),
                    new BlockEntry(
                        text: 'Bedeli sonra ödenir: vaat edilen farkın var olmadığı ve ima edilen ayarın bulunamadığı gün.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'Tek ürün ve dürüstçe adlandırılmış farklar', [
                    new BlockEntry(
                        text: 'Zabuno tek bir işletme türü tanır ve bunu kendi kodunda söyler. Bir kafe ile bir fine dining salonu aynı menü düzenleyicisini, masadaki aynı kodları ve aynı raporları alır.',
                    ),
                    new BlockEntry(
                        text: 'Gerçek farklar yine gerçektir ve zaten var olan şeylerle karşılanır: saatine göre birbirine devreden birden çok menü, şube başına belirlenen fiyatlar, gün gün çalışma saatleri ve mutfağın hazır olduğunda açtığı sipariş.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'Tek ürün farklı salonlara nasıl uyar', [
                    new BlockEntry(
                        term: 'Bilerek tek bir işletme türü',
                        text: 'Ürün yalnızca yeme içme işletmelerini tanır, başkasını değil. Davranışı değiştirmeyen bir ayrım veriye yazılmaz.',
                        source: 'app/Domain/Publication/BusinessType.php',
                    ),
                    new BlockEntry(
                        term: 'Kahvaltı, öğle ve gece menüsü',
                        text: 'Bir şube, o şubenin kendi saat diliminde saatine göre birbirinden devralan birkaç menü tutabilir. Bir kafenin ve bir barın birbirinden gerçekten istediği şey budur.',
                        source: 'database/migrations/2026_09_05_000400_allow_many_menus_per_location.php',
                    ),
                    new BlockEntry(
                        term: 'Kodlara salon karar verir',
                        text: 'Kodlar masa ya da alan başına üretilir; böylece on iki masalık bir salon ile tek kodlu bir tezgâh, farklı kullanılan aynı üründür.',
                        source: 'database/migrations/2026_08_22_000005_create_qr_destination_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Sipariş yalnız mutfağın baktığı yerde',
                        text: 'Masadan sipariş göndermek şube başına açılır ve kapalı başlar; çünkü sessiz bir tezgâh, kimsenin bakmadığı bir siparişi asla almamalıdır.',
                        source: 'database/migrations/2026_09_06_000700_add_ordering_switch_and_frozen_plan.php',
                    ),
                    new BlockEntry(
                        term: 'Sizin diliniz ve sizin paranız',
                        text: 'İşletme, menüsünün yazıldığı dili ve tahsil ettiği para birimini belirler; misafir sayfası ikisini de izler.',
                        source: 'database/migrations/2026_08_19_000001_create_brands_and_locations_tables.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'Adı ne olursa olsun her mekânın aldığı şeyler', [
                    new BlockEntry(
                        term: 'Misafirin kendi tarayıcısında açtığı bir menü',
                        text: 'Masada bir kod, uygulamasız açılan bir sayfa ve servisin ortasında düzeltebileceğiniz bir menü.',
                        source: 'app/Http/Controllers/QrDestination/ShowPublicMenuController.php',
                    ),
                    new BlockEntry(
                        term: 'Canlı bir belge değil, yayınlanmış bir sürüm',
                        text: 'Misafirler yayınladığınız bir sürümü okur. Servis sırasında taslağı düzenlemek, o anda birinin okuduğunu değiştirmez.',
                        source: 'database/migrations/2026_08_22_000004_create_menu_publications_table.php',
                    ),
                    new BlockEntry(
                        term: 'İşletmenin sahip olduğu kadar şube',
                        text: 'Bir hesap tek bir marka ve istediğiniz kadar şube tutar; her biri kendi menüleri, fiyatları ve saatleriyle.',
                        source: 'database/migrations/2026_08_19_000001_create_brands_and_locations_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Kötü bağlantıdaki bir telefon için boyutlanmış fotoğraflar',
                        text: 'Telefonunuzun çektiği görseli yükleyin; misafir, gösterildiği boyuta göre üretilmiş küçük bir kopya alır.',
                        source: 'app/Infrastructure/Media/Processing/GdMediaAssetProcessor.php',
                    ),
                    new BlockEntry(
                        term: 'Misafirlerin neye baktığı ve boşuna ne aradığı',
                        text: 'Okutma, açılış, ürün görüntüleme ve sonuçsuz arama sayıları — basılı bir menünün size asla gösteremeyeceği talep.',
                        source: 'app/Domain/Analytics/AnalyticsEventType.php',
                    ),
                    new BlockEntry(
                        term: 'Anlamlı olduğu yerde masadan sipariş',
                        text: 'Sahibin bunu açtığı şubelerde misafirler bir sepet kurup mutfağa gönderebilir.',
                        source: 'app/Http/Controllers/Ordering/UpdateOrderingSwitchController.php',
                    ),
                    new BlockEntry(
                        term: 'Gerçek rolleri olan bir ekip',
                        text: 'Müdürler, editörler ve bütün işletmeyi değil alerjenleri ile neyin tükendiğini gören bir mutfak rolü.',
                        source: 'app/Domain/Tenancy/MembershipRole.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'Neye ihtiyacınız var', [
                    new BlockEntry(
                        term: 'Bir işletme ve en az bir şube',
                        text: 'Adı olan bir marka ve adresi ile saat dilimi olan bir şube. Tek mekânlı bir kafe, tek şubeli bir işletmedir.',
                        source: 'app/Http/Controllers/Tenancy/StoreLocationController.php',
                    ),
                    new BlockEntry(
                        term: 'Yayınlamaya razı olduğunuz bir menü',
                        text: 'Adları ve para biriminizde fiyatları olan ürünler. Yayınlamak otomatik kayıt değil, bilinçli bir eylemdir.',
                        source: 'app/Http/Controllers/Publication/StorePublicationController.php',
                    ),
                    new BlockEntry(
                        term: 'Kodu misafirin önüne koyacak bir yol',
                        text: 'Masada basılı bir kart, bir etiket ya da tezgâhın yanında bir kod. Basılabilir tabakayı Zabuno üretir; kâğıt sizindir.',
                        source: 'app/Http/Controllers/QrDestination/ExportQrCardController.php',
                    ),
                    new BlockEntry(
                        term: 'Başlamak için plan gerekmez',
                        text: 'Menüyü yazmak, yayınlamak ve kod bastırmak ödeme yapmadan çalışır. Planlar raporları, marka görünümünü ve ekibi açar.',
                        source: 'database/seeders/PlanCatalogueSeeder.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'Ne değildir', [
                    new BlockEntry(
                        term: 'Yazarkasa ya da satış noktası değildir',
                        text: 'Zabuno misafirden ödeme almaz, adisyon basmaz ve gününüzün ne kadar ettiğini bilmez.',
                        source: 'app/Application/Ordering/UseCase/BuildOrderLines.php',
                    ),
                    new BlockEntry(
                        term: 'Rezervasyon, kurye ve sadakat yok',
                        text: 'Masa rezervasyonu, teslimat ya da kurye entegrasyonu ve puan kartı yoktur. Bunlar kapalı değildir; yokturlar.',
                        source: 'app/Domain/Authorization/Permission.php',
                    ),
                    new BlockEntry(
                        term: 'Sektör kipi ve hazır menü yok',
                        text: 'Kafe ayarı, fırın şablonu ya da mekân türünüz için içe aktarılacak bir başlangıç menüsü yoktur. Menünüzü yazmak size aittir.',
                        source: 'app/Domain/Publication/BusinessType.php',
                    ),
                    new BlockEntry(
                        term: 'Bu sayfada müşteri hikâyesi ya da rakam yok',
                        text: 'Burada daha hızlı servis ya da daha yüksek hesap hakkında bir yüzde okumayacaksınız. Böyle bir ölçüm yok ve birini yazmak onu uydurmak olurdu.',
                        source: 'app/Support/Seo/CorporatePageStructuredData.php',
                    ),
                    new BlockEntry(
                        term: 'Raporlarda paraya dair hiçbir şey yok',
                        text: 'Raporlar ciroyu değil ilgiyi sayar. Gelir raporlamasına ihtiyacı olan bir mekânın onu başka bir yerden alması gerekir.',
                        source: 'app/Application/Analytics/Port/AnalyticsRepositoryPort.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'İşletmecilerin kapsam hakkında sorduğu sorular', [
                    new BlockEntry(
                        term: 'Kafeler için ayrı bir sürüm var mı?',
                        text: 'Hayır ve bu bir eksiklik değil, dürüst cevaptır. Kafe aynı ürünü kullanır; onu kafe yapan şey yazdığı menü ve tuttuğu saatlerdir.',
                    ),
                    new BlockEntry(
                        term: 'Gece menüsü olan bir bar için çalışır mı?',
                        text: 'Evet. Bir şube kendi saat diliminde saatine göre birbirine devreden birkaç menü çalıştırabilir; böylece gece listesi kendiliğinden belirir.',
                    ),
                    new BlockEntry(
                        term: 'Yazarkasamın yerini alabilir mi?',
                        text: 'Hayır. Zabuno menüyü gösterir ve bir siparişi mutfağa taşıyabilir. Ödeme almaz ve hasılatınızı raporlamaz.',
                    ),
                    new BlockEntry(
                        term: 'Tek küçük bir yerim var, bu fazla mı gelir?',
                        text: 'Tek mekân, tek şubeli bir işletmedir ve temel yolculuk hiçbir şeye mal olmaz. Birkaç şube için yapılmış parçalar yolunuzdan çekilir.',
                    ),
                    new BlockEntry(
                        term: 'Mekân türüm için hazır bir menü şablonunuz var mı?',
                        text: 'Hayır. Bir sektör için içe aktarılacak hazır bir menü yoktur.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'Neye mal olduğunu ve neye olmadığını görün', [
                    new BlockEntry(
                        text: 'Planlar, her birinin neyi açtığı ve hiçbir zaman plana ihtiyaç duymayan kısım.',
                        href: '/pricing',
                        term: 'Plan sayfasına gidin',
                    ),
                ]),

                new ContentBlock(BlockType::Related, 'İlgili sayfalar', [
                    new BlockEntry(text: 'QR menü', pageKey: 'urun.qr-menu'),
                    new BlockEntry(text: 'Çoklu şube', pageKey: 'urun.coklu-sube'),
                    new BlockEntry(text: 'Fiyatlandırma', pageKey: 'fiyatlandirma'),
                ]),
            ],
        );
    }
}
