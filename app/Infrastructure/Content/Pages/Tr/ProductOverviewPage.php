<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages\Tr;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;

/**
 * `/tr/urun/` — ürün genel bakışının Türkçesi (P0). Kırıntı atası.
 *
 * Hub olmanın ölçüsü iki dilde de aynı: yazılmış her Türkçe ürün sayfasına
 * buradan bir bağlantı çıkar. Sayfanın işi bir harita çizmek, ana sayfayı
 * tekrar etmek değil.
 *
 * BİLEREK YAZILMAYANLAR (İngilizce aslıyla aynı): kasa/POS, misafirden ödeme
 * alma, rezervasyon, paket servis, sadakat programı, üçüncü taraf
 * entegrasyon, mağazada uygulama, sektöre özel sürüm, birden çok marka.
 */
final class ProductOverviewPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'urun',
            locale: 'tr',
            metadata: new PageMetadata(
                seoTitle: 'Zabuno ürünü nelerden oluşuyor',
                metaDescription: 'Kod okutularak açılan bir menü, arkasındaki düzenleyici, basılabilir kodlar, fotoğraflar, raporlar, sipariş ve tek hesapta bir ekip. Ne var, ne yok.',
                h1: 'Ürün genel bakışı',
                breadcrumbTitle: 'Ürün genel bakışı',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'Zabuno, yeme içme sunan yerler için tek bir web ürünüdür: menüyü yazar, yayınlar, her masaya bir kod bastırırsınız; misafirler menüyü kendi tarayıcılarında okur. Fotoğraflar, raporlar, masadan sipariş, marka görünümü ve ekip aynı hesabın parçalarıdır, ayrı ürünler değil.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'Afişte dokuz özellik ve hiç harita yok', [
                    new BlockEntry(
                        text: 'Menü yazılımlarını karşılaştıran bir restoran sahibi uzun özellik listeleri okur ve hangilerinin var olduğunu, hangilerinin bir planla açıldığını, hangilerinin gelecek hakkında bir cümle olduğunu ayırt edemez. Liste, ürün bitmiş de olsa bitmemiş de olsa aynı uzunluktadır.',
                    ),
                    new BlockEntry(
                        text: 'Yanlış tahminin bedeli masada ödenir: sahibin var sandığı bir özelliğin eksik olduğu, hiçbir şeyi değiştirmeye vakit olmayan servis anında anlaşılır.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'Tek ürün, parça parça anlatılmış', [
                    new BlockEntry(
                        text: 'Bu sayfa haritadır. Zabuno\'nun her parçasının kendi sayfası vardır ve o sayfaların her biri, parçanın bugün ne yaptığını, neye ihtiyaç duyduğunu ve neyi yapmadığını, her iddiayı ürünün kendisine bağlayarak söyler.',
                    ),
                    new BlockEntry(
                        text: 'Temel yolculuk herkes için aynıdır ve hiçbir şeye mal olmaz: bir hesap, bir işletme, bir şube, bir menü, yayınlanmış bir sürüm, basılı bir kod. Planlar bu zincirin üstüne yetenek ekler; zinciri asla kapatmaz.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'Yolculuk, sırayla', [
                    new BlockEntry(
                        term: 'Hesap ve işletme oluşturun',
                        text: 'Bir e-posta adresiyle kaydolun, sonra işletmenizi adı, dili, para birimi ve saat dilimiyle tarif edin.',
                        source: 'app/Http/Controllers/Tenancy/StoreBrandController.php',
                    ),
                    new BlockEntry(
                        term: 'Şube ekleyin',
                        text: 'Şubenin bir adresi ve kendi saati vardır. Tek bir restoran, tek şubeli bir işletmedir; zincir ise aynı hesabın altına daha fazlasını ekler.',
                        source: 'app/Http/Controllers/Tenancy/StoreLocationController.php',
                    ),
                    new BlockEntry(
                        term: 'Menüyü yazın',
                        text: 'Kategoriler, ürünler, fiyatlar, fotoğraflar ve beyan edilen alerjenler, misafirlerin göremediği bir taslağa girer.',
                        source: 'app/Http/Controllers/MenuCatalog/StoreMenuEntryController.php',
                    ),
                    new BlockEntry(
                        term: 'Bir sürüm yayınlayın',
                        text: 'Yayınlamak numaralı bir anlık görüntüyü dondurur. Misafirler her zaman yayınlanmış bir sürümü okur ve daha eski bir sürüm geri getirilebilir.',
                        source: 'app/Http/Controllers/Publication/StorePublicationController.php',
                    ),
                    new BlockEntry(
                        term: 'Her masaya bir kod bastırın',
                        text: 'Masa başına kod üretin; kart, afiş ya da kesilecek tabaka olarak dışa aktarın ve neyi gösterdiğini sonradan yeniden bastırmadan değiştirin.',
                        source: 'app/Http/Controllers/QrDestination/StoreQrCodeController.php',
                    ),
                    new BlockEntry(
                        term: 'Misafirler okutur',
                        text: 'Kod, telefonda zaten var olan tarayıcıda menüyü açar. Uygulama yok, hesap yok ve sayfa betiksiz de okunur.',
                        source: 'app/Http/Controllers/QrDestination/ShowPublicMenuController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'Parçalar ve her birinin anlatıldığı yer', [
                    new BlockEntry(
                        term: 'QR menü',
                        text: 'Misafirlerin okuduğu sayfa: her ürünün fotoğrafı, açıklaması, fiyatı, beyan edilen alerjenleri ve tükendi durumu; bir karta sığan kalıcı bir adreste.',
                        source: 'resources/views/public-menu.blade.php',
                    ),
                    new BlockEntry(
                        term: 'Menü yönetimi',
                        text: 'Taslakta düzenlenen ve numaralı sürümler hâlinde çıkarılan kategoriler, ürünler, fiyatlar ve stok; neyi kimin değiştirdiğinin kaydıyla.',
                        source: 'app/Http/Controllers/MenuCatalog/UpdateMenuItemPriceController.php',
                    ),
                    new BlockEntry(
                        term: 'Masalar ve karekodlar',
                        text: 'Masa başına bir kod; dosyaya dönüşmeden önce bir çözücüyle geri okunur, vektörel kart olarak basılır ve yeniden bastırmadan başka yere yönlendirilir.',
                        source: 'app/Http/Controllers/QrDestination/ExportQrCardController.php',
                    ),
                    new BlockEntry(
                        term: 'Tasarım ve marka',
                        text: 'Bir logo, bir ana ve bir ikincil renk; her yayının içine dondurulur, böylece yayınlanmış bir menü misafirin altında değişmez.',
                        source: 'app/Domain/Publication/MenuIdentity.php',
                    ),
                    new BlockEntry(
                        term: 'Görsel ve medya',
                        text: 'Fotoğraflar taranır, bir kez boyutlandırılır ve küçük sunulur; orijinal saklanır, böylece kopyalar yeniden üretilebilir.',
                        source: 'app/Infrastructure/Media/Processing/GdMediaAssetProcessor.php',
                    ),
                    new BlockEntry(
                        term: 'Dil ve para birimi',
                        text: 'Misafir sayfası, misafirin seçimine göre Türkçe ya da İngilizcedir; ürün adları sizin dilinizde kalır ve fiyatlar para biriminizin ondalığını izler.',
                        source: 'app/Support/Localization/GuestLocale.php',
                    ),
                    new BlockEntry(
                        term: 'Çoklu şube',
                        text: 'Tek bir işletmenin altında istediğiniz kadar şube; her biri kendi menüleri, fiyatları, kodları ve çalışma saatleriyle.',
                        source: 'database/migrations/2026_08_19_000001_create_brands_and_locations_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Analitik',
                        text: 'Okutmalar, menü açılışları, ürün görüntülemeleri, sonuçsuz aramalar ve gönderilen siparişler; sunucu tarafından sayılır ve bir menü raporuna dönüşür.',
                        source: 'app/Domain/Analytics/AnalyticsEventType.php',
                    ),
                    new BlockEntry(
                        term: 'Zabuno yapay zekâsı',
                        text: 'Basılı bir menünün fotoğrafı onayladığınız bir taslağa dönüşür, bir ürün açıklamasının ilk taslağını alır ve iki kez yazılmış ürünler bulunur.',
                        source: 'app/Http/Controllers/Ai/StoreMenuAiImportController.php',
                    ),
                    new BlockEntry(
                        term: 'Masadan sipariş',
                        text: 'Sahibin açtığı yerlerde misafirler bir sepet kurar ve mutfak ekranına gönderir; masa, yazılarak değil koddan okunur.',
                        source: 'app/Http/Controllers/Ordering/StoreGuestOrderController.php',
                    ),
                    new BlockEntry(
                        term: 'Rolleri olan bir ekip',
                        text: 'Editörler, müdürler ve alerjenlerle tükenen ürünleri işaretleyip başka hiçbir şey görmeyen bir mutfak rolü; hepsi sahip tarafından davet edilir.',
                        source: 'app/Domain/Tenancy/MembershipRole.php',
                    ),
                    new BlockEntry(
                        term: 'Masadan değerlendirme',
                        text: 'Kod okutmuş bir misafir bir ürünü değerlendirebilir ve sahip buna yanıt verebilir.',
                        source: 'app/Http/Controllers/Rating/StoreGuestRatingController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'Neye ihtiyacınız var', [
                    new BlockEntry(
                        term: 'Tarayıcısı olan bir telefon ya da bilgisayar',
                        text: 'Sahibin tarafı da bir web uygulamasıdır. Ne sizin ne misafirlerinizin kuracağı bir şey vardır.',
                        source: 'routes/web.php',
                    ),
                    new BlockEntry(
                        term: 'Okuyabildiğiniz bir e-posta adresi',
                        text: 'Kayıt, hesap kullanılmadan önce e-postayla doğrulanır.',
                        source: 'app/Http/Controllers/Auth/SendEmailVerificationNotificationController.php',
                    ),
                    new BlockEntry(
                        term: 'Bir yazıcı ya da bir matbaa',
                        text: 'Basılabilir dosyaları Zabuno üretir; masadaki kâğıt sizindir.',
                        source: 'app/Http/Controllers/QrDestination/ExportQrPrintSheetController.php',
                    ),
                    new BlockEntry(
                        term: 'Başlamak için plan gerekmez',
                        text: 'Kaydolmak, kurmak, yayınlamak ve bastırmak ücretsiz plandadır. Ücretli planlar toplu kod üretimini, raporları, marka görünümünü, ekibi ve siparişi açar.',
                        source: 'database/seeders/PlanCatalogueSeeder.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'Zabuno ne değildir', [
                    new BlockEntry(
                        term: 'Yazarkasa ve ödeme sistemi değildir',
                        text: 'Misafirler Zabuno üzerinden ödeme yapmaz ve burada hiçbir şey adisyon basmaz ya da hasılatınızı raporlamaz.',
                        source: 'app/Application/Ordering/UseCase/BuildOrderLines.php',
                    ),
                    new BlockEntry(
                        term: 'Rezervasyon, teslimat ve sadakat yok',
                        text: 'Masa rezervasyonu, kurye ya da teslimat entegrasyonu ve puan kartı yoktur.',
                        source: 'app/Domain/Authorization/Permission.php',
                    ),
                    new BlockEntry(
                        term: 'Üçüncü taraf entegrasyonu yok',
                        text: 'Hiçbir şey bir satış noktası sistemine, muhasebe paketine ya da yemek pazaryerine bağlanmaz. Menünüz hesap tablosu dosyası olarak çıkar ve aynı yoldan geri gelir.',
                        source: 'app/Http/Controllers/MenuCatalog/ExportMenuCsvController.php',
                    ),
                    new BlockEntry(
                        term: 'Mağazada uygulama yok',
                        text: 'Ne sizin ne de misafirleriniz için bir iPhone ya da Android uygulaması vardır; iki taraf da web sayfasıdır.',
                        source: 'resources/views/public-menu.blade.php',
                    ),
                    new BlockEntry(
                        term: 'Ürün adları bir kez yazılır',
                        text: 'Menünün etrafındaki arayüz iki dil konuşur; ürünler sizin dilinizde yazılır ve çevrilmez.',
                        source: 'database/migrations/2026_08_20_000002_create_menu_catalog_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Hesap başına tek marka',
                        text: 'Bir hesap, birçok şubesi olan tek bir işletme tutar, birkaç işletme değil.',
                        source: 'database/migrations/2026_08_19_000001_create_brands_and_locations_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Sektör sürümleri yok',
                        text: 'Kafe sürümü, fırın sürümü ya da otel sürümü yoktur. Ürün tek bir işletme türü tanır: yeme içme sunan yerler.',
                        source: 'app/Domain/Publication/BusinessType.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'İşletmecilerin ilk sorduğu sorular', [
                    new BlockEntry(
                        term: 'Denemeden önce plan almak zorunda mıyım?',
                        text: 'Hayır. Hesaptan basılı koda uzanan zincir plansız çalışır. Planlar bunun üstüne yetenek ekler.',
                    ),
                    new BlockEntry(
                        term: 'Misafirler bir şey kurar mı?',
                        text: 'Hayır. Kod, telefonda zaten var olan tarayıcıda bir web sayfası açar.',
                    ),
                    new BlockEntry(
                        term: 'Zabuno bir satış noktası sistemi mi?',
                        text: 'Hayır. Menüyü gösterir ve bir siparişi mutfağa taşıyabilir. Ödeme almaz ve hasılatınızı bilmez.',
                    ),
                    new BlockEntry(
                        term: 'Önce hangi parçayı okumalıyım?',
                        text: 'QR menü sayfasını, çünkü misafirlerinizin gördüğü şey odur. Menü yönetimi ise kendi zamanınızı harcadığınız yerdir.',
                    ),
                    new BlockEntry(
                        term: 'Şu anki yazılımımla çalışır mı?',
                        text: 'Bir entegrasyon üzerinden değil; bugün öyle bir şey yok. Menü, hesap tablosu dosyası olarak dışa ve içe aktarılabilir.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'Her planın neyi eklediğini görün', [
                    new BlockEntry(
                        text: 'Ücretsiz plan yukarıdaki zinciri kapsar. Ücretli planlar onun üstüne ekler.',
                        href: '/pricing',
                        term: 'Planları inceleyin',
                    ),
                ]),

                new ContentBlock(BlockType::Related, 'Ürünün parçaları', [
                    new BlockEntry(text: 'QR menü', pageKey: 'urun.qr-menu'),
                    new BlockEntry(text: 'Menü yönetimi', pageKey: 'urun.menu-yonetimi'),
                    new BlockEntry(text: 'Masalar ve karekodlar', pageKey: 'urun.masa-ve-qr-yonetimi'),
                    new BlockEntry(text: 'Tasarım ve marka', pageKey: 'urun.tasarim-ve-marka'),
                    new BlockEntry(text: 'Görsel ve medya', pageKey: 'urun.gorsel-ve-medya'),
                    new BlockEntry(text: 'Dil ve para birimi', pageKey: 'urun.coklu-dil-ve-para-birimi'),
                    new BlockEntry(text: 'Çoklu şube', pageKey: 'urun.coklu-sube'),
                    new BlockEntry(text: 'Analitik', pageKey: 'urun.analitik'),
                    new BlockEntry(text: 'Zabuno yapay zekâsı', pageKey: 'urun.zabuno-ai'),
                    new BlockEntry(text: 'Sipariş', pageKey: 'urun.siparis'),
                    new BlockEntry(text: 'Çözümler', pageKey: 'cozumler'),
                    new BlockEntry(text: 'Fiyatlandırma', pageKey: 'fiyatlandirma'),
                ]),
            ],
        );
    }
}
