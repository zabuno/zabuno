<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages\Tr;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;

/**
 * `/tr/urun/menu-yonetimi/urun-fiyatlari/` — menü fiyatlarının Türkçesi (P0).
 *
 * Rakam burada da YAZILMAZ: sayfada tek bir fiyat örneği yok, çünkü örnek
 * bir fiyat yarın birinin gerçek sandığı fiyattır. Boyut/porsiyon fiyatı,
 * indirim, happy hour, kampanya fiyatı, KDV satırı, döviz çevrimi, panelde
 * toplu fiyat değişikliği, misafire fiyat geçmişi ve ödeme yazılmaz.
 */
final class MenuPricesPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'urun.menu-yonetimi.urun-fiyatlari',
            locale: 'tr',
            metadata: new PageMetadata(
                seoTitle: 'Menü fiyatları',
                metaDescription: 'Tahsil ettiğiniz para biriminde ürün başına tek fiyat; taslakta değişir, zamanlayabildiğiniz bir yayınla çıkar ve kimin ne zaman değiştirdiğiyle kaydedilir.',
                h1: 'Fiyatlar',
                breadcrumbTitle: 'Fiyatlar',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'Her ürün, işletmenizin para biriminde tek bir fiyat taşır. Fiyatı taslakta değiştirirsiniz, siz yayınladığınızda misafirlere ulaşır, yayın seçtiğiniz dakikaya zamanlanabilir ve her fiyat değişikliği kimin ne zaman yaptığıyla kaydedilir.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'Masadaki fiyat bir sözdür', [
                    new BlockEntry(
                        text: 'Misafir okuduğu fiyattan sipariş verir. Kart geçen ayın rakamını gösteriyorsa fark kasada tartışılır ve o tartışmayı orada duran kişi kaybeder.',
                    ),
                    new BlockEntry(
                        text: 'Kâğıtta fiyat değiştirmek yeniden baskı demektir; bu yüzden fiyatlar nadiren ve topluca, genellikle akşamın yanlış anında değişir.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'Kapalı kapı ardında değiştirin, bilerek çıkarın', [
                    new BlockEntry(
                        text: 'Fiyatlar, masadaki hiç kimsenin göremediği bir taslakta düzenlenir. Yayınladığınızda o andan sonra okutan her misafir yeni fiyatı okur; zaten okumakta olduğu sürüm altından değişmez.',
                    ),
                    new BlockEntry(
                        text: 'Yayın zamanlanabilir; böylece bu gece yaptığınız düzenlemeler kimse uyanık olmadan sabah altıda yürürlüğe girer.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'Nasıl çalışır', [
                    new BlockEntry(
                        term: 'Fiyatı yazın',
                        text: 'Ondalıklı bir tutar girin. Para biriminizin ondalığına karşı denetlenir ve tam sayı kuruş olarak saklanır; böylece hiçbir şey iki kez yuvarlanmaz.',
                        source: 'app/Domain/Money/Money.php',
                    ),
                    new BlockEntry(
                        term: 'Para biriminize karşı denetlenir',
                        text: 'Markanınkinden başka bir para birimindeki fiyat çevrilmez, reddedilir.',
                        source: 'app/Http/Controllers/MenuCatalog/UpdateMenuItemPriceController.php',
                    ),
                    new BlockEntry(
                        term: 'Telefonda önizleyin',
                        text: 'İmzalı ve süresi dolan bir önizleme bağlantısı, taslağı yeni fiyatlarıyla birlikte, başka kimse görmeden gerçek bir telefonda gösterir.',
                        source: 'app/Http/Controllers/Publication/CreateDraftPreviewLinkController.php',
                    ),
                    new BlockEntry(
                        term: 'Şimdi ya da belirli bir saatte yayınlayın',
                        text: 'Hemen yayınlayın ya da yayını zamanlayın; sunucu onu şubenin saat diliminde, dakikası dakikasına gerçekleştirir.',
                        source: 'app/Http/Controllers/Publication/StorePublicationScheduleController.php',
                    ),
                    new BlockEntry(
                        term: 'Hatayı geri alın',
                        text: 'Yeni fiyatlar yanlışsa daha önce yayınlanmış bir sürümü geri getirin.',
                        source: 'app/Http/Controllers/Publication/RestorePublicationController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'Neler yapar', [
                    new BlockEntry(
                        term: 'Alışkanlığa göre değil, para birimine göre biçimlenir',
                        text: 'Lira ve euroda iki ondalık, yende hiç, dinarda üç. Ondalık sayısı para biriminin kendisinden gelir.',
                        source: 'app/Domain/Money/MoneyFormatter.php',
                    ),
                    new BlockEntry(
                        term: 'Misafirin sayı okuduğu biçimde yazılır',
                        text: 'Binlik ve ondalık ayırıcı, misafirin arayüz dilini izler.',
                        source: 'app/Support/Money/PriceLabel.php',
                    ),
                    new BlockEntry(
                        term: 'Yanlış fiyat yerine fiyatsız',
                        text: 'Biçimlendirilemeyen bir fiyat, yanlış gösterilmek yerine sayfanın dışında bırakılır.',
                        source: 'app/Support/Money/PriceLabel.php',
                    ),
                    new BlockEntry(
                        term: 'Fiyatlar her yayında dondurulur',
                        text: 'Yayınlanmış bir sürüm fiyatlarını içinde taşır. Bugün düzenlenen bir fiyat, dünkü sürümün gösterdiğini değiştirmez.',
                        source: 'app/Application/Publication/UseCase/BuildPublicationSnapshot.php',
                    ),
                    new BlockEntry(
                        term: 'Kebabı kim değiştirdi',
                        text: 'Her fiyat değişikliği kişi ve zamanla birlikte denetim izine yazılır. Birçok fiyatı değiştiren bir hesap tablosu içe aktarımı tek bir özet kayıt olarak yazılır.',
                        source: 'app/Domain/MenuCatalog/MenuAuditAction.php',
                    ),
                    new BlockEntry(
                        term: 'Hesap tablosuyla bütün fiyatlar birden',
                        text: 'Menüyü dışa aktarın, fiyat sütununu değiştirin, geri alın. İçe aktarma taslağa yazar, asla doğrudan masalara değil.',
                        source: 'app/Http/Controllers/MenuCatalog/ImportMenuCsvController.php',
                    ),
                    new BlockEntry(
                        term: 'Şubeye göre farklı fiyatlar',
                        text: 'Menüler bir şubeye aittir, dolayısıyla bir şubenin fiyatları kendisinindir. Aynı ürün iki adreste farklı tutabilir.',
                        source: 'database/migrations/2026_09_05_000400_allow_many_menus_per_location.php',
                    ),
                    new BlockEntry(
                        term: 'Misafir fiyata göre süzebilir',
                        text: 'Menü sayfasında misafir, betikler çalıştığında listeyi bir fiyat aralığına daraltabilir.',
                        source: 'resources/views/public-menu.blade.php',
                    ),
                    new BlockEntry(
                        term: 'Siparişler sunucunun fiyatını taşır',
                        text: 'Sipariş açıkken bir sipariş satırındaki fiyat telefonun gönderdiğinden değil, sunucudan gelir.',
                        source: 'app/Application/Ordering/UseCase/BuildOrderLines.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'Neye ihtiyacınız var', [
                    new BlockEntry(
                        term: 'Her görünür üründe sıfırdan büyük bir fiyat',
                        text: 'Yayınlamak, fiyatı olmayan görünür bir ürünü reddeder.',
                        source: 'app/Application/Publication/UseCase/BuildPublicationSnapshot.php',
                    ),
                    new BlockEntry(
                        term: 'İşletmede tek para birimi',
                        text: 'Para birimi markaya gerçek para birimleri listesinden ayarlanır ve işletmenin her menüsü onunla fiyatlanır.',
                        source: 'app/Http/Requests/Tenancy/UpdateBrandRequest.php',
                    ),
                    new BlockEntry(
                        term: 'Menüyü yönetme yetkisi',
                        text: 'Fiyatları sahipler, müdürler ve editörler değiştirir. Mutfak rolü denese bile fiyat ucunda reddedilir.',
                        source: 'tests/Feature/MenuCatalog/KitchenRoleMenuBoundaryTest.php',
                    ),
                    new BlockEntry(
                        term: 'Plan gerekmez',
                        text: 'Fiyat girmek, değiştirmek, zamanlamak ve geri getirmek ücretsiz plandadır.',
                        source: 'database/seeders/PlanCatalogueSeeder.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'Neyi yapmaz', [
                    new BlockEntry(
                        term: 'Ürün başına tek fiyat',
                        text: 'Boyut fiyatı, porsiyon fiyatı ve seçenek farkı yoktur.',
                        source: 'database/migrations/2026_08_20_000002_create_menu_catalog_tables.php',
                    ),
                    new BlockEntry(
                        term: 'İndirim ve happy hour yok',
                        text: 'İndirim alanı, saate bağlı fiyat ve kampanya fiyatı yoktur. Daha uygun bir öğleden sonra listesi, saatine göre devreden ikinci bir menüdür.',
                        source: 'app/Http/Controllers/MenuCatalog/UpdateMenuServiceWindowController.php',
                    ),
                    new BlockEntry(
                        term: 'KDV satırı yok',
                        text: 'Fiyat, fiyattır. Ayrı bir vergi tutarı yoktur ve misafir sayfası vergi hakkında hiçbir şey söylemez.',
                        source: 'app/Support/Money/PriceLabel.php',
                    ),
                    new BlockEntry(
                        term: 'Döviz çevrimi yok',
                        text: 'Fiyatlar yalnız sizin para biriminizde gösterilir. Misafir onları başka bir birime çeviremez.',
                        source: 'app/Domain/Money/MoneyFormatter.php',
                    ),
                    new BlockEntry(
                        term: 'Panelde toplu fiyat değişikliği yok',
                        text: 'Panelde fiyatlar tek tek değiştirilir. Birçoğunu birden değiştirmek hesap tablosuyla yapılır.',
                        source: 'routes/api/menu-catalog.php',
                    ),
                    new BlockEntry(
                        term: 'Misafire fiyat geçmişi yok',
                        text: 'Misafir yalnız yayındaki güncel fiyatı görür. Önceki fiyatlar sürümlerde ve denetim izinde size görünür, ona değil.',
                        source: 'app/Http/Controllers/Publication/ListPublicationsController.php',
                    ),
                    new BlockEntry(
                        term: 'Ödeme yok',
                        text: 'Fiyat okunur, tahsil edilmez. Menü üzerinden kimse ödeme yapmaz.',
                        source: 'routes/web.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'İşletmecilerin fiyatlar hakkında sorduğu sorular', [
                    new BlockEntry(
                        term: 'Yeni bir fiyat masalara ne zaman ulaşır?',
                        text: 'Siz yayınladığınızda. O ana kadar yalnız taslaktadır.',
                    ),
                    new BlockEntry(
                        term: 'Ben yayınlarken menüyü okuyan misafire ne olur?',
                        text: 'Açtığı sürümü okumaya devam eder. Yeni fiyat bir sonraki okutmada okunur.',
                    ),
                    new BlockEntry(
                        term: 'Fiyat lirada neden iki ondalık gösteriyor da yende hiç göstermiyor?',
                        text: 'Çünkü karar para biriminindir. Ondalık sayısı bir ayardan değil, para biriminden gelir.',
                    ),
                    new BlockEntry(
                        term: 'Happy hour fiyatı ekleyebilir miyim?',
                        text: 'Hayır. Saate bağlı fiyat yoktur. Daha uygun bir öğleden sonra listesi, saatine göre devralan ikinci bir menüdür.',
                    ),
                    new BlockEntry(
                        term: 'Hesap tablosundan içe aktarma fiyatları hemen değiştirir mi?',
                        text: 'Hayır. Taslağa yazar. Misafirler yeni fiyatları siz yayınladıktan sonra görür.',
                    ),
                    new BlockEntry(
                        term: 'Ondalık sayısı yanlış bir fiyat yazarsam ne olur?',
                        text: 'Bir mesajla reddedilir. Ondalıklar para biriminize uymak zorundadır.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'Fiyat değiştirmek ücretsizdir', [
                    new BlockEntry(
                        text: 'Fiyat girmek, zamanlamak ve geri getirmek ücretsiz plandadır.',
                        href: '/pricing',
                        term: 'Planlar sayfası',
                    ),
                ]),

                new ContentBlock(BlockType::Related, 'İlgili sayfalar', [
                    new BlockEntry(text: 'Menü yönetimi', pageKey: 'urun.menu-yonetimi'),
                    new BlockEntry(text: 'Dil ve para birimi', pageKey: 'urun.coklu-dil-ve-para-birimi'),
                    new BlockEntry(text: 'Çoklu şube', pageKey: 'urun.coklu-sube'),
                ]),
            ],
        );
    }
}
