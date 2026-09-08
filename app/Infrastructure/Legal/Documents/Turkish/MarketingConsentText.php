<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal\Documents\Turkish;

use App\Domain\Legal\LegalDocument;
use App\Domain\Legal\LegalSection;

final class MarketingConsentText
{
    public static function document(): LegalDocument
    {
        return new LegalDocument(
            language: 'tr',
            key: 'marketing-consent',
            version: '0.1',
            effectiveDate: '2026-09-06',
            title: 'Ticari Elektronik İleti Onayı',
            summary: 'Kayıt sırasında isteğe bağlı ticari ileti kutusunu işaretlediğinizde neyi kabul ettiğiniz ve bu onayı nasıl geri çekebileceğiniz.',
            sections: [
                new LegalSection('Neye onay veriyorsunuz?', [
                    'Kayıt ekranındaki ticari ileti kutusunu işaretleyerek {company.legal_name} tarafından Zabuno hakkında ürün haberleri, yeni özellikler, teklifler ve etkinliklerle ilgili ticari elektronik iletilerin e-posta yoluyla gönderilmesini kabul edersiniz.',
                    'Bu iletiler için kullandığımız tek kanal e-postadır. SMS veya telefon yoluyla ticari ileti göndermeyiz.',
                ]),
                new LegalSection('Hukuki dayanak', [
                    'Bu onay, 6563 sayılı Elektronik Ticaretin Düzenlenmesi Hakkında Kanun ve Ticari İletişim ve Ticari Elektronik İletiler Hakkında Yönetmelik kapsamında istenir. Onay vermek isteğe bağlıdır; kutuyu işaretleseniz de işaretlemeseniz de hizmet aynı şekilde çalışır.',
                ]),
                new LegalSection('Onayınızı geri çekme', [
                    'Onayınızı istediğiniz zaman ücretsiz olarak bu sitedeki iletişim formundan veya hesabınızdaki e-posta adresinden {company.email} adresine yazarak geri çekebilirsiniz. Geri çekme işlemi tamamlandığında ticari ileti göndermeyi durdurur ve geri çekmeyi ilk onayın yanına kaydederiz.',
                ]),
                new LegalSection('Onayınız nasıl kaydedilir?', [
                    'Onayınız; tarih ve saat, bu metnin sürümü, o andaki ağ adresiniz ve tarayıcı tanımlayıcınızla birlikte hesabınıza kaydedilir. Kutuyu işaretlemezseniz hiçbir şey kaydedilmez ve ticari ileti gönderilmez.',
                ]),
                new LegalSection('Ticari olmayan iletiler', [
                    'Hesabınızı işletmek için gerekli iletiler bu onaydan bağımsız gönderilir: e-posta doğrulama, şifre sıfırlama, ekip davetleri, güvenlik bildirimleri ve ödeme onayları. Bunlar pazarlama değil, hizmetin bir parçasıdır.',
                ]),
            ],
        );
    }
}
