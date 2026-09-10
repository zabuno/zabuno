<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages\Tr;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;

/**
 * `/tr/urun/masa-ve-qr-yonetimi/` — masa ve karekod yönetiminin Türkçesi (P0).
 *
 * İngilizce aslında bilerek yazılmayanlar burada da yazılmaz: ürün bazlı
 * karekod kartları, menü dışı karekod hedefleri (serbest adres, Wi-Fi,
 * kampanya), kalibrasyon cetveli ve "test taraması kaydedildi" durumu.
 */
final class TablesAndQrPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'urun.masa-ve-qr-yonetimi',
            locale: 'tr',
            metadata: new PageMetadata(
                seoTitle: 'Masa ve karekod yönetimi',
                metaDescription: 'Her masaya bir kod üretin; kart, afiş ya da kesilecek tabaka olarak bastırın ve sonradan yeniden bastırmadan başka bir menüye yönlendirin.',
                h1: 'Masalar ve karekodlar',
                breadcrumbTitle: 'Masalar ve karekodlar',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'Burası bir masanın adrese dönüştüğü yerdir. Her masa için bir kod üretir; kart, afiş ya da kesip dağıtacağınız bir tabaka olarak dışa aktarır ve kodun neyi açtığını sonradan hiçbir şeyi yeniden bastırmadan değiştirirsiniz.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'Basılı bir kod, geri alamayacağınız bir sözdür', [
                    new BlockEntry(
                        text: 'Kâğıdı bir kez basmak ucuz, iki kez basmak pahalıdır. Çalışmayan bir kod ya da tasarımcının ekranında okunup dokuz numaralı masanın üstündeki lambanın altında okunmayan bir kod, koca bir yeniden baskıya ve bir akşamlık özre mal olur.',
                    ),
                    new BlockEntry(
                        text: 'Üstelik arıza sessizdir: okunmayan bir kartı kimse bildirmez. Misafir vazgeçip garsona sorar ve işletmeci bunu hiç duymaz.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'Kod matbaaya gitmeden önce denetlenir', [
                    new BlockEntry(
                        text: 'Her kod sunucuda çizilir ve size verilmeden önce bir çözücüyle geri okunur. Okunamayan bir kod dosyaya dönüşmez.',
                    ),
                    new BlockEntry(
                        text: 'Kontrast da aynı yoldan denetlenir. Okutulamayacak kadar soluk bir marka rengi reddedilir, yerine klasik siyah kod kullanılır ve bunun olduğu size söylenir; misafirden öğrenmezsiniz.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'Nasıl çalışır', [
                    new BlockEntry(
                        term: 'Salonu tarif edin',
                        text: 'Kaç alanınız ve kaç masanız olduğunu, numaralandırma önekini ve koltuk sayılarını yazın; masalar ve kodları birlikte oluşturulur.',
                        source: 'app/Http/Controllers/QrDestination/StoreBulkQrCodesController.php',
                    ),
                    new BlockEntry(
                        term: 'Ya da tek bir kod ekleyin',
                        text: 'Bir kod, önce salon tarif edilmeden tek başına da oluşturulabilir.',
                        source: 'app/Http/Controllers/QrDestination/StoreQrCodeController.php',
                    ),
                    new BlockEntry(
                        term: 'Görünümünü seçin',
                        text: 'Bir kod biçemi ve bir kart biçemi, bir boyut ve bir yön seçin; kendi kısa metninizi de ekleyin.',
                        source: 'app/Domain/QrDestination/CardSize.php',
                    ),
                    new BlockEntry(
                        term: 'Sunucu okunduğunu kanıtlar',
                        text: 'Dışa aktarmadan önce üretilen kod sunucuda yeniden çözülür. Önizleme ve baskı aynı çizimden gelir, yani onayladığınız şey basılan şeydir.',
                        source: 'app/Infrastructure/QrDestination/Rendering/EndroidQrCodeImageExportAdapter.php',
                    ),
                    new BlockEntry(
                        term: 'Bastırın',
                        text: 'Tek bir kartı, bir afişi, matbaa için tek arşiv hâlinde bütün bir salonu ya da kesilecek on iki kodluk bir A4 tabakasını dışa aktarın.',
                        source: 'app/Http/Controllers/QrDestination/ExportQrCardsZipController.php',
                    ),
                    new BlockEntry(
                        term: 'Sonradan değiştirin',
                        text: 'Var olan bir kodu başka bir yayınlanmış menüye yönlendirin. Jeton, dolayısıyla masadaki kâğıt değişmez.',
                        source: 'app/Http/Controllers/QrDestination/RetargetQrCodeController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'Neler yapabilirsiniz', [
                    new BlockEntry(
                        term: 'Masa başına bir kod',
                        text: 'Masalar bir ad, koltuk sayısı ve alan taşır; her biri kendi kodunu alır, böylece bir okutma yalnız restorana değil bir yere bağlanır.',
                        source: 'database/migrations/2026_08_22_000006_create_dining_areas_and_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Tahmin edilemeyen kodlar',
                        text: 'Her kod 43 karakterlik bir jeton taşır; böylece kimse bir başkasının adresinin yanındakini yazarak menünüzü bulmaz.',
                        source: 'app/Domain/QrDestination/QrToken.php',
                    ),
                    new BlockEntry(
                        term: 'Altı kod biçemi, altı kart biçemi',
                        text: 'Klasik, sade, kalın, yuvarlatılmış, markalı ve yüksek kontrastlı kodlar; logonuzu ve ana renginizi taşıyan kartların üstünde.',
                        source: 'app/Domain/QrDestination/QrTheme.php',
                    ),
                    new BlockEntry(
                        term: 'Gerçek kâğıt boyutları',
                        text: 'ISO boyutlarında ve yaygın ekran oranlarında kartlar, dikey ya da yatay; ayrıca A7 boyutuna kadar afişler.',
                        source: 'app/Http/Controllers/QrDestination/ExportQrCodePdfController.php',
                    ),
                    new BlockEntry(
                        term: 'Matbaa için vektörel dosyalar',
                        text: 'Kartlar SVG ve PDF olarak dışa aktarılır; böylece matbaa onları büyütürken kod dağılmaz. Raster yalnız sade kod için sunulur, kartlar için değil.',
                        source: 'app/Http/Controllers/QrDestination/ExportQrCardController.php',
                    ),
                    new BlockEntry(
                        term: 'Bütün salon için tek arşiv',
                        text: 'Bütün kartları tek seferde, alana göre süzerek ve her dosyayı masasının adıyla adlandırarak dışa aktarın; matbaa tahmin etmek zorunda kalmaz.',
                        source: 'app/Http/Controllers/QrDestination/ExportQrCardsZipController.php',
                    ),
                    new BlockEntry(
                        term: 'Kesilecek bir tabaka',
                        text: 'Bugün kapatmak istediğiniz masalar için matbaaya gitmeden, tek A4 sayfada on iki kod.',
                        source: 'app/Domain/QrDestination/QrPrintSheet.php',
                    ),
                    new BlockEntry(
                        term: 'Kontrast varsayılmaz, ölçülür',
                        text: 'Güvenilir biçimde okunmayacak bir marka rengi reddedilir ve yerine klasik kod kullanılır; bu geri düşüş gizlenmez, bildirilir.',
                        source: 'app/Domain/QrDestination/QrContrast.php',
                    ),
                    new BlockEntry(
                        term: 'Bir kodu kapatın',
                        text: 'Bir kod devre dışı bırakılıp yeniden açılabilir; böylece kaybolan ya da çalınan bir kart, diğer masalar etkilenmeden çalışmaz olur.',
                        source: 'app/Http/Controllers/QrDestination/DisableQrCodeController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'Neye ihtiyacınız var', [
                    new BlockEntry(
                        term: 'Yayınlanmış bir menü',
                        text: 'Kod, yayınlanmış bir menüyü gösterir. Menü yoksa kodun açacağı bir şey de yoktur.',
                        source: 'app/Http/Controllers/QrDestination/RetargetQrCodeController.php',
                    ),
                    new BlockEntry(
                        term: 'Tek tek kod için',
                        text: 'Kodları tek tek oluşturmak ve dışa aktarmak ücretsiz plandadır.',
                        source: 'database/seeders/PlanCatalogueSeeder.php',
                    ),
                    new BlockEntry(
                        term: 'Bütün salonu tek seferde üretmek için',
                        text: 'Bütün bir salonu tarif edip kodlarını birlikte üretmek ücretli bir plana aittir.',
                        source: 'app/Http/Controllers/QrDestination/StoreBulkQrCodesController.php',
                    ),
                    new BlockEntry(
                        term: 'Kartta kendi renkleriniz için',
                        text: 'Marka renginizin kartta ve kodda kullanılması ücretli bir plana aittir; onsuz nötr varsayılan kullanılır.',
                        source: 'app/Domain/Entitlement/Entitlement.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'Neyi yapmaz', [
                    new BlockEntry(
                        term: 'Kod bir menü açar, başka bir şey değil',
                        text: 'Bir kod herhangi bir web adresine, Wi-Fi parolasına ya da kampanya sayfasına yönlendirilemez. Tek hedefi yayınlanmış bir menüdür.',
                        source: 'app/Infrastructure/QrDestination/Persistence/EloquentQrCodeRepository.php',
                    ),
                    new BlockEntry(
                        term: 'Ürün bazlı kod yok',
                        text: 'Tek bir ürünü açan bir kod yoktur. Ürünün kendi web adresi vardır ama kendi basılabilir kodu yoktur.',
                        source: 'routes/api/qr-destination.php',
                    ),
                    new BlockEntry(
                        term: 'Masalar salon sihirbazıyla oluşturulur',
                        text: 'Masalar ve alanlar, siz salonu tarif ettiğinizde birlikte oluşturulur. Sonradan tek bir masa eklemek, adını değiştirmek ya da silmek için bir ekran yoktur; bir alanın adı değiştirilebilir.',
                        source: 'app/Http/Controllers/QrDestination/RenameDiningAreaController.php',
                    ),
                    new BlockEntry(
                        term: 'Baskı kalibrasyon aracı yok',
                        text: 'Basılan boyutu ölçecek bir cetvel ve bir kartın test okutulduğunu kaydedecek bir yer yoktur. Gerisini bastırmadan önce ilk kartı kendiniz okutun.',
                        source: 'app/Http/Controllers/QrDestination/ExportQrCardController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'İşletmecilerin karekod hakkında sorduğu sorular', [
                    new BlockEntry(
                        term: 'Menü değişince yeniden bastırmam gerekir mi?',
                        text: 'Hayır. Kod, değişmeyen bir adresi gösterir. Onu başka bir menüye bile yönlendirebilirsiniz; aynı kart çalışmaya devam eder.',
                    ),
                    new BlockEntry(
                        term: 'Marka rengim kodu okunması zor hâle getirirse ne olur?',
                        text: 'Reddedilir. Kontrast ölçülür, yerine klasik siyah kod kullanılır ve bu geri düşüşün olduğu size söylenir.',
                    ),
                    new BlockEntry(
                        term: 'Matbaa boyutu değiştirince kod hâlâ okunur mu?',
                        text: 'Kartlar vektörel dosya olarak dışa aktarılır, yani ölçek büyütmek kodu yumuşatmaz. Ayrıca üretilen her kod, dosya size verilmeden sunucuda yeniden çözülür.',
                    ),
                    new BlockEntry(
                        term: 'Bütün masaların kartını tek indirmede alabilir miyim?',
                        text: 'Evet. Tek arşiv, masa başına bir dosya taşır, dosyalar masanın adını alır ve tek bir alana göre süzülebilir.',
                    ),
                    new BlockEntry(
                        term: 'Biri kartı alıp götürdü, şimdi ne olacak?',
                        text: 'O kodu devre dışı bırakın. Diğer masalar etkilenmez ve kart ortaya çıkarsa kod yeniden açılabilir.',
                    ),
                    new BlockEntry(
                        term: 'Birkaç kodu kendim bastırabilir miyim?',
                        text: 'Evet. Bir A4 tabakası, hiçbir şey sipariş etmeden kesilecek on iki kod taşır.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'Salonunuzu hangi planın kapsadığına bakın', [
                    new BlockEntry(
                        text: 'Tek tek kodlar ücretsizdir; bütün bir salonu tek seferde üretmek değildir.',
                        href: '/pricing',
                        term: 'Planlara göz atın',
                    ),
                ]),

                new ContentBlock(BlockType::Related, 'İlgili sayfalar', [
                    new BlockEntry(text: 'QR menü', pageKey: 'urun.qr-menu'),
                    new BlockEntry(text: 'Analitik', pageKey: 'urun.analitik'),
                ]),
            ],
        );
    }
}
