<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal\Documents\Turkish;

use App\Domain\Legal\LegalDocument;
use App\Domain\Legal\LegalSection;

final class DistanceSalesAgreement
{
    public static function document(): LegalDocument
    {
        return new LegalDocument(
            language: 'tr',
            key: 'distance-sales',
            version: '0.2',
            effectiveDate: '2026-09-07',
            title: 'Mesafeli Satış Sözleşmesi',
            summary: 'Bir işletme ücretli Zabuno planına abone olduğunda elektronik ortamda kurulan sözleşme: satıcının kim olduğu, neyin satıldığı, bedeli, ne zaman başladığı ve hangi durumda cayılabileceği.',
            sections: [
                new LegalSection('Taraflar', [
                    'Satıcı: {company.legal_name}, {company.address}, MERSİS numarası {company.mersis}, vergi dairesi {company.tax_office}, vergi numarası {company.tax_number}, e-posta {company.email}, telefon {company.phone}.',
                    'Alıcı: siparişin verildiği Zabuno hesabının sahibi; hesaptaki ad ve e-posta adresi ile ödeme sırasında girilen faturalandırma bilgileriyle tanımlanır.',
                ]),
                new LegalSection('Sözleşmenin konusu', [
                    'Bu sözleşme, ödeme sırasında seçilen Zabuno planına aboneliği kapsar; satış ve ifa tamamen internet üzerinden yapılır. Alıcının siparişi onayladığı anda elektronik olarak kurulur.',
                    'Alıcının ödemeden önce onayladığı Ön Bilgilendirme Formu, Teslimat ve İfa Koşulları ve İptal ve İade Politikası bu sözleşmenin parçasıdır. Bu sözleşmeyle bu metinlerden biri aynı konuyu düzenliyorsa bu sözleşme uygulanır.',
                ]),
                new LegalSection('Hizmetin temel nitelikleri', [
                    'Zabuno, işletmenin menüsünü tuttuğu ve kalıcı QR kod üzerinden yayımladığı çalışma alanına aboneliktir. Çalışma alanı işletmeyi, şubelerini, menü kategorilerini ve yemeklerini, fiyatlarını ve görsellerini tutar; yayımlama, misafirin basılı kodu tarayarak ulaştığı bir sayfa üretir ve menüde daha sonra yapılan değişiklik kod yeniden basılmadan aynı sayfada gösterilir.',
                    'Seçilen plana bağlı olarak çalışma alanı ayrıca masa ve QR yönetimi, misafir siparişi, misafir puanlaması, kendi izinlerine sahip ekip üyeleri, özel marka görünümü, raporlama ve sonucu bir kişinin onayladığı fotoğraftan menü aktarımı içerebilir. Seçilen plana ait özellikler, sipariş anında Fiyatlar sayfasında ve sipariş özetinde gösterilenlerdir; orada listelenmeyen özellik satılmaz.',
                    'Hizmet için alıcının sağlayacağı cihaz ve internet bağlantısı gerekir. Alıcıya kurması için yazılım teslim edilmez.',
                ]),
                new LegalSection('Fiyat, vergiler ve ek masraflar', [
                    'Fiyat, ödeme anında sipariş özetinde gösterilen tutardır; para birimi ve vergiler o özette belirtildiği gibidir. Bu tutar toplam bedeldir: fiziksel gönderim ve başka ücret bulunmadığı için teslimat, paketleme veya ayrı hizmet bedeli eklenmez.',
                    'Sitedeki fiyatlar sonradan değişebilir. Her iki tarafı bağlayan fiyat, bedeli ödenen dönem için alıcının sipariş özetindeki fiyattır.',
                ]),
                new LegalSection('Kabul edilen ödeme yöntemleri', [
                    'Ödeme, ödeme hizmeti sağlayıcısı Iyzico üzerinden kartla yapılır. Başka yöntem sunulmaz: satıcı abonelik için havale, nakit, çek veya kapıda ödeme kabul etmez.',
                    'Kart bilgileri bu siteye değil ödeme hizmeti sağlayıcısının kendi sayfalarına girilir; satıcı kart numarasını görmez ve saklamaz. Kartı veren kuruluşun istediği ek doğrulama adımları sağlayıcının sayfalarında gerçekleşir.',
                ]),
                new LegalSection('Uzaktan iletişim aracının kullanım bedeli', [
                    'Bu sözleşme bu web sitesi üzerinden kurulur. Alıcıdan siteyi kullanmak için ek ücret alınmaz; kendi internet bağlantısı, kendi sağlayıcısı tarafından olağan tarifesi üzerinden ücretlendirilir.',
                ]),
                new LegalSection('Hizmetin ifası', [
                    'Hizmet elektronik ortamda teslim edilir; burada "teslimat", ücretli planın alıcının çalışma alanında kullanılabilir hale gelmesidir. Bu, ödeme hizmeti sağlayıcısı ödemeyi onayladığında gerçekleşir. Fiziksel teslimat, gönderim veya ayrı etkinleştirme adımı yoktur; Teslimat ve İfa Koşulları bunu ayrıntılı açıklar.',
                ]),
                new LegalSection('Süre, yenileme ve sona erme', [
                    'Sözleşme sipariş özetindeki abonelik dönemi boyunca geçerlidir; her ödeme aboneliğin bitişini bu dönemlerden biri kadar ileri taşır. Dönem uzunluğu, sipariş anındaki sipariş özetinde gösterilendir.',
                    'Alıcı sonraki dönemleri ödemeye devam ettiği sürece sözleşme sürer; alıcı iptal ederse son ödenen dönemin sonunda sona erer. İptal, bedeli ödenmiş dönemi kısaltmaz. İptalin nasıl yapılacağı ve sonuçları İptal ve İade Politikasında düzenlenir.',
                ]),
                new LegalSection('Cayma hakkı', [
                    'Alıcı, 6502 sayılı Tüketicinin Korunması Hakkında Kanun kapsamında tüketiciyse Mesafeli Sözleşmeler Yönetmeliğinin öngördüğü şekilde sözleşmenin kurulmasından itibaren on dört gün içinde gerekçe göstermeden ve ceza ödemeden cayabilir. Fiziksel gönderim yapılmadığı için cayma nedeniyle iade gönderim masrafı doğmaz.',
                    'Cayma hakkını kullanmak için bu süre içinde hesabınızdaki e-posta adresinden, sitedeki iletişim formuyla veya {company.email} adresine e-postayla satıcıya yazın ve caymak istediğinizi belirtin. Süre içinde gönderilen bildirim zamanındadır. Satıcı alındığını yazılı olarak teyit eder; geçerli caymada bedelin tamamı ödeme hizmeti sağlayıcısı üzerinden satın almada kullanılan karta iade edilir.',
                    'Ticari veya mesleki amaçlarla abone olan alıcılar tüketici değildir ve bu yasal hakka sahip değildir. Bunların sözleşmeyi sona erdirmesi İptal ve İade Politikasına tabidir.',
                ]),
                new LegalSection('Cayma hakkının kullanılamadığı durumlar', [
                    'Mesafeli Sözleşmeler Yönetmeliği cayma hakkının uygulanmadığı sözleşmeleri listeler. Bunlardan ikisi bu sözleşmeye uygulanabilir; alıcı sipariş vermeden önce bunları okumalıdır.',
                    'Birincisi: elektronik ortamda anında ifa edilen hizmetler. Bu abonelik elektronik ortamda ifa edilir ve ifası ödeme onaylanır onaylanmaz başlar.',
                    'İkincisi: cayma süresi dolmadan önce tüketicinin onayıyla ifasına başlanan hizmetler. Alıcı planın hemen başlamasını istediğinde ifa başlayınca cayma hakkı sona erer.',
                    'Satıcı, alıcıdan cayma hakkından vazgeçmesini istemez; bu sözleşme de böyle bir vazgeçme içermez. Alıcının ödeme sırasında onayladığı şey ifanın hemen başlamasıdır; bunun cayma hakkı üzerindeki etkisi yalnızca Yönetmeliğin öngördüğü etkidir.',
                ]),
                new LegalSection('Hemen ifaya açık onay', [
                    'Ödeme sırasında alıcıdan, önceden işaretlenmeyen ayrı bir kutuyla, ödeme onaylanır onaylanmaz hizmetin başlamasını ve bunun cayma hakkı bakımından anlamını yukarıdaki bölümde okuduğunu onaylaması istenir. Bu onay olmadan sipariş verilemez ve sessizlik onay sayılmaz.',
                    'Bu onay alıcının hesabıyla birlikte kaydedilir: hangi belge, hangi sürüm, hangi tarih ve saat, hangi ağ adresi. Alıcı bu metnin kaydedilen sürümünü bu sayfadan istediği zaman okuyabilir.',
                ]),
                new LegalSection('İptal ve iadeler', [
                    'İptal, mevcut abonelik dönemine etkisi ve ücretlerin iade edildiği durumlar, bu sözleşmenin parçası olan İptal ve İade Politikasında düzenlenir. İadeler ödeme hizmeti sağlayıcısı üzerinden satın almada kullanılan ödeme yöntemine yapılır.',
                ]),
                new LegalSection('Alıcının yükümlülükleri', [
                    'Alıcı, ödeme sırasında girdiği faturalandırma bilgilerinin doğru olduğunu ve orada belirtilen işletme adına satın almaya yetkili olduğunu teyit eder. Hizmetin kullanımı Hizmet Koşullarına tabidir.',
                ]),
                new LegalSection('Satıcının yükümlülükleri', [
                    'Satıcı, ücretli dönem boyunca sipariş özetinde açıklanan planı sağlar ve planın sunduklarını önemli ölçüde azaltacak değişikliği önceden alıcıya bildirir.',
                ]),
                new LegalSection('Şikâyet ve itirazlar', [
                    'Bu sözleşmeyle ilgili şikâyetler sitedeki iletişim formundan veya {company.email} adresine e-postayla satıcıya gönderilir. Satıcı, hesaptaki e-posta adresine yazılı yanıt verir.',
                    'Tüketici olan alıcı, kendi yerleşim yerindeki veya işlemin yapıldığı yerdeki tüketici hakem heyetine ya da tüketici mahkemesine de başvurabilir. Başvuruyu hangisinin inceleyeceği o yıl için kanunla belirlenen parasal sınırlara bağlıdır; sınırlar her yıl duyurulduğu için burada tekrarlanmaz.',
                ]),
                new LegalSection('Uygulanacak hukuk ve yetki', [
                    'Bu sözleşmeye Türkiye Cumhuriyeti hukuku uygulanır. Yukarıda açıklanan tüketici başvuru yolu dışındaki uyuşmazlıklarda satıcının kayıtlı merkezinin bulunduğu yerdeki mahkemeler ve icra daireleri yetkilidir.',
                ]),
                new LegalSection('Yürürlük ve sözleşmenin kaydı', [
                    'Bu sözleşme alıcı siparişi onaylayıp ödeme tamamlandığında yürürlüğe girer. Alıcının kabul ettiği sürüm, kabul tarihi ve saatiyle birlikte hesabına kaydedilir; her sürümün metni, alıcının kabul ettiği metni okuyabilmesi için bu sitede erişilebilir kalır.',
                ]),
                new LegalSection('Bu sözleşmenin dili', [
                    'Bu metin, İngilizce kaynak metnin Türkçe çevirisidir. Kabul edilmiş olarak kaydedilen metin İngilizce metindir. İlgili sürüm numarası ve yürürlük tarihi bu sayfanın üstünde gösterilir.',
                ]),
            ],
            requiresSellerIdentity: true,
        );
    }
}
