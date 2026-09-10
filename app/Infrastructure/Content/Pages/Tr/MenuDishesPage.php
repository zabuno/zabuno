<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages\Tr;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;

/**
 * `/tr/urun/menu-yonetimi/urunler/` — ürünlerin Türkçesi (P0).
 *
 * Sayfanın sorusu iki dilde de aynı: bir ürün NE TAŞIR. Boyut/porsiyon,
 * ekstra ve seçenek, kalori ve besin değeri, diyet etiketi, galeri, çeviri
 * ve ürün başına karekod burada da yazılmaz.
 */
final class MenuDishesPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'urun.menu-yonetimi.urunler',
            locale: 'tr',
            metadata: new PageMetadata(
                seoTitle: 'Menüdeki ürünler',
                metaDescription: 'Bir ürün neler taşır: ad, açıklama, fotoğraf, fiyat ve beyan edilen alerjenler; menüde ve kendi sayfasında. Bir de bugün neyi taşıyamaz.',
                h1: 'Ürünler',
                breadcrumbTitle: 'Ürünler',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'Ürün, menünüzdeki tek bir kalemdir: bir ad, bir açıklama, bir fotoğraf, para biriminizde bir fiyat ve beyan ettiğiniz alerjenler. Silinmeden gizlenebilir, gün için tükendi işaretlenebilir ve gösterecek bir şeyi olduğunda misafirin paylaşabileceği kendi sayfasını alır.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'Kartta bir satır ve garsona bir soru', [
                    new BlockEntry(
                        text: '"Adana kebap" ve bir rakam bir satırdır. İçinde ne olduğunu, neye benzediğini ya da sosundaki fıstığın altı numaralı masadaki misafir için önemli olup olmadığını söylemez. Garson aynı üç soruyu bütün akşam yanıtlar.',
                    ),
                    new BlockEntry(
                        text: 'Kâğıtta bu yanıtları eklemek yer tutar. Telefonda ise eklememek satışa mal olur.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'Her ürün için bir kart, bir kez yazılmış', [
                    new BlockEntry(
                        text: 'Her ürün, misafirin sorduğu şeyi taşır: bir açıklama, bir fotoğraf ve beyan ettiğiniz alerjenler. Gösterecek bir şeyi olan ürün kendi adresini alır; böylece misafir onu bir arkadaşına gönderir ve arkadaşı o ürünün üstünde açar.',
                    ),
                    new BlockEntry(
                        text: 'Ürün bir kez yazılır. İki bölümde listeleyin; açıklama ve alerjenler paylaşılır, dolayısıyla düzeltme tek bir yerde yapılır.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'Nasıl çalışır', [
                    new BlockEntry(
                        term: 'Ürünü tek adımda ekleyin',
                        text: 'Ad, fiyat, para birimi ve beyan edilen alerjenler tek bir işlemde birlikte yazılır. Yarım yazılmış bir ürün hiç var olmaz.',
                        source: 'app/Http/Controllers/MenuCatalog/StoreMenuEntryController.php',
                    ),
                    new BlockEntry(
                        term: 'Tarif edin',
                        text: 'Açıklama, ürünün kendisinde duran serbest metindir. Elle düzenleyin ya da bir ilk taslak isteyip beğenirseniz uygulayın.',
                        source: 'app/Http/Controllers/MenuCatalog/RenameMenuItemController.php',
                    ),
                    new BlockEntry(
                        term: 'Fotoğraf bağlayın',
                        text: 'Medya kütüphanesinden bir fotoğraf seçin. Ürün o fotoğrafa belirli bir sürümde bağlanır; böylece görselin sonraki bir düzenlemesi yayınlanmış bir menüyü değiştirmez. Misafirin görüp görmemesi planınıza bağlıdır.',
                        source: 'app/Http/Controllers/MenuCatalog/BindMenuItemImageController.php',
                    ),
                    new BlockEntry(
                        term: 'Alerjenleri beyan edin',
                        text: 'Beyan ettiğiniz alerjenleri işaretleyin. Misafire sizin beyanınız olarak gösterilir ve değiştirilmeleri bir ad ve bir zamanla kaydedilir.',
                        source: 'app/Http/Controllers/MenuCatalog/UpdateMenuItemAllergensController.php',
                    ),
                    new BlockEntry(
                        term: 'Gizleyin ya da kaldırın',
                        text: 'Mevsimlik bir ürün gizlenir ve saklanır; yanlış olan silinir. Siz yeniden yayınlayana kadar yayınlanmış sürüm ikisinden de etkilenmez.',
                        source: 'app/Http/Controllers/MenuCatalog/DeleteMenuItemController.php',
                    ),
                    new BlockEntry(
                        term: 'Yayınlayın',
                        text: 'Yeni bir ürün misafire bir sonraki yayınlanmış sürümle ulaşır. Yalnız tükendi işareti bu adımı atlar.',
                        source: 'app/Http/Controllers/Publication/StorePublicationController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'Bir ürün neler yapabilir', [
                    new BlockEntry(
                        term: 'Hak ettiğinde kendi sayfası',
                        text: 'Açıklaması, fotoğrafı ya da beyan edilmiş alerjeni olan bir ürün, hepsini gösteren paylaşılabilir bir adres alır. Yalnız adı ve fiyatı olan almaz; böylece arama motorlarına bir satırın yüzlerce kopyası verilmez.',
                        source: 'app/Http/Controllers/QrDestination/ShowPublicMenuItemController.php',
                    ),
                    new BlockEntry(
                        term: 'Eklendiği gün görünür',
                        text: 'Yeni bir ürün varsayılan olarak görünürdür. İlk yayından önce bulunması gereken bir anahtar yoktur.',
                        source: 'database/migrations/2026_08_28_000300_menu_items_default_to_visible.php',
                    ),
                    new BlockEntry(
                        term: 'Sonsuza kadar değil, bugün için tükendi',
                        text: 'Tükendi işaretlenen bir ürün, şubenin günü bitene kadar tükendi gösterilir ve sonra kendiliğinden geri gelir.',
                        source: 'app/Domain/MenuCatalog/StockState.php',
                    ),
                    new BlockEntry(
                        term: 'Açıklamanın ilk taslağı',
                        text: 'Bir açıklama taslağı isteyin, okuyun ve uygulayın ya da atın. Onayınız olmadan ürüne hiçbir şey yazılmaz.',
                        source: 'app/Http/Controllers/Ai/ApplyProductDescriptionDraftController.php',
                    ),
                    new BlockEntry(
                        term: 'İki kez yazılmış aynı ürün bulunur',
                        text: 'Zabuno, adları kopya görünen ürünleri gösterebilir. Gösterir; birleştirmez.',
                        source: 'app/Http/Controllers/Ai/ShowDuplicateProductCandidatesController.php',
                    ),
                    new BlockEntry(
                        term: 'Değişiklikler kaydedilir',
                        text: 'Bir ürünü eklemek, adlandırmak, yeniden fiyatlamak, gizlemek ve silmek ile alerjenlerini değiştirmek, kim ve ne zaman bilgisiyle denetim izine gider.',
                        source: 'app/Domain/MenuCatalog/MenuAuditAction.php',
                    ),
                    new BlockEntry(
                        term: 'Ekrana göre boyutlanmış fotoğraf',
                        text: 'Misafir sayfası, telefonunuzun çektiği orijinali değil, ekrana uyan kopyayı ister.',
                        source: 'resources/views/public-menu-item.blade.php',
                    ),
                    new BlockEntry(
                        term: 'Misafir arayabilir ve süzebilir',
                        text: 'Menü sayfasında misafir ürünleri ada göre arayabilir ve betikler çalıştığında listeyi beyan edilen alerjene ya da fiyat aralığına göre daraltabilir.',
                        source: 'resources/views/public-menu.blade.php',
                    ),
                    new BlockEntry(
                        term: 'Hesap tablosuyla girer ve çıkar',
                        text: 'Her ürün kategori, ad, fiyat, para birimi, alerjenler, açıklama ve görünürlükle birlikte bir satır olarak dışa aktarılır ve aynı yoldan içeri alınır.',
                        source: 'app/Application/MenuCatalog/Csv/MenuCsv.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'Neye ihtiyacınız var', [
                    new BlockEntry(
                        term: 'Bir ad ve bir fiyat',
                        text: 'Görünür bir ürün, menü yayınlanmadan önce boş olmayan bir ad ve sıfırdan büyük bir fiyat ister.',
                        source: 'app/Application/Publication/UseCase/BuildPublicationSnapshot.php',
                    ),
                    new BlockEntry(
                        term: 'Markanın para biriminde bir fiyat',
                        text: 'Fiyat, işletmenizin para biriminde ondalıklı olarak yazılır ve ondalık basamaklar o para birimine uymalıdır.',
                        source: 'app/Domain/Money/Money.php',
                    ),
                    new BlockEntry(
                        term: 'Menüyü yönetme yetkisi',
                        text: 'Ürünleri sahipler, müdürler ve editörler ekler ve düzenler. Mutfak rolü yalnız alerjenleri ve tükendi durumunu işaretler.',
                        source: 'app/Domain/Authorization/RolePermissions.php',
                    ),
                    new BlockEntry(
                        term: 'Plan gerekmez',
                        text: 'Bu sayfadaki her şey ücretsiz plandadır.',
                        source: 'database/seeders/PlanCatalogueSeeder.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'Bir ürün neyi taşıyamaz', [
                    new BlockEntry(
                        term: 'Tek fiyat, boyut ya da porsiyon yok',
                        text: 'Küçük ve büyük bugün iki üründür; iki fiyatı olan tek bir ürün değil.',
                        source: 'database/migrations/2026_08_20_000002_create_menu_catalog_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Ekstra ya da seçim yok',
                        text: 'Ekstra peynir, sos seçimi ve pişirme tercihi bir ürünün parçası değildir.',
                        source: 'database/migrations/2026_08_20_000002_create_menu_catalog_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Kalori, besin değeri ve diyet etiketi yok',
                        text: 'Yalnız beyan ettiğiniz alerjenler saklanır. Vegan, vejetaryen ya da glutensiz etiketi ve enerji rakamı yoktur.',
                        source: 'app/Domain/MenuCatalog/Product.php',
                    ),
                    new BlockEntry(
                        term: 'Tek fotoğraf',
                        text: 'Bir ürün tek bir fotoğraf taşır, bir galeri değil.',
                        source: 'app/Application/Media/Port/MenuMediaPort.php',
                    ),
                    new BlockEntry(
                        term: 'Adlar ve açıklamalar çevrilmez',
                        text: 'Ürün tek bir dilde yazılır. Misafir arayüzünün dilini değiştirmek onu çevirmez.',
                        source: 'database/migrations/2026_08_28_000400_add_description_to_products.php',
                    ),
                    new BlockEntry(
                        term: 'Beyan, garanti değil',
                        text: 'Sayfa hangi alerjenleri beyan ettiğinizi yazar. Bir ürünün herhangi bir şeyden arınmış olduğunu asla iddia etmez.',
                        source: 'resources/views/public-menu-item.blade.php',
                    ),
                    new BlockEntry(
                        term: 'Ürün başına kod yok',
                        text: 'Ürünün bir web adresi vardır ama kendi basılabilir karekodu yoktur.',
                        source: 'routes/api/qr-destination.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'İşletmecilerin ürünler hakkında sorduğu sorular', [
                    new BlockEntry(
                        term: 'Bir ürüne açıklama yazıp fotoğraf ekleyebilir miyim?',
                        text: 'Evet. İkisi de ürünün üstünde yaşar ve menüde ve ürünün kendi sayfasında görünür.',
                    ),
                    new BlockEntry(
                        term: 'Bir ürünün neden kendi sayfası var da ötekinin yok?',
                        text: 'Sayfa yalnız açıklaması, fotoğrafı ya da beyan edilmiş alerjeni olan bir ürün için üretilir. Tek başına ad ve fiyat, menü satırının kopyası olurdu.',
                    ),
                    new BlockEntry(
                        term: 'Küçük ve büyük boy sunabilir miyim?',
                        text: 'Tek bir üründe hayır. Bugün için iki ürün ekleyin.',
                    ),
                    new BlockEntry(
                        term: 'Açıklamalarımı Zabuno mu yazıyor?',
                        text: 'Bir ilk taslak sunabilir. Siz okur ve uygularsınız ya da uygulamazsınız; siz olmadan hiçbir şey yazılmaz.',
                    ),
                    new BlockEntry(
                        term: 'Bir ürünü vegan ya da glutensiz işaretleyebilir miyim?',
                        text: 'Hayır. Bugün yalnız beyan edilen alerjenler saklanır. Bunu açıklamada söyleyebilirsiniz.',
                    ),
                    new BlockEntry(
                        term: 'Bir ürünü gizledim, masalardaki menüden gitti mi?',
                        text: 'Siz yayınlayana kadar hayır. Yayınlanmış sürüm olduğu gibi kalır.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'Ürün eklemek bir bedel istemez', [
                    new BlockEntry(
                        text: 'Bir ürünü eklemek, tarif etmek ve fotoğraflamak ücretsiz plandadır. O fotoğrafı menüde misafire göstermek ise ücretli planın açtığı şeydir.',
                        href: '/pricing',
                        term: 'Planları karşılaştırın',
                    ),
                ]),

                new ContentBlock(BlockType::Related, 'İlgili sayfalar', [
                    new BlockEntry(text: 'Menü yönetimi', pageKey: 'urun.menu-yonetimi'),
                    new BlockEntry(text: 'Kategoriler', pageKey: 'urun.menu-yonetimi.kategoriler'),
                    new BlockEntry(text: 'Görsel ve medya', pageKey: 'urun.gorsel-ve-medya'),
                    new BlockEntry(text: 'Zabuno yapay zekâsı', pageKey: 'urun.zabuno-ai'),
                ]),
            ],
        );
    }
}
