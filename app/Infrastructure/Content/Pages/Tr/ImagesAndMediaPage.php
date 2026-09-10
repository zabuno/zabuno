<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages\Tr;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;

/**
 * `/tr/urun/gorsel-ve-medya/` — görsel ve medya yönetiminin Türkçesi (P0).
 *
 * İngilizce aslındaki on bir sınırlama satırının on biri burada da var:
 * video yok, arka plan silme yok, filigran yok, odak noktası yok, büyütme
 * yok, hareketli görsel yok, PDF kapağı yok, kategori görseli yok, kütüphane
 * içi arama yok, orijinalin kamera verisi silinmiyor ve dosyalar bir dağıtım
 * ağından değil ürünün kendi sunucusundan geliyor.
 */
final class ImagesAndMediaPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'urun.gorsel-ve-medya',
            locale: 'tr',
            metadata: new PageMetadata(
                seoTitle: 'Menü fotoğrafları ve medya kütüphanesi',
                metaDescription: 'Fotoğrafı bir kez yükleyin; misafire küçük, hızlı ve doğru boyutlu bir kopya gitsin. Girişte taranır ve yayındaki menünün altından hiç silinmez.',
                h1: 'Görsel ve medya',
                breadcrumbTitle: 'Görsel ve medya',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'Telefonunuzun çektiği fotoğrafı yüklersiniz. Zabuno onu zararlı yazılıma karşı tarar, yavaş bağlantıdaki bir telefonun gerçekten yükleyebileceği küçük kopyaları üretir ve orijinalinize dokunmaz; böylece kopyalar her zaman yeniden üretilebilir.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'Telefondan çıkan fotoğraf yanlış fotoğraftır', [
                    new BlockEntry(
                        text: 'Telefondan doğrudan çıkan bir görüntü, baskıya yetecek genişlikte ve birkaç megabayttır. Kalabalık bir restoran bağlantısında misafire gönderildiğinde, garson geri döndükten sonra açılan bir menü olur.',
                    ),
                    new BlockEntry(
                        text: 'Alışılmış çözümler daha kötüdür. Elle küçültmek her fotoğrafın biraz farklı boyutta olması demektir. Ne geliyorsa yüklemek ise bedelini misafirin ödemesi demektir ve misafir, bunu size şikâyet edemeyecek tek kişidir.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'Tek yükleme, birkaç boyut, tek orijinal', [
                    new BlockEntry(
                        text: 'Her görsel iki kere saklanır: tam olarak gönderdiğiniz hâliyle orijinali ve ondan üretilen sunulan kopyalar. Orijinal saklandığı için bir kopya, sizden fotoğrafı yeniden bulmanız istenmeden sonradan yeniden üretilebilir.',
                    ),
                    new BlockEntry(
                        text: 'Ürün yapamadığı şeyi yüksek sesle reddeder. Çözemediği bir dosya, taranamayan bir dosya, betik saklayan bir çizim; her biri sebebiyle reddedilir. Kabul edilip misafirin ekranında sessizce bozulmaz.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'Telefonunuzdan masaya', [
                    new BlockEntry(
                        term: 'Dosya okunur, güvenilmez',
                        text: 'İlk baytlar dosyanın gerçekte ne olduğunu söyler. Başka bir şey gibi görünmek için adı değiştirilmiş bir fotoğraf burada yakalanır, çünkü uzantı asla cevap değildir.',
                        source: 'app/Http/Requests/Media/StoreMediaRequest.php',
                    ),
                    new BlockEntry(
                        term: 'Ayrı tutulur ve taranır',
                        text: 'Yükleme, zararlı yazılım taraması temiz diyene kadar karantinada bekler. Tarama çalışamıyorsa dosya geçirilmez, tutulur; güvenli cevap kapalı olandır.',
                        source: 'app/Application/Media/UseCase/ScanQuarantinedMediaAsset.php',
                    ),
                    new BlockEntry(
                        term: 'Küçük kopyalar, bir kez üretilir',
                        text: 'Sunulan kopyalar o yuvanın ihtiyaç duyduğu genişliklerde üretilir, biçimine göre kırpılır ve sunucu destekliyorsa WebP olarak kodlanır. Hiçbir şey orijinalinin ötesine büyütülmez.',
                        source: 'app/Infrastructure/Media/Processing/GdMediaAssetProcessor.php',
                    ),
                    new BlockEntry(
                        term: 'Parmak iziyle sunulur',
                        text: 'Her kopyanın adresi, baytlarının parmak izini taşır; böylece tarayıcılar onu bir yıl saklayabilir. Görseli değiştirdiğinizde adres de değişir; kimsenin önbellek temizlemesi gerekmez.',
                        source: 'app/Support/Media/RenditionUrl.php',
                    ),
                    new BlockEntry(
                        term: 'Bir ürüne, bir sürümde bağlanır',
                        text: 'Fotoğraf, menü öğesine belirli bir sürümde bağlanır ve yayınlanmış bir menü, yayınlandığı sürümü göstermeye devam eder.',
                        source: 'app/Http/Controllers/MenuCatalog/BindMenuItemImageController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'Medya kütüphanesi neler yapar', [
                    new BlockEntry(
                        term: 'Ürünlerde fotoğraf, markada logo',
                        text: 'Menü öğesi için bir görsel, işletme için bir logo. Logo basılı karekod kartlarına da taşınır; böylece masadaki kart genel bir kare değil, sizin kartınız olur.',
                        source: 'app/Http/Controllers/QrDestination/ExportQrCardController.php',
                    ),
                    new BlockEntry(
                        term: 'Alternatif metin istenir, isteğe bağlı değildir',
                        text: 'Her yükleme bir açıklama taşır. Ekran okuyucu kullanan bir misafir, dosya adı yerine ürünün neye benzediğini duyar.',
                        source: 'app/Http/Requests/Media/StoreMediaRequest.php',
                    ),
                    new BlockEntry(
                        term: 'Yüklemeden kırpma ve tarayıcıda küçültme',
                        text: 'Görsel gönderilmeden önce kendi cihazınızda kırpılır ve küçültülür; böylece restorandaki bir telefondan yapılan yavaş yükleme kısa sürer.',
                        source: 'resources/js/components/workspace/pages/media/clientDownscale.ts',
                    ),
                    new BlockEntry(
                        term: 'iPhone fotoğrafı da işlenir',
                        text: 'Telefonların kaydettiği HEIC biçimi gönderilmeden önce tarayıcıda JPEG\'e çevrilir; çünkü sunucu HEIC okuyamaz ve bunu hata vermek yerine açıkça söyler.',
                        source: 'app/Infrastructure/Media/Processing/GdMediaAssetProcessor.php',
                    ),
                    new BlockEntry(
                        term: 'Klasörler ve onları izleyen bir süzgeç',
                        text: 'Başlangıçları tatlılardan ayrı tutmak için iki kademe klasör. Üçüncü kademe yoktur, çünkü kimsenin gezinemediği bir kütüphane daha derli toplu değildir.',
                        source: 'app/Domain/Media/FolderNesting.php',
                    ),
                    new BlockEntry(
                        term: 'Bir görseli başka biçime çevirme',
                        text: 'Var olan bir görsel, sunucu o biçimi destekliyorsa WebP, AVIF ya da JPEG\'e çevrilebilir. Orijinal saklanır ve üzerine yazılmak yerine yeni bir sürüm açılır.',
                        source: 'app/Application/Media/UseCase/ConvertMediaAssetsToFormat.php',
                    ),
                    new BlockEntry(
                        term: 'Sürümler ve geri dönüş yolu',
                        text: 'Bir fotoğrafı değiştirmek yeni bir sürüm açar. Eskisi hâlâ orada durur ve geri getirilebilir; böylece yoğun bir akşamdaki hatalı değişim kalıcı olmaz.',
                        source: 'app/Http/Controllers/Media/RestoreMediaVersionController.php',
                    ),
                    new BlockEntry(
                        term: 'Silmeden önce "bu nerede kullanılıyor?"',
                        text: 'Silme, önce görselin bağlı olduğu ürünleri gösterir. Sonra yokluğa değil çöp kutusuna gider ve geri getirilebilir.',
                        source: 'app/Http/Controllers/Media/ShowMediaUsagesController.php',
                    ),
                    new BlockEntry(
                        term: 'Yayındaki menünün gösterdiği hiçbir şey temizlenmez',
                        text: 'Kalıcı temizlik, yayınlanmış bir menünün hâlâ gösterdiği hiçbir dosyaya dokunmaz. Elinde basılı kod olan bir misafir, fotoğrafın kaybolmasını izlemez.',
                        source: 'app/Console/Commands/PurgeMediaTrashCommand.php',
                    ),
                    new BlockEntry(
                        term: 'Neyi kimin değiştirdiğinin kaydı',
                        text: 'Yüklemeler, değiştirmeler ve silmeler kişi ve zamanla birlikte yazılır.',
                        source: 'app/Http/Controllers/Media/ListMediaAuditsController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'Neye ihtiyacınız var', [
                    new BlockEntry(
                        term: 'Medya yönetme yetkisi',
                        text: 'Yükleme ve silme medya yetkisine aittir. Bu yetkisi olmayan bir ekip üyesi menüyü okuyabilir ama görsellerini değiştiremez.',
                        source: 'app/Domain/Authorization/RolePermissions.php',
                    ),
                    new BlockEntry(
                        term: 'Ürünün kabul ettiği bir dosya',
                        text: 'Fotoğraflar JPEG, PNG, GIF ya da WebP; logolar SVG; belgeler PDF. Başka her şey kapıda, sebebiyle birlikte reddedilir.',
                        source: 'config/media-slots.php',
                    ),
                    new BlockEntry(
                        term: 'Boyut sınırları içinde bir dosya',
                        text: 'Fotoğraf için yirmi beş megabayt, logo çizimi için iki, belge için kırk beş; ve kırk megapikselin üstünde dosya yok.',
                        source: 'config/media-slots.php',
                    ),
                    new BlockEntry(
                        term: 'Planınızın içinde yer',
                        text: 'Depolama, dosya sayısı ve aylık yükleme sayısı plana bağlıdır. Yerin bitmesi yalnız yeni yüklemeleri durdurur; zaten yayınlanmış menü sunulmaya devam eder.',
                        source: 'config/media-quota.php',
                    ),
                    new BlockEntry(
                        term: 'Sunucuda bir zararlı yazılım tarayıcısı',
                        text: 'Tarama, ürünün yanına kurulan bir tarayıcı tarafından yapılır. Tarayıcı yoksa yüklemeler geçirilmez, tutulur.',
                        source: 'config/media.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'Neyi yapmaz', [
                    new BlockEntry(
                        term: 'Video yok',
                        text: 'Hiçbir biçimde video hattı yoktur. Bir video dosyası kabına bakılarak tanınır ve reddedilir; kabul edilip oynatılamaz hâlde bırakılmaz.',
                        source: 'app/Infrastructure/Media/Processing/RuntimeMediaFormatSupport.php',
                    ),
                    new BlockEntry(
                        term: 'Arka plan silme ve görsel üretme yok',
                        text: 'Fotoğraflar boyutlandırılır, yuvasına kırpılır ve yeniden kodlanır. Rötuşlanmaz, arka planından kesilmez ve uydurulmaz.',
                        source: 'app/Infrastructure/Media/Processing/GdMediaAssetProcessor.php',
                    ),
                    new BlockEntry(
                        term: 'Filigran yok',
                        text: 'Görseller olduğu gibi sunulur. Üzerlerine hiçbir şey basılmaz.',
                        source: 'app/Http/Controllers/Media/ShowMediaSettingsController.php',
                    ),
                    new BlockEntry(
                        term: 'Odak noktası yok, büyütme yok',
                        text: 'Bir yuvanın sabit biçimi varsa kırpma ortadan alınır; kalması gereken parçayı işaretleyemezsiniz. Hedeften küçük bir görsel asla şişirilmez.',
                        source: 'app/Infrastructure/Media/Processing/GdMediaAssetProcessor.php',
                    ),
                    new BlockEntry(
                        term: 'Hareket yok',
                        text: 'Hareketli bir görsel tek kareye indirilir. Hareketli menü fotoğrafı desteklenen bir şey değildir.',
                        source: 'config/media-slots.php',
                    ),
                    new BlockEntry(
                        term: 'Orijinal, kamera verisini korur',
                        text: 'Misafirin indirdiği kopyalar yeniden kodlanır ve kamera üstverisi taşımaz. Yüklediğiniz orijinal ise konum etiketi dahil geldiği gibi saklanır.',
                        source: 'app/Http/Controllers/Media/ShowMediaSettingsController.php',
                    ),
                    new BlockEntry(
                        term: 'Kütüphanede arama kutusu yok',
                        text: 'Klasöre göre süzebilir; tarihe, ada ya da boyuta göre sıralayabilirsiniz. Dosya adları arasında metin araması yoktur.',
                        source: 'app/Http/Controllers/Media/ListMediaController.php',
                    ),
                    new BlockEntry(
                        term: 'PDF için kapak görseli yok',
                        text: 'Belge saklanır ve panelde okunabilir ama onun için küçük görsel üretilmez. Uydurulmuş bir kapak, hiçbir şeyin resmi olurdu.',
                        source: 'app/Infrastructure/Media/Processing/PdfMediaAssetProcessor.php',
                    ),
                    new BlockEntry(
                        term: 'Kategorilerde görsel yok',
                        text: 'Fotoğraf bir ürüne ya da marka logosuna bağlanır. Menüdeki bir bölüm başlığı görsel taşımaz.',
                        source: 'app/Infrastructure/Media/Persistence/EloquentMenuMedia.php',
                    ),
                    new BlockEntry(
                        term: 'Misafirin fotoğrafı görüp görmemesi plana bağlıdır',
                        text: 'Yüklemek, saklamak, sürümlemek ve panelde görmek her planda çalışır. Menüde misafire göstermek ücretli bir haktır ve bu hakkı olmayan bir çalışma alanı ürünü fotoğrafsız gösterir — boş çerçeve de yoktur, misafire bir uyarı da.',
                        source: 'app/Application/Publication/UseCase/ApplyGuestRichMedia.php',
                    ),
                    new BlockEntry(
                        term: 'Dosyaları dağıtım ağı değil ürün sunar',
                        text: 'Görseller menüyle aynı sunucudan gelir. Önlerinde ayrı bir içerik dağıtım ağı yoktur.',
                        source: 'app/Infrastructure/Media/Persistence/EloquentMediaRepository.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'İşletmecilerin görseller hakkında sorduğu sorular', [
                    new BlockEntry(
                        term: 'Fotoğrafı doğrudan telefonumdan yükleyebilir miyim?',
                        text: 'Evet, iPhone\'un kaydettiği HEIC dosyaları dahil. Gönderilmeden önce tarayıcınızda çevrilir ve küçültülür; böylece restoran bağlantısında bile yükleme kısa sürer.',
                    ),
                    new BlockEntry(
                        term: 'Misafirlerim devasa bir dosya mı indirecek?',
                        text: 'Hayır. Misafir, gösterildiği boyuta göre üretilmiş, küçük kodlanmış bir kopya alır ve tarayıcısı onu sonrasında saklar.',
                    ),
                    new BlockEntry(
                        term: 'Mutfaktan bir video ekleyebilir miyim?',
                        text: 'Hayır. Hiçbir biçimde video desteği yoktur ve bir video dosyası kabul edilip bozuk bırakılmak yerine yüklemede reddedilir.',
                    ),
                    new BlockEntry(
                        term: 'Zabuno fotoğrafımın arka planını silebilir mi?',
                        text: 'Hayır. Görseller boyutlandırılır ve kırpılır, düzenlenmez.',
                    ),
                    new BlockEntry(
                        term: 'Menüde duran bir görseli silersem ne olur?',
                        text: 'Önce nerede kullanıldığı gösterilir. Sonra çöp kutusuna gider ve geri getirilebilir; kalıcı temizlik ise yayınlanmış bir menünün hâlâ gösterdiği hiçbir şeye dokunmayı reddeder.',
                    ),
                    new BlockEntry(
                        term: 'Alternatif metin yazmak zorunda mıyım?',
                        text: 'Evet ve bu bilinçlidir. Ekran okuyucu kullanan bir misafirin duyduğu şey odur ve yalnız sizin yazabileceğiniz bir cümledir.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'Depolama planla birlikte büyür', [
                    new BlockEntry(
                        text: 'Her planın ne kadar yer verdiğini ve plansız neyin açık kaldığını görün.',
                        href: '/pricing',
                        term: 'Plan karşılaştırması',
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
