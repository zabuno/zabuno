<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages\Tr;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;

/**
 * `/tr/urun/zabuno-ai/` — Zabuno AI genel bakışının Türkçesi (P0).
 *
 * Türkçe sayfa da yalnız BUGÜN uçtan uca çalışan üç yeteneği anlatır:
 * fotoğraftan menü çıkarma, ürün açıklaması taslağı ve kopya ürün adı
 * tespiti. Doğruluk/başarım oranı burada da yazılmaz; ölçülmemiş bir yüzdeyi
 * ikinci dilde yazmak, birinci dilde yazmaktan daha az yanlış olmazdı.
 */
final class ZabunoAiPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'urun.zabuno-ai',
            locale: 'tr',
            metadata: new PageMetadata(
                seoTitle: 'Zabuno yapay zekâ',
                metaDescription: 'Menünüzün fotoğrafını taslağa çevirin, bir ürün açıklamasının ilk taslağını alın ve iki kez girdiğiniz ürünleri bulun.',
                h1: 'Zabuno yapay zekâ',
                breadcrumbTitle: 'Zabuno yapay zekâ',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'Zabuno yapay zekâsı bugün üç iş yapar: bir menü fotoğrafını taslağa okur, bir ürün için açıklama taslağı yazar ve iki kez girdiğiniz ürünleri gösterir. Ürettiği hiçbir şey, bir insan okuyup uygulamadan misafire ulaşmaz.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'Zor olan ilk akşamdır', [
                    new BlockEntry(
                        text: 'Bir restoranın başlamasını engelleyen iş, yazmaktır. Doksan ürün, kategorileri ve fiyatları; servisten sonra, sabahtan beri ayakta olan biri tarafından, telefonda elle girilecek.',
                    ),
                    new BlockEntry(
                        text: 'Menü zaten var. Basılmış, kaplanmış ve masanın üstünde duruyor. Eksik olan tek şey, onu kâğıttan çıkarıp ekrana taşıyacak bir yol.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'Bir taslak, asla bir yayın', [
                    new BlockEntry(
                        text: 'Menünün fotoğrafını çekin; Zabuno onu düzeltebileceğiniz bir taslağa çevirir. Bu ilk geçiştir, son cevap değil; ve ürün tam olarak bu ayrımın üstüne kurulmuştur.',
                    ),
                    new BlockEntry(
                        text: 'Üretilen hiçbir şey bir insan onu uygulamadan hiçbir yere yazılmaz; uygulandığında bile misafirin masasına değil, sizin çalıştığınız taslağa düşer. Aksi hâlde bir fiyatı yanlış okuyan bir model, o fiyatı menüye koyardı.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'Nasıl çalışır', [
                    new BlockEntry(
                        term: 'Menünün fotoğrafını çekin',
                        text: 'Tek fotoğraf gönderin, tek seferde ona kadar gönderin ya da bir tomar sayfayı arka planda okunmak üzere kuyruğa alın.',
                        source: 'app/Http/Controllers/Ai/StoreMenuAiBatchController.php',
                    ),
                    new BlockEntry(
                        term: 'Cevap bir şemaya karşı denetlenir',
                        text: 'Beklenen biçime uymayan bir cevap kapıda reddedilir. Hiç saklanmaz ve size hiç gösterilmez.',
                        source: 'app/Infrastructure/Ai/ArtifactSchemaValidator.php',
                    ),
                    new BlockEntry(
                        term: 'Taslak olarak bekler',
                        text: 'Sonuç, onu üreten model ve istem sürümüyle birlikte uygulanmamış bir taslak olarak dosyalanır; böylece neyin neyi okuduğunu sonradan söyleyebilirsiniz.',
                        source: 'app/Application/Ai/UseCase/ExtractMenuFromImage.php',
                    ),
                    new BlockEntry(
                        term: 'Siz okur ve uygularsınız',
                        text: 'Uygulamak, menüyü yönetme yetkisi olan bir kişinin ayrı ve bilinçli eylemidir ve yalnız bir kez yapılabilir.',
                        source: 'app/Application/Ai/UseCase/ApplyMenuArtifact.php',
                    ),
                    new BlockEntry(
                        term: 'Yayınlarsınız ya da yayınlamazsınız',
                        text: 'Uygulamak taslak menünüze yazar. Siz yayınlayana kadar misafir hâlâ hiçbir şey görmez.',
                        source: 'app/Http/Controllers/Ai/ApplyMenuAiImportController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'Bugün neler yapar', [
                    new BlockEntry(
                        term: 'Fotoğraf menü taslağına dönüşür',
                        text: 'Kategoriler, ürün adları, fiyatlar ve para birimi bir fotoğraftan okunur ve uygulamadan önce düzeltebileceğiniz satırlara dönüşür.',
                        source: 'app/Application/Ai/UseCase/ExtractMenuFromImage.php',
                    ),
                    new BlockEntry(
                        term: 'Bir tomar sayfa birden',
                        text: 'Çok sayfalı bir menü kuyruğa alınıp arka planda sayfa sayfa okunabilir; restoran başına bir sınır vardır, böylece tek bir büyük aktarım herkesin önünü kesemez.',
                        source: 'app/Jobs/ExtractMenuBatchPageJob.php',
                    ),
                    new BlockEntry(
                        term: 'Açıklamanın ilk taslağı',
                        text: 'Bir ürün açıklaması, siz düzenleyesiniz diye taslak olarak yazılır. Taslak yalnız siz onayladıktan sonra uygulanır ve yalnız açıklamanın yerine geçer.',
                        source: 'app/Application/Ai/UseCase/ApplyProductDescriptionDraft.php',
                    ),
                    new BlockEntry(
                        term: 'Aynı ürün, iki kez yazılmış',
                        text: 'Neredeyse aynı adı taşıyan ürünler aday olarak listelenir; böylece uzun bir menünün sakladığı kopyaları görürsünüz.',
                        source: 'app/Application/Ai/UseCase/DetectDuplicateProductNames.php',
                    ),
                    new BlockEntry(
                        term: 'Talimat ile menü metni ayrı tutulur',
                        text: 'Menü metniniz talimat olarak değil veri olarak gönderilir; böylece menünün içindeki bir satır modele ne yapacağını söyleyemez.',
                        source: 'app/Infrastructure/Ai/GeminiVisionProvider.php',
                    ),
                    new BlockEntry(
                        term: 'Alerjen asla uydurulmaz',
                        text: 'Üretilen metnin alerjen iddiası kurması yasaktır ve içe aktarılan bir ürün hiç alerjensiz gelir. Onları siz beyan edersiniz.',
                        source: 'app/Application/Ai/UseCase/ApplyMenuArtifact.php',
                    ),
                    new BlockEntry(
                        term: 'Kapalı olduğunda söyler',
                        text: 'Her yetenek kullanılabilir olup olmadığını ve değilse nedenini bildirir; böylece çalışamayacak bir düğme çalışabilirmiş gibi sunulmaz.',
                        source: 'app/Http/Controllers/Ai/ShowAiAvailabilityController.php',
                    ),
                    new BlockEntry(
                        term: 'Bir restoranın taslakları kendisinde kalır',
                        text: 'Her yapay zekâ kaydı onu oluşturan çalışma alanına bağlıdır ve onunla birlikte kaybolur.',
                        source: 'database/migrations/2026_08_27_000500_create_ai_plane_tables.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'Neye ihtiyacınız var', [
                    new BlockEntry(
                        term: 'Yapay zekânın açılmış olması',
                        text: 'Yapay zekâ kapalı gelir. Açılıp bir sağlayıcı tanımlanana kadar her yapay zekâ eylemi kullanılamaz olduğunu bildirir ve numara yapmak yerine reddeder.',
                        source: 'config/ai.php',
                    ),
                    new BlockEntry(
                        term: 'Tanımlanmış bir sağlayıcı',
                        text: 'Sağlayıcı anahtarları şifreli platform kasasına bir kez girilir. Ortam dosyalarından okunmaz.',
                        source: 'app/Infrastructure/Platform/Credential/EloquentPlatformCredentialStore.php',
                    ),
                    new BlockEntry(
                        term: 'Belge değil fotoğraf',
                        text: 'İçe aktarıcı görüntü dosyalarını okur. Yalnız PDF olarak var olan bir menünün önce fotoğraflanması ya da görüntüye çevrilmesi gerekir.',
                        source: 'app/Infrastructure/Ai/OpenAiVisionProvider.php',
                    ),
                    new BlockEntry(
                        term: 'Menüyü yönetme yetkisi',
                        text: 'Bir taslağı uygulamak, menüyü elle düzenlemekle aynı yetkiye karşı denetlenir; çünkü yaptığı iş tam olarak budur.',
                        source: 'app/Http/Controllers/Ai/ApplyMenuAiImportController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'Neyi yapmaz', [
                    new BlockEntry(
                        term: 'Menünüzü yazmaz',
                        text: 'Ürün icadı yoktur. Zaten sahip olduğunuz bir menüyü okur; sahip olmadığınız bir menüyü önermez.',
                        source: 'app/Domain/Ai/Capability.php',
                    ),
                    new BlockEntry(
                        term: 'Çeviri yapmaz',
                        text: 'Çeviri asistanı yoktur. Menünüz yazdığınız dilde kalır.',
                        source: 'app/Domain/Ai/Capability.php',
                    ),
                    new BlockEntry(
                        term: 'Fotoğrafa, besin değerine ve raporlara dokunmaz',
                        text: 'Görsel üretimi ya da rötuşu yok, kalori ya da besin değeri tahmini yok, analitiğinizin yazılı özeti yok, kampanya metni yok.',
                        source: 'app/Domain/Ai/Capability.php',
                    ),
                    new BlockEntry(
                        term: 'Ürün adlarını değiştirmez',
                        text: 'Açıklamalar taslak olarak yazılır; adlar tam olarak sizin yazdığınız gibi bırakılır.',
                        source: 'app/Application/Ai/UseCase/ApplyProductDescriptionDraft.php',
                    ),
                    new BlockEntry(
                        term: 'Kopyaları bulur ama birleştirmez',
                        text: 'Kopya listesi okunacak bir şeydir. İki ürünü birleştirmek hâlâ elle yapılır.',
                        source: 'app/Application/Ai/UseCase/DetectDuplicateProductNames.php',
                    ),
                    new BlockEntry(
                        term: 'Doğruluk oranı yayınlamıyoruz',
                        text: 'Sağlayıcı adaptörleri canlı bir sağlayıcıya karşı değil, kayıtlı cevaplara karşı denendi. Buraya yazacağımız herhangi bir yüzde, ölçmediğimiz bir sayı olurdu.',
                        source: 'app/Infrastructure/Ai/OpenAiVisionProvider.php',
                    ),
                    new BlockEntry(
                        term: 'Taslaklar siz silene kadar durur',
                        text: 'İçe aktarılan taslaklar ve kayıtları çalışma alanı silinene kadar kalır. Otomatik süre dolumu yoktur.',
                        source: 'database/migrations/2026_08_27_000500_create_ai_plane_tables.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'İşletmecilerin yapay zekâ hakkında sorduğu sorular', [
                    new BlockEntry(
                        term: 'Menümü fotoğraftan okuyabilir mi?',
                        text: 'Evet. Tek fotoğraf, tek seferde on tane ya da kuyruğa alınmış bir tomar sayfa. Sonuç, uygulamadan önce düzelttiğiniz bir taslaktır.',
                    ),
                    new BlockEntry(
                        term: 'PDF okuyabilir mi?',
                        text: 'Hayır. İçe aktarıcı görüntü okur. PDF bir menünün önce fotoğraflanması ya da görüntüye çevrilmesi gerekir.',
                    ),
                    new BlockEntry(
                        term: 'Misafirin önüne yanlış bir fiyat koyabilir mi?',
                        text: 'Kendi başına koyamaz. Siz uygulamadan hiçbir şey yazılmaz ve uygulamak taslağınıza yazar. Misafir bunu ancak siz yayınladıktan sonra görür.',
                    ),
                    new BlockEntry(
                        term: 'Alerjenleri tahmin eder mi?',
                        text: 'Hayır ve bu bilinçlidir. Üretilen metin alerjen iddiası kuramaz ve içe aktarılan ürünler alerjensiz gelir. Alerjenleri beyan etmek size aittir.',
                    ),
                    new BlockEntry(
                        term: 'Ne kadar doğru çalışıyor?',
                        text: 'Bir rakam yayınlamıyoruz, çünkü canlı bir sağlayıcıya karşı ölçmedik. Her içe aktarmayı denetlenecek bir taslak olarak görün.',
                    ),
                    new BlockEntry(
                        term: 'Varsayılan olarak açık mı?',
                        text: 'Hayır. Yapay zekâ kapalı gelir ve her yetenek kullanılabilir olup olmadığını, değilse nedenini bildirir.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'Yapay zekâdan değil, menüden başlayın', [
                    new BlockEntry(
                        text: 'Menüyü elle kurmak ve yayınlamak, yapay zekâ olsun ya da olmasın ücretsiz planda çalışır.',
                        href: '/pricing',
                        term: 'Planları inceleyin',
                    ),
                ]),

                new ContentBlock(BlockType::Related, 'İlgili sayfalar', [
                    new BlockEntry(text: 'Menü yönetimi', pageKey: 'urun.menu-yonetimi'),
                    new BlockEntry(text: 'QR menü', pageKey: 'urun.qr-menu'),
                ]),
            ],
        );
    }
}
