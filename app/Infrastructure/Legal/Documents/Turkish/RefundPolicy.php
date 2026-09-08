<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal\Documents\Turkish;

use App\Domain\Legal\LegalDocument;
use App\Domain\Legal\LegalSection;

final class RefundPolicy
{
    public static function document(): LegalDocument
    {
        return new LegalDocument(
            language: 'tr',
            key: 'refund-policy',
            version: '0.2',
            effectiveDate: '2026-09-08',
            title: 'İptal ve İade Politikası',
            summary: 'Ücretli Zabuno planının nasıl iptal edildiği, iptalin ne zaman geçerli olduğu ve ücretlerin hangi durumlarda iade edildiği.',
            sections: [
                new LegalSection('Kapsam', [
                    'Bu politika, Mesafeli Satış Sözleşmesi kapsamında satın alınan ücretli Zabuno planlarına uygulanır ve {company.legal_name} tarafından yayımlanır. Hizmetin ücretsiz bölümlerini kullanmak abonelik oluşturmaz; iptal edilecek bir şey yoktur.',
                ]),
                new LegalSection('Nasıl iptal edilir?', [
                    'Çalışma alanınızın Faturalandırma ekranından iptal edin: Abonelik bölümü ücretli döneminizin bitiş tarihini gösterir ve aboneliği sizin için iptal eder. Ayrıca hesabınızdaki e-posta adresinden, bu sitedeki iletişim formuyla veya {company.email} adresine e-postayla bize yazabilirsiniz.',
                    'Ücretli dönem sona ermeden önce aynı ekrandan istediğiniz zaman iptali geri alabilirsiniz; bunu yaptığınızda tekrar ödeme istenmez.',
                ]),
                new LegalSection('İptal ne zaman geçerli olur?', [
                    'İptal, aboneliğin yenilenmesini durdurur. Plan, bedelini ödediğiniz dönemin sonuna kadar etkin kalır; çalışma alanınızın yayımlanmış menüleri de o zamana kadar çevrimiçi kalır.',
                ]),
                new LegalSection('Farklı bir plana geçiş', [
                    'Daha pahalı bir plana geçiş, ilgili ödeme başarılı olur olmaz geçerli olur.',
                    'Daha ucuz bir plana geçiş Faturalandırma ekranından planlanır ve bedelini ödediğiniz dönemin sonunda geçerli olur. Ödediğiniz dönem, içerdiği tüm özelliklerle sonuna kadar değişmeden devam ettiği için iade yapılmaz ve farkın hiçbir kısmı alacak olarak tanımlanmaz. Onaylamadan önce ekran, daha ucuz planın içermediği özellikleri belirtir.',
                ]),
                new LegalSection('Bir dönemin ücreti ödenmezse', [
                    'Abonelik dönemi bittiğinde sonraki dönem için ödeme alınmamışsa plan özellikleri kısa bir ek süre boyunca açık kalır; böylece süresi dolan kart veya başarısız ödeme, hiçbir şey kaybedilmeden düzeltilebilir. Bu sürenin uzunluğu Faturalandırma ekranında gösterilir.',
                    'Ardından planın eklediği özellikler kapatılır. Bu bir hesap kapatma işlemi değildir: çalışma alanınız, menüleriniz ve verileriniz olduğu gibi kalır; yayımladığınız menüler misafirleriniz için çevrimiçi kalır ve başarılı ödeme, bize başvurmanız gerekmeden özellikleri yeniden açar.',
                ]),
                new LegalSection('İadeler', [
                    'Başlamış abonelik döneminin ücretleri şu durumlar dışında iade edilmez: aşağıda açıklanan yasal cayma hakkını kullanan tüketici olmanız; sizin neden olmadığınız sebeplerle, ücretli dönem bitmeden hizmeti sonlandırmamız veya önemli ölçüde azaltmamız — bu durumda dönemin kullanılmayan kısmı iade edilir; ya da kanunun başka bir nedenle iade gerektirmesi.',
                    'İadeler ödeme hizmeti sağlayıcısı üzerinden, satın almada kullanılan ödeme yöntemine yapılır. Tutarın hesap özetinize ne zaman yansıyacağı sağlayıcıya ve bankanıza bağlıdır.',
                ]),
                new LegalSection('Tüketiciler: yasal cayma hakkı', [
                    '6502 sayılı Kanun kapsamında tüketici olarak satın aldıysanız Mesafeli Sözleşmeler Yönetmeliğinin öngördüğü şekilde sözleşmenin kurulmasından itibaren on dört gün içinde gerekçe göstermeden cayabilirsiniz. Hizmet talebiniz üzerine hemen başladığı için hizmet başladığında bu hak sona erer. Geçerli cayma halinde bedelin tamamı ödeme hizmeti sağlayıcısı üzerinden iade edilir.',
                ]),
                new LegalSection('Misafirleriniz ne görür?', [
                    'Bu politikadaki hiçbir şey misafirin gördüklerini değiştirmez. Daha önce yayımladığınız menü, basılmış QR kodlar dahil olmak üzere, yayımladığınız haliyle sunulmaya devam eder; misafirlere aboneliğiniz, ödemeleriniz veya bu politika hakkında hiçbir şey gösterilmez.',
                ]),
                new LegalSection('İletişim', [
                    'İptal ve iadelerle ilgili sorular için: bu sitedeki iletişim formu veya {company.email}.',
                ]),
            ],
        );
    }
}
