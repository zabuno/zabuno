<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal\Documents;

use App\Domain\Legal\LegalDocument;
use App\Domain\Legal\LegalSection;

/**
 * Mesafeli Satış Sözleşmesi — İNGİLİZCE KAYNAK METİN (FF-198, FF-216'da
 * tamamlandı).
 *
 * FF-198'de yayına giren metin dokuz bölümdü ve Mesafeli Sözleşmeler
 * Yönetmeliği'nin saydığı başlıkların bir kısmı YOKTU. Ölçülen eksikler:
 * cayma hakkının KULLANILAMAYACAĞI hâller ayrı bir başlık olarak yoktu;
 * ifaya derhâl başlanmasına verilen AÇIK ONAY sözleşmede bir cümle olarak
 * geçiyordu ama alındığı ve kaydedildiği söylenmiyordu; sözleşmenin süresi
 * ve yenilenmesi ayrı bir başlık değildi; uzaktan iletişim aracının kullanım
 * bedeli hiç yazmıyordu; kabul edilen ödeme yöntemleri sayılmıyordu; şikâyet
 * yolu uyuşmazlık başlığının içine gömülüydü; metnin dili ve Türkçe
 * sürümünün durumu okuyucuya hiç söylenmiyordu.
 *
 * SÜRÜM 0.2. Sürüm bir kimliktir: 0.1'i kabul etmiş bir alıcı 0.1'i kabul
 * etmiştir ve `consent_records` bunu böyle taşır.
 *
 * UYDURMA YOK. Tutar sipariş özetine, dönem `billing.subscription.period_days`
 * ayarına, sağlayıcı depodan ölçülen Iyzico'ya, cayma süresi kanuna, hakem
 * heyeti sınırı kanuna ATIFLA bağlandı — hiçbiri metne rakam olarak
 * gömülmedi, çünkü hepsi metnin dışında ve metinden hızlı değişir.
 *
 * BU METİN BİR HUKUKÇU TARAFINDAN OKUNMADI. Yönetmeliğin başlıklarının
 * hepsinin VAR olduğu, ürünün gerçek davranışını doğru anlatan bir taslaktır;
 * `LEGAL_REVIEWED_AT` dolana kadar her sayfa bunu üstte söyler.
 */
final class DistanceSalesAgreement
{
    public static function document(): LegalDocument
    {
        return new LegalDocument(
            key: 'distance-sales',
            version: '0.2',
            effectiveDate: '2026-09-07',
            title: 'Distance Sales Agreement',
            summary: 'The agreement concluded electronically when a business subscribes to a paid Zabuno plan: who sells, what is sold, what it costs, when it starts, and when it can be withdrawn from.',
            sections: [
                new LegalSection('Parties', [
                    'Seller: {company.legal_name}, {company.address}, MERSIS number {company.mersis}, tax office {company.tax_office}, tax number {company.tax_number}, e-mail {company.email}, phone {company.phone}.',
                    'Buyer: the holder of the Zabuno account from which the order is placed, identified by the name and e-mail address on that account and by the billing details entered at checkout.',
                ]),
                new LegalSection('Subject of the agreement', [
                    'This agreement covers the subscription to the Zabuno plan selected at checkout, sold and performed entirely over the internet. It is concluded electronically at the moment the buyer confirms the order.',
                    'The Preliminary Information Form that the buyer confirmed before payment, the Delivery and Performance Terms and the Cancellation and Refund Policy form part of this agreement. Where this agreement and one of those texts describe the same thing, this agreement is the one that applies.',
                ]),
                new LegalSection('Essential characteristics of the service', [
                    'Zabuno is a subscription to a workspace in which a business keeps its menu and publishes it behind a permanent QR code. The workspace holds the business, its branches, its menu categories and dishes, prices and images; publishing produces a page that a guest reaches by scanning the printed code, and a later change to the menu is shown on that same page without reprinting the code.',
                    'Depending on the plan selected, the workspace may also include table and QR management, guest ordering, guest rating, team members with their own permissions, custom branding, reporting, and importing a menu from a photograph with a human confirming the result. The features that belong to the selected plan are the ones shown on the Pricing page and in the order summary at the moment of the order; a feature that is not listed there is not sold.',
                    'The service needs a device and an internet connection, which the buyer provides. No software is delivered to the buyer to install.',
                ]),
                new LegalSection('Price, taxes and additional costs', [
                    'The price is the amount shown in the order summary at the moment of payment, in the currency shown there, with taxes shown as stated in that summary. That amount is the total: no delivery cost, no packaging cost and no separate service charge is added to it, because nothing is shipped and no other charge exists.',
                    'Prices shown on the site may change afterwards. The price that binds both parties is the one in the buyer\'s order summary for the period being paid for.',
                ]),
                new LegalSection('Payment methods accepted', [
                    'Payment is made by card, through the payment service provider Iyzico. No other payment method is offered: the seller does not accept bank transfer, cash, cheque or payment on delivery for a subscription.',
                    'Card details are entered on the payment service provider\'s own pages, not on this site, and the seller neither sees nor stores the card number. Any additional authentication step required by the card issuer takes place on the provider\'s pages.',
                ]),
                new LegalSection('Cost of the means of distance communication', [
                    'This agreement is concluded over this website. The buyer is charged nothing extra for using it; the buyer\'s own internet connection is charged by the buyer\'s own provider at the buyer\'s ordinary tariff.',
                ]),
                new LegalSection('Performance of the service', [
                    'The service is delivered electronically, and "delivery" here means that the paid plan becomes usable in the buyer\'s workspace. That happens when the payment service provider confirms the payment. There is no physical delivery, no shipment and no separate activation step; the Delivery and Performance Terms describe this in full.',
                ]),
                new LegalSection('Duration, renewal and termination', [
                    'The agreement runs for the subscription period shown in the order summary, and each payment moves the end of the subscription forward by one such period. The length of a period is the one shown in the order summary at the moment of the order.',
                    'The agreement continues for as long as the buyer keeps paying for further periods, and ends at the end of the last period paid for if the buyer cancels. Cancellation does not shorten a period already paid for. How to cancel, and what cancellation does, are set out in the Cancellation and Refund Policy.',
                ]),
                new LegalSection('Right of withdrawal', [
                    'If the buyer is a consumer under Law No. 6502 on Consumer Protection, the buyer may withdraw from this agreement within fourteen days of its conclusion without giving a reason, as provided by the Distance Contracts Regulation, and without paying a penalty. Because nothing is shipped, no return cost arises from a withdrawal.',
                    'To exercise the right of withdrawal, write to the seller within that period through the contact form on this site or by e-mail to {company.email}, from the e-mail address on the account, and say that you are withdrawing. A notice sent within the period is in time. The seller confirms receipt in writing, and a valid withdrawal is refunded in full through the payment service provider to the card used for the purchase.',
                    'Buyers that subscribe for purposes within their trade or profession are not consumers and do not have this statutory right. For them, ending the agreement is governed by the Cancellation and Refund Policy.',
                ]),
                new LegalSection('Cases in which the right of withdrawal cannot be used', [
                    'The Distance Contracts Regulation lists contracts to which the right of withdrawal does not apply. Two of them can apply to this agreement, and the buyer should read them before ordering.',
                    'First: services performed instantly in an electronic environment. This subscription is performed in an electronic environment and its performance begins as soon as the payment is confirmed.',
                    'Second: services whose performance has begun, with the consumer\'s approval, before the withdrawal period has expired. When the buyer asks for the plan to start immediately, the right of withdrawal ends once performance has begun.',
                    'The seller does not ask the buyer to give up the right of withdrawal, and this agreement does not do so. What the buyer confirms at checkout is that performance should begin at once; the effect that this has on the right of withdrawal is the effect the Regulation gives it, and nothing more.',
                ]),
                new LegalSection('Express consent to immediate performance', [
                    'At checkout the buyer is asked, in a separate tick box that is never pre-ticked, to confirm that the service should begin as soon as the payment is confirmed, and that the buyer has read what this means for the right of withdrawal in the section above. The order cannot be placed without that confirmation, and silence is not treated as consent.',
                    'That confirmation is recorded with the buyer\'s account: which document, which version of it, on which date and time, and from which network address. The buyer can read the recorded version of this text on this page at any time.',
                ]),
                new LegalSection('Cancellation and refunds', [
                    'Cancellation, its effect on the current subscription period and the cases in which fees are refunded are set out in the Cancellation and Refund Policy, which forms part of this agreement. Refunds are returned through the payment service provider to the payment method used for the purchase.',
                ]),
                new LegalSection('Obligations of the buyer', [
                    'The buyer confirms that the billing details entered at checkout are accurate and that the buyer is authorised to make the purchase on behalf of the business named there. Use of the service is subject to the Terms of Service.',
                ]),
                new LegalSection('Obligations of the seller', [
                    'The seller provides the plan as described in the order summary for the paid period and informs the buyer before a change that materially reduces what the plan provides.',
                ]),
                new LegalSection('Complaints and objections', [
                    'Complaints about this agreement are sent to the seller through the contact form on this site or by e-mail to {company.email}. The seller answers in writing to the e-mail address on the account.',
                    'A buyer who is a consumer may also apply to the consumer arbitration committee or to the consumer court at the buyer\'s own place of residence or at the place where the transaction was made. Which of the two hears the application depends on the monetary limits set by law for that year; those limits are announced yearly and are therefore not repeated here.',
                ]),
                new LegalSection('Governing law and jurisdiction', [
                    'This agreement is governed by the laws of the Republic of Turkey. For disputes that are not covered by the consumer route described above, the courts and enforcement offices at the registered seat of the seller have jurisdiction.',
                ]),
                new LegalSection('Entry into force and the record of the agreement', [
                    'This agreement enters into force when the buyer confirms the order and the payment is completed. The version accepted by the buyer, together with the date and time of acceptance, is recorded with the buyer\'s account, and the text of every version stays available on this site so the buyer can read the one that was accepted.',
                ]),
                new LegalSection('Language of this agreement', [
                    'This text is published in English, which is the language it was written in, and the English text is the one recorded as accepted. A Turkish text of this agreement is not published yet. When one is published it will carry its own version and effective date, and this note will say so.',
                ]),
            ],
            requiresSellerIdentity: true,
        );
    }
}
