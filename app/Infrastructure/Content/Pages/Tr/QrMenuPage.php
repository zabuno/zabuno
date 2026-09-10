<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages\Tr;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;

/**
 * `/tr/urun/qr-menu/` — QR menü sayfasının TÜRKÇESİ (P0).
 *
 * İkinci dil, birinci dilin kısaltılmışı DEĞİLDİR. Her blok, her satır ve
 * her kanıt yolu İngilizce aslıyla birebir aynı iddiayı taşır; eksik bırakılan
 * bir sınırlama satırı, Türkçe okuyan kişiye ürünün YAPMADIĞI bir şeyi
 * yapıyormuş gibi gösterirdi — yani çeviri eksikliği bir dürüstlük
 * eksikliğine dönüşürdü.
 *
 * `source` alanları çevrilmez ve çevrilemez: onlar depodaki gerçek yollardır
 * ve testin ölçtüğü şey de tam olarak o yolların var olmasıdır.
 */
final class QrMenuPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'urun.qr-menu',
            locale: 'tr',
            metadata: new PageMetadata(
                seoTitle: 'Restoranlar için QR menü',
                metaDescription: 'Misafir masadaki karekodu okutur ve menünüzü tarayıcıda okur. Uygulama yok, indirme yok; sayfa betikler çalışmasa bile açılır.',
                h1: 'QR menü',
                breadcrumbTitle: 'QR menü',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'Zabuno QR menüsü, misafirinizin masasındaki karekodu okutarak açtığı bir web sayfasıdır. Telefonundaki tarayıcıda açılır, uygulama ya da indirme istemez; yayınladığınız her ürünün fotoğrafını, açıklamasını, fiyatını ve beyan ettiğiniz alerjenlerini gösterir.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'Basılı menünün sorunu', [
                    new BlockEntry(
                        text: 'Basılı menü, matbaaya gittiği gün donar. Saat yedide biten kebap dokuzda hâlâ karttadır, geçen ayın fiyatı misafirin okuduğu fiyat olarak durur ve yeni tatlı arka sayfaya elle yazılmış bir satırdır.',
                    ),
                    new BlockEntry(
                        text: 'Her düzeltme bir yeniden baskıdır ve iki baskı arasında menü misafire sessizce yalan söyler. Aradaki farkı garson kapatır, her masada bir kez.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'Zabuno neyi değiştiriyor', [
                    new BlockEntry(
                        text: 'Basılı kart menü olmaktan çıkar, bir kapıya dönüşür. Misafirin okuduğu şey sizin tarafınızda yaşar ve siz değiştirdiğinizde değişir; böylece tükenen bir ürün bir sonraki baskıda değil, bir sonraki okutmada tükendi olarak görünür.',
                    ),
                    new BlockEntry(
                        text: 'Kodun arkasındaki adres hiç değişmez. Bir ürünün adını değiştirdikten, fiyat yükselttikten ya da o masayı başka bir menüye yönlendirdikten sonra da bastırdığınız kartlar çalışmaya devam eder.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'Nasıl çalışır', [
                    new BlockEntry(
                        term: 'Menüyü kurun',
                        text: 'Kategorileri ve ürünleri oluşturun, fiyatları girin, fotoğrafları ekleyin ve beyan ettiğiniz alerjenleri işaretleyin.',
                        source: 'app/Http/Controllers/MenuCatalog/StoreMenuEntryController.php',
                    ),
                    new BlockEntry(
                        term: 'Bir sürüm yayınlayın',
                        text: 'Yayınlamak numaralı bir anlık görüntü alır. Misafir her zaman yayınlanmış bir sürümü okur, çalıştığınız taslağı değil.',
                        source: 'database/migrations/2026_08_22_000004_create_menu_publications_table.php',
                    ),
                    new BlockEntry(
                        term: 'Kodu bastırın',
                        text: 'Her kod tahmin edilemeyen 43 karakterlik bir jeton taşır; yani bir kod, komşu adresi yazarak bulunmaz.',
                        source: 'app/Domain/QrDestination/QrToken.php',
                    ),
                    new BlockEntry(
                        term: 'Misafir okutur',
                        text: 'Okutma menü sayfasına çözülür ve sunucu HTML\'i göndermeden önce çizer. Kurulacak bir şey yoktur, üye olunacak bir yer yoktur.',
                        source: 'app/Http/Controllers/QrDestination/RedirectQrTokenController.php',
                    ),
                    new BlockEntry(
                        term: 'Yeniden bastırmadan değiştirin',
                        text: 'Bir ürünü tükendi işaretleyin, yeni sürüm yayınlayın ya da aynı kodu başka bir menüye yönlendirin. Masadaki kâğıt aynı kâğıt kalır.',
                        source: 'app/Http/Controllers/QrDestination/RetargetQrCodeController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'Misafirin gördüğü sayfa ne yapar', [
                    new BlockEntry(
                        term: 'JavaScript olmadan da okunur',
                        text: 'Kategoriler sıradan bağlantı, ürünler sıradan bağlantıdır; böylece gezinme, fiyatlar, alerjenler ve tükendi işaretleri betik çalıştırmayan bir tarayıcıda da yaşar.',
                        source: 'resources/views/public-menu.blade.php',
                    ),
                    new BlockEntry(
                        term: 'Her ürünün kendi sayfası var',
                        text: 'Fotoğraf, açıklama, fiyat, beyan edilen alerjenler ve tükendi durumu; misafirin bir arkadaşına gönderebileceği bir adreste.',
                        source: 'resources/views/public-menu-item.blade.php',
                    ),
                    new BlockEntry(
                        term: 'Kalıcı ve söylenebilir bir adres',
                        text: 'Okutmanın yanı sıra her menünün koda değil bir ada benzeyen kalıcı bir adresi vardır; kartvizite, vitrine ya da sosyal medya profiline yazılabilir.',
                        source: 'routes/web.php',
                    ),
                    new BlockEntry(
                        term: 'Tükendi, tükendi yazar',
                        text: 'Stok ve alerjen durumları kelimeyle yazılır, yalnız renkle işaret edilmez; böylece renkleri ayırt edemeyen misafire de ulaşır.',
                        source: 'resources/views/public-menu-item.blade.php',
                    ),
                    new BlockEntry(
                        term: 'Fiyatlar kendi para biriminizde, doğru',
                        text: 'Ondalık basamak para biriminin kendisinden gelir ve çözülemeyen bir fiyat yanlış gösterilmek yerine gizlenir.',
                        source: 'app/Support/Money/PriceLabel.php',
                    ),
                    new BlockEntry(
                        term: 'Başparmak için tasarlandı',
                        text: 'Dokunma hedefleri en az 44 piksel kalır, klavye kullananlar için odak görünürdür ve telefon daha az hareket istediğinde hareket bırakılır.',
                        source: 'resources/views/partials/guest-surface-style.blade.php',
                    ),
                    new BlockEntry(
                        term: 'Arayüz dili misafirin seçimi',
                        text: 'Çevredeki arayüz, betiksiz de çalışan sade bir bağlantıyla Türkçe ile İngilizce arasında değiştirilebilir.',
                        source: 'app/Support/Localization/GuestLocale.php',
                    ),
                    new BlockEntry(
                        term: 'Yalnız sayfayı hak eden ürünün sayfası olur',
                        text: 'Açıklaması, fotoğrafı ve alerjen bilgisi olmayan bir ürün arama sonuçlarına girmez ve menüden bağlantı almaz; çünkü hiçbir yere götürmeyen bir bağlantı bir yalandır.',
                        source: 'app/Http/Controllers/QrDestination/ShowPublicMenuItemController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'Neye ihtiyacınız var', [
                    new BlockEntry(
                        term: 'Misafir tarafında',
                        text: 'Kamerası ve tarayıcısı olan bir telefon. Uygulama yok, hesap yok, indirme yok.',
                        source: 'resources/views/public-menu.blade.php',
                    ),
                    new BlockEntry(
                        term: 'Sizin tarafınızda',
                        text: 'Bir Zabuno hesabı, yayınlanmış bir menü ve bastırılmış bir kod. Kaydolmak, menü kurmak, yayınlamak ve kod bastırmak ücretsiz planda çalışır.',
                        source: 'database/seeders/PlanCatalogueSeeder.php',
                    ),
                    new BlockEntry(
                        term: 'Masa masa kod üretmek için',
                        text: 'Bütün bir salonun kodlarını tek seferde üretmek ücretli bir plana aittir; tek tek üretmek değil.',
                        source: 'app/Http/Controllers/QrDestination/StoreBulkQrCodesController.php',
                    ),
                    new BlockEntry(
                        term: 'Misafir sayfasında kendi renkleriniz için',
                        text: 'Marka renginizi misafir menüsüne taşımak ücretli bir plana aittir. Onsuz menü nötr varsayılan görünümde gösterilir.',
                        source: 'app/Domain/Entitlement/Entitlement.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'Neyi yapmaz', [
                    new BlockEntry(
                        term: 'Boyut, porsiyon ya da ekstra yok',
                        text: 'Bir ürün tek bir fiyat taşır. Yarım porsiyon, boy seçenekleri ve ücretli ekstralar bugün menünün parçası değildir.',
                        source: 'database/migrations/2026_08_20_000002_create_menu_catalog_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Kalori ya da besin değeri yok',
                        text: 'Alerjenler gösterilir çünkü onları siz beyan edersiniz. Enerji ve besin değerleri saklanmaz ve gösterilmez.',
                        source: 'database/migrations/2026_08_28_000400_add_description_to_products.php',
                    ),
                    new BlockEntry(
                        term: 'Alerjenler beyandır, sertifika değil',
                        text: 'Sayfa hangi alerjenleri beyan ettiğinizi yazar. Bir ürünün herhangi bir şeyden arınmış olduğunu asla iddia etmez, çünkü mutfak laboratuvar değildir.',
                        source: 'resources/views/public-menu-item.blade.php',
                    ),
                    new BlockEntry(
                        term: 'Ürün adları çevrilmez',
                        text: 'Arayüz dilini değiştirmek menünüzü çevirmez. Ürün adları ve açıklamaları yazdığınız dilde kalır ve sayfa bunu misafire söyler.',
                        source: 'resources/views/public-menu.blade.php',
                    ),
                    new BlockEntry(
                        term: 'Misafir burada ödeme yapmaz',
                        text: 'Misafir menüsünde ödeme adımı yoktur. Bu sayfada kimse kart bilgisi girmez.',
                        source: 'routes/web.php',
                    ),
                    new BlockEntry(
                        term: 'Çevrimdışı tekrar gösterim sınırlıdır',
                        text: 'Okutulan bir koddan açılan menü zayıf bağlantıda yeniden gösterilebilir. Paylaşılabilir kalıcı adresin çevrimdışı davranışı yoktur.',
                        source: 'public/public-diner-sw.js',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'Misafirlerin ve işletmecilerin sorduğu sorular', [
                    new BlockEntry(
                        term: 'Misafirin uygulama kurması gerekiyor mu?',
                        text: 'Hayır. Kod, telefonda zaten var olan tarayıcıda bir web sayfası açar. İndirme de yoktur, hesap da.',
                    ),
                    new BlockEntry(
                        term: 'Menüyü değiştirince kodları yeniden bastırmam gerekir mi?',
                        text: 'Hayır. Basılı kod değişmeyen bir adresi gösterir. Bir ürünü düzenleyin, yeni bir sürüm yayınlayın ya da kodu başka bir menüye yönlendirin; aynı kart çalışmaya devam eder.',
                    ),
                    new BlockEntry(
                        term: 'Bir ürün bittiğinde misafir ne görür?',
                        text: 'Ürün, siz işaretlediğiniz andan itibaren hem menüde hem kendi sayfasında kelimeyle tükendi olarak görünür. Bunun için yeni bir sürüm yayınlamanız gerekmez.',
                    ),
                    new BlockEntry(
                        term: 'Misafir tek bir ürünü arkadaşına gönderebilir mi?',
                        text: 'Evet. Gösterecek bir şeyi olan ürünün kendi adresi vardır; bağlantı menünün başında değil, o ürünün üzerinde açılır.',
                    ),
                    new BlockEntry(
                        term: 'Betikler engellenmişse menü okunur mu?',
                        text: 'Evet. Kategoriler, ürünler, fiyatlar, alerjenler ve tükendi işaretleri sunucuda çizilir. Arama ve süzgeçler betik ister ve betiksiz hâlde hiç gösterilmez.',
                    ),
                    new BlockEntry(
                        term: 'Başlamak para tutar mı?',
                        text: 'Kaydolmak, menü kurmak, yayınlamak, kod bastırmak ve misafire sunmak ücretsiz plandadır. Ücretli planlar üstüne yetenek ekler; temel işleri açmaz.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'Bir planın neleri kapsadığına bakın', [
                    new BlockEntry(
                        text: 'Planlar, her birinin neyi eklediği ve ücretsiz olanın zaten neleri kapsadığı.',
                        href: '/pricing',
                        term: 'Planları karşılaştırın',
                    ),
                ]),

                new ContentBlock(BlockType::Related, 'İlgili sayfalar', [
                    new BlockEntry(text: 'Menü yönetimi', pageKey: 'urun.menu-yonetimi'),
                    new BlockEntry(text: 'Masa ve karekod yönetimi', pageKey: 'urun.masa-ve-qr-yonetimi'),
                ]),
            ],
        );
    }
}
