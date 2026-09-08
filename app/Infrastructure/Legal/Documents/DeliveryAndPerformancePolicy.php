<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal\Documents;

use App\Domain\Legal\LegalDocument;
use App\Domain\Legal\LegalSection;

/**
 * Teslimat ve İfa Koşulları — İNGİLİZCE KAYNAK METİN (FF-216).
 *
 * NEDEN AYRI BİR SAYFA. Ödeme kuruluşunun üye iş yeri incelemesi sitede
 * ADIYLA bir "teslimat koşulları" başlığı arar ve altbilgiden ona bir
 * bağlantı bekler. Mesafeli satış sözleşmesinin içine gömülmüş bir bölüm o
 * incelemede görünmez: inceleyen kişi altbilgideki bağlantı listesine bakar,
 * on sayfalık bir sözleşmenin yedinci bölümüne değil. Ayrıca bu ürün için
 * "teslimat" kelimesi açıklanmayı hak ediyor — kargolanan bir şey yok ve
 * ziyaretçi bunu bir yerde okuyabilmeli.
 *
 * BU SAYFA MESAFELİ SATIŞTAN BAĞIMSIZ DEĞİL, ONUN PARÇASIDIR: sözleşme bu
 * belgeye atıf yapar ve ikisi aynı olguyu iki kez YAZMAZ — ifa anı burada
 * tanımlanır, sözleşme buraya bağlanır.
 *
 * UYDURMA YOK: "şu kadar dakikada aktifleşir" gibi bir süre yazılmadı.
 * Ölçülen olgu şudur — abonelik penceresi ödeme sağlayıcısı ödemeyi
 * doğruladığı anda açılır (`ManageCheckout::settle` → `extendFromPayment`);
 * o an sağlayıcının cevabına bağlıdır ve bir taahhüt olarak yazılamaz.
 */
final class DeliveryAndPerformancePolicy
{
    public static function document(): LegalDocument
    {
        return new LegalDocument(
            key: 'delivery',
            version: '0.1',
            effectiveDate: '2026-09-07',
            title: 'Delivery and Performance Terms',
            summary: 'What "delivery" means for a service that is provided entirely online: when the paid plan becomes usable, what is never shipped, and what happens if it does not start.',
            sections: [
                new LegalSection('Seller', [
                    '{company.legal_name}, {company.address}. MERSIS number {company.mersis}; tax office {company.tax_office}; tax number {company.tax_number}. E-mail {company.email}; phone {company.phone}.',
                ]),
                new LegalSection('Nothing is shipped', [
                    'Zabuno is a service provided over the internet. There is no physical product, no package, no courier and no shipping address. For that reason there is no delivery cost, no delivery time window and no delivery region in the sense those words have for goods.',
                    'The word "delivery" in these terms therefore means one thing only: the moment the paid plan becomes usable in your workspace.',
                ]),
                new LegalSection('When the paid plan starts', [
                    'The paid plan starts when the payment service provider confirms your payment. From that moment the subscription period runs and the features of the plan are available to every member of your workspace who has the right to use them.',
                    'You do not have to do anything after the payment to start the service, and no separate activation step is sent to you. If your browser returns from the payment page before the provider has finished confirming, the confirmation still arrives on its own and the plan starts then.',
                ]),
                new LegalSection('Where the service is used', [
                    'The service is reached through a web browser, from anywhere with an internet connection. Using it needs a device and an internet connection that you provide; their cost is yours and is not part of the price of the plan.',
                ]),
                new LegalSection('If the plan does not start', [
                    'If your payment was taken and the plan did not start, write to the seller through the contact form on this site or by e-mail to {company.email}, from the e-mail address on the account, and say when you paid. A payment that the provider reports as failed does not start the plan and does not create a subscription; in that case the workspace stays on the plan it had.',
                ]),
                new LegalSection('Interruptions and maintenance', [
                    'The service can be interrupted by maintenance, by a fault, or by a failure at a supplier the service depends on. These terms do not state an availability figure, because none is measured or promised today. Where an interruption caused by the seller removes a materially large part of a period that has already been paid for, the Cancellation and Refund Policy applies.',
                ]),
                new LegalSection('Your own content', [
                    'The menus, images and other content you put into your workspace stay yours. You can export your menu yourself, as a CSV file, from your workspace; you do not need to ask the seller for it. What happens to the content after a subscription ends is described in the Terms of Service and in the Privacy Policy.',
                ]),
                new LegalSection('Language of this text', [
                    'This text is published in English, which is the language it was written in. A Turkish text is not published yet. When one is published it will carry its own version and effective date, and this note will say so.',
                ]),
            ],
            requiresSellerIdentity: true,
        );
    }
}
