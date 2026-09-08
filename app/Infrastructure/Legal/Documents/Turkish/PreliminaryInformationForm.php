<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal\Documents\Turkish;

use App\Domain\Legal\LegalDocument;
use App\Domain\Legal\LegalSection;

final class PreliminaryInformationForm
{
    public static function document(): LegalDocument
    {
        return new LegalDocument(
            language: 'tr',
            key: 'pre-information',
            version: '0.2',
            effectiveDate: '2026-09-07',
            title: 'Ön Bilgilendirme Formu',
            summary: 'Zabuno planı için ödemeden önce gösterilen bilgiler: satıcının kim olduğu, ne satın aldığınız, bedeli, nasıl teslim edildiği, süresi ve ne zaman cayabileceğiniz.',
            sections: [
                new LegalSection('Satıcı', [
                    '{company.legal_name}, {company.address}. MERSİS numarası {company.mersis}; vergi dairesi {company.tax_office}; vergi numarası {company.tax_number}. E-posta {company.email}; telefon {company.phone}.',
                ]),
                new LegalSection('Hizmet', [
                    'Ödeme sırasında seçtiğiniz Zabuno planına abonelik: işletmenizin menüsünü tuttuğu ve kalıcı QR kod üzerinden yayımladığı çalışma alanı; böylece fiyat değişikliği kodu yeniden basmanızı gerektirmez.',
                    'Plana bağlı olarak çalışma alanı ayrıca masa ve QR yönetimi, misafir siparişi, misafir puanlaması, kendi izinlerine sahip ekip üyeleri, özel marka görünümü, raporlama ve sonucu bir kişinin onayladığı fotoğraftan menü aktarımı içerebilir. Satın aldığınız özellikler sipariş anında Fiyatlar sayfasında ve sipariş özetinde gösterilenlerdir; orada listelenmeyen özellik satılmaz. Hizmet web tarayıcısından kullanılır; cihazınıza bir şey kurulmaz.',
                ]),
                new LegalSection('Fiyat, vergiler ve ek masraflar', [
                    'Gösterilen vergiler dahil toplam fiyat, ödeme anında sipariş özetindeki tutardır ve orada gösterilen para birimindedir. Buna teslimat, paketleme veya ayrı hizmet bedeli eklenmez.',
                ]),
                new LegalSection('Kabul edilen ödeme yöntemleri', [
                    'Ödeme, ödeme hizmeti sağlayıcısı Iyzico üzerinden kartla yapılır. Havale, nakit veya kapıda ödeme dahil başka yöntem sunulmaz. Kart bilgileri sağlayıcının kendi sayfalarına girilir; satıcı bunları görmez ve saklamaz.',
                    'Ücretler, sipariş özetinizdeki dönem ve yenileme koşullarına göre her abonelik dönemi için peşin alınır.',
                ]),
                new LegalSection('Bu web sitesini kullanmanın bedeli', [
                    'Bu web sitesinden sipariş vermeniz için ek ücret alınmaz. Kendi internet bağlantınız, kendi sağlayıcınız tarafından olağan tarifeniz üzerinden ücretlendirilir.',
                ]),
                new LegalSection('Teslimat ve hizmetin başlaması', [
                    'Hizmet elektronik olarak teslim edilir; ödeme hizmeti sağlayıcısı ödemeyi onaylar onaylamaz çalışma alanınızda başlar. Fiziksel gönderim ve ayrı etkinleştirme adımı yoktur. Teslimat ve İfa Koşulları bunu ayrıntılı açıklar.',
                ]),
                new LegalSection('Süre ve yenileme', [
                    'Abonelik sipariş özetindeki dönem boyunca sürer; her ödeme bitişini bu dönemlerden biri kadar ileri taşır. Sonraki dönemleri ödediğiniz sürece devam eder; iptal ederseniz son ödediğiniz dönemin sonunda sona erer. İptal, bedelini ödediğiniz dönemi kısaltmaz; nasıl iptal edileceği İptal ve İade Politikasında açıklanır.',
                ]),
                new LegalSection('Cayma hakkı', [
                    '6502 sayılı Kanun kapsamında tüketici olarak satın alıyorsanız sözleşmenin kurulmasından itibaren on dört gün içinde gerekçe göstermeden ve ceza ödemeden cayabilirsiniz. Fiziksel gönderim yapılmadığı için geri gönderme masrafınız olmaz.',
                    'Caymak için bu süre içinde hesabınızdaki e-posta adresinden, sitedeki iletişim formuyla veya {company.email} adresine e-postayla satıcıya yazın ve caymak istediğinizi belirtin. Geçerli caymada bedelin tamamı ödeme hizmeti sağlayıcısı üzerinden kullanılan karta iade edilir.',
                    'Ticari veya mesleki amaçlarla satın alıyorsanız tüketici değilsiniz ve bu yasal hak size uygulanmaz; sözleşmenin sona erdirilmesi bu durumda İptal ve İade Politikasına tabidir.',
                ]),
                new LegalSection('Cayma hakkının kullanılamadığı durumlar', [
                    'Mesafeli Sözleşmeler Yönetmeliği cayma hakkının uygulanmadığı sözleşmeleri listeler. Bunlardan ikisi burada uygulanabilir: elektronik ortamda anında ifa edilen hizmetler ve cayma süresi dolmadan onayınızla ifasına başlanan hizmetler.',
                    'Bu abonelik elektronik ortamda ifa edilir; ifası ödemeniz onaylanır onaylanmaz başlar. Talebiniz üzerine ifa başladığında Yönetmeliğin öngördüğü şekilde cayma hakkı sona erer.',
                ]),
                new LegalSection('Hemen ifaya açık onayınız', [
                    'Ödeme sırasında, önceden işaretlenmeyen ayrı kutuyla, ödeme onaylanır onaylanmaz hizmetin başlamasını ve bunun cayma hakkınız bakımından anlamını okuduğunuzu onaylamanız istenir. Bu onay olmadan sipariş veremezsiniz; kutuyu boş bırakmak onay sayılmaz.',
                    'Bu onay hesabınızla birlikte kaydedilir: hangi belge, hangi sürüm, hangi tarih ve saat, hangi ağ adresi.',
                ]),
                new LegalSection('Şikâyet ve itirazlar', [
                    'Şikâyetlerinizi sitedeki iletişim formundan veya {company.email} adresine gönderebilirsiniz; satıcı hesabınızdaki e-posta adresine yazılı yanıt verir.',
                    'Tüketici olarak kendi yerleşim yerinizdeki veya işlemin yapıldığı yerdeki tüketici hakem heyetine ya da tüketici mahkemesine de başvurabilirsiniz. Başvuruyu hangisinin inceleyeceği o yıl için kanunla belirlenen parasal sınırlara bağlıdır; sınırlar her yıl duyurulduğu için burada tekrarlanmaz.',
                ]),
                new LegalSection('Bu formun geçerliliği', [
                    'Bu form size ödemeden önce gösterilir ve ödeme sırasında elektronik olarak onaylarsınız. O anda verdiğiniz siparişe uygulanır. Sitedeki fiyatlar sonradan değişebilir; iki tarafı bağlayan fiyat sipariş özetinizdeki fiyattır. Onayladığınız sürüm, tarih ve saatle birlikte hesabınıza kaydedilir ve metin bu sitede erişilebilir kalır.',
                ]),
                new LegalSection('Bu formun dili', [
                    'Bu metin, İngilizce kaynak metnin Türkçe çevirisidir. Onaylanmış olarak kaydedilen metin İngilizce metindir. İlgili sürüm numarası ve yürürlük tarihi bu sayfanın üstünde gösterilir.',
                ]),
            ],
            requiresSellerIdentity: true,
        );
    }
}
