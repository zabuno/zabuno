<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages\Tr;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;

/**
 * `/tr/urun/menu-yonetimi/` — menü yönetimi sayfasının Türkçesi (P0).
 *
 * İngilizce aslıyla aynı sınırlamalar aynı sayıda ve aynı kanıtla yazılır:
 * varyant/porsiyon, ekstra ve seçenek grupları, menü kopyalama ve toplu
 * fiyat/görünürlük/silme işlemleri ürün tarafından YAPILMIYOR ve Türkçe
 * okuyan kişi de bunu okumak zorundadır.
 */
final class MenuManagementPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'urun.menu-yonetimi',
            locale: 'tr',
            metadata: new PageMetadata(
                seoTitle: 'Menü yönetimi',
                metaDescription: 'Kategori ve ürün kurun, fiyat girin, bir ürünü tükendi işaretleyin ve misafirinizin bir saniye sonra okuyacağı numaralı bir sürüm yayınlayın.',
                h1: 'Menü yönetimi',
                breadcrumbTitle: 'Menü yönetimi',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'Menü yönetimi, misafirinizin okuduğu menüyü kurduğunuz yerdir: kategoriler, ürünler, fiyatlar, fotoğraflar ve beyan ettiğiniz alerjenler. Yazdığınız hiçbir şey siz yayınlamadan misafire ulaşmaz ve her yayın, geri dönebileceğiniz numaralı bir sürümdür.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'Zor olan düzenlemek değil', [
                    new BlockEntry(
                        text: 'Bir fiyatı değiştirmek her yerde kolaydır. Zor olan, tam o anda menüyü okuyan bir misafirin okuduğunu bozmadan değiştirmek ve yarın kimin değiştirdiğini, öncesinde ne olduğunu söyleyebilmektir.',
                    ),
                    new BlockEntry(
                        text: 'Çoğu mutfak bunu hiç düzenlemeyerek çözer. Menü kayar, düzeltmeyi de tabakları taşıyan kişi sesli olarak yapar.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'Taslak ile yayın iki ayrı şeydir', [
                    new BlockEntry(
                        text: 'Siz bir taslak üzerinde çalışırsınız. Misafir yayınlanmış bir sürümü okur. İkisinin arasında bilinçli bir adım vardır; böylece yarım kalmış bir kahvaltı menüsü hiçbir masaya düşmez.',
                    ),
                    new BlockEntry(
                        text: 'Tek bir durum bu adımı bilerek atlar: bir ürünü tükendi işaretlemek anında etkili olur, çünkü biten ürün zaten bitmiştir ve yayını beklemek hiçbir şeyi beklemek olurdu.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'Nasıl çalışır', [
                    new BlockEntry(
                        term: 'Önce kategoriler',
                        text: 'Kategori oluşturun, adını değiştirin, silin ve sıralayın. Kurduğunuz sıra, misafirin okuduğu sıradır.',
                        source: 'app/Http/Controllers/MenuCatalog/ReorderCategoriesController.php',
                    ),
                    new BlockEntry(
                        term: 'Sonra ürünler',
                        text: 'Ürün, menü satırı ve alerjenleri tek bir işlemde yazılır; böylece yarım yazılmış bir ürün hiç görünmez.',
                        source: 'app/Http/Controllers/MenuCatalog/StoreMenuEntryController.php',
                    ),
                    new BlockEntry(
                        term: 'Önce telefonda bakın',
                        text: 'İmzalı bir önizleme bağlantısı taslağı gerçek bir telefonda gösterir ve kendiliğinden süresi dolar; böylece iletilen bir taslak sızmaz.',
                        source: 'app/Http/Controllers/Publication/CreateDraftPreviewLinkController.php',
                    ),
                    new BlockEntry(
                        term: 'Numaralı bir sürüm yayınlayın',
                        text: 'Yayınlamak bir anlık görüntüyü dondurur ve ona bir sürüm numarası verir. Misafir o anlık görüntüyü okur.',
                        source: 'database/migrations/2026_08_22_000004_create_menu_publications_table.php',
                    ),
                    new BlockEntry(
                        term: 'Yanlışsa geri dönün',
                        text: 'Daha önce yayınlanmış her sürüm geri getirilebilir; böylece hatalı bir yayın bir akşamlık değil, bir dakikalık dert olur.',
                        source: 'app/Http/Controllers/Publication/RestorePublicationController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'Neler yapabilirsiniz', [
                    new BlockEntry(
                        term: 'Fiyatlar',
                        text: 'Ürün fiyatını kendi para biriminizde girin; tam sayı kuruş olarak saklanır, böylece yuvarlama kaymaz.',
                        source: 'app/Http/Controllers/MenuCatalog/UpdateMenuItemPriceController.php',
                    ),
                    new BlockEntry(
                        term: 'Fotoğraflar',
                        text: 'Medya kütüphanesinden bir fotoğraf bağlayın; misafir sayfası isteyen ekrana doğru boyutu sunar.',
                        source: 'app/Http/Controllers/MenuCatalog/BindMenuItemImageController.php',
                    ),
                    new BlockEntry(
                        term: 'Beyan edilen alerjenler',
                        text: 'Bir ürün için beyan ettiğiniz alerjenleri işaretleyin. Misafire garanti olarak değil, beyan olarak gösterilirler.',
                        source: 'app/Http/Controllers/MenuCatalog/UpdateMenuItemAllergensController.php',
                    ),
                    new BlockEntry(
                        term: 'Tükendi, anında',
                        text: 'Tek bir ürünü ya da tek bir istekte koca bir listeyi tükendi veya yeniden stokta işaretleyin. Misafir bunu yayın olmadan, bir sonraki okutmada görür.',
                        source: 'app/Http/Controllers/MenuCatalog/UpdateMenuStockController.php',
                    ),
                    new BlockEntry(
                        term: 'Silmeden gizleyin',
                        text: 'Bir ürün yayındaki menüden çıkarılıp katalogda tutulabilir; böylece mevsimlik bir ürün yeniden yazılmak zorunda kalmaz.',
                        source: 'app/Http/Controllers/MenuCatalog/UpdateMenuItemVisibilityController.php',
                    ),
                    new BlockEntry(
                        term: 'Birden çok menü, tek yerde',
                        text: 'Bir şube birden fazla menü tutabilir ve bir menü günün belirli bir saatinde bir diğerine devredebilir; böylece kahvaltı ve akşam yemeği aynı basılı kodun arkasında yaşar.',
                        source: 'database/migrations/2026_09_05_000400_allow_many_menus_per_location.php',
                    ),
                    new BlockEntry(
                        term: 'Seçtiğiniz saatte yayın',
                        text: 'Bir yayın zamanlanabilir. Sunucu onu dakikası dakikasına, kimse oturum açmış olmasa da gerçekleştirir.',
                        source: 'app/Console/Commands/PublishScheduledMenusCommand.php',
                    ),
                    new BlockEntry(
                        term: 'CSV ile içe ve dışa aktarma',
                        text: 'Bütün bir menü bir hesap tablosundan getirilebilir ve yine öyle geri alınabilir; böylece menü hiçbir zaman ürünün içinde kilitli kalmaz.',
                        source: 'app/Http/Controllers/MenuCatalog/ImportMenuCsvController.php',
                    ),
                    new BlockEntry(
                        term: 'Fiyatı kim değiştirdi',
                        text: 'Menü değişiklikleri bir denetim izine yazılır; böylece "kebap neden 20 lira arttı" sorusunun adı ve saati olan bir cevabı olur.',
                        source: 'database/migrations/2026_09_06_000200_create_menu_audits_table.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'Neye ihtiyacınız var', [
                    new BlockEntry(
                        term: 'Bir hesap ve bir şube',
                        text: 'Menü kurmak ve yayınlamak için bir çalışma alanı ve bir şube yeterlidir.',
                        source: 'app/Http/Controllers/MenuCatalog/StoreMenuController.php',
                    ),
                    new BlockEntry(
                        term: 'Bir plan',
                        text: 'Menü kurmak, düzenlemek, yayınlamak ve geri getirmek ücretsiz plandadır. Bu sayfanın hiçbir parçası ücretli planın arkasında değildir.',
                        source: 'database/seeders/PlanCatalogueSeeder.php',
                    ),
                    new BlockEntry(
                        term: 'Menü başına tek para birimi',
                        text: 'Bir menü, markasının para biriminde fiyatlanır. Tek menü içinde karışık para birimi desteklenmez.',
                        source: 'app/Domain/Money/MoneyFormatter.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'Neyi yapmaz', [
                    new BlockEntry(
                        term: 'Boyut ya da porsiyon yok',
                        text: 'Bir ürünün tek bir fiyatı vardır. Küçük ve büyük ya da yarım ve tam, bugün tek bir ürün olarak ifade edilemez.',
                        source: 'database/migrations/2026_08_20_000002_create_menu_catalog_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Ekstra ya da seçenek grubu yok',
                        text: 'Ekstra peynir gibi ücretli eklemeler ya da sos gibi seçimler bir ürünün parçası değildir.',
                        source: 'database/migrations/2026_08_20_000002_create_menu_catalog_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Menü kopyalama yok',
                        text: 'Bir menü ikinci bir menüye kopyalanamaz. İkinci menü ya kurulur ya da bir hesap tablosundan getirilir.',
                        source: 'routes/api/menu-catalog.php',
                    ),
                    new BlockEntry(
                        term: 'Toplu iş yalnız stokla sınırlı',
                        text: 'Tükendi işaretleri birçok ürün için birden değiştirilebilir. Fiyat, görünürlük ve silme tek tek değiştirilir.',
                        source: 'app/Http/Controllers/MenuCatalog/UpdateMenuStockController.php',
                    ),
                    new BlockEntry(
                        term: 'Vergi satırı yok',
                        text: 'Fiyat, fiyat olarak gösterilir. Ayrı bir vergi alanı yoktur ve misafir sayfası vergi hakkında hiçbir şey söylemez.',
                        source: 'app/Support/Money/PriceLabel.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'İşletmecilerin sorduğu sorular', [
                    new BlockEntry(
                        term: 'Yaptığım düzenleme misafire hemen ulaşır mı?',
                        text: 'Yalnızca tükendi işaretiyse. Geri kalan her şey sizin yayınlamanızı bekler; böylece misafir yarım kalmış bir menüyü hiç okumaz.',
                    ),
                    new BlockEntry(
                        term: 'Hatalı bir yayını geri alabilir miyim?',
                        text: 'Evet. Her yayın numaralı bir sürümdür ve daha eski bir sürüm geri getirilebilir.',
                    ),
                    new BlockEntry(
                        term: 'Yeni fiyatları bu akşam girip yarın başlatabilir miyim?',
                        text: 'Evet. Yayını zamanlayın; sunucu onu o saatte kendiliğinden gerçekleştirir.',
                    ),
                    new BlockEntry(
                        term: 'Kahvaltı ile akşam yemeği tek basılı kodu paylaşabilir mi?',
                        text: 'Evet. Bir şube birden fazla menü tutabilir ve biri günün belirli bir saatinde diğerine devredebilir.',
                    ),
                    new BlockEntry(
                        term: 'Menümü tekrar dışarı alabilir miyim?',
                        text: 'Evet. Bütün menü bir hesap tablosu dosyasına aktarılır ve aynı yolla içeri alınabilir.',
                    ),
                    new BlockEntry(
                        term: 'Bir fiyatı kimin değiştirdiğini görebilir miyim?',
                        text: 'Evet. Menü değişiklikleri kimin yaptığı ve ne zaman yaptığıyla birlikte kaydedilir.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'Ücretsiz planla başlayın', [
                    new BlockEntry(
                        text: 'Menü kurmak, yayınlamak ve geri getirmek ücretli planın arkasında değildir.',
                        href: '/pricing',
                        term: 'Planlara bakın',
                    ),
                ]),

                new ContentBlock(BlockType::Related, 'İlgili sayfalar', [
                    new BlockEntry(text: 'QR menü', pageKey: 'urun.qr-menu'),
                    new BlockEntry(text: 'Zabuno yapay zekâsı', pageKey: 'urun.zabuno-ai'),
                    new BlockEntry(text: 'Sürümler ve geri alma', pageKey: 'urun.menu-yonetimi.menu-versiyonlari'),
                ]),
            ],
        );
    }
}
