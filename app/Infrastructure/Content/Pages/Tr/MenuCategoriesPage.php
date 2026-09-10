<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages\Tr;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;

/**
 * `/tr/urun/menu-yonetimi/kategoriler/` — menü kategorilerinin Türkçesi (P0).
 *
 * Alt sayfa, ebeveyninin kopyası değildir: ebeveynin tek adımda geçtiği şeyin
 * kendi soruları burada sorulur. Alt kategori, gizli kategori, kategori
 * görseli ya da açıklaması, saate bağlı bölüm ve çevrilmiş başlık burada da
 * yazılmaz.
 */
final class MenuCategoriesPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'urun.menu-yonetimi.kategoriler',
            locale: 'tr',
            metadata: new PageMetadata(
                seoTitle: 'Menü kategorileri',
                metaDescription: 'Misafirin aralarında atladığı menü bölümleri. Oluşturun, adlandırın, sıralayın ve kaldırın; kaldırılan bölüm içindeki ürünleri de götürür.',
                h1: 'Kategoriler',
                breadcrumbTitle: 'Kategoriler',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'Kategori, menünüzün bir bölümüdür: Başlangıçlar, Izgaralar, Tatlılar. Kategorileri taslakta oluşturur, adlandırır, sıralar ve kaldırırsınız; kurduğunuz sıra, misafirin okuduğu ve menü sayfasının başında aralarında atladığı sıradır.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'Tek uzun listede kırk ürün', [
                    new BlockEntry(
                        text: 'Bölümsüz bir menü bir kaydırmadır. Tatlı arayan misafir yolda bütün ızgaraların önünden geçer; çorba arayan ise çorbaya varmadan vazgeçer.',
                    ),
                    new BlockEntry(
                        text: 'Kâğıt bunu başlıklarla çözer ve sonra dondurur: haziranda basılan mevsimlik bir bölüm ekimde hâlâ kartın üstündedir.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'Mevsimle birlikte hareket eden bölümler', [
                    new BlockEntry(
                        text: 'Kategoriler menünüzün başlıklarıdır ve yayınlar arasında değiştirmek size aittir. Mayısta bir yaz bölümü ekleyin, en üste taşıyın ve eylülde kaldırın; içindeki ürünler de onunla gider.',
                    ),
                    new BlockEntry(
                        text: 'Misafir aynı başlıkları sayfanın başında atlama bağlantısı olarak alır; böylece telefon ekranı bir kaydırma gibi değil, bir içindekiler sayfası gibi davranır.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'Nasıl çalışır', [
                    new BlockEntry(
                        term: 'Kategori oluşturun',
                        text: 'Ona bir ad verin. Menünün sonuna eklenir ve taşınabilir.',
                        source: 'app/Http/Controllers/MenuCatalog/StoreCategoryController.php',
                    ),
                    new BlockEntry(
                        term: 'İçine ürün koyun',
                        text: 'Ürün bir kategoriye tek adımda eklenir: ad, fiyat, para birimi ve beyan edilen alerjenler birlikte.',
                        source: 'app/Http/Controllers/MenuCatalog/StoreMenuEntryController.php',
                    ),
                    new BlockEntry(
                        term: 'Sırayı belirleyin',
                        text: 'Kategorileri istediğiniz sıraya taşıyın. Konum menü başına saklanır ve iki kategori aynı sırayı tutamaz.',
                        source: 'app/Http/Controllers/MenuCatalog/ReorderCategoriesController.php',
                    ),
                    new BlockEntry(
                        term: 'Hiçbir şey kaybetmeden adlandırın',
                        text: 'Bir kategorinin adını değiştirmek onun ürünlerini ve konumunu korur.',
                        source: 'app/Http/Controllers/MenuCatalog/RenameCategoryController.php',
                    ),
                    new BlockEntry(
                        term: 'Bir bölümü kaldırın',
                        text: 'Bir kategoriyi silmek içindeki her ürün satırını da siler. Ürünler başka bir yere taşınmaz; korumak istiyorsanız önce onları taşıyın.',
                        source: 'app/Http/Controllers/MenuCatalog/DeleteCategoryController.php',
                    ),
                    new BlockEntry(
                        term: 'Yayınlayın',
                        text: 'Misafirler yeni başlıkları ve sırayı bir sonraki yayınlanmış sürümle görür; siz onları düzenlerken değil.',
                        source: 'app/Http/Controllers/Publication/StorePublicationController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'Neler yapar', [
                    new BlockEntry(
                        term: 'Misafir için atlama bağlantıları',
                        text: 'Her kategori, misafir menüsünün başında kendi bölümüne kaydıran bir bağlantıya dönüşür. Bağlantılar sıradan çıpalardır ve betiksiz çalışır.',
                        source: 'resources/views/public-menu.blade.php',
                    ),
                    new BlockEntry(
                        term: 'Tek sıra, birebir korunur',
                        text: 'Kategori konumları menü içinde tekildir; böylece kaydettiğiniz sıra yayınlanan sıradır ve çözülecek bir eşitlik yoktur.',
                        source: 'database/migrations/2026_08_20_000002_create_menu_catalog_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Aynı ürün iki bölümde',
                        text: 'Hem Kahvaltı hem Gün Boyu bölümüne ait bir ürün ikisinde de listelenebilir. Adı, açıklaması ve alerjenleri bir kez yazılır ve paylaşılır.',
                        source: 'app/Http/Controllers/MenuCatalog/StoreMenuItemController.php',
                    ),
                    new BlockEntry(
                        term: 'Boş bir bölüm bunu söyler',
                        text: 'Görünür ürünü olmayan bir kategori, misafire boş bir başlık yerine kısa bir not gösterir.',
                        source: 'resources/views/public-menu.blade.php',
                    ),
                    new BlockEntry(
                        term: 'Hesap tablosunda kategoriler',
                        text: 'Dışa aktarma kategori adını her ürün satırına yazar; içe aktarma ise bölümleri o sütundan yeniden kurar.',
                        source: 'app/Application/MenuCatalog/Csv/MenuCsv.php',
                    ),
                    new BlockEntry(
                        term: 'Ekleme, adlandırma ve kaldırmanın kaydı',
                        text: 'Bir kategoriyi oluşturmak, adlandırmak ve kaldırmak, kimin ne zaman yaptığıyla birlikte menü denetim izine yazılır.',
                        source: 'app/Domain/MenuCatalog/MenuAuditAction.php',
                    ),
                    new BlockEntry(
                        term: 'Fotoğraftan okunan bölümler',
                        text: 'Bir menü fotoğraftan okunduğunda, onayladığınız taslak bölümleri zaten gruplanmış hâlde gelir.',
                        source: 'app/Application/Ai/UseCase/ApplyMenuArtifact.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'Neye ihtiyacınız var', [
                    new BlockEntry(
                        term: 'Yayınlamak için en az bir kategori',
                        text: 'Kategorisi olmayan bir menü yayınlanamaz ve yayın, adı boş bir kategoriyi reddeder.',
                        source: 'app/Application/Publication/UseCase/BuildPublicationSnapshot.php',
                    ),
                    new BlockEntry(
                        term: 'Menüyü yönetme yetkisi',
                        text: 'Kategorileri sahipler, müdürler ve editörler düzenler. Mutfak rolü düzenleyemez.',
                        source: 'app/Domain/Authorization/RolePermissions.php',
                    ),
                    new BlockEntry(
                        term: 'Başka bir şey değil ve plan da değil',
                        text: 'Kategoriler ücretsiz yolculuğun parçasıdır.',
                        source: 'database/seeders/PlanCatalogueSeeder.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'Neyi yapmaz', [
                    new BlockEntry(
                        term: 'Alt kategori yok',
                        text: 'Bir kategori başka kategorileri değil ürünleri tutar. Izgaralar, iç içe bölümler olarak Kuzu ve Tavuk içeremez.',
                        source: 'database/migrations/2026_08_20_000002_create_menu_catalog_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Gizli kategori yok',
                        text: 'Bir ürün gizlenebilir, bir kategori gizlenemez. Bir bölümü menüden uzak tutmak için ürünlerini gizleyin ya da bölümü kaldırın.',
                        source: 'routes/api/menu-catalog.php',
                    ),
                    new BlockEntry(
                        term: 'Kategoride görsel ya da açıklama yok',
                        text: 'Kategori bir addır. Kendi fotoğrafı ve kendi metni yoktur.',
                        source: 'database/migrations/2026_08_20_000002_create_menu_catalog_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Saatli bölüm yok',
                        text: 'Bir kategori kahvaltı saatinde kendini açamaz. O iş menülere aittir: bir şube saatine göre birbirine devreden birkaç menü tutabilir.',
                        source: 'app/Http/Controllers/MenuCatalog/UpdateMenuServiceWindowController.php',
                    ),
                    new BlockEntry(
                        term: 'Sıra değişiklikleri kaydedilmez',
                        text: 'Bir kategoriyi yukarı ya da aşağı taşımak denetim izine yazılmaz. Menü düzenlenirken onlarca kez değişir ve kimse bunu kimin yaptığını sormaz.',
                        source: 'app/Domain/MenuCatalog/MenuAuditAction.php',
                    ),
                    new BlockEntry(
                        term: 'Kategori adları çevrilmez',
                        text: 'Başlık bir kez, sizin dilinizde yazılır ve misafir onu yazıldığı gibi görür.',
                        source: 'database/migrations/2026_08_20_000002_create_menu_catalog_tables.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'İşletmecilerin bölümler hakkında sorduğu sorular', [
                    new BlockEntry(
                        term: 'Bir kategoriyi silince ürünlere ne olur?',
                        text: 'Onunla birlikte silinirler. Korumak istiyorsanız önce başka bir kategoriye taşıyın.',
                    ),
                    new BlockEntry(
                        term: 'Bütün bir bölümü kış boyunca gizleyebilir miyim?',
                        text: 'Bölüm olarak hayır. İçindeki ürünleri gizleyin ya da bölümü kaldırıp sonradan bir hesap tablosundan geri getirin.',
                    ),
                    new BlockEntry(
                        term: 'Tek bir ürün iki kategoride olabilir mi?',
                        text: 'Evet. İkisinde de listelenir ve bir kez düzenlenir.',
                    ),
                    new BlockEntry(
                        term: 'Misafir kategorileri benim sıramla mı görür?',
                        text: 'Evet, hem başlık olarak hem de sayfanın başındaki atlama bağlantıları olarak.',
                    ),
                    new BlockEntry(
                        term: 'Bir kategorinin kendi fotoğrafı olabilir mi?',
                        text: 'Hayır. Fotoğraflar ürünlere aittir.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'Bölümler hiçbir şeye mal olmaz', [
                    new BlockEntry(
                        text: 'Kategoriler, ürünler ve yayınlamak ücretsiz planın parçasıdır.',
                        href: '/pricing',
                        term: 'Planlara göz atın',
                    ),
                ]),

                new ContentBlock(BlockType::Related, 'İlgili sayfalar', [
                    new BlockEntry(text: 'Menü yönetimi', pageKey: 'urun.menu-yonetimi'),
                    new BlockEntry(text: 'Ürünler', pageKey: 'urun.menu-yonetimi.urunler'),
                    new BlockEntry(text: 'QR menü', pageKey: 'urun.qr-menu'),
                ]),
            ],
        );
    }
}
