<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages\Tr;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;
use Database\Seeders\PlanCatalogueSeeder;

/**
 * `/tr/urun/menu-yonetimi/menu-versiyonlari/` — yayın, sürümler ve geri
 * almanın Türkçesi (P1).
 *
 * *"Yanlış fiyat listesini yayınlarsam ne olur"* bir yardım sorusu değil, bir
 * SATIN ALMA sorusudur; ve Türkçe okuyan bir işletmeci de kararını bunun
 * cevabına göre verir.
 *
 * BİLEREK YAZILMAYANLAR (İngilizce aslıyla aynı): iki sürüm arasındaki farkı
 * gösteren ekran, onay/inceleme kuyruğu, tekrarlayan plan, yayın bildirimi ve
 * yayını başkasının onaylaması.
 */
final class MenuVersionsPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'urun.menu-yonetimi.menu-versiyonlari',
            locale: 'tr',
            metadata: new PageMetadata(
                seoTitle: 'Menü sürümleri, zamanlanmış yayın ve geri alma',
                metaDescription: 'Her yayın, aynı basılı kodun arkasında numaralı bir sürümdür. Önce önizleyin, seçtiğiniz saatte yayınlayın ve eskisini tek adımda geri getirin.',
                h1: 'Sürümler ve geri dönüş',
                breadcrumbTitle: 'Sürümler ve geri alma',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'Her yayınladığınızda menü numaralı bir sürüm olarak dondurulur ve misafirler onu okumaya başlar. Çıkmadan önce telefonda bakabilir, seçtiğiniz bir saatte çıkmasını sağlayabilir ve eski bir sürümü tek adımda geri koyabilirsiniz. Basılı kod hiç değişmez.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'Yanlış fiyat listesi zaten kırk masada', [
                    new BlockEntry(
                        text: 'Menü, koca bir sütunu yanlış olarak çıkar ve misafirler şu anda onu okumaktadır. Kâğıt geri çağrılamaz ve biri fiyatları yeniden yazarken akşam durdurulamaz.',
                    ),
                    new BlockEntry(
                        text: 'O baskı altında düzeltmek, çalışmanın en yavaş ve ikinci bir hata üretmeye en yatkın hâlidir. Bir işletmecinin satın almadan önce gerçekten cevabını istediği soru "menüyü düzenleyebilir miyim" değil, "yanlış yaptığım gece ne olur"dur.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'Menü kâğıt değil, arkasındaki sürümdür', [
                    new BlockEntry(
                        text: 'Masadaki kart kalıcı bir adresi gösterir. O adresin gösterdiği şey en son yayınladığınız sürümdür; dolayısıyla menüyü değiştirmek hiçbir zaman kâğıdı değiştirmek anlamına gelmez.',
                    ),
                    new BlockEntry(
                        text: 'Her yayın saklandığı için geri dönmek yeniden yazma işi değil, tek bir adımdır. Kötü akşam bir dakikalık derde dönüşür ve kayıt yine neyin ne zaman yayında olduğunu söyler.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'Nasıl çalışır', [
                    new BlockEntry(
                        term: 'Önce taslağa gerçek bir telefonda bakın',
                        text: 'Bir önizleme bağlantısı taslağı misafirin göreceği gibi açar ve çeyrek saat sonra kendiliğinden sona erer; böylece kontrol etmek artık önce yayınlamak anlamına gelmez.',
                        source: 'app/Http/Controllers/Publication/CreateDraftPreviewLinkController.php',
                    ),
                    new BlockEntry(
                        term: 'Yayınlayın, numaralı bir sürüm donar',
                        text: 'Yayınlamak, taslağın fotoğraflarıyla, logonuzla ve renklerinizle bir kopyasını alır, ona bir sonraki sürüm numarasını verir ve misafirlere sunar.',
                        source: 'app/Http/Controllers/Publication/StorePublicationController.php',
                    ),
                    new BlockEntry(
                        term: 'Ya da bir saat seçin',
                        text: 'Sunulan saatler, önünde oturduğunuz bilgisayarın değil şubenin kendi saatinin üstünden hesaplanır ve mutlak anlar olarak saklanır; böylece saat değişimi olan bir gece belirsiz kalamaz.',
                        source: 'app/Application/Publication/UseCase/BuildScheduleOptions.php',
                    ),
                    new BlockEntry(
                        term: 'Onayladığınız şey çıkan şeydir',
                        text: 'Zamanlanmış yayın, menüyü siz kurduğunuz anda dondurur ve o anda hazır olmayan bir taslağı reddeder. Sonrasında yarım bıraktığınız iş, gecenin üçünde misafire ulaşamaz.',
                        source: 'app/Http/Controllers/Publication/StorePublicationScheduleController.php',
                    ),
                    new BlockEntry(
                        term: 'Sunucu onu kendiliğinden gerçekleştirir',
                        text: 'Zamanlanmış yayın sıradan bir yayındır: aynı geçmiş, bir sonraki sürüm numarası, aynı basılı kod. Kimse oturum açmış olmasa da olur ve iki kez olamaz.',
                        source: 'app/Console/Commands/PublishScheduledMenusCommand.php',
                    ),
                    new BlockEntry(
                        term: 'Hangi sürümün canlı olduğunu görün',
                        text: 'Geçmiş, sürümleri en yeniden başlayarak listeler ve misafirlerin şu an okuduğunu işaretler; çünkü bir misafirle fiyat tartışan işletmeci tam olarak o satırı arar.',
                        source: 'app/Http/Controllers/Publication/ListPublicationsController.php',
                    ),
                    new BlockEntry(
                        term: 'Eskisini geri koyun',
                        text: 'Geri getirmek, eski kopyayı yeni bir sürüm olarak yeniden yayınlar. Birinci sürüme dönmek üçüncü sürümü üretir; böylece geçmiş neyin ne zaman yayında olduğunu söylemeye devam eder.',
                        source: 'app/Http/Controllers/Publication/RestorePublicationController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'Neler yapabilirsiniz', [
                    new BlockEntry(
                        term: 'Basılı karta dokunmadan yayınlayın',
                        text: 'Bir menünün genel adresi hiç değişmeyen bir tanımlayıcı taşır. İşletmenin adını değiştirin, şubeyi taşıyın ya da menüyü baştan yazın; masadaki kart çalışmaya devam eder.',
                        source: 'app/Domain/Publication/MenuPublicAddress.php',
                    ),
                    new BlockEntry(
                        term: 'Misafirler taslağınızı değil, donmuş bir kopyayı okur',
                        text: 'Yayınlanmış bir sürüm menüyü o andaki hâliyle tutar. Bu gece düzenlemek, siz yeniden yayınlayana kadar hiçbir masada hiçbir şeyi değiştirmez.',
                        source: 'database/migrations/2026_08_22_000004_create_menu_publications_table.php',
                    ),
                    new BlockEntry(
                        term: 'Plan da sürümün içine donar',
                        text: 'Yürürlükteki haklar yayınla birlikte kaydedilir; böylece sona ermiş bir plan, yemeğinin yarısındaki bir misafirin altından sayfayı kesmez. Değişiklik bir sonraki yayınınıza düşer.',
                        source: 'database/migrations/2026_09_06_000700_add_ordering_switch_and_frozen_plan.php',
                    ),
                    new BlockEntry(
                        term: 'Geri dönmek taslağınıza dokunmaz',
                        text: 'Geri alma, misafirin okuduğunu düzeltir; sizin üzerinde çalıştığınızı değil. Taslaktaki yarım düzenlemeler el değmeden kalır.',
                        source: 'app/Application/Publication/UseCase/AssembleDraftSnapshot.php',
                    ),
                    new BlockEntry(
                        term: 'Bir zamanlama iptal edilebilir',
                        text: 'Bu gece için kurulmuş bir yayın, çalışmadan önce iptal edilebilir. Plan silinmez, iptal edilmiş bir kayıt olarak tutulur; çünkü "o gece ne oldu" sorusu er ya da geç sorulur.',
                        source: 'app/Http/Controllers/Publication/CancelPublicationScheduleController.php',
                    ),
                    new BlockEntry(
                        term: 'Çıkmayan bir yayın yüksek sesle söylenir',
                        text: 'Gecikmiş, kesintiye uğramış ve başarısız üç ayrı cevaptır; üçünde de menü değişmemiştir: misafirler hâlâ ondan önceki sürümü okur.',
                        source: 'app/Domain/Publication/ScheduledPublicationOutcome.php',
                    ),
                    new BlockEntry(
                        term: 'En fazla bir ay ileri',
                        text: 'Bir yayın otuz güne kadar ileriye kurulabilir. Ötesinde donmuş bir kopya, verdiğinizi hatırladığınız bir karar olmaktan çıkar.',
                        source: 'app/Application/Publication/UseCase/BuildScheduleOptions.php',
                    ),
                    new BlockEntry(
                        term: 'Hazır olmayan taslak düğmede reddedilir',
                        text: 'Yayınlanamayacak bir menü, gecenin ortasında sessizce değil, siz yayınla düğmesine bastığınızda ya da zamanlamayı kurduğunuzda reddedilir.',
                        source: 'app/Application/Publication/Exception/UnreadyDraftException.php',
                    ),
                    new BlockEntry(
                        term: 'Tükendi yayını beklemez',
                        text: 'Bir ürünü bugünlük bitti işaretlemek, araya bir sürüm ve bir yayın girmeden okutan bir sonraki misafire ulaşır. Bütün bu sayfayı atlayan tek değişiklik odur.',
                        source: 'app/Http/Controllers/MenuCatalog/UpdateMenuStockController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'Neye ihtiyacınız var', [
                    new BlockEntry(
                        term: 'Yayınlama yetkisi',
                        text: 'Geri dönmek de yayınlamaktır ve aynı yetkiyi ister. Yalnız okuyabilen bir üye geçmişi okuyabilir ve hangi sürümün canlı olduğunu görebilir, değiştiremez.',
                        source: 'app/Domain/Authorization/RolePermissions.php',
                    ),
                    new BlockEntry(
                        term: 'Ücretli plan gerekmez',
                        text: self::freePlanSentence(),
                        source: 'database/seeders/PlanCatalogueSeeder.php',
                    ),
                    new BlockEntry(
                        term: 'Saat dilimi tanınan bir şube',
                        text: 'Zamanlanmış saatler şubenin saati üzerinden hesaplanır. O saatin okunamadığı yerde tahmini bir saat sunulmaz, hiç saat sunulmaz ve şimdi yayınlamak açık kalır.',
                        source: 'app/Application/Publication/UseCase/BuildScheduleOptions.php',
                    ),
                    new BlockEntry(
                        term: 'Zamanlanmış işini çalıştıran bir sunucu',
                        text: 'Gecenin üçüne kurulmuş yayını, sunucuda her dakika çalışan bir görev gerçekleştirir. Bu sayfada siz uyurken bir şeyin çalışmasını gerektiren tek parça zamanlamadır.',
                        source: 'routes/console.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'Neyi yapmaz', [
                    new BlockEntry(
                        term: 'Düzenlemeleriniz için bir geri al değildir',
                        text: 'Geri alma misafirin okuduğunu değiştirir. Taslakta yazdığınız bir fiyatı geri almaz ve orada sildiğiniz bir ürünü geri getirmez.',
                        source: 'app/Http/Controllers/Publication/RestorePublicationController.php',
                    ),
                    new BlockEntry(
                        term: 'İki sürüm arasında karşılaştırma yok',
                        text: 'Geçmiş sürüm numaralarını, saatleri ve hangisinin canlı olduğunu gösterir. İkisi arasında neyin değiştiğini göstermez; dolayısıyla döneceğiniz sürüm tanıdığınız bir sürüm olmalıdır.',
                        source: 'app/Http/Controllers/Publication/ListPublicationsController.php',
                    ),
                    new BlockEntry(
                        term: 'Onay adımı ve ikinci bir göz yok',
                        text: 'Yayınlamasına izin verilen herkes anında yayınlar. İnceleme kuyruğu, onaya gönderilen taslak ve ikinci bir kişiyi zorunlu kılmanın bir yolu yoktur.',
                        source: 'routes/api/publication.php',
                    ),
                    new BlockEntry(
                        term: 'Başarısız zamanlanmış yayın yeniden denenmez',
                        text: 'İşaretlenir ve size bırakılır. Gece boyunca sessizce yeniden denemek, menünün kimse öyle olması gerektiğine karar vermeden değişebilmesi demekti.',
                        source: 'app/Console/Commands/PublishScheduledMenusCommand.php',
                    ),
                    new BlockEntry(
                        term: 'Tekrarlayan zamanlama yok',
                        text: 'Zamanlama bir kural değil, tek bir andır. Her pazartesi değişen bir menü her hafta yeniden kurulur.',
                        source: 'database/migrations/2026_09_05_000300_create_menu_publication_schedules_table.php',
                    ),
                    new BlockEntry(
                        term: 'Bir sürüm yayına girince e-posta gitmez',
                        text: 'Bir yayın başarılı ya da başarısız olduğunda mesaj gönderilmez. Cevap ekranda yaşar; yani ertesi sabah birinin ona bakması gerekir.',
                        source: 'app/Application/Publication/Dto/ScheduledPublicationRecord.php',
                    ),
                    new BlockEntry(
                        term: 'Önizleme bağlantısı bilerek kısa ömürlüdür',
                        text: 'Dolaştırılacak kalıcı bir adres olarak kullanılamaz. Çeyrek saat sonra çalışmaz olur; böylece bir gruba iletilen taslak ertesi güne kalmaz.',
                        source: 'app/Http/Controllers/Publication/ShowDraftPreviewController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'İşletmecilerin yayın hakkında sorduğu sorular', [
                    new BlockEntry(
                        term: 'Yanlış fiyat listesini yayınladım, şimdi ne yapacağım?',
                        text: 'Geçmişi açın, doğru olan sürümü bulun ve geri getirin. Menüyü okuyan misafirler düzeltilmiş hâlini bir sonraki okutmada görür ve hiçbir kartın yeniden basılması gerekmez.',
                    ),
                    new BlockEntry(
                        term: 'Geri dönmek, ayrıldığım sürümü siler mi?',
                        text: 'Hayır. Geri getirmek eski kopyayı yeni bir sürüm olarak yazar; birinci sürüme dönmek üçüncü sürümü üretir. Kayıttan hiçbir şey çıkarılmaz.',
                    ),
                    new BlockEntry(
                        term: 'Ortasında olduğum düzenlemeleri kaybeder miyim?',
                        text: 'Hayır. Geri alma yalnız misafirin okuduğunu değiştirir. Taslağınız tam olarak bıraktığınız yerdedir.',
                    ),
                    new BlockEntry(
                        term: 'Geri aldığımda karttaki adres değişir mi?',
                        text: 'Hayır. Bir menünün genel adresi, arkasında ne yayınlarsanız yayınlayın hiç değişmeyen bir tanımlayıcı taşır.',
                    ),
                    new BlockEntry(
                        term: 'Geceye kurduğum yayın çıkmazsa ne olur?',
                        text: 'Size söylenir ve adı konur: gecikmiş, kesintiye uğramış ya da başarısız. Üçünde de menü değişmemiştir, dolayısıyla misafirler hâlâ ondan önceki sürümü okur.',
                    ),
                    new BlockEntry(
                        term: 'Yayına girmeden taslağı birine gösterebilir miyim?',
                        text: 'Evet; taslağı gerçek bir telefonda açan ve çeyrek saat sonra kendiliğinden sona eren bir önizleme bağlantısıyla.',
                    ),
                    new BlockEntry(
                        term: 'Bir değişikliğin her hafta tekrarlanmasını sağlayabilir miyim?',
                        text: 'Hayır. Zamanlama tek bir andır; haftalık bir değişiklik her hafta yeniden kurulur.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'Yayınlamak bir planın arkasında değildir', [
                    new BlockEntry(
                        text: 'Menü kurmak, yayınlamak ve eski bir sürüme dönmek hiçbir şeye mal olmaz.',
                        href: '/pricing',
                        term: 'Planları görüntüleyin',
                    ),
                ]),

                new ContentBlock(BlockType::Related, 'İlgili sayfalar', [
                    new BlockEntry(text: 'Menü yönetimi', pageKey: 'urun.menu-yonetimi'),
                    new BlockEntry(text: 'Masalar ve karekodlar', pageKey: 'urun.masa-ve-qr-yonetimi'),
                    new BlockEntry(text: 'QR menü', pageKey: 'urun.qr-menu'),
                ]),
            ],
        );
    }

    /**
     * Ücretsiz kademenin adı — KATALOGDAN, elle değil.
     */
    private static function freePlanSentence(): string
    {
        foreach (PlanCatalogueSeeder::catalogue() as $plan) {
            if ($plan['amount_minor'] === 0) {
                return 'Yayınlamak, önizlemek, zamanlamak ve geri dönmek; hiçbir şeye mal olmayan '
                    .$plan['name'].' planındadır. Bu sayfanın hiçbir parçası ödeme yaparak açılmaz.';
            }
        }

        return 'Yayınlamak, önizlemek, zamanlamak ve geri dönmek hiçbir ücretli planın arkasında değildir.';
    }
}
