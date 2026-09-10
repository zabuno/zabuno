<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages\Tr;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;

/**
 * `/tr/urun/tasarim-ve-marka/` — tasarım ve markanın Türkçesi (P0).
 *
 * Bu sayfanın en kolay yalanı iki dilde de aynı: *"menünüz baştan aşağı sizin
 * renginizde"*. Misafir menüsünde marka rengi yalnız DEKORASYONDUR ve Türkçe
 * sayfa da rengin nerede bittiğini adıyla söyler. Biçim varyantı, yazı tipi
 * seçimi, düzen/tema editörü, özel alan adı, kapak görseli ve kendi sekme
 * ikonu burada da yazılmaz.
 */
final class DesignAndBrandingPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'urun.tasarim-ve-marka',
            locale: 'tr',
            metadata: new PageMetadata(
                seoTitle: 'Misafir menüsünde logonuz ve renkleriniz',
                metaDescription: 'Bir logo, bir ana ve bir ikincil renk ekleyin; misafir menüsünde ve basılı masa kartlarında görünsün. Her yayınla dondurulur, baskıdan önce denetlenir.',
                h1: 'Tasarım ve marka',
                breadcrumbTitle: 'Tasarım ve marka',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'Tasarım ve marka, restoranınızın logosunun, ana renginin ve ikincil renginin bir kez belirlenip misafir menüsüne ve basılı masa kartlarına taşındığı yerdir. Renginiz dekorasyon olarak kullanılır, metin olarak asla; böylece soluk bir marka rengi bir fiyatı okunmaz yapamaz.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'Restorana değil yazılıma benzeyen bir menü', [
                    new BlockEntry(
                        text: 'Kod okutan bir misafir, teknik olarak bir başkasının web sitesi olan bir sayfaya düşer. O sayfa restorandan hiçbir iz taşımıyorsa, misafir bir fiyata güvenmeden önce adres çubuğuna bakar.',
                    ),
                    new BlockEntry(
                        text: 'Tersi daha kötüdür: işletmeciye bütün sayfayı marka sarısına boyatan ve sonra loş bir lambanın altındaki misafire o sarının üstünde beyaz fiyatlar gösteren bir yazılım.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'Sayfada sizin kimliğiniz, okunabilirlik üründe', [
                    new BlockEntry(
                        text: 'Bir logo ve iki renk verirsiniz. Zabuno logoyu restoranın adının yanına koyar, menünün üstüne ana renginizde bir şerit ve her kategorinin altına ikincil renginizde bir çizgi çizer; metni ve zeminleri ise kendi ölçülmüş mürekkebinde bırakır.',
                    ),
                    new BlockEntry(
                        text: 'Misafirin gördüğü şey her yayınlanmış sürümle birlikte dondurulur. Yarın bir rengi değiştirmek, dünkü yayından açılmış bir menüyü yeniden boyamaz.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'Nasıl çalışır', [
                    new BlockEntry(
                        term: 'Logoyu yükleyin',
                        text: 'Logo, medya kütüphanenizdeki bir dosyadır; her fotoğraf gibi taranır ve boyutlandırılır, işlendikten sonra markaya bağlanır.',
                        source: 'app/Http/Controllers/Tenancy/BindBrandLogoController.php',
                    ),
                    new BlockEntry(
                        term: 'İki renk seçin',
                        text: 'Yan yana bir renk kutusu ve altı haneli bir kod alanı; böylece bir belgede yazılı marka kodu birebir yazılabilir.',
                        source: 'resources/js/components/workspace/pages/profile/BrandColorsRegion.tsx',
                    ),
                    new BlockEntry(
                        term: 'Kod doğrulanır',
                        text: 'Yalnız tam altı haneli bir renk saklanır. Kısa yazımlar ve renk adları reddedilir; böylece menü şablonu çizemeyeceği bir değeri hiç almaz.',
                        source: 'app/Http/Requests/Tenancy/UpdateBrandRequest.php',
                    ),
                    new BlockEntry(
                        term: 'Yayınlayın',
                        text: 'Logo ve renkler, restoranın adı, adresi ve telefonuyla birlikte yayın anlık görüntüsüne yazılır. Misafirler o anlık görüntüyü okur.',
                        source: 'app/Domain/Publication/MenuIdentity.php',
                    ),
                    new BlockEntry(
                        term: 'Kartları bastırın',
                        text: 'Aynı ana renk masa kartlarına çerçeve, şerit ya da zemin olarak gider. Kodun kendisi her zaman açık üstüne koyu basılır.',
                        source: 'app/Domain/QrDestination/CardTheme.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'Neler yapar', [
                    new BlockEntry(
                        term: 'Adın yanında logo',
                        text: 'Küçük bir logo, menü başlığında restoranınızın adının yanında durur; ekrana uygun boyutta ve sizin yazdığınız alternatif metniyle sunulur.',
                        source: 'resources/views/public-menu.blade.php',
                    ),
                    new BlockEntry(
                        term: 'Renginizde bir şerit',
                        text: 'Ana renk misafir menüsünün üstünde bir şerit, ikincil renk her kategori başlığının altında bir çizgi olarak görünür. Renk seçmemiş bir restoranda şerit de görünmez.',
                        source: 'resources/views/public-menu.blade.php',
                    ),
                    new BlockEntry(
                        term: 'Basılı kartlarda renkler ve logo',
                        text: 'Her kart tasarımı marka rengini kartın üstünde kullanır ve tasarım gösteriyorsa restoranın adını yazar. Logo kart dosyasının içine gömülür, böylece matbaa onu kaybedemez.',
                        source: 'app/Support/QrDestination/QrCardSvg.php',
                    ),
                    new BlockEntry(
                        term: 'Kodu bozacak bir renk reddedilir',
                        text: 'Markalı kod biçemi renginizi ancak kontrast okutmaya yetiyorsa kullanır. Yetmiyorsa klasik koyu kod basılır ve geri düşüş bildirilir.',
                        source: 'app/Domain/QrDestination/QrContrast.php',
                    ),
                    new BlockEntry(
                        term: 'Okunabilirlik umulmaz, ölçülür',
                        text: 'Zabuno renginizden metin, dolgu ve çizgi için tonlar türetir; misafir sayfasının kullanabileceği iki zemine karşı da WCAG 2.2 eşiklerine göre ölçer ve ölçülen sonucu her yayınla birlikte saklar.',
                        source: 'app/Domain/Branding/BrandRamp.php',
                    ),
                    new BlockEntry(
                        term: 'Ad, adres ve telefon da birlikte gider',
                        text: 'Restoran adı, şube adı, adres ve dokunulabilir bir telefon numarası menüde görünür ve renkler gibi yayın anında dondurulur.',
                        source: 'app/Application/Publication/UseCase/BuildPublicationSnapshot.php',
                    ),
                    new BlockEntry(
                        term: 'Ad değişiklikleri plana takılmaz',
                        text: 'Restoran adını, saat dilimini ya da para birimini düzeltmek her planda çalışır. Yalnız görünüm alanları marka hakkının arkasındadır.',
                        source: 'app/Http/Controllers/Tenancy/UpdateBrandController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'Neye ihtiyacınız var', [
                    new BlockEntry(
                        term: 'Marka görünümünü içeren bir plan',
                        text: 'Renklerinizi misafir menüsüne taşımak ücretli bir plana aittir. Onsuz misafir sayfası nötr varsayılanda kalır ve menü yine yayınlanır, yine bastırılır.',
                        source: 'app/Domain/Entitlement/Entitlement.php',
                    ),
                    new BlockEntry(
                        term: 'İşletmeyi yönetme yetkisi',
                        text: 'Logo ve renkler sahip ya da müdür tarafından belirlenir; editör ya da mutfak tarafından değil.',
                        source: 'app/Domain/Authorization/RolePermissions.php',
                    ),
                    new BlockEntry(
                        term: 'Medya kütüphanesinin kabul ettiği bir logo dosyası',
                        text: 'Logo, bir ürün fotoğrafıyla aynı zararlı yazılım taramasından ve boyut sınırlarından geçer ve ancak işlendikten sonra bağlanır.',
                        source: 'app/Http/Controllers/Tenancy/BindBrandLogoController.php',
                    ),
                    new BlockEntry(
                        term: 'Değişiklikten sonra bir yayın',
                        text: 'Misafirler yayınlanmış sürümleri okur. Yeni bir renk masaya bir sonraki yayınla ulaşır, öncesinde değil.',
                        source: 'app/Domain/Publication/MenuIdentity.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'Neyi yapmaz', [
                    new BlockEntry(
                        term: 'Renginiz sayfa değil, dekorasyondur',
                        text: 'Misafir menüsündeki düğmeler, fiyatlar ve bağlantılar sizin renginizi değil ürünün kendi mürekkebini kullanır. Ölçülmüş tonlar vardır ama misafir sayfası metnini onlardan çizmez.',
                        source: 'resources/views/partials/guest-surface-style.blade.php',
                    ),
                    new BlockEntry(
                        term: 'Yazı tipi yok',
                        text: 'Misafir menüsü telefonun kendi sistem yazı tipini kullanır. Yazı tipi seçimi ve yazı tipi yükleme yoktur.',
                        source: 'resources/views/partials/guest-surface-style.blade.php',
                    ),
                    new BlockEntry(
                        term: 'Düzen ya da tema editörü yok',
                        text: 'Boşluk, köşe, gölge ya da bölüm sırası için bir editör yoktur. Misafir sayfasının tek bir düzeni vardır ve tek elle tutulan bir telefona göre ayarlanmıştır.',
                        source: 'resources/views/partials/guest-surface-style.blade.php',
                    ),
                    new BlockEntry(
                        term: 'Özel alan adı yok',
                        text: 'Menü bir Zabuno adresinde yaşar. Kendi alan adınızdan sunulamaz.',
                        source: 'routes/web.php',
                    ),
                    new BlockEntry(
                        term: 'Sekme ikonu Zabuno\'nundur',
                        text: 'Tarayıcı sekmesindeki küçük ikon sizin logonuz değil, ürünün ikonudur.',
                        source: 'resources/views/public-menu.blade.php',
                    ),
                    new BlockEntry(
                        term: 'Kapak fotoğrafı yok',
                        text: 'Başlığın arkasında bir görsel ve menü için bir kapak görseli yoktur. Fotoğraflar ürünlere aittir.',
                        source: 'resources/views/public-menu.blade.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'İşletmecilerin görünüm hakkında sorduğu sorular', [
                    new BlockEntry(
                        term: 'Logomu menüye koyabilir miyim?',
                        text: 'Evet. Medya kütüphanesine yükleyin, markaya bağlayın ve yayınlayın. Menüde restoranın adının yanında ve basılı kartlarda görünür.',
                    ),
                    new BlockEntry(
                        term: 'Düğmelerin rengini değiştirebilir miyim?',
                        text: 'Hayır. Renginiz bir şerit ve bir çizgi olarak kullanılır; düğmeler, fiyatlar ve bağlantılar her telefonda okunabilir kalsın diye ürünün kendi mürekkebinde durur.',
                    ),
                    new BlockEntry(
                        term: 'Marka rengim kodun üstünde neden görünmedi?',
                        text: 'Çünkü kontrast okutmaya yetmedi. Yerine klasik koyu kod basıldı ve geri düşüş bildirildi. Etrafındaki kart yine sizin renginizi taşır.',
                    ),
                    new BlockEntry(
                        term: 'Rengi değiştirdim, menü neden aynı görünüyor?',
                        text: 'Misafir yayınlanmış bir sürümü okur. Yeniden yayınlayın; yeni renk bir sonraki sürümde olur.',
                    ),
                    new BlockEntry(
                        term: 'Kendi yazı tipimi kullanabilir miyim?',
                        text: 'Hayır. Menü, misafirin telefonunun sistem yazı tipini kullanır.',
                    ),
                    new BlockEntry(
                        term: 'Marka görünümü ücretsiz planda mı?',
                        text: 'Hayır. Ücretsiz plan menüyü nötr görünümde yayınlar. Misafir sayfasındaki renkleriniz ücretli bir planın parçasıdır.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'Renklerinizi hangi planın taşıdığına bakın', [
                    new BlockEntry(
                        text: 'Yayınlamak ve bastırmak ücretsizdir; misafir sayfasındaki renkleriniz değildir.',
                        href: '/pricing',
                        term: 'Plan listesine bakın',
                    ),
                ]),

                new ContentBlock(BlockType::Related, 'İlgili sayfalar', [
                    new BlockEntry(text: 'QR menü', pageKey: 'urun.qr-menu'),
                    new BlockEntry(text: 'Masalar ve karekodlar', pageKey: 'urun.masa-ve-qr-yonetimi'),
                    new BlockEntry(text: 'Görsel ve medya', pageKey: 'urun.gorsel-ve-medya'),
                ]),
            ],
        );
    }
}
