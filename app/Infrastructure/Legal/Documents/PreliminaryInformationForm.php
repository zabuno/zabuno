<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal\Documents;

use App\Domain\Legal\LegalDocument;
use App\Domain\Legal\LegalSection;

/**
 * Ön Bilgilendirme Formu — İNGİLİZCE KAYNAK METİN (FF-198, FF-216'da
 * tamamlandı).
 *
 * Mesafeli Sözleşmeler Yönetmeliği madde 5'in saydığı başlıklar. FF-198'de
 * yedi bölümdü; eksik olan başlıklar mesafeli satış sözleşmesiyle AYNI
 * eksiklerdi ve aynı turda kapatıldı: cayma hakkının kullanılamayacağı
 * hâller, ifaya derhâl başlama onayının nasıl alındığı, sözleşmenin süresi
 * ve yenilenmesi, uzaktan iletişim aracının bedeli, kabul edilen ödeme
 * yöntemleri, şikâyet yolunun ayrı başlığı ve metnin dili.
 *
 * BU FORM SÖZLEŞMENİN ÖZETİ DEĞİL, ÖNCESİDİR: alıcı ödemeden ÖNCE bunu
 * okur ve onaylar; sözleşme ödemeyle kurulur. İkisi aynı olguları anlatır
 * ve bu bilinçli bir tekrardır — yönetmelik ikisini de istiyor.
 *
 * UYDURMA YOK: tutar sipariş özetine, sağlayıcı Iyzico'ya, dönem sipariş
 * özetine, süre kanuna, hakem heyeti sınırı kanuna ATIFLA bağlanır.
 */
final class PreliminaryInformationForm
{
    public static function document(): LegalDocument
    {
        return new LegalDocument(
            key: 'pre-information',
            version: '0.2',
            effectiveDate: '2026-09-07',
            title: 'Preliminary Information Form',
            summary: 'The information you are shown before paying for a Zabuno plan: who the seller is, what you buy, what it costs, how it is delivered, how long it runs and when you can withdraw.',
            sections: [
                new LegalSection('Seller', [
                    '{company.legal_name}, {company.address}. MERSIS number {company.mersis}; tax office {company.tax_office}; tax number {company.tax_number}. E-mail {company.email}; phone {company.phone}.',
                ]),
                new LegalSection('Service', [
                    'A subscription to the Zabuno plan you select at checkout: a workspace in which your business keeps its menu and publishes it behind a permanent QR code, so that changing a price does not mean reprinting the code.',
                    'Depending on the plan, the workspace may also include table and QR management, guest ordering, guest rating, team members with their own permissions, custom branding, reporting, and importing a menu from a photograph with a person confirming the result. The features you buy are the ones shown on the Pricing page and in the order summary at the moment you order; a feature not listed there is not sold. The service is used through a web browser; nothing is installed on your device.',
                ]),
                new LegalSection('Price, taxes and additional costs', [
                    'The total price, including the taxes shown, is the amount displayed in the order summary at the moment of payment, in the currency displayed there. Nothing is added to it: no delivery cost, no packaging cost, no separate service charge.',
                ]),
                new LegalSection('Payment methods accepted', [
                    'Payment is made by card through the payment service provider Iyzico. No other payment method is offered — not bank transfer, not cash, not payment on delivery. Card details are entered on the provider\'s own pages and are neither seen nor stored by the seller.',
                    'Fees are charged per subscription period in advance, for the period and renewal terms shown in your order summary.',
                ]),
                new LegalSection('Cost of using this website', [
                    'You are charged nothing extra for placing the order over this website. Your own internet connection is charged by your own provider at your ordinary tariff.',
                ]),
                new LegalSection('Delivery and start of the service', [
                    'The service is delivered electronically and starts in your workspace as soon as the payment service provider confirms the payment. Nothing is shipped and there is no separate activation step. The Delivery and Performance Terms describe this in full.',
                ]),
                new LegalSection('Duration and renewal', [
                    'The subscription runs for the period shown in your order summary, and each payment moves its end forward by one such period. It continues while you keep paying for further periods and ends at the end of the last period you paid for if you cancel. Cancelling does not shorten a period you have already paid for; the Cancellation and Refund Policy sets out how to cancel.',
                ]),
                new LegalSection('Right of withdrawal', [
                    'If you buy as a consumer under Law No. 6502, you may withdraw from the agreement within fourteen days of its conclusion, without giving a reason and without paying a penalty. Because nothing is shipped, a withdrawal costs you nothing to send back.',
                    'To withdraw, write to the seller within that period through the contact form on this site or by e-mail to {company.email}, from the e-mail address on your account, and say that you are withdrawing. A valid withdrawal is refunded in full through the payment service provider to the card used.',
                    'If you buy for purposes within your trade or profession you are not a consumer and this statutory right does not apply to you; ending the agreement is then governed by the Cancellation and Refund Policy.',
                ]),
                new LegalSection('Cases in which the right of withdrawal cannot be used', [
                    'The Distance Contracts Regulation lists contracts to which the right of withdrawal does not apply. Two of them can apply here: services performed instantly in an electronic environment, and services whose performance has begun, with your approval, before the withdrawal period has expired.',
                    'This subscription is performed in an electronic environment, and its performance begins as soon as your payment is confirmed. Once performance has begun at your request, the right of withdrawal ends, as the Regulation provides.',
                ]),
                new LegalSection('Your express consent to immediate performance', [
                    'At checkout you are asked, in a separate tick box that is never pre-ticked, to confirm that the service should begin as soon as your payment is confirmed and that you have read what this means for your right of withdrawal. You cannot place the order without that confirmation, and leaving the box empty is not treated as consent.',
                    'That confirmation is recorded with your account: which document, which version of it, on which date and time and from which network address.',
                ]),
                new LegalSection('Complaints and objections', [
                    'You can send complaints through the contact form on this site or to {company.email}; the seller answers in writing to the e-mail address on your account.',
                    'As a consumer you may also apply to the consumer arbitration committee or to the consumer court at your own place of residence or at the place where the transaction was made. Which of the two hears the application depends on the monetary limits set by law for that year; those limits are announced yearly and are therefore not repeated here.',
                ]),
                new LegalSection('Validity of this form', [
                    'This form is shown to you before payment and you confirm it electronically at checkout. It applies to the order you place at that moment. Prices shown on the site may change afterwards; the price that binds both parties is the one in your order summary. The version you confirmed, with the date and time, is recorded with your account and the text stays available on this site.',
                ]),
                new LegalSection('Language of this form', [
                    'This text is published in English, which is the language it was written in and the English text is the one recorded as confirmed. A Turkish translation is also available. Each language displays the applicable version and effective date at the top of the page.',
                ]),
            ],
            requiresSellerIdentity: true,
        );
    }
}
