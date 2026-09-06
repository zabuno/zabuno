<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal\Documents;

use App\Domain\Legal\LegalDocument;
use App\Domain\Legal\LegalSection;

/**
 * İptal ve İade Politikası — İNGİLİZCE KAYNAK METİN (FF-198).
 *
 * DÜRÜST: ürün içinde iptal bugün YOK (`docs/107` 1.3), bu yüzden iptal
 * yolu çalışan iletişim formudur. "Şu kadar günde iade" gibi bir süre
 * UYDURULMADI; iade süresi sağlayıcı ve bankaya bağlıdır ve öyle yazıldı.
 */
final class RefundPolicy
{
    public static function document(): LegalDocument
    {
        return new LegalDocument(
            key: 'refund-policy',
            version: '0.1',
            effectiveDate: '2026-09-06',
            title: 'Cancellation and Refund Policy',
            summary: 'How a paid Zabuno plan is cancelled, when the cancellation takes effect, and in which cases fees are refunded.',
            sections: [
                new LegalSection('Scope', [
                    'This policy applies to paid Zabuno plans bought under the Distance Sales Agreement. It is issued by {company.legal_name}. Using the free parts of the service does not create a subscription and there is nothing to cancel.',
                ]),
                new LegalSection('How to cancel', [
                    'Until cancellation is available inside the workspace, send your cancellation request through the contact form on this site or by e-mail to {company.email}, from the e-mail address on the account. We confirm the cancellation to you in writing.',
                    'Plan changes are requested the same way until they are available inside the workspace.',
                ]),
                new LegalSection('When cancellation takes effect', [
                    'Cancellation stops the subscription from renewing. The plan stays active until the end of the period you have already paid for, and the published menus of your workspace stay online until then.',
                ]),
                new LegalSection('Refunds', [
                    'Fees for a subscription period that has already started are not refunded, except in the following cases: you are a consumer exercising the statutory right of withdrawal described below; we ended or materially reduced the service before the end of a paid period for reasons not caused by you, in which case the unused part of the period is refunded; or the law otherwise requires a refund.',
                    'Refunds are returned through the payment service provider to the payment method used for the purchase. How long the amount takes to appear on your statement depends on the provider and on your bank.',
                ]),
                new LegalSection('Consumers: statutory right of withdrawal', [
                    'If you bought as a consumer under Law No. 6502, you may withdraw within fourteen days of concluding the agreement without giving a reason, as the Distance Contracts Regulation provides. Because the service starts immediately at your request, the right ends once the service has begun. A valid withdrawal is refunded in full through the payment service provider.',
                ]),
                new LegalSection('Contact', [
                    'Questions about cancellation and refunds: the contact form on this site, or {company.email}.',
                ]),
            ],
        );
    }
}
