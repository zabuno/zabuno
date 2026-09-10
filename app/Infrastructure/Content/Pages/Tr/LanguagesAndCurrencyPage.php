<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages\Tr;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;

/**
 * `/tr/urun/coklu-dil-ve-para-birimi/` — dil ve para biriminin Türkçesi (P0).
 *
 * Bu sayfanın en kolay yalanı iki dilde de aynıdır: *"menünüz her dilde"*.
 * Ürün yemek adlarını ÇEVİRMİYOR ve Türkçe sayfa bunu İngilizcesiyle aynı
 * netlikte söyler. Makine çevirisi, dile göre fiyat, döviz çevrimi, sağdan
 * sola misafir menüsü ve panelde dil değiştirici burada da yazılmaz.
 */
final class LanguagesAndCurrencyPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'urun.coklu-dil-ve-para-birimi',
            locale: 'tr',
            metadata: new PageMetadata(
                seoTitle: 'Misafir menüsünde dil ve para birimi',
                metaDescription: 'Yabancı misafir menü sayfasını kendi dilinde okur, ürün adları sizin dilinizde kalır ve fiyatlar gerçekten tahsil ettiğiniz para biriminde doğru görünür.',
                h1: 'Çoklu dil ve para birimi',
                breadcrumbTitle: 'Dil ve para birimi',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'Misafir menüsü Türkçe ya da İngilizce konuşur; seçimi misafir yapar ve seçim hatırlanır. Ürün adları sizin dilinizde kalır ve sayfa bunu saklamak yerine söyler. Fiyatlar sizin para biriminizde, o para biriminin kendi ondalığıyla gösterilir.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'Turist ile fiyat iki ayrı sorundur', [
                    new BlockEntry(
                        text: 'Yurt dışından gelen bir misafir bir yemeğin ne olduğunu genellikle çıkarabilir. Çıkaramayacağı şey, alerjenleri açan düğme, arama kutusunun adı ya da mutfağın sipariş alıp almadığıdır.',
                    ),
                    new BlockEntry(
                        text: 'Fiyat daha keskin bir sorundur. Her yerde yüze bölen bir menü Japonya\'da da Kuveyt\'te de yanlıştır; ve masadaki yanlış fiyat bir biçimlendirme hatası değil, vermediğiniz bir sözdür.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'Sayfayı çevirin, mutfağı değil', [
                    new BlockEntry(
                        text: 'Misafir sayfasının Zabuno\'nun yazdığı parçaları çevrilidir ve değiştirilebilir. Sizin yazdığınız parçalar — ürün adları, açıklamalar, kendi mutfağınıza dair notlar — tam olarak yazdığınız gibi kalır.',
                    ),
                    new BlockEntry(
                        text: 'Bu çizgi yüksek sesle çekilir. Misafir sizinkinden başka bir dilde okurken sayfa ona ürün adlarının restoranın dilinde olduğunu söyler; hiç yapılmamış bir çeviriyi varsaymasına izin vermez.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'Dil ve para nasıl seçilir', [
                    new BlockEntry(
                        term: 'Misafir bir bağlantıyla seçer',
                        text: 'Misafir menüsündeki dil değiştirici sıradan bir bağlantıdır; böylece hiçbir betik çalışmadan önce ve zar zor bağlanan bir telefonda da çalışır.',
                        source: 'app/Support/Localization/GuestLocale.php',
                    ),
                    new BlockEntry(
                        term: 'Tarayıcı onun yerine seçmez',
                        text: 'Misafirin tarayıcı dili bilerek yok sayılır. Aynı kod, biri aksini seçene kadar masadaki herkese aynı sayfayı gösterir.',
                        source: 'app/Support/Localization/GuestLocale.php',
                    ),
                    new BlockEntry(
                        term: 'Seçim hatırlanır',
                        text: 'Bir kez dil değiştiren misafire bir sonraki ziyarette yeniden sorulmaz; seçim tarayıcısında bir yıl tutulur.',
                        source: 'app/Http/Controllers/QrDestination/ShowPublicMenuController.php',
                    ),
                    new BlockEntry(
                        term: 'Kendi diliniz işletmenin bir ayarıdır',
                        text: 'İşletme, menüsünün yazıldığı dili taşır ve bu dil yazılarak değil listeden seçilir. Sayfanın geri düştüğü ve ekran okuyuculara söylenen dil odur.',
                        source: 'app/Domain/Tenancy/ValueObject/LocaleCode.php',
                    ),
                    new BlockEntry(
                        term: 'Para tam sayı olarak saklanır, ondalık olarak değil',
                        text: 'Fiyat, bir tam sayı ve bir para birimi kodudur. Hiçbir şey kayan noktalı sayı olarak saklanmaz, böylece zaman içinde yüzde birlik kaymalar olmaz.',
                        source: 'database/migrations/2026_08_20_000002_create_menu_catalog_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Ondalığa para biriminin kendisi karar verir',
                        text: 'Ondalık basamak sayısı sabit bir yüze bölmeden değil, para biriminin kendisinden gelir. Yende hiç yoktur, dinarda üç tanedir ve ikisi de doğru çıkar.',
                        source: 'app/Domain/Money/MoneyFormatter.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'Bugün neler yapar', [
                    new BlockEntry(
                        term: 'Türkçe ya da İngilizce bir misafir menüsü',
                        text: 'Zabuno\'nun misafir sayfasına yazdığı her şey — düğmeler, etiketler, alerjen ifadeleri, arama kutusu — ikisine de çevrilidir ve her biri başlığı değiştirilmiş İngilizce değil, gerçek bir çeviridir.',
                        source: 'resources/js/i18n/guest.ts',
                    ),
                    new BlockEntry(
                        term: 'Ürün adları kendi dilini korur ve bunu söyler',
                        text: 'Menü içeriği yazıldığı dille işaretlenir; böylece ekran okuyucu, İngilizce bir sayfanın içinde bile Türkçe bir ürün adını Türkçe telaffuz eder.',
                        source: 'resources/views/public-menu.blade.php',
                    ),
                    new BlockEntry(
                        term: 'İşletme başına tek para birimi, gerçek listeden',
                        text: 'Para birimi, işletmeye tam standart listeden, simgesi ve ondalığıyla birlikte, kayıt sırasında ve sonrasında ayarlanır.',
                        source: 'app/Infrastructure/Reference/IcuMarketReference.php',
                    ),
                    new BlockEntry(
                        term: 'Yanlış para birimindeki fiyat çevrilmez, reddedilir',
                        text: 'İşletmenin para biriminden başka bir birimde fiyatlanan ürün kaydedilmeden önce reddedilir. Sessizce çevirmek, kimsenin kabul etmediği bir sayı uydurmak olurdu.',
                        source: 'app/Domain/Money/Money.php',
                    ),
                    new BlockEntry(
                        term: 'Yanlış fiyat yerine fiyatsız',
                        text: 'Misafir sayfasında bir para birimi çözülemezse ürün fiyatsız gösterilir. Yanlış bir fiyat, eksik olandan kötüdür.',
                        source: 'app/Support/Money/PriceLabel.php',
                    ),
                    new BlockEntry(
                        term: 'Paranın nasıl yazılacağı tarayıcıya söylenir',
                        text: 'Sunucu kendi biçimlendirmesini — simge, ayırıcılar, basamaklar — ölçer ve sayfaya verir; böylece fiyat sunucu ile telefon arasında biçim değiştirmez.',
                        source: 'app/Support/Money/MoneyFormatContract.php',
                    ),
                    new BlockEntry(
                        term: 'Tek sepet iki para birimini karıştıramaz',
                        text: 'Bir sipariş, ikinci bir para birimindeki satırları anlamsız bir toplama eklemek yerine reddeder.',
                        source: 'app/Application/Ordering/UseCase/BuildOrderLines.php',
                    ),
                    new BlockEntry(
                        term: 'Dilini taşıyan adresler',
                        text: 'Bu web sitesinde dil, tarayıcınız hakkında bir tahmin değil adresin parçasıdır; ve sayfalar karşılıklarını arama motorlarına yalnız gerçek bir karşılık yayınlandığında bildirir.',
                        source: 'app/Application/Content/UseCase/ResolveLocaleAlternates.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'Neye ihtiyacınız var', [
                    new BlockEntry(
                        term: 'İşletmede bir dil ve bir para birimi',
                        text: 'İkisi de işletme oluşturulurken sorulur, çünkü hangi dilde olduğu ve neyi tahsil ettiği bilinmeden bir menü yayınlanamaz.',
                        source: 'resources/js/components/workspace/BrandOnboardingForm.tsx',
                    ),
                    new BlockEntry(
                        term: 'O para biriminde yazılmış fiyatlar',
                        text: 'Her ürün işletmenin para biriminde ve o para biriminin taşıdığından fazla ondalık basamak olmadan fiyatlanır.',
                        source: 'app/Http/Controllers/MenuCatalog/StoreMenuItemController.php',
                    ),
                    new BlockEntry(
                        term: 'Başka bir şey değil ve plan da değil',
                        text: 'Dil ve para birimi satılmaz. Burada satın alınacak bir hak yoktur; davranış ücretsiz planda da aynıdır.',
                        source: 'app/Domain/Entitlement/Entitlement.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'Neyi yapmaz', [
                    new BlockEntry(
                        term: 'Ürün adları çevrilmez',
                        text: 'Bir ürünün tek adı ve tek açıklaması vardır, sizin dilinizde. Menü içeriği için ikinci bir dil yoktur ve üretilmez.',
                        source: 'database/migrations/2026_08_20_000002_create_menu_catalog_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Hiçbir şey makineyle çevrilmez',
                        text: 'Çeviri üretimi bir ayarın arkasında değil, kodda kapalıdır. Bu üründeki hiçbir metin makine çevirmeniyle üretilmedi.',
                        source: 'app/Domain/Localization/TranslationGenerationLock.php',
                    ),
                    new BlockEntry(
                        term: 'Misafir sayfasında dokuz değil iki dil',
                        text: 'Misafir sayfayı Türkçe ya da İngilizce okuyabilir. Öteki diller adres ve altyapı olarak vardır; değiştirici onları sunmaz.',
                        source: 'app/Support/Localization/GuestLocale.php',
                    ),
                    new BlockEntry(
                        term: 'Panel yalnız İngilizcedir',
                        text: 'Çalıştığınız ekranlar tek dilde gelir. Öteki kataloglar yazılır ve saklanır; bir dil ancak içindeki her metin tamamlandığında girer.',
                        source: 'config/i18n.php',
                    ),
                    new BlockEntry(
                        term: 'Döviz çevrimi yok',
                        text: 'Üründe hiçbir yerde kur yoktur ve "bunu euro olarak göster" diye bir şey yoktur. Misafir, sizin tahsil ettiğiniz para birimini görür.',
                        source: 'app/Domain/Money/Money.php',
                    ),
                    new BlockEntry(
                        term: 'Menü başına değil işletme başına tek para birimi',
                        text: 'Bir şube tek bir menüyü ikinci bir para biriminde fiyatlayamaz. Para birimi işletmeye aittir.',
                        source: 'database/migrations/2026_08_19_000001_create_brands_and_locations_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Dile göre değişen fiyat yok',
                        text: 'Fiyatın dil boyutu yoktur. Aynı ürün, misafir sayfayı hangi dilde okursa okusun aynı tutar.',
                        source: 'database/migrations/2026_08_20_000002_create_menu_catalog_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Sağdan sola misafir menüsü yok',
                        text: 'Yazı yönü altyapıda ele alınır ve test edilir ama bugün misafire sağdan sola yazılan bir dil sunulmaz.',
                        source: 'app/Support/Localization/DocumentLocale.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'İşletmecilerin dil ve para hakkında sorduğu sorular', [
                    new BlockEntry(
                        term: 'Menüm turistler için çevrilecek mi?',
                        text: 'Menünüzün etrafındaki sayfa çevrilecek, Türkçe ya da İngilizce olarak. Ürün adları çevrilmeyecek ve sayfa bunu misafire, varsaymasına izin vermeden söyleyecek.',
                    ),
                    new BlockEntry(
                        term: 'Bir ürünün İngilizcesini kendim yazabilir miyim?',
                        text: 'Bugün ayrı bir dil olarak değil. Bir ürün tek ad ve tek açıklama taşır; bazı işletmeciler iki dili o açıklamanın içine koyuyor, bu dürüsttür ama çevrilmiş bir menü değildir.',
                    ),
                    new BlockEntry(
                        term: 'Misafir fiyatları euro olarak görebilir mi?',
                        text: 'Hayır. Üründe çevrim yoktur ve masada bir kur uydurmak, bir fiyat uydurmak olurdu.',
                    ),
                    new BlockEntry(
                        term: 'Menü misafirimin telefon dilini takip eder mi?',
                        text: 'Hayır, bilerek. Aynı kodu okutan herkes, bir misafir aksini seçene kadar aynı sayfayı görür; o seçim ise sonrasında hatırlanır.',
                    ),
                    new BlockEntry(
                        term: 'Para birimimin ondalığı yok, fiyatlar yüz kat yanlış mı olacak?',
                        text: 'Hayır. Ondalık, para biriminin kendisinden gelir; yen yen olarak görünür ve dinar üç basamağını korur.',
                    ),
                    new BlockEntry(
                        term: 'Bunların herhangi biri ücretli planda mı?',
                        text: 'Hayır. Dil ve para birimi, ücretsiz olan dahil her planda aynı davranır.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'Dil ve para birimi ek bir maliyet getirmez', [
                    new BlockEntry(
                        text: 'Planların neyi değiştirdiğini ve plansız neyin açık kaldığını görün.',
                        href: '/pricing',
                        term: 'Planlara bir bakın',
                    ),
                ]),

                new ContentBlock(BlockType::Related, 'İlgili sayfalar', [
                    new BlockEntry(text: 'QR menü', pageKey: 'urun.qr-menu'),
                    new BlockEntry(text: 'Menü yönetimi', pageKey: 'urun.menu-yonetimi'),
                ]),
            ],
        );
    }
}
