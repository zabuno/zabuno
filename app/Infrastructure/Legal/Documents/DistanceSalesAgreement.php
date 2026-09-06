<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal\Documents;

use App\Domain\Legal\LegalDocument;
use App\Domain\Legal\LegalSection;

/**
 * Mesafeli Satış Sözleşmesi — İNGİLİZCE KAYNAK METİN (FF-198).
 *
 * FİYAT UYDURULMAZ: tutar, ödeme anındaki sipariş özetine bağlanır
 * (`docs/107` 1.1 — üretim tahsilatı henüz yok). Sağlayıcı depodan
 * ölçüldü: Iyzico (`IyzipaySandboxGateway`). Cayma süresi kanundan gelir,
 * uydurulmadı (Mesafeli Sözleşmeler Yönetmeliği). Ödeme adımındaki onay
 * ayrı paketin işi (ff-197); bu belge o adımda kabul edilir.
 */
final class DistanceSalesAgreement
{
    public static function document(): LegalDocument
    {
        return new LegalDocument(
            key: 'distance-sales',
            version: '0.1',
            effectiveDate: '2026-09-06',
            title: 'Distance Sales Agreement',
            summary: 'The agreement concluded electronically when a business subscribes to a paid Zabuno plan.',
            sections: [
                new LegalSection('Parties', [
                    'Seller: {company.legal_name}, {company.address}, MERSIS number {company.mersis}, tax office {company.tax_office}, tax number {company.tax_number}, e-mail {company.email}, phone {company.phone}.',
                    'Buyer: the holder of the Zabuno account from which the order is placed, identified by the name and e-mail address on that account and by the billing details entered at checkout.',
                ]),
                new LegalSection('Subject of the agreement', [
                    'This agreement covers the subscription to the Zabuno plan selected at checkout. The features included in the plan are the ones shown on the Pricing page and in the order summary at the moment of the order.',
                    'The agreement is concluded electronically. The Preliminary Information Form that you confirmed before payment forms part of it.',
                ]),
                new LegalSection('Price and payment', [
                    'The price is the amount shown in the order summary at the moment of payment, in the currency shown there, with taxes shown as stated in that summary. No delivery cost applies, because nothing is shipped.',
                    'Payment is collected through the payment service provider Iyzico. Card details are entered on the provider\'s own systems and are not stored by the seller. Subscription fees are charged per subscription period in advance; the length of the period and its renewal terms are the ones shown in the order summary.',
                ]),
                new LegalSection('Performance of the service', [
                    'The service is delivered electronically. The features of the plan become available in the buyer\'s workspace when the payment is confirmed by the payment service provider. There is no physical delivery.',
                ]),
                new LegalSection('Right of withdrawal', [
                    'If the buyer is a consumer under Law No. 6502 on Consumer Protection, the buyer may withdraw from this agreement within fourteen days of its conclusion without giving a reason, as provided by the Distance Contracts Regulation. Because the service starts as soon as payment is confirmed, the right of withdrawal ends when the buyer asks for the service to begin during that period, as the Regulation provides.',
                    'Buyers that subscribe for business purposes do not have a statutory right of withdrawal. Cancellation for them is governed by the Cancellation and Refund Policy.',
                    'To exercise the right of withdrawal, write to the seller through the contact form on this site or by e-mail to {company.email} from the e-mail address on the account.',
                ]),
                new LegalSection('Cancellation and refunds', [
                    'Cancellation, its effect on the current subscription period and the cases in which fees are refunded are set out in the Cancellation and Refund Policy, which forms part of this agreement.',
                ]),
                new LegalSection('Obligations of the buyer', [
                    'The buyer confirms that the billing details entered at checkout are accurate and that the buyer is authorised to make the purchase on behalf of the business named there. Use of the service is subject to the Terms of Service.',
                ]),
                new LegalSection('Obligations of the seller', [
                    'The seller provides the plan as described in the order summary for the paid period and informs the buyer before a change that materially reduces what the plan provides.',
                ]),
                new LegalSection('Disputes', [
                    'This agreement is governed by the laws of the Republic of Turkey. A buyer who is a consumer may apply to the consumer arbitration committee or consumer court where the buyer lives, within the monetary limits set by law. In all other cases the courts and enforcement offices at the registered seat of the seller have jurisdiction.',
                ]),
                new LegalSection('Entry into force', [
                    'This agreement enters into force when the buyer confirms the order and the payment is completed. The text remains available on this site; the version accepted by the buyer, together with the date and time of acceptance, is recorded with the buyer\'s account.',
                ]),
            ],
        );
    }
}
