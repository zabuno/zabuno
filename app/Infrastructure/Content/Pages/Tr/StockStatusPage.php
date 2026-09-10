<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages\Tr;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;

/**
 * `/tr/urun/menu-yonetimi/stok-durumu/` — "bugün tükendi" (P0).
 *
 * Türkçe sayfa da "stok" kelimesini SATMAZ: sayım, adet ve otomatik düşüm
 * yok. Anlatılan şey, "bugün tükendi" işaretinin ne yaptığı, ne zaman
 * düştüğü ve kimin koyabildiğidir. Adet/envanter, siparişten otomatik
 * tükenme, "saat altıda geri gelir", çok günlük işaret, bildirim ve işaretin
 * denetim izi burada da yazılmaz.
 */
final class StockStatusPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'urun.menu-yonetimi.stok-durumu',
            locale: 'tr',
            metadata: new PageMetadata(
                seoTitle: 'Bugün tükendi',
                metaDescription: 'Bir ürünü tükendi işaretleyin; misafir bunu yayın olmadan bir sonraki okutmada görsün. İşaret gününüz bitince kendiliğinden düşer, ürün menüden çıkmaz.',
                h1: 'Stok durumu',
                breadcrumbTitle: 'Stok durumu',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'Stok durumu, "bugün tükendi" işaretidir. Bir ürünü ya da koca bir listeyi tükendi işaretleyin; misafirler bunu yayın olmadan, bir sonraki okutmada kelimeyle okur. İşaret şubenizin günü bitince kendiliğinden düşer ve ürün bu süre boyunca menüde kalır.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'Balık bitti ve menünün haberi yok', [
                    new BlockEntry(
                        text: 'Saat dokuzda levrek bitmiştir. Dört numaralı masadaki kart onu hâlâ listeler, misafir sipariş eder ve garson kötü haberi geri taşır. Ürünü gizlemek yanlış araçtır: gizlenen ürün menüden yok olur ve balık için gelen misafir, hiç balığı olmamış bir menü okur.',
                    ),
                    new BlockEntry(
                        text: 'Ertesi sabah balık geri gelir ve birinin, ilk misafir oturmadan önce altı ürünü tek tek gizlilikten çıkarmayı hatırlaması gerekir.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'Menü değişikliği değil, tebeşirle bir not', [
                    new BlockEntry(
                        text: 'Tükendi, ürünün menüde olup olmamasından ayrı, ürünün üstünde duran bir işarettir. Ürün yerinde, fiyatıyla listelenmeye devam eder ve kısa bir satır tükendiğini söyler. Balık için gelen misafir, balığınızın olduğunu ama bu akşam olmadığını bilir.',
                    ),
                    new BlockEntry(
                        text: 'İşaret bir anahtar değil, bir zamandır. Şubenin günü boyunca doğrudur ve ertesi sabah yanlıştır; kimse ona dokunmadan ve çalışmayı kaçırabilecek zamanlanmış bir iş olmadan.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'Nasıl çalışır', [
                    new BlockEntry(
                        term: 'Tek bir ürünü işaretleyin',
                        text: 'Tek bir istek bir ürünü tükendi ya da yeniden stokta olarak işaretler.',
                        source: 'app/Http/Controllers/MenuCatalog/UpdateMenuItemStockController.php',
                    ),
                    new BlockEntry(
                        term: 'Ya da listeyi işaretleyin',
                        text: 'Birkaç ürün tek istekte işaretlenir: bitenler ve geri gelenler birlikte.',
                        source: 'app/Http/Controllers/MenuCatalog/UpdateMenuStockController.php',
                    ),
                    new BlockEntry(
                        term: 'Misafir bir sonraki okutmada görür',
                        text: 'Yayına gerek yoktur. Yayınlanmış sürüm değişmez; işaret onun üstünde canlı okunur.',
                        source: 'app/Http/Controllers/QrDestination/ShowPublicMenuController.php',
                    ),
                    new BlockEntry(
                        term: 'Gece yarısı kendiliğinden düşer, sizin gece yarınızda',
                        text: 'İşaret konduğu zamanı taşır ve yalnız şubenin saat diliminde aynı gün için tükendi sayılır.',
                        source: 'app/Domain/MenuCatalog/StockState.php',
                    ),
                    new BlockEntry(
                        term: 'Ya da siz kaldırın',
                        text: 'Bir sevkiyat gelirse ürünü yeniden stokta işaretleyin; bir sonraki okutmada geri döner.',
                        source: 'app/Http/Controllers/MenuCatalog/UpdateMenuItemStockController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'Neler yapar', [
                    new BlockEntry(
                        term: 'Misafir sayfasında kelimeyle yazılır',
                        text: 'Tükenen ürün kendi bölümünde adı ve fiyatıyla, kısa bir tükendi satırıyla kalır; böylece durum yalnız renge bağlı olmaz.',
                        source: 'resources/views/public-menu.blade.php',
                    ),
                    new BlockEntry(
                        term: 'Ürün sayfasında da',
                        text: 'Ürünün kendi sayfası aynı tükendi satırını gösterir.',
                        source: 'resources/views/public-menu-item.blade.php',
                    ),
                    new BlockEntry(
                        term: 'Mutfak da yapabilir',
                        text: 'Mutfak rolü alerjenleri ve tükendi durumunu işaretler, başka hiçbir şeyi değil. Aşçı, bir fiyatı değiştiremeden balığın bittiğini söyleyebilir.',
                        source: 'app/Domain/Tenancy/MembershipRole.php',
                    ),
                    new BlockEntry(
                        term: 'Sınır sunucudadır',
                        text: 'Fiyat ucunu çağıran bir mutfak hesabı reddedilir. Bir düğmeyi gizlemek bir yetki değildir; sunucu yetkidir.',
                        source: 'tests/Feature/MenuCatalog/KitchenRoleMenuBoundaryTest.php',
                    ),
                    new BlockEntry(
                        term: 'Tükendi, gizli demek değildir',
                        text: 'Gizli ile tükendi iki ayrı durumdur. Gizli ürün yoktur; tükenen ürün vardır ama alınamaz.',
                        source: 'database/migrations/2026_08_28_000500_add_out_of_stock_to_menu_items.php',
                    ),
                    new BlockEntry(
                        term: 'Sipariş reddeder',
                        text: 'Masadan sipariş açıkken tükenen bir ürün sepete konamaz ve onu içeren bir sipariş sebebiyle birlikte reddedilir.',
                        source: 'app/Application/Ordering/UseCase/BuildOrderLines.php',
                    ),
                    new BlockEntry(
                        term: 'Bilerek denetim izinin dışında',
                        text: 'Tükendi işaretleri sistemdeki en sık değişikliktir ve kendiliğinden düşer. Onları kaydetmek, fiyat sorusunu bir akşamlık servisin altına gömerdi.',
                        source: 'app/Domain/MenuCatalog/MenuAuditAction.php',
                    ),
                    new BlockEntry(
                        term: 'Panelde doğru gösterilir',
                        text: 'Menü düzenleyicisi, tükendi durumunu şubenin saat diliminde bugün için hesaplanmış hâliyle, görünürlükten ayrı gösterir.',
                        source: 'app/Http/Controllers/MenuCatalog/Support/MenuTreePayload.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'Neye ihtiyacınız var', [
                    new BlockEntry(
                        term: 'Yayınlanmış bir menü',
                        text: 'İşaret, yayınlanmış sürümün üstünde okunur. Yayınlanmamış bir ürünün tükendi gösterileceği bir yer yoktur.',
                        source: 'app/Http/Controllers/QrDestination/ShowPublicMenuController.php',
                    ),
                    new BlockEntry(
                        term: 'Stok işaretleme yetkisi',
                        text: 'Sahipler, müdürler, editörler ve mutfak rolü stok işaretleyebilir.',
                        source: 'app/Domain/Authorization/RolePermissions.php',
                    ),
                    new BlockEntry(
                        term: 'Şubede bir saat dilimi',
                        text: 'Gün, şubenin kendi saat diliminde biter; bu yüzden şube bir saat dilimi taşır ve zaten her zaman taşır.',
                        source: 'database/migrations/2026_08_28_000100_move_timezone_ownership_to_locations.php',
                    ),
                    new BlockEntry(
                        term: 'Plan gerekmez',
                        text: 'Bir ürünü tükendi işaretlemek ücretsiz plandadır.',
                        source: 'database/seeders/PlanCatalogueSeeder.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'Neyi yapmaz', [
                    new BlockEntry(
                        term: 'Adet yok',
                        text: 'Miktar yoktur. Zabuno dört porsiyonunuzun kaldığını bilmez; yalnız tükenip tükenmediğini bilir.',
                        source: 'database/migrations/2026_08_28_000500_add_out_of_stock_to_menu_items.php',
                    ),
                    new BlockEntry(
                        term: 'Siparişten otomatik tükenme yok',
                        text: 'Masadan gönderilen siparişler bir stok rakamını düşürmez, çünkü öyle bir rakam yoktur. Ürünü biri işaretler.',
                        source: 'app/Application/Ordering/UseCase/BuildOrderLines.php',
                    ),
                    new BlockEntry(
                        term: '"Altıda geri gelir" yok',
                        text: 'Bir ürün seçtiğiniz bir saate kadar tükendi işaretlenemez. Gün bitene ya da biri kaldırana kadar tükendidir.',
                        source: 'app/Domain/MenuCatalog/StockState.php',
                    ),
                    new BlockEntry(
                        term: 'Çok günlük işaret yok',
                        text: 'Bütün hafta bulunmayacak bir ürünün her gün yeniden işaretlenmesi ya da gizlenmesi gerekir.',
                        source: 'app/Domain/MenuCatalog/StockState.php',
                    ),
                    new BlockEntry(
                        term: 'Bildirim yok',
                        text: 'Bir ürün tükendi işaretlendiğinde kimseye e-posta ya da mesaj gitmez.',
                        source: 'routes/api/menu-catalog.php',
                    ),
                    new BlockEntry(
                        term: 'Kimin işaretlediğinin kaydı yok',
                        text: 'Tükendi işaretleri denetim izinde değildir.',
                        source: 'app/Domain/MenuCatalog/MenuAuditAction.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'İşletmecilerin tükendi hakkında sorduğu sorular', [
                    new BlockEntry(
                        term: 'Bir ürünü tükendi işaretledikten sonra yayınlamam gerekir mi?',
                        text: 'Hayır. İşaret yayınlanmış sürümün üstünde canlı okunur ve misafirler bunu bir sonraki okutmada görür.',
                    ),
                    new BlockEntry(
                        term: 'Ürün yarın kendiliğinden geri gelir mi?',
                        text: 'Evet. İşaret yalnız konduğu gün için, şubenizin saat diliminde geçerlidir.',
                    ),
                    new BlockEntry(
                        term: 'Aşçım fiyatları görmeden bir ürünü tükendi işaretleyebilir mi?',
                        text: 'Evet. Mutfak rolü alerjenleri ve tükendi durumunu işaretler, başka hiçbir şeyi değil; sınır da sunucuda uygulanır.',
                    ),
                    new BlockEntry(
                        term: 'Ürünü gizlemeli miyim yoksa tükendi mi işaretlemeliyim?',
                        text: 'Geri gelecekse tükendi. Gizlenen ürün menüden kaybolur; tükenen ürün bir notla listede kalır.',
                    ),
                    new BlockEntry(
                        term: '"Altıda geri gelir" diyebilir miyim?',
                        text: 'Hayır. İşaret gün bitene ya da siz kaldırana kadar sürer.',
                    ),
                    new BlockEntry(
                        term: 'Misafir tükenen bir ürünü sipariş edebilir mi?',
                        text: 'Hayır. Sipariş açıkken tükenen bir ürün sepete eklenemez ve onu içeren bir sipariş reddedilir.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'Tükendi ücretsizdir', [
                    new BlockEntry(
                        text: 'Bir ürünü tükendi işaretlemek ücretsiz planın parçasıdır.',
                        href: '/pricing',
                        term: 'Plan seçeneklerine bakın',
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
