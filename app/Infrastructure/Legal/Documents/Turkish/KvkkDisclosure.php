<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal\Documents\Turkish;

use App\Domain\Legal\LegalDocument;
use App\Domain\Legal\LegalSection;

final class KvkkDisclosure
{
    public static function document(): LegalDocument
    {
        return new LegalDocument(
            language: 'tr',
            key: 'kvkk',
            version: '0.2',
            effectiveDate: '2026-09-08',
            title: 'Kişisel Verilerin Korunması Aydınlatma Metni (KVKK)',
            summary: '6698 sayılı Kişisel Verilerin Korunması Kanununun 10. maddesi uyarınca aydınlatma: verilerinizi kimin, neden ve hangi hukuki sebeple işlediği ve haklarınız.',
            sections: [
                new LegalSection('Veri sorumlusu', [
                    'Bu aydınlatma, kayıtlı adresi {company.address}, MERSİS numarası {company.mersis} olan {company.legal_name} tarafından, 6698 sayılı Kişisel Verilerin Korunması Kanunu ("Kanun") kapsamında veri sorumlusu sıfatıyla yapılmaktadır.',
                ]),
                new LegalSection('İşlediğimiz kişisel veriler', [
                    'Kimlik ve iletişim verileri: hesap oluştururken verdiğiniz ad ve e-posta adresi ile çalışma alanınıza girmeyi seçtiğiniz işletme iletişim bilgileri.',
                    'İşlem güvenliği verileri: hizmeti kullanırken ağ adresi, tarayıcı tanımlayıcısı ve oturum kayıtları ile bir hukuki metni kabul ettiğiniz andaki aynı veriler.',
                    'Müşteri işlem verileri: abone olduğunuz plan ve ödeme hizmeti sağlayıcısının döndürdüğü ödeme onayları. Kart bilgileri sağlayıcının sistemlerine girilir ve tarafımızca işlenmez.',
                    'Görsel veriler: menünüze yüklediğiniz görseller.',
                    'Pazarlama verileri: yalnızca vermeniz halinde ticari elektronik ileti tercihiniz.',
                    'Misafir verileri: yayımlanmış menülerdeki takma kimlikli ziyaret, sipariş ve puanlama olayları; bir kişinin kimliğini belirlemeyen ve her gün değişen ziyaretçi anahtarıyla tutulur.',
                ]),
                new LegalSection('İşleme amaçları', [
                    'Hizmet sözleşmesini yerine getirmek: çalışma alanınızı oluşturmak ve işletmek, menünüzü yayımlamak, misafirlere sunmak, doğrulama, davet ve bildirim e-postaları göndermek.',
                    'Hesapları ve hizmeti güvenli tutmak, kötüye kullanımı tespit etmek ve önlemek.',
                    'Ticari ve vergisel kayıt tutma ve onay verildiğini kanıtlama dahil yasal yükümlülüklerimizi yerine getirmek.',
                    'Her biri yalnızca açık rızanızla olmak üzere, ticari elektronik ileti göndermek ve ölçüm araçlarını yüklemek.',
                ]),
                new LegalSection('Toplama yöntemi ve hukuki sebep', [
                    'Kişisel veriler kayıt formu, çalışma alanı ekranları, yayımlanmış menü sayfaları, iletişim formu ve ödeme adımı üzerinden elektronik olarak toplanır.',
                    'Hukuki sebepler Kanunun 5. maddesinde belirtilenlerdir: tarafı olduğunuz sözleşmenin ifası için gerekli işleme (madde 5/2-c); hukuki yükümlülüğe uyum (madde 5/2-ç); temel hak ve özgürlüklerinize zarar vermemek kaydıyla meşru menfaatlerimiz için gerekli işleme (madde 5/2-f); ticari iletiler ve ölçüm araçları için ise açık rızanız (madde 5/1).',
                ]),
                new LegalSection('Veriler kimlere ve neden aktarılır?', [
                    'Yukarıdaki amaçlar için gerekli ölçüde, talimatlarımızla hareket eden veri işleyenlere aktarım yapılır: size gönderdiğimiz e-postalar için e-posta iletim sağlayıcısı Mailgun; ödemeler için ödeme hizmeti sağlayıcısı Iyzico; yapay zekâ destekli özellik kullandığınızda gönderdiğiniz menü metni veya görselleri için hizmette yapılandırılmış yapay zekâ sağlayıcısı; onayınızdan sonra Google Tag Manager üzerinden yüklenen ölçüm araçları.',
                    'Bir sağlayıcının sistemlerini Türkiye dışında işletmesi halinde yurt dışına aktarım, Kanunun 9. maddesindeki şartlara göre yapılır. Kamu makamlarına yalnızca kanunun gerektirdiği durumlarda veri açıklanır.',
                    'Hizmetin çalıştığı sunucular da Türkiye dışında, Almanya\'daki bir barındırma sağlayıcısında bulunur. Bu, yukarıdaki verilerin yalnızca bir sağlayıcı devreye girdiğinde değil sürekli olarak yurt dışında saklandığı anlamına gelir; yurt dışına aktarım Kanunun 9. maddesindeki şartlara göre yapılır.',
                    'Yedek kopyalar aynı sunucularda tutulur. Dolayısıyla yedek, canlı verilerle aynı ülkede ve aynı koşullarda saklanır.',
                ]),
                new LegalSection('Saklama', [
                    'Hesap ve çalışma alanı verileri hesap var olduğu sürece, sonrasında ise yalnızca kanunun gerektirdiği süre boyunca saklanır. Onay kayıtları, onayı kanıtlayabilmemiz gereken süre boyunca tutulur. Takma kimlikli misafir olayları bir kişiyle ilişkilendirilmez ve hizmeti işletmek ve iyileştirmek için saklanır.',
                    'Çalışma alanının sahibi verilerinin silinmesini istediğinde talep bir bekleme süresinden sonra yerine getirilir; çalışma alanı ayarları kesin tarihi gösterir. O tarihe kadar talep geri alınabilir ve hiçbir şey kaldırılmaz.',
                    'Kanunen saklamamız gereken kayıtlar bu taleple kaldırılmaz. Bunlar çalışma alanına düzenlenen faturalar, bunların arkasındaki muhasebe kayıtları, dayandıkları ödeme kayıtları ve verilmiş onayların kayıtlarıdır. Çalışma alanı ayarları her birini adıyla belirtir ve neden saklandığını açıklar.',
                    'Silinen verinin bir kopyası, silme işleminden sonra yedeklerin kendileri yenilenene kadar bir süre yedek kopyalarda kalabilir.',
                ]),
                new LegalSection('11. madde kapsamındaki haklarınız', [
                    'Kanunun 11. maddesi uyarınca kişisel verilerinizin işlenip işlenmediğini öğrenme; işlenmişse bilgi talep etme; işlenme amacını ve amacına uygun kullanılıp kullanılmadığını öğrenme; yurt içinde veya yurt dışında aktarıldığı üçüncü kişileri bilme; eksik veya yanlış işlenen verilerin düzeltilmesini isteme; 7. madde kapsamında silinmesini veya yok edilmesini isteme; düzeltme, silme veya yok etme işlemlerinin verilerin aktarıldığı üçüncü kişilere bildirilmesini isteme; münhasıran otomatik analizle ortaya çıkan aleyhinize sonuca itiraz etme; hukuka aykırı işleme nedeniyle uğradığınız zararın giderilmesini talep etme haklarına sahipsiniz.',
                    'Çalışma alanının sahibiyseniz bu haklardan ikisini bize yazmadan, ürünün Ayarlar bölümünden kullanabilirsiniz: çalışma alanının tuttuğu her şeyi hem makine tarafından hem insan tarafından okunabilir biçimde tek arşiv olarak indirebilir ve çalışma alanı verilerinin silinmesini isteyebilirsiniz. Her iki talep ve sonuçları, aynı Ayarlar ekranındaki denetim izine kaydedilir.',
                    'Diğer tüm konularda ve kendinize ait çalışma alanınız yoksa bu haklarınızı sitedeki iletişim formundan, {company.address} adresine yazarak veya {company.email} adresine e-posta göndererek kullanabilirsiniz. Kanunun 13. maddesinde belirtilen süre içinde yanıt veririz.',
                ]),
            ],
        );
    }
}
