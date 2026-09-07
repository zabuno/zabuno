<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal\Documents;

use App\Domain\Legal\LegalDocument;
use App\Domain\Legal\LegalSection;

/**
 * Ön Bilgilendirme Formu — İNGİLİZCE KAYNAK METİN (FF-198).
 *
 * Mesafeli Sözleşmeler Yönetmeliği madde 5'in başlıkları; tutar sipariş
 * özetine, sağlayıcı Iyzico'ya, süre kanuna bağlanır. Uydurma yok.
 */
final class PreliminaryInformationForm
{
    public static function document(): LegalDocument
    {
        return new LegalDocument(
            key: 'pre-information',
            version: '0.1',
            effectiveDate: '2026-09-06',
            title: 'Preliminary Information Form',
            summary: 'The information you are shown before paying for a Zabuno plan: who the seller is, what you buy, what it costs, how it is delivered and how you can withdraw.',
            sections: [
                new LegalSection('Seller', [
                    '{company.legal_name}, {company.address}. MERSIS number {company.mersis}; tax office {company.tax_office}; tax number {company.tax_number}. E-mail {company.email}; phone {company.phone}.',
                ]),
                new LegalSection('Service', [
                    'A subscription to the Zabuno plan you select at checkout. The plan\'s features are the ones shown on the Pricing page and in the order summary at the moment you order; they are provided in your Zabuno workspace.',
                ]),
                new LegalSection('Price', [
                    'The total price, including the taxes shown, is the amount displayed in the order summary at the moment of payment, in the currency displayed there. No delivery or other additional cost applies.',
                    'Payment is made by card through the payment service provider Iyzico. Card details are entered on the provider\'s systems and are not stored by the seller. Fees are charged per subscription period in advance, for the period and renewal terms shown in the order summary.',
                ]),
                new LegalSection('Delivery and start of the service', [
                    'The service is delivered electronically and starts in your workspace as soon as the payment service provider confirms the payment. Nothing is shipped.',
                ]),
                new LegalSection('Right of withdrawal', [
                    'If you buy as a consumer under Law No. 6502, you may withdraw from the agreement within fourteen days of its conclusion without giving a reason. Because the service starts immediately at your request, the right of withdrawal ends once the service has begun, as the Distance Contracts Regulation provides. To withdraw, write to the seller through the contact form or by e-mail to {company.email}.',
                    'If you buy for business purposes there is no statutory right of withdrawal; cancellation is governed by the Cancellation and Refund Policy.',
                ]),
                new LegalSection('Complaints and disputes', [
                    'You can send complaints through the contact form on this site or to {company.email}. A consumer may also apply to the consumer arbitration committee or consumer court where the consumer lives, within the monetary limits set by law.',
                ]),
                new LegalSection('Validity of this form', [
                    'This form is shown to you before payment and you confirm it electronically at checkout. It applies to the order you place at that moment; prices shown on the site may change afterwards, and the price that binds both parties is the one in your order summary.',
                ]),
            ],
        );
    }
}
