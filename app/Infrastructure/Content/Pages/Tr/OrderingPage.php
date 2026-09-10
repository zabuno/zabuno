<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages\Tr;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;
use App\Domain\Entitlement\Entitlement;
use Database\Seeders\PlanCatalogueSeeder;

/**
 * `/tr/urun/siparis/` — masadan siparişin Türkçesi (P1).
 *
 * Hub'ın kendisi masadan siparişi anlatır ve kardeşlerin (gel-al, paket
 * servis, ön sipariş, çoklu satıcı, açık hesap) YOKLUĞUNU açıkça yazar —
 * İngilizce aslındaki gerekçenin aynısı.
 *
 * Plan adı burada da elle YAZILMAZ, katalogdan okunur: sayfa "Restaurant"
 * derken ürün başka bir kademede satıyor olsaydı, bunu ancak parasını ödeyip
 * sipariş gönderemeyen bir restoran fark ederdi.
 *
 * BİLEREK YAZILMAYANLAR: masada ödeme, kasa/POS bağlantısı, paket servis,
 * gel-al, ön sipariş, rezervasyon, açık hesap ve hesap bölme, sipariş notu,
 * mutfak fişi yazıcısı, sesli/anlık bildirim, misafirin takip ekranı.
 */
final class OrderingPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'urun.siparis',
            locale: 'tr',
            metadata: new PageMetadata(
                seoTitle: 'Karekodla masadan sipariş',
                metaDescription: 'Misafir masadaki kodu okutur ve sipariş gönderir; mutfak görmeden önce bir garson onaylar. Bunun kapsadıkları ve kapsamadıkları.',
                h1: 'Masadan sipariş',
                breadcrumbTitle: 'Sipariş',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'Açtığınız yerlerde misafir kendi masasındaki kodu okutur, tarayıcıda bir sepet kurar ve gönderir. Bir garson onaylar ya da reddeder; mutfak ekranına yalnız onaylanmış sipariş ulaşır. Zabuno siparişi taşır, parayı almaz.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'Sipariş iki kez alınır ve ikincisi ezberden', [
                    new BlockEntry(
                        text: 'Dolu bir masada garson bloknota yazar, mutfağa yürür ve sesli olarak söyler. Bloknot ile tezgâh arasında bir ürün değişir, bir porsiyon unutulur ve masada söylenmiş bir alerji bir daha söylenmez.',
                    ),
                    new BlockEntry(
                        text: 'En yoğun saat, aynı zamanda bloknotun en zor okunduğu saattir. Neyin ters gittiğini kimse kaydetmez, dolayısıyla aynı akşam gelecek hafta yine yaşanır.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'Misafir yazar, kararı yine bir insan verir', [
                    new BlockEntry(
                        text: 'Misafir, telefonunda zaten açık olan menüden seçer; böylece ürün adları, fiyatlar ve beyan edilen alerjenler sizin yayınladıklarınızdır. Hiçbir şey yeniden yazılmaz ve hiçbir şey ezberlenmez.',
                    ),
                    new BlockEntry(
                        text: 'Misafirin gönderdiği şey bir taleptir, bir iş emri değil. Bir garson onaylar ve ancak o zaman mutfakta görünür. O masada oturmayan biri ocağınıza iş açamaz.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'Nasıl çalışır', [
                    new BlockEntry(
                        term: 'Şube için siz açarsınız',
                        text: 'Sipariş, siz açana kadar kapalıdır ve şube şube açılır. Siparişleri görebilen herkes şubenin sipariş alıp almadığını bilir; anahtarı yalnız sahip çevirebilir.',
                        source: 'app/Http/Controllers/Ordering/UpdateOrderingSwitchController.php',
                    ),
                    new BlockEntry(
                        term: 'Misafir masadaki kodu okutur',
                        text: 'Sepet yalnız okutulan kod bir masaya aitse, planınız hakkı taşıyorsa, şube sipariş alıyorsa ve yayınlanmış menü tek para biriminde fiyatlıysa görünür.',
                        source: 'app/Http/Controllers/QrDestination/ShowPublicMenuController.php',
                    ),
                    new BlockEntry(
                        term: 'Sepet telefonda kalır',
                        text: 'Misafir siparişi göndermeden sunucuya hiçbir şey yazılmaz. Açılacak bir hesap ve kurulacak bir uygulama yoktur.',
                        source: 'database/migrations/2026_09_06_000600_create_orders_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Fiyatları telefon değil sunucu yazar',
                        text: 'Misafirden yalnız bir ürün ve bir adet kabul edilir. Ad, fiyat ve beyan edilen alerjenler sizin yayınladığınız menüden kopyalanır; böylece misafir bir ürünün ne tuttuğunu belirleyemez.',
                        source: 'app/Application/Ordering/UseCase/BuildOrderLines.php',
                    ),
                    new BlockEntry(
                        term: 'Bir garson onaylar ya da sebebiyle reddeder',
                        text: 'Gönderilen siparişler bir kuyrukta, en eskisi önde bekler. Onaylamak ve reddetmek aynı kapıdır ve üstüne bir sebep yazılmadan bir ret kaydedilemez.',
                        source: 'app/Http/Controllers/Ordering/ChangeOrderStatusController.php',
                    ),
                    new BlockEntry(
                        term: 'Mutfak ancak o zaman görür',
                        text: 'Bekleyen bir sipariş mutfak panosunda görünmez. Bir aşçının gördüğü tek durumlar onaylandı, hazırlanıyor ve hazır.',
                        source: 'app/Domain/Ordering/OrderStatus.php',
                    ),
                    new BlockEntry(
                        term: 'Mutfak ilerletir, servis kapatır',
                        text: 'Mutfak bir siparişi hazırlanıyor ve sonra hazır olarak işaretler. Teslim edildi işareti ocağa değil, tabağı taşıyan kişiye aittir.',
                        source: 'app/Http/Controllers/Ordering/ListKitchenOrdersController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'Neler yapabilirsiniz', [
                    new BlockEntry(
                        term: 'Masa okunur, asla yazılmaz',
                        text: 'Sipariş, kodu okutulan masaya düşer. Misafir masa seçemez; dolayısıyla yanlış masaya sipariş, bu ürünün yapabileceği bir hata değildir.',
                        source: 'database/migrations/2026_08_22_000006_create_dining_areas_and_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Kurulacak bir şey yok, üye olunacak bir yer yok',
                        text: 'Misafir, menüyü gösteren aynı web sayfasından; hesap açmadan ve ad, e-posta ya da telefon vermeden sipariş verir.',
                        source: 'routes/web.php',
                    ),
                    new BlockEntry(
                        term: 'Sipariş gönderildiği fiyatı korur',
                        text: 'Adlar, fiyatlar ve alerjenler sipariş satırına kopyalanır. Bu gece bir ürünün adını ya da fiyatını değiştirmek, bu akşamın fişlerini yeniden yazmaz.',
                        source: 'database/migrations/2026_09_06_000600_create_orders_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Beyan edilen alerjenler satırla birlikte gider',
                        text: 'Bir ürün için beyan ettikleriniz siparişle birlikte mutfak panosuna taşınır; böylece yalnız sipariş edildiği yerde değil, yemeğin yapıldığı yerde de okunur.',
                        source: 'app/Application/Ordering/Dto/OrderLineSummary.php',
                    ),
                    new BlockEntry(
                        term: 'İki pano, iki ayrı yetki',
                        text: 'Servis kuyruğu ile mutfak panosu ayrıdır. Mutfak hesabı onaylanmış işi, alerjenleri ve tükendi işaretlerini görür; bir siparişi onaylayamaz ve işletme hakkında başka hiçbir şey okuyamaz.',
                        source: 'app/Domain/Authorization/RolePermissions.php',
                    ),
                    new BlockEntry(
                        term: 'İki kişi aynı siparişi onaylayamaz',
                        text: 'Durum değişikliği, siparişin hâlâ bulunduğu yerde olması koşuluyla yazılır. İkinci garsona onayın işlediği değil, siparişin şu an ne durumda olduğu söylenir.',
                        source: 'app/Infrastructure/Ordering/Persistence/EloquentOrderRepository.php',
                    ),
                    new BlockEntry(
                        term: 'Silinmeyen bir geçmiş',
                        text: 'Her sipariş, içinde ne olduğu, başına ne geldiği, ne zaman olduğu ve reddedildiyse sebebiyle birlikte kalır. Kayıttan bir akşamı çıkarmanın bir yolu yoktur.',
                        source: 'app/Http/Controllers/Ordering/ListOrderHistoryController.php',
                    ),
                    new BlockEntry(
                        term: 'Masa başına açık sipariş tavanı',
                        text: 'Bir masa aynı anda beş açık sipariş tutabilir. Kapanmış siparişler sayılmaz, dolayısıyla bütün akşam yemek yiyen bir masa sipariş vermeye devam edebilir.',
                        source: 'app/Http/Controllers/Ordering/StoreGuestOrderController.php',
                    ),
                    new BlockEntry(
                        term: 'Panolar en son ne zaman güncellendiğini söyler',
                        text: 'Kuyruk ve mutfak panosu on saniyede bir tazelenir ve son başarılı tazelemenin anını, ekranın değil sunucunun saatiyle yazar.',
                        source: 'resources/js/components/workspace/pages/orders/useOrderFeed.ts',
                    ),
                    new BlockEntry(
                        term: 'Gönderilen siparişler raporda sayılır',
                        text: 'Sipariş göndermek, okutmaların ve menü açılışlarının yanında bir olay olarak kaydedilir; böylece menü raporu bunu gösterebilir. Siparişin içeriği raporlama tablosuna kopyalanmaz.',
                        source: 'app/Domain/Analytics/AnalyticsEventType.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'Neye ihtiyacınız var', [
                    new BlockEntry(
                        term: 'Bir masaya ait bir kod',
                        text: 'Bir afiş, kapı kodu ya da kartvizit sipariş taşıyamaz, çünkü arkasında bir masa yoktur. Böyle bir okutma reddedilir ve misafirden kendi masasındaki kodu okutması istenir.',
                        source: 'app/Http/Controllers/Ordering/StoreGuestOrderController.php',
                    ),
                    new BlockEntry(
                        term: 'Hakkı taşıyan bir plan',
                        text: self::planSentence(),
                        source: 'database/seeders/PlanCatalogueSeeder.php',
                    ),
                    new BlockEntry(
                        term: 'Sizin açtığınız anahtar',
                        text: 'Bir şube, biri açana kadar sipariş almaz. Bu bilinçlidir: paneli hiç açmayan bir restoranın ocağında hiç iş belirmemelidir.',
                        source: 'database/migrations/2026_09_06_000700_add_ordering_switch_and_frozen_plan.php',
                    ),
                    new BlockEntry(
                        term: 'Yayınlanmış menüde tek para birimi',
                        text: 'Sepet bir toplam gösterir. Yayınlanmış menü para birimlerini karıştırıyorsa hiçbir toplam dürüst olamaz; bu yüzden sepet hiç çizilmez.',
                        source: 'app/Http/Controllers/QrDestination/ShowPublicMenuController.php',
                    ),
                    new BlockEntry(
                        term: 'Bir ekrana bakan biri',
                        text: 'Sipariş, bir kişi onaylayana kadar bekler. Siparişi açan planda sahip zaten bütün sipariş yetkilerini taşır; başlamak için ekip daveti gerekmez.',
                        source: 'app/Domain/Authorization/RolePermissions.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'Neyi yapmaz', [
                    new BlockEntry(
                        term: 'Ödeme almaz',
                        text: 'Hiçbir misafir Zabuno üzerinden ödeme yapmaz. Siparişin ödendi durumu, kart adımı ve adisyonu yoktur; para hâlâ masada, tam olarak eskisi gibi alınır.',
                        source: 'app/Domain/Ordering/OrderStatus.php',
                    ),
                    new BlockEntry(
                        term: 'Yazarkasanızla konuşmaz',
                        text: 'Bir satış noktası sistemine ya da muhasebe paketine bağlantı yoktur. Sipariş Zabuno içinde yaşar ve bir Zabuno ekranında okunur.',
                        source: 'routes/api/ordering.php',
                    ),
                    new BlockEntry(
                        term: 'Yalnız salondaki bir masadan',
                        text: 'Paket servis, teslimat, gel-al ve gelmeden önce sipariş yoktur. Sipariş göndermenin tek yolu bir masadaki kodu okutmaktır.',
                        source: 'routes/web.php',
                    ),
                    new BlockEntry(
                        term: 'Masa rezervasyonu yok',
                        text: 'Masa ayırtmak bu ürünün parçası değildir. Sipariş, zaten oturmuş bir misafirle başlar.',
                        source: 'app/Domain/Authorization/Permission.php',
                    ),
                    new BlockEntry(
                        term: 'Gönderdikten sonra misafirin ekranı yok',
                        text: 'Misafire siparişin alındığı söylenir ve telefonun payı orada biter. Siparişi izleyen bir sayfa, telefondan iptal etmenin bir yolu yoktur ve ret gerekçesi telefona itilmez, masada söylenir.',
                        source: 'resources/js/i18n/guest.ts',
                    ),
                    new BlockEntry(
                        term: 'Üründe not ve seçenek yok',
                        text: 'Sipariş satırı bir ürün ve bir adettir. "Soğansız", "iyi pişmiş" ve sos seçimi gönderilemez, çünkü menüde de ürünün seçeneği yoktur.',
                        source: 'database/migrations/2026_09_06_000600_create_orders_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Hiçbir şey basmaz ve hiçbir şey ötmez',
                        text: 'Mutfak fişi yazıcısı, ses ve anlık bildirim yoktur. Siparişler kendini tazeleyen bir ekranda belirir; dolayısıyla o ekranın birine görünüyor olması gerekir.',
                        source: 'resources/js/components/workspace/kitchen/KitchenMonitor.tsx',
                    ),
                    new BlockEntry(
                        term: 'Açık hesap ve hesap bölme yok',
                        text: 'Her siparişin kendi toplamı vardır. Ürün, bir masanın siparişlerini tek bir hesaba toplamaz ve bir hesabı misafirler arasında bölmez.',
                        source: 'app/Application/Ordering/Dto/OrderSummary.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'İşletmecilerin sipariş hakkında sorduğu sorular', [
                    new BlockEntry(
                        term: 'Misafirlerim menü üzerinden ödeme yapabilir mi?',
                        text: 'Hayır. Sipariş, talebi mutfağınıza taşır; ödeme bugün olduğu gibi masada alınır. Bu akışın hiçbir yerinde kart adımı yoktur.',
                    ),
                    new BlockEntry(
                        term: 'Sipariş doğrudan mutfağa mı gidiyor?',
                        text: 'Hayır ve mesele de bu. Bir garson onaylayana kadar servis kuyruğunda bekler. Mutfak panosu onaylanmamış bir siparişi hiç göstermez.',
                    ),
                    new BlockEntry(
                        term: 'Siparişi hiç açmazsam ne olur?',
                        text: 'Hiçbir şey değişmez. Misafirler menüyü eskisi gibi okur ve telefonlarında sepet çizilmez; böylece kimse okunmadan bekleyecek bir sipariş göndermeye davet edilmez.',
                    ),
                    new BlockEntry(
                        term: 'Misafir kapıdaki afişten sipariş verebilir mi?',
                        text: 'Hayır. Bir masaya bağlı olmayan kod reddedilir, çünkü yemeğin gönderileceği bir yer olmazdı. Misafirden kendi masasındaki kodu okutması istenir.',
                    ),
                    new BlockEntry(
                        term: 'Misafir gönderdikten sonra siparişi değiştirebilir ya da iptal edebilir mi?',
                        text: 'Telefondan hayır. Misafir personele söyler, garson da siparişi bir sebeple reddeder ve o sebep kayıtta kalır.',
                    ),
                    new BlockEntry(
                        term: 'Sipariş gelince mutfak ekranı ses çıkarır mı?',
                        text: 'Hayır. On saniyede bir tazelenir ve en son ne zaman güncellendiğini gösterir; böylece donmuş bir ekran, sessiz bir akşamdan ayırt edilebilir.',
                    ),
                    new BlockEntry(
                        term: 'Misafir soğansız bir yemek isteyebilir mi?',
                        text: 'Hayır. Sipariş satırı bir ürün ve bir adettir; not alanı ve seçenek listesi yoktur. Bu istekler hâlâ sesli olarak yapılır.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'Siparişi hangi planın açtığına bakın', [
                    new BlockEntry(
                        text: 'Menüyü okumak hiçbir şeye mal olmaz; masadan sipariş göndermek ücretli bir planın parçasıdır.',
                        href: '/pricing',
                        term: 'Planlara bakalım',
                    ),
                ]),

                new ContentBlock(BlockType::Related, 'İlgili sayfalar', [
                    new BlockEntry(text: 'Masalar ve karekodlar', pageKey: 'urun.masa-ve-qr-yonetimi'),
                    new BlockEntry(text: 'QR menü', pageKey: 'urun.qr-menu'),
                    new BlockEntry(text: 'Analitik', pageKey: 'urun.analitik'),
                ]),
            ],
        );
    }

    /**
     * Sipariş hakkını taşıyan planlar — KATALOGDAN, elle değil.
     *
     * Boş liste de bir cevaptır ve uydurulmaz.
     */
    private static function planSentence(): string
    {
        $names = [];

        foreach (PlanCatalogueSeeder::catalogue() as $plan) {
            if (in_array(Entitlement::OrderingBasic->value, $plan['entitlements'], true)) {
                $names[] = $plan['name'];
            }
        }

        if ($names === []) {
            return 'Katalogdaki hiçbir plan bugün masadan siparişi açmıyor, dolayısıyla hiçbir misafire sepet çizilmez.';
        }

        return 'Masadan siparişi şu planlar açar: '.self::joined($names)
            .'. Bunlardan biri olmadan sepet hiç çizilmez ve elle gönderilen bir sipariş, eksik hakkın adıyla reddedilir.';
    }

    /**
     * Virgülle ayrılmış liste, sonunda "ve".
     *
     * @param  list<string>  $names
     */
    private static function joined(array $names): string
    {
        $last = array_pop($names);

        if ($last === null) {
            return '';
        }

        return $names === [] ? $last : implode(', ', $names).' ve '.$last;
    }
}
