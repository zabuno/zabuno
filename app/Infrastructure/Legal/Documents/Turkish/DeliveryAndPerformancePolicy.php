<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal\Documents\Turkish;

use App\Domain\Legal\LegalDocument;
use App\Domain\Legal\LegalSection;

final class DeliveryAndPerformancePolicy
{
    public static function document(): LegalDocument
    {
        return new LegalDocument(
            language: 'tr',
            key: 'delivery',
            version: '0.1',
            effectiveDate: '2026-09-07',
            title: 'Teslimat ve İfa Koşulları',
            summary: 'Tamamen çevrimiçi sunulan bir hizmette "teslimat" ne anlama gelir: ücretli planın ne zaman kullanılabilir olduğu, nelerin hiçbir zaman gönderilmediği ve hizmet başlamazsa ne olduğu.',
            sections: [
                new LegalSection('Satıcı', [
                    '{company.legal_name}, {company.address}. MERSİS numarası {company.mersis}; vergi dairesi {company.tax_office}; vergi numarası {company.tax_number}. E-posta {company.email}; telefon {company.phone}.',
                ]),
                new LegalSection('Fiziksel gönderim yapılmaz', [
                    'Zabuno internet üzerinden sunulan bir hizmettir. Fiziksel ürün, paket, kurye veya teslimat adresi yoktur. Bu nedenle mallar için kullanılan anlamıyla teslimat ücreti, teslimat zaman aralığı veya teslimat bölgesi bulunmaz.',
                    'Bu koşullarda "teslimat" yalnızca bir şeyi ifade eder: ücretli planın çalışma alanınızda kullanılabilir hale geldiği anı.',
                ]),
                new LegalSection('Ücretli plan ne zaman başlar?', [
                    'Ücretli plan, ödeme hizmeti sağlayıcısı ödemenizi onayladığında başlar. O andan itibaren abonelik süresi işler ve planın özellikleri çalışma alanınızda bunları kullanma hakkı olan her üyeye açılır.',
                    'Hizmeti başlatmak için ödemeden sonra bir şey yapmanız gerekmez ve size ayrı bir etkinleştirme adımı gönderilmez. Tarayıcınız, sağlayıcı onayı tamamlamadan ödeme sayfasından dönerse onay yine kendiliğinden gelir ve plan o zaman başlar.',
                ]),
                new LegalSection('Hizmet nerede kullanılır?', [
                    'Hizmete internet bağlantısı olan her yerden bir web tarayıcısıyla erişilir. Kullanım için sizin sağlayacağınız bir cihaz ve internet bağlantısı gerekir; bunların maliyeti size aittir ve plan ücretine dahil değildir.',
                ]),
                new LegalSection('Plan başlamazsa', [
                    'Ödemeniz alındığı halde plan başlamadıysa hesabınızdaki e-posta adresinden, bu sitedeki iletişim formuyla veya {company.email} adresine e-postayla satıcıya yazın ve ne zaman ödeme yaptığınızı belirtin. Sağlayıcının başarısız bildirdiği ödeme planı başlatmaz ve abonelik oluşturmaz; bu durumda çalışma alanı önceki planında kalır.',
                ]),
                new LegalSection('Kesintiler ve bakım', [
                    'Hizmet bakım, arıza veya hizmetin bağımlı olduğu bir tedarikçideki aksaklık nedeniyle kesintiye uğrayabilir. Bugün ölçülen veya vaat edilen bir oran olmadığı için bu koşullar kullanılabilirlik oranı belirtmez. Satıcının neden olduğu bir kesinti, bedeli ödenmiş dönemin önemli bir kısmını ortadan kaldırırsa İptal ve İade Politikası uygulanır.',
                ]),
                new LegalSection('Kendi içeriğiniz', [
                    'Çalışma alanınıza eklediğiniz menüler, görseller ve diğer içerikler size ait kalır. Menünüzü çalışma alanınızdan CSV dosyası olarak kendiniz dışa aktarabilirsiniz; satıcıdan istemeniz gerekmez. Abonelik sona erdikten sonra içeriğe ne olacağı Hizmet Koşullarında ve Gizlilik Politikasında açıklanır.',
                ]),
                new LegalSection('Bu metnin dili', [
                    'Bu metin, İngilizce kaynak metnin Türkçe çevirisidir. İlgili sürüm numarası ve yürürlük tarihi bu sayfanın üstünde gösterilir.',
                ]),
            ],
            requiresSellerIdentity: true,
        );
    }
}
