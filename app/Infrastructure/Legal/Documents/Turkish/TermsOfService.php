<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal\Documents\Turkish;

use App\Domain\Legal\LegalDocument;
use App\Domain\Legal\LegalSection;

final class TermsOfService
{
    public static function document(): LegalDocument
    {
        return new LegalDocument(
            language: 'tr',
            key: 'terms',
            version: '0.1',
            effectiveDate: '2026-09-06',
            title: 'Hizmet Koşulları',
            summary: '{company.legal_name} tarafından restoranlara ve diğer gıda işletmelerine sunulan Zabuno hizmetinin koşulları.',
            sections: [
                new LegalSection('Biz kimiz ve bu koşullar neleri kapsıyor?', [
                    'Bu Hizmet Koşulları ("Koşullar"), kayıtlı adresi {company.address}, MERSİS numarası {company.mersis}, vergi dairesi {company.tax_office}, vergi numarası {company.tax_number} olan {company.legal_name} ("biz") tarafından işletilen Zabuno web uygulamasının kullanımını düzenler. Bize {company.email} veya {company.phone} üzerinden ulaşabilirsiniz.',
                    'Hesap oluşturarak bu Koşulları ve Gizlilik Politikasını kabul edersiniz. Bir işletme adına kabul ediyorsanız o işletmeyi bağlayıcı işlem yapmaya yetkili olduğunuzu teyit edersiniz.',
                ]),
                new LegalSection('Hizmet', [
                    'Zabuno, bir işletmenin çalışma alanı oluşturmasını, menü hazırlamasını (kategoriler, ürünler, fiyatlar, alerjenler ve görseller), menüyü sabit bir QR kodla herkese açık bir sayfada yayımlamasını ve daha sonra QR kodu değiştirmeden güncellemesini sağlar.',
                    'Plana ve işletmenin etkinleştirdiği özelliklere bağlı olarak hizmet, yayımlanmış menüden sipariş verme, misafir puanları, rollere sahip ekip üyeleri ve yapay zekâ destekli menü aktarımı da sunar. Hizmetin bugün yaptıklarını ürün sayfalarında ve yardım merkezinde açıklıyoruz; henüz kullanılamayan özellikler vaat etmiyoruz.',
                ]),
                new LegalSection('Hesaplar', [
                    'Çalışma alanını kullanmak için geçerli bir e-posta adresiyle oluşturulmuş hesabınız olmalıdır. Şifrenizi gizli tutmaktan ve hesabınız altında yapılan tüm işlemlerden siz sorumlusunuz. Hesabınızı başka birinin kullandığını düşünüyorsanız bize derhal bildirin.',
                    'Bir kişi birden fazla çalışma alanına üye olabilir. Her çalışma alanının üyelerini ve rollerini yöneten bir sahibi vardır.',
                ]),
                new LegalSection('İçeriğiniz', [
                    'Zabuno\'ya eklediğiniz menüler, fiyatlar, metinler, görseller ve diğer materyaller size ait olmaya devam eder. Yayımlanmış menünüzü misafirlerinize göstermek de dahil olmak üzere, hizmeti yürütmek için gerekli ölçüde bu materyalleri saklama, işleme ve gösterme hakkını bize verirsiniz.',
                    'Menünüzün doğruluğundan (fiyatlar, alerjenler, bulunabilirlik) ve yüklediğiniz görsellerin haklarına sahip olmaktan siz sorumlusunuz. Hukuka aykırı veya başkalarının haklarını ihlal eden materyalleri kaldırabiliriz.',
                ]),
                new LegalSection('Kabul edilebilir kullanım', [
                    'Hizmeti hukuka aykırı içerik yayımlamak, istenmeyen mesajlar göndermek, hizmete saldırmak veya aşırı yük bindirmek ya da size ait olmayan verilere erişmek için kullanamazsınız. Yüklenen dosyalar yayımlanmadan önce taranır; taramadan geçemeyen dosya yayımlanmaz.',
                ]),
                new LegalSection('Planlar, fiyatlar ve ödeme', [
                    'Hizmetin bazı bölümleri ücretlidir. Planlar ve fiyatları, abone olduğunuz anda Fiyatlar sayfasında ve sipariş özetinde gösterilenlerdir.',
                    'Ödeme koşulları, cayma hakkı ve iadeler Ön Bilgilendirme Formu, Mesafeli Satış Sözleşmesi ve İptal ve İade Politikasında düzenlenir. Ücretli bir plana abone olurken bu belgeleri ayrıca kabul edersiniz.',
                ]),
                new LegalSection('Kullanılabilirlik ve hizmet değişiklikleri', [
                    'Hizmeti kullanılabilir tutmak için çalışırız ancak kesintisiz erişimi garanti etmeyiz. Özellikleri değiştirebilir, ekleyebilir veya kaldırabiliriz. Bir değişiklik ücretli planın sunduklarını önemli ölçüde azaltıyorsa yürürlüğe girmeden önce size bildiririz.',
                ]),
                new LegalSection('Sözleşmenin sona ermesi', [
                    'Hizmeti kullanmayı istediğiniz zaman bırakabilir ve iletişim formundan hesabınızın silinmesini isteyebilirsiniz. Bu Koşulları ihlal eden veya hukuken kapatmamız gereken bir hesabı askıya alabilir ya da kapatabiliriz. Bir hesap kapatıldığında çalışma alanlarının yayımlanmış menüleri artık sunulmaz.',
                ]),
                new LegalSection('Sorumluluk', [
                    'Hizmet, bu Koşullarda ve ürün sayfalarında açıklandığı şekilde sunulur. Kanunun izin verdiği ölçüde, kâr kaybı gibi dolaylı zararlardan sorumlu değiliz; ücretli bir planla bağlantılı toplam sorumluluğumuz, olayın gerçekleştiği abonelik döneminde o plan için ödediğiniz ücretlerle sınırlıdır. Bu Koşullardaki hiçbir hüküm, kanunen sınırlandırılamayan sorumluluğu sınırlandırmaz.',
                ]),
                new LegalSection('Uygulanacak hukuk ve uyuşmazlıklar', [
                    'Bu Koşullara Türkiye Cumhuriyeti hukuku uygulanır. Uyuşmazlıklar, {company.legal_name} şirketinin kayıtlı merkezinin bulunduğu yerdeki mahkemeler ve icra dairelerinde görülür. Hizmeti tüketici olarak kullanıyorsanız kanunun öngördüğü şekilde, yaşadığınız yerdeki tüketici hakem heyetine veya tüketici mahkemesine de başvurabilirsiniz.',
                ]),
                new LegalSection('Bu koşullardaki değişiklikler', [
                    'Bu Koşulları güncelleyebiliriz. Sayfanın üstündeki sürüm numarası ve yürürlük tarihi güncel metni tanımlar. Kabul ettiğiniz sürüm, kabul anında hesabınızla birlikte kaydedilir; böylece size hangi metnin uygulandığı her zaman bellidir.',
                ]),
            ],
        );
    }
}
