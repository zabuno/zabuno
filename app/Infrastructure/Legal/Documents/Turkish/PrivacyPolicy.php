<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal\Documents\Turkish;

use App\Domain\Legal\LegalDocument;
use App\Domain\Legal\LegalSection;

final class PrivacyPolicy
{
    public static function document(): LegalDocument
    {
        return new LegalDocument(
            language: 'tr',
            key: 'privacy',
            version: '0.3',
            effectiveDate: '2026-09-09',
            title: 'Gizlilik Politikası',
            summary: 'Zabuno\'nun hangi kişisel verileri topladığı, bunların neden toplandığı, kimlere iletildiği ve ne kadar süre saklandığı.',
            sections: [
                new LegalSection('Verilerinizden kim sorumlu?', [
                    'Veri sorumlusu {company.legal_name}, {company.address} adresindedir. Kişisel verilerle ilgili olarak bize {company.email} üzerinden ulaşabilirsiniz.',
                    'Türkiye\'deki kişiler için bu sitede yayımlanan Kişisel Verilerin Korunması Aydınlatma Metni (KVKK), 6698 sayılı Kanunun gerektirdiği bildirimdir. Bu Gizlilik Politikası aynı işleme faaliyetlerini sade bir dille açıklar.',
                ]),
                new LegalSection('Hesap oluşturduğunuzda topladığımız veriler', [
                    'Adınız, e-posta adresiniz ve şifreniz. Şifre yalnızca kriptografik özet olarak saklanır; şifrenizi okuyamayız.',
                    'Hizmet Koşullarını kabul ettiğiniz an ve ayrıca bu Gizlilik Politikasını okuduğunuzu beyan ettiğiniz an; her metnin sürümü, o andaki ağ adresiniz ve tarayıcı tanımlayıcınız. Bu ikisi defterde ayrı ayrı tutulur: Hizmet Koşulları KABUL EDİLİR, bu politika ise yalnız OKUNDUĞU BEYAN EDİLİR. Okuduğunuzu beyan etmeniz bir açık rıza değildir ve açık rıza olarak istenmez. İsteğe bağlı ticari ileti kutusunu işaretlerseniz o onay da ayrıca kaydedilir.',
                    'E-posta adresinizin doğrulanıp doğrulanmadığı ve oturumunuz açıkken oturum verileri (ağ adresi, tarayıcı tanımlayıcısı).',
                ]),
                new LegalSection('Çalışma alanınızı kullanırken girdiğiniz veriler', [
                    'Girmeyi seçtiğiniz işletme bilgileri (ad, adres, iletişim e-postası ve telefonu, çalışma saatleri), menünün kendisi (kategoriler, ürünler, fiyatlar, alerjenler, açıklamalar), yüklediğiniz görseller ve davet ettiğiniz ekip üyelerinin e-posta adresleri ile rolleri.',
                    'İletişim formundan gönderdiğiniz mesajlar: adınız, e-posta adresiniz ve mesajınız.',
                ]),
                new LegalSection('Menünüzü açan misafirlerle ilgili veriler', [
                    'Bir misafir yayımlanmış menüyü açtığında hangi menünün açıldığını, hangi ürünlerin görüntülendiğini veya arandığını ve o menü için sipariş ya da puanlama açıksa siparişin veya puanlamanın kendisini kaydederiz. Misafirlerden ad veya hesap istenmez.',
                    'Ziyaretçileri kimliklerini belirlemeden saymak için ağ adresi, tarayıcı tanımlayıcısı ve tarihten takma kimlikli bir ziyaretçi anahtarı türetiriz. Bu anahtar tekrar adrese dönüştürülemez ve her gün değişir.',
                    'Misafirin menü için seçtiği dil, zabuno_guest_locale adlı çerezde tutulur.',
                ]),
                new LegalSection('Ölçüm araçları ve çerezler', [
                    'Ziyaretçi çerez tercih çubuğunda açıkça kabul etmeden hiçbir üçüncü taraf ölçüm aracı yüklemeyiz. Tercih, zabuno_measurement_consent adlı çerezde bir yıl saklanır ve Çerez Politikası sayfasından istediğiniz zaman değiştirilebilir.',
                    'Sitenin yerleştirdiği tüm çerezlerin amaçları ve ömürleriyle birlikte listesi Çerez Politikasındadır.',
                ]),
                new LegalSection('Verileri neden işliyoruz?', [
                    'Sizinle yaptığımız sözleşme kapsamında kaydolduğunuz hizmeti sağlamak için: çalışma alanınızı işletmek, menünüzü yayımlamak, misafirlerinize sunmak, doğrulama ve bildirim e-postaları göndermek.',
                    'Hizmeti güvenli tutmak için: kötüye kullanımı tespit etmek, istek hızını sınırlamak, yüklenen dosyaları taramak.',
                    'Ticari ve vergisel kayıtları tutmak ve onay verildiğini kanıtlamak gibi yasal yükümlülükleri yerine getirmek için.',
                    'Yalnızca onayınızla: ticari elektronik ileti göndermek ve ölçüm araçlarını yüklemek için.',
                    'Hizmetin nasıl kullanıldığını anlamaya yönelik meşru menfaatimiz doğrultusunda, bir kişinin kimliğini belirlemeyen takma kimlikli veriler kullanmak için.',
                ]),
                new LegalSection('Veriler kimlere iletilir?', [
                    'Bizim adımıza ve talimatlarımız doğrultusunda veri işleyen hizmet sağlayıcıları kullanırız: doğrulama, davet ve bildirim e-postaları için e-posta iletim sağlayıcısı Mailgun; bir plan için ödeme yaptığınızda ödeme hizmeti sağlayıcısı Iyzico — kart bilgileri sağlayıcının kendi sistemlerine girilir ve bize hiçbir zaman ulaşmaz; yapay zekâ destekli özellikler etkinse, ilgili özellik için gönderdiğiniz menü metnini veya görsellerini alan, hizmet için yapılandırılmış yapay zekâ sağlayıcısı.',
                    'Google Tag Manager üzerinden yüklenen ölçüm araçlarına yalnızca kabul etmenizden sonra veri iletilir; etkin araçlar Çerez Politikasında listelenir.',
                    'Hizmetin kendisi Almanya\'daki bir barındırma sağlayıcısından kiralanan sunucularda çalışır; dolayısıyla burada açıklanan veriler Türkiye dışında saklanır. Yedek kopyalar aynı sunucularda tutulur.',
                    'Kişisel verileri satmayız ve üçüncü taraflara kendi amaçları için aktarmayız. Yetkili makamlara yalnızca kanunun gerektirdiği durumlarda veri açıklarız.',
                ]),
                new LegalSection('Verileri ne kadar süre saklıyoruz?', [
                    'Hesap ve çalışma alanı verileri hesap var olduğu sürece saklanır. Çalışma alanının sahibi verilerinin silinmesini istediğinde, yasal olarak saklamak zorunda olmadığımız verileri kaldırırız.',
                    'Bu talep aynı gün yerine getirilmez. Geri alınabilmesi için bir bekleme süresi geçer; çalışma alanı ayarları işlemin yapılacağı kesin tarihi gösterir ve o tarihe kadar tek bir düğmeyle iptal edilebilir. Silme talebi, bu süre içinde çalışma alanını kapatmaz.',
                    'Kaldırmadıklarımız ve nedenleri: ticari ve vergisel kayıt oldukları için çalışma alanına düzenlenen faturalar ve bunların arkasındaki muhasebe kayıtları; aynı nedenle bu faturaların dayandığı ödeme kayıtları; kanıtlayabilmemiz gerektiği için verilen onayların kayıtları. Çalışma alanı ayarları bunların her birini adıyla listeler ve neden saklandığını açıklar; böylece kimse "her şey" sözüne güvenmek zorunda kalmaz.',
                    'Silinen verinin bir kopyası, silme işleminden sonra yedekler yenilenene kadar bir süre yedek kopyalarda kalabilir.',
                    'Onay kayıtları, onay verildiğini kanıtlayabilmemizi kanunun gerektirdiği süre boyunca saklanır.',
                    'Takma kimlikli misafir olayları ve sunucu günlükleri, hizmeti işletmek ve iyileştirmek için saklanır ve bir kişiyle yeniden ilişkilendirilmez.',
                ]),
                new LegalSection('Haklarınız', [
                    'Hakkınızda hangi verileri tuttuğumuzu sorabilir, düzeltilmesini veya silinmesini isteyebilir, meşru menfaatimize dayalı işlemeye itiraz edebilir ve verdiğiniz onayı geri çekebilirsiniz. Onayın geri çekilmesi, daha önce yapılan işleme faaliyetlerini etkilemez.',
                    'Menünüzü istediğiniz zaman çalışma alanınızdan CSV dosyası olarak indirebilirsiniz; bizden istemeniz gerekmez.',
                    'Bir çalışma alanının sahibiyseniz Ayarlar bölümünden o çalışma alanının tuttuğu her şeyi tek arşiv olarak da indirebilirsiniz. Arşiv aynı satırları iki biçimde taşır: başka bir sisteme aktarılabilecek makine tarafından okunabilir biçimde ve bir kişinin açıp okuyabileceği elektronik tablo dosyaları olarak. Ayrıca nelerin dahil olduğunu, nelerin olmadığını ve nedenlerini açıklayan düz metin notu içerir. Boyut nedeniyle yüklenen dosyaların kendileri arşivde bulunmaz; ayrıntıları bulunur ve asılları Medya ekranından tek tek indirilebilir.',
                    'Çalışma alanı verilerinin silinmesini aynı ekrandan ister ve bu tür her talebin sonucunu orada görürsünüz. Diğer tüm konular için iletişim formundan veya {company.email} adresinden bize yazın.',
                ]),
                new LegalSection('Güvenlik', [
                    'Şifreler özetlenir, bağlantılar şifrelenir, yüklenen dosyalar yayımlanmadan önce taranır ve çalışma alanındaki erişim rollerle denetlenir. Hiçbir saklama veya iletim yöntemi kusursuz güvenli değildir; bir sorun fark ederseniz bize bildirin.',
                ]),
                new LegalSection('Bu politikadaki değişiklikler', [
                    'Bu politikayı güncelleyebiliriz. Sayfanın üstündeki sürüm numarası ve yürürlük tarihi güncel metni tanımlar. Hesabınızı oluştururken yürürlükte olan ve okuduğunuzu beyan ettiğiniz sürüm, hesabınızla birlikte kaydedilir.',
                ]),
            ],
        );
    }
}
