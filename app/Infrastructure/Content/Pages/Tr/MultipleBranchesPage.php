<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages\Tr;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;

/**
 * `/tr/urun/coklu-sube/` — çoklu şube sayfasının Türkçesi (P0).
 *
 * Sayfanın en çok yanıltabileceği yer iki dilde de aynı: "merkezi yönetim".
 * Bugün şubeler AYRI çalışır ve Türkçe sayfa da bunu açıkça yazar; merkezi
 * menü yayını, şube karşılaştırma raporu, franchise standartları, şubeye
 * bağlı personel yetkisi, tek hesapta ikinci marka ve şube silme yoktur.
 */
final class MultipleBranchesPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'urun.coklu-sube',
            locale: 'tr',
            metadata: new PageMetadata(
                seoTitle: 'Tek hesapta birden çok şube',
                metaDescription: 'Her şube tek bir işletmenin altında kendi menülerini, fiyatlarını, kodlarını ve çalışma saatlerini tutar; aralarında geçiş üst çubukta tek tıktır.',
                h1: 'Çoklu şube',
                breadcrumbTitle: 'Çoklu şube',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'Tek bir işletme, ücretsiz olan dahil her planda ihtiyaç duyduğu kadar şube tutabilir. Her şubenin kendi menüleri, kendi fiyatları, kendi kodları ve kendi çalışma saatleri vardır; aralarında üst çubuktan geçersiniz.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'İkinci şube ikinci kopya değildir', [
                    new BlockEntry(
                        text: 'İkinci şube açılır ve hiçbir şey birbirini tutmaz. Öğle menüsü bir saat sonra başlar, iki ürün orada hiç servis edilmez ve fiyatlar birinci şubenin fiyatları değildir.',
                    ),
                    new BlockEntry(
                        text: 'Tek menüyü paylaşmaya zorlanınca cevap ikinci bir hesap olur: ikinci bir giriş, ikinci bir logo yüklemesi, her şeyin ikincisi ve ikisini birlikte görmenin hiçbir yolu.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'Tek işletme, kendi ayakları üstünde duran şubeler', [
                    new BlockEntry(
                        text: 'İşletme tektir: tek marka, tek logo, tek ekip, tek plan. Onun altında şube, kendi adresi, kendi saat dilimi ve kendi haftalık çalışma saatleri olan gerçek bir şeydir.',
                    ),
                    new BlockEntry(
                        text: 'Bağımsızlık bir eksiklik değil, işin özüdür. Bir şubede düzeltilen fiyat kazayla başka bir şubeye ulaşamaz, çünkü iki menü hiçbir zaman aynı satırlar değildi.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'Şubeler nasıl birleşir', [
                    new BlockEntry(
                        term: 'Tek işletme, çok şube',
                        text: 'Bir çalışma alanı tek bir marka tutar ve o marka eklediğiniz kadar şube tutar. İkinci bir marka ikinci bir çalışma alanı ister.',
                        source: 'database/migrations/2026_08_19_000001_create_brands_and_locations_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Saat şubenin kendisine aittir',
                        text: 'Saat dilimi işletmenin değil şubenin özelliğidir. Başka bir şehirdeki şube kendi yerel akşamında kapanır.',
                        source: 'database/migrations/2026_08_28_000100_move_timezone_ownership_to_locations.php',
                    ),
                    new BlockEntry(
                        term: 'Çalışma saatleri, gün gün',
                        text: 'Her şube haftalık çalışma saatleri taşır ve kapalı olan bir gün, henüz kimsenin doldurmadığı bir günden farklı saklanır.',
                        source: 'database/migrations/2026_09_06_000100_create_location_opening_hours_table.php',
                    ),
                    new BlockEntry(
                        term: 'Menüler bir şubeye aittir',
                        text: 'Menü tek bir şube için yazılır. Aynı şubede birden çok menü yaşayabilir ve saatine göre, o şubenin kendi saatiyle birbirine devredebilir.',
                        source: 'database/migrations/2026_09_05_000400_allow_many_menus_per_location.php',
                    ),
                    new BlockEntry(
                        term: 'Her şube kendisi için yayınlar',
                        text: 'Yayınlamak o şube için bir sürüm yazar. Bir şubede yarının menüsünü düzeltmek, öteki şubenin akşamını yeniden basmaz.',
                        source: 'database/migrations/2026_08_22_000004_create_menu_publications_table.php',
                    ),
                    new BlockEntry(
                        term: 'Geçiş tek bir denetimdir',
                        text: 'Üzerinde çalıştığınız şube üst çubuktan seçilir. Tek şube varken denetim hiç gösterilmez, çünkü tek seçenek bir seçim değildir.',
                        source: 'resources/js/components/workspace/shell/WorkspaceContextControls.tsx',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'Şubeler arasında neler yapabilirsiniz', [
                    new BlockEntry(
                        term: 'Kimseye sormadan şube ekleyin',
                        text: 'Şube, adresi ve saat dilimiyle birlikte şubeler ekranından oluşturulur. Hiçbir planda şube sınırı yoktur ve ücretsiz plan da istisna değildir.',
                        source: 'app/Http/Controllers/Tenancy/StoreLocationController.php',
                    ),
                    new BlockEntry(
                        term: 'Şubeye göre değişen fiyatlar',
                        text: 'Ürün işletme genelinde aynı üründür; ne tuttuğu ise şubenin kendi menüsünde yazılıdır. Havalimanı fiyatı ile cadde fiyatı hiçbir kurnazlığa gerek kalmadan yan yana yaşar.',
                        source: 'database/migrations/2026_08_20_000002_create_menu_catalog_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Şube başına kodlar ve masalar',
                        text: 'Her şubenin kendi kodları ve kendi masa planı vardır; böylece bir salon için basılmış bir kod başka bir şubenin menüsünü asla açmaz.',
                        source: 'database/migrations/2026_08_22_000005_create_qr_destination_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Sipariş şube şube açılır',
                        text: 'Misafirlerin masadan sipariş gönderip gönderemeyeceğine her şube için karar verilir ve bu canlı okunur; böylece gece yarısı kapatmak gece yarısı etkili olur.',
                        source: 'database/migrations/2026_09_06_000700_add_ordering_switch_and_frozen_plan.php',
                    ),
                    new BlockEntry(
                        term: 'Her okutma hangi şubeden geldiğini bilir',
                        text: 'Misafir olayları şubeye karşı kaydedilir; böylece raporlar işletmeyi tek bir sayıya ortalamak yerine tek bir şube için cevap verebilir.',
                        source: 'database/migrations/2026_08_22_000008_create_analytics_events_table.php',
                    ),
                    new BlockEntry(
                        term: 'Her şubenin işletmedeki payı',
                        text: 'Birden çok şube varsa raporlar okutmaların aralarında nasıl bölündüğünü gösterir ve bu haftanın rakamı her şube kartının üstünde durur.',
                        source: 'app/Application/Analytics/Dto/AnalyticsBreakdownRow.php',
                    ),
                    new BlockEntry(
                        term: 'Bütün şubelerin üstünde tek ekip',
                        text: 'Bir müdür ya da editör bir kez davet edilir ve her şubede çalışır. Şube başına ayrı giriş yoktur.',
                        source: 'app/Domain/Authorization/RolePermissions.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'Neye ihtiyacınız var', [
                    new BlockEntry(
                        term: 'İşletmeyi yönetme yetkisi',
                        text: 'Şube eklemek ve düzenlemek sahibe ve müdürlere aittir. Editör bir şubenin içinde çalışır ama şube oluşturamaz.',
                        source: 'app/Domain/Tenancy/MembershipRole.php',
                    ),
                    new BlockEntry(
                        term: 'Her şube için bir adres ve bir saat dilimi',
                        text: 'Şubeye nerede olduğu ve orada saatin kaç olduğu sorulur; çünkü çalışma saatleri ve menü devri bu olmadan hiçbir anlam taşımaz.',
                        source: 'app/Models/Location.php',
                    ),
                    new BlockEntry(
                        term: 'Plan, yalnız raporlar ve ekip için',
                        text: 'Şubelerin kendisi satılmaz. Raporları okumak analitik içeren bir plan ister, bir çalışma arkadaşını davet etmek ise davetleri açan planı.',
                        source: 'app/Http/Controllers/Team/StoreTeamInvitationController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'Neyi yapmaz', [
                    new BlockEntry(
                        term: 'Her şubeye itilen merkezi menü yok',
                        text: '"Bütün şubelere yayınla" diye bir şey ve bir menüyü bir şubeden diğerine kopyalamak yoktur. İkinci şubenin menüsü o şubede yazılır.',
                        source: 'database/migrations/2026_08_22_000004_create_menu_publications_table.php',
                    ),
                    new BlockEntry(
                        term: 'Şube karşılaştırma raporu yok',
                        text: 'Şubeleri tek tek okuyabilir ve okutmaların aralarında nasıl bölündüğünü görebilirsiniz. Şubeleri her ölçüde yan yana koyan bir tablo yoktur.',
                        source: 'app/Application/Analytics/Dto/AnalyticsSummary.php',
                    ),
                    new BlockEntry(
                        term: 'Hiçbir şubede paraya dair hiçbir şey yok',
                        text: 'Raporlar ilgiyi sayar: okutmalar, açılışlar, ürün görüntülemeleri. Ciro yok, hasılat yok ve dolayısıyla şubeler arası gelir raporu da yok.',
                        source: 'app/Application/Analytics/Port/AnalyticsRepositoryPort.php',
                    ),
                    new BlockEntry(
                        term: 'Bir rol tek şubeyle sınırlanamaz',
                        text: 'Müdür, işletmenin her yerinde müdürdür. Birine tek bir şubenin anahtarını veremezsiniz.',
                        source: 'database/migrations/2026_08_19_000000_create_workspaces_and_workspace_memberships_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Hesap başına tek marka',
                        text: 'Şubeler tek bir adı ve tek bir logoyu paylaşır. İki farklı marka iki ayrı hesap ister.',
                        source: 'database/migrations/2026_08_19_000001_create_brands_and_locations_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Franchise standardı ya da onay akışı yok',
                        text: 'Bir şubeyi merkezin ifadelerine, fiyatlarına ya da fotoğraflarına uymaya zorlayan bir şey ve yayınlamadan önce şubeyi inceleyen bir şey yoktur.',
                        source: 'app/Domain/Authorization/Permission.php',
                    ),
                    new BlockEntry(
                        term: 'Bir şube kapatılamaz ya da silinemez',
                        text: 'Şubeler oluşturulur ve düzenlenir. Birini hesaptan çıkarmak bugün ekranların yaptığı bir iş değildir.',
                        source: 'routes/api/tenancy.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'İşletmecilerin şubeler hakkında sorduğu sorular', [
                    new BlockEntry(
                        term: 'Kaç şube ekleyebilirim?',
                        text: 'Kaç şubeniz varsa o kadar. Hiçbir planda şube sınırı yoktur ve şube eklemek planın fiyatını değiştirmez.',
                    ),
                    new BlockEntry(
                        term: 'İki şubenin fiyatları farklı olabilir mi?',
                        text: 'Evet ve bunun için bir kurnazlığa gerek yok. Fiyat şubenin kendi menüsünde yazılıdır, dolayısıyla ikisi birbirine karışamaz.',
                    ),
                    new BlockEntry(
                        term: 'Menüyü bir kez yazıp bütün şubelere gönderebilir miyim?',
                        text: 'Hayır. Her şubenin menüsü o şubede yazılır. Bugünün dürüst cevabı budur ve dördüncü şubeyi açmadan önce bilinmeye değer.',
                    ),
                    new BlockEntry(
                        term: 'Şubelerimi karşılaştırabilir miyim?',
                        text: 'Okutmaların aralarında nasıl bölündüğünü ve her şube kartındaki bu haftanın rakamını görebilirsiniz. Şube şube tam bir karşılaştırma tablosu yoktur.',
                    ),
                    new BlockEntry(
                        term: 'Şube müdürüne yalnız kendi şubesinin erişimini verebilir miyim?',
                        text: 'Hayır. Roller bütün işletme boyunca geçerlidir; bir müdür işletmedeki her şubeyi görebilir.',
                    ),
                    new BlockEntry(
                        term: 'Şubelerim aynı logoyu mu paylaşır?',
                        text: 'Evet. Bir hesap tek bir marka taşır. İki farklı marka iki hesap ister.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'Şubeler ek bir ücret getirmez', [
                    new BlockEntry(
                        text: 'Planların neyi değiştirdiğini ve plansız neyin açık kaldığını görün.',
                        href: '/pricing',
                        term: 'Planları görün',
                    ),
                ]),

                new ContentBlock(BlockType::Related, 'İlgili sayfalar', [
                    new BlockEntry(text: 'Masalar ve karekodlar', pageKey: 'urun.masa-ve-qr-yonetimi'),
                    new BlockEntry(text: 'Analitik', pageKey: 'urun.analitik'),
                ]),
            ],
        );
    }
}
