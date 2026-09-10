<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages\Tr;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;

/**
 * `/tr/urun/analitik/` — analitik ve raporlama sayfasının Türkçesi (P0).
 *
 * İngilizce aslında bilerek yazılmayanlar burada da yok: ciro/satış raporu,
 * rapor dışa aktarımı, şube karşılaştırma ekranı, misafir demografisi ve
 * "önerilen aksiyonlar" merkezi. Ölçülen beş olay dışında bir şey ölçüldüğü
 * Türkçe sayfada da iddia edilmez.
 */
final class AnalyticsPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'urun.analitik',
            locale: 'tr',
            metadata: new PageMetadata(
                seoTitle: 'Menü analitiği ve raporlama',
                metaDescription: 'Kaç kişi kodu okuttu, kaçı menüyü açtı, hangi ürünlere baktılar ve arayıp bulamadıkları ne oldu; hepsini tek yerde görün.',
                h1: 'Analitik',
                breadcrumbTitle: 'Analitik',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'Zabuno, dijital bir menünün dürüstçe bilebileceği beş şeyi sayar: okutulan kodlar, açılan menüler, bakılan ürünler, hiçbir sonuç vermeyen aramalar ve masadan gönderilen siparişler. Raporlar bu sayımlardan kurulur, başka hiçbir şeyden değil.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'Basılı menü size hiçbir şey söylemez', [
                    new BlockEntry(
                        text: 'Mutfaktan çıkan tabakları sayabilirsiniz. Tatlı sayfasını okuyup hiçbir şey sipariş etmeyen misafirleri ya da sunmadığınız bir vejetaryen yemeği arayıp telefonu bırakanları sayamazsınız.',
                    ),
                    new BlockEntry(
                        text: 'Hiç görmediğiniz talep, hiç karşılamadığınız taleptir; ve menü, üzerine kurulacak başka bir şey olmadığı için bir hisle yeniden tasarlanır.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'Menü nerede sunuluyorsa orada sayılır', [
                    new BlockEntry(
                        text: 'Sayım, menü sunulurken Zabuno tarafında yapılır. Kırk kişinin menüyü açtığını öğrenmeniz için misafirleriniz bir reklam ağına teslim edilmez.',
                    ),
                    new BlockEntry(
                        text: 'Raporların "henüz değil" deme hakkı da vardır. Ürün raporu ve eğilim görünümü, belirli bir tekil ziyaretçi sayısının altında rakamlarını göstermez; üç kişiyi bir örüntü gibi giydirmektense susar, çünkü bir kez yanlış çıkan rapor bir daha okunmaz.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'Nasıl çalışır', [
                    new BlockEntry(
                        term: 'Beş olay, bir kez adlandırılmış',
                        text: 'Okutma, menü açıldı, ürüne bakıldı, sonuçsuz arama, sipariş gönderildi. Liste kodda sabittir; böylece bir yazım hatası bir raporu sessizce ikiye bölemez.',
                        source: 'app/Domain/Analytics/AnalyticsEventType.php',
                    ),
                    new BlockEntry(
                        term: 'Sunucu kaydeder',
                        text: 'Misafir olayları kendi alan adınızdaki hız sınırlı bir uca gönderilir ve çalışma alanınızın satırlarına yazılır.',
                        source: 'app/Http/Controllers/Analytics/StoreGuestMenuEventsController.php',
                    ),
                    new BlockEntry(
                        term: 'Ziyaretçiler özetlenir, kimliklendirilmez',
                        text: 'Bir ziyaretçi, kişiye geri çevrilemeyen türetilmiş bir anahtarla temsil edilir. Tek masadan altı açılış altı misafir değildir.',
                        source: 'database/migrations/2026_08_28_000200_add_visitor_key_to_analytics_events.php',
                    ),
                    new BlockEntry(
                        term: 'Özet olarak ya da biçim olarak okunur',
                        text: 'Bir istek "bu dönemde ne kadar" sorusunu, bir diğeri "dönemin biçimi neydi" sorusunu yanıtlar: güne göre, saate göre ve bir önceki döneme karşı.',
                        source: 'app/Application/Analytics/Dto/AnalyticsTimeSeries.php',
                    ),
                    new BlockEntry(
                        term: 'Menü kararına dönüşür',
                        text: 'Menü raporu ürünleri kaç farklı kişinin açtığına göre sıralar ve hiç kimsenin açmadıklarını listeler.',
                        source: 'app/Http/Controllers/Analytics/ShowMenuEngineeringController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'Raporlar hangi soruları yanıtlar', [
                    new BlockEntry(
                        term: 'Kaç okutma, kaç açılış',
                        text: 'İki sayım ve aralarındaki oran; böylece okutulup hiç açılmayan bir kod, kodda ya da bağlantıda bir sorun olduğunu söyler.',
                        source: 'app/Application/Analytics/Dto/AnalyticsSummary.php',
                    ),
                    new BlockEntry(
                        term: 'Yaklaşık kaç kişi',
                        text: 'Tekil ziyaretçilerin yaklaşık sayısı; bu, menünün kaç kez açıldığından farklı bir sorudur.',
                        source: 'app/Application/Analytics/Dto/AnalyticsSummary.php',
                    ),
                    new BlockEntry(
                        term: 'Hangi şube, hangi kod',
                        text: 'Aynı dönem şubeye ve tek tek karekoda göre ayrıştırılır; böylece salonun sessiz köşesi sessiz köşe olarak görünür.',
                        source: 'app/Application/Analytics/Dto/AnalyticsBreakdownRow.php',
                    ),
                    new BlockEntry(
                        term: 'Gün gün ve geçen sefere karşı',
                        text: 'Aralık boyunca günlük kovalar ve aynı uzunluktaki önceki dönemle karşılaştırma.',
                        source: 'app/Application/Analytics/Dto/AnalyticsComparison.php',
                    ),
                    new BlockEntry(
                        term: 'Hangi saatler yoğun',
                        text: 'Menülerin hangi saatte açıldığını saat saat gösteren bir görünüm. Anlamlı olmayacak kadar az ziyaretçili hücreler sıfır gösterilmez; saklanır ve sayılır.',
                        source: 'app/Application/Analytics/Dto/AnalyticsHourCell.php',
                    ),
                    new BlockEntry(
                        term: 'İnsanların gerçekten baktığı ürünler',
                        text: 'Tekil izleyiciye göre sıralanmış ürünler ve hiç kimsenin açmadığı ürünler; böylece menü sezgiyle değil kanıtla kısaltılabilir.',
                        source: 'app/Http/Controllers/Analytics/ShowMenuEngineeringController.php',
                    ),
                    new BlockEntry(
                        term: 'Misafirin arayıp bulamadığı şeyler',
                        text: 'Hiçbir sonuç döndürmeyen arama terimleri, kaç farklı kişinin aradığına göre sayılır. Bir menünün size başka hiçbir yoldan gösteremeyeceği tek talep budur.',
                        source: 'app/Application/Analytics/Port/AnalyticsRepositoryPort.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'Neye ihtiyacınız var', [
                    new BlockEntry(
                        term: 'Raporlamayı kapsayan bir plan',
                        text: 'Analitik ücretli bir plana aittir. Onsuz menü, kodlar ve misafir sayfası çalışmaya devam eder; yalnız raporlar kapalıdır.',
                        source: 'app/Domain/Entitlement/Entitlement.php',
                    ),
                    new BlockEntry(
                        term: 'Çalışma alanı içinde yetki',
                        text: 'Ekip üyesinin analitik yetkisi olmalıdır. Yetki yoksa rapor yalnızca boş değildir, hiç yoktur.',
                        source: 'app/Http/Controllers/Analytics/ShowAnalyticsSummaryController.php',
                    ),
                    new BlockEntry(
                        term: 'Menü raporu için yeterli ziyaretçi',
                        text: 'Ürün raporu, rakam göstermeden önce aralıkta en az beş tekil ziyaretçi ister ve sıfır göstermek yerine bunu söyler.',
                        source: 'app/Http/Controllers/Analytics/ShowMenuEngineeringController.php',
                    ),
                    new BlockEntry(
                        term: 'Resmin bir kısmı için betikler',
                        text: 'Okutmaları ve menü açılışlarını sunucu kaydeder. Ürün görüntülemeleri ve aramaları misafirin tarayıcısı bildirir; betikleri engelleyen bir misafir menüyü yine okur ama ürün ya da arama satırı bırakmaz.',
                        source: 'app/Http/Controllers/Analytics/StoreGuestMenuEventsController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'Neyi yapmaz', [
                    new BlockEntry(
                        term: 'Yalnız üç aralık',
                        text: 'Bugün, son yedi gün ve son otuz gün. Özel tarih aralığı ve ay ay geçmiş yoktur.',
                        source: 'app/Http/Controllers/Analytics/ShowAnalyticsSummaryController.php',
                    ),
                    new BlockEntry(
                        term: 'Ciro ya da satış raporu yok',
                        text: 'Raporlar parayı değil ilgiyi sayar. Ciro rakamı, ortalama harcama ve en çok satanlar listesi yoktur.',
                        source: 'app/Application/Analytics/Port/AnalyticsRepositoryPort.php',
                    ),
                    new BlockEntry(
                        term: 'Dışa aktarma yok',
                        text: 'Raporlar ekranda okunur. Hesap tablosu ya da belge indirmesi yoktur.',
                        source: 'resources/js/components/workspace/pages/AnalyticsPage.tsx',
                    ),
                    new BlockEntry(
                        term: 'Misafirin kim olduğuna dair hiçbir şey yok',
                        text: 'Yaş yok, cinsiyet yok, konum yok, geri gelen ziyaretçi geçmişi yok. Ziyaretçi, tek bir çalışma alanının içindeki türetilmiş bir anahtardan ibarettir.',
                        source: 'database/migrations/2026_08_22_000008_create_analytics_events_table.php',
                    ),
                    new BlockEntry(
                        term: 'Otomatik öneri yok',
                        text: 'Raporlar ne olduğunu gösterir. Bu konuda ne yapılacağına karar vermek hâlâ sizin işiniz.',
                        source: 'app/Http/Controllers/Analytics/ShowMenuEngineeringController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'İşletmecilerin raporlar hakkında sorduğu sorular', [
                    new BlockEntry(
                        term: 'Ne kadar geriye bakabilirim?',
                        text: 'Bugün, son yedi gün ya da son otuz gün. Raporların kabul ettiği üç aralık bunlardır.',
                    ),
                    new BlockEntry(
                        term: 'Rapor neden yeterli veri yok diyor?',
                        text: 'Çünkü o aralıkta beşten az farklı kişi ürün açtı. Üç ziyaretçi bir örüntü değildir ve onların üstüne kurulmuş bir sayı sizi boş yere menü yeniden dizmeye gönderirdi.',
                    ),
                    new BlockEntry(
                        term: 'Menünün ne kadar para kazandırdığını görebilir miyim?',
                        text: 'Hayır. Analitik okutmaları, açılışları, ürün görüntülemelerini, aramaları ve gönderilen siparişleri sayar. Ciro bildirmez.',
                    ),
                    new BlockEntry(
                        term: 'En işe yarayan rapor hangisi?',
                        text: 'Genellikle sonuçsuz kalan aramalar. Bir menünün size sunmadığınız bir yemekten söz edebildiği tek yer orasıdır.',
                    ),
                    new BlockEntry(
                        term: 'Misafirlerim bir reklam ağı tarafından izleniyor mu?',
                        text: 'Bu sayımlar kendi alan adınızda Zabuno tarafından kaydedilir ve çalışma alanınızın içinde kalır. Ziyaretçi bir kişi değil, türetilmiş bir anahtardır.',
                    ),
                    new BlockEntry(
                        term: 'Rakamları indirebilir miyim?',
                        text: 'Bugün için hayır. Raporlar ekranda okunur; dışa aktarma yoktur.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'Raporlama ücretli planlardadır', [
                    new BlockEntry(
                        text: 'Hangi planın raporları açtığını ve plansız neyin açık kaldığını görün.',
                        href: '/pricing',
                        term: 'Planlara bakın',
                    ),
                ]),

                new ContentBlock(BlockType::Related, 'İlgili sayfalar', [
                    new BlockEntry(text: 'Menü yönetimi', pageKey: 'urun.menu-yonetimi'),
                    new BlockEntry(text: 'Masalar ve karekodlar', pageKey: 'urun.masa-ve-qr-yonetimi'),
                ]),
            ],
        );
    }
}
