<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal\Documents;

use App\Domain\Legal\LegalDocument;
use App\Domain\Legal\LegalSection;

/**
 * İptal ve İade Politikası — İNGİLİZCE KAYNAK METİN (FF-198, FF-219'da 0.2).
 *
 * DÜRÜST: 0.1 sürümü "ürün içinde iptal bugün YOK" diyordu ve o gün doğruydu.
 * FF-219 iptali, iptalden caymayı ve plan düşürmeyi panele indirdi
 * (`docs/107` Faz 1.3, `docs/134`); metin O GÜN güncellendi, çünkü artık
 * çalışan bir düğmeyi "yok" diye anlatan bir politika, kullanıcıyı gereksiz
 * yere e-postaya gönderirdi.
 *
 * ÜRÜNLE ÇELİŞMEZ ve çelişmemesi ölçülür: "başlamış bir dönemin ücreti iade
 * edilmez" cümlesi, düşürmenin dönem sonunda yürürlüğe girmesinin ve fark
 * iadesi yapılmamasının GEREKÇESİDİR — kod o cümleye uydu, cümle koda değil
 * (`SubscriptionLifecycleJourneyTest`).
 *
 * "Şu kadar günde iade" gibi bir süre UYDURULMADI; iade süresi sağlayıcı ve
 * bankaya bağlıdır ve öyle yazıldı. Ödemesiz sürenin gün sayısı da buraya
 * YAZILMADI: o sayı bir yapılandırmadır (`billing.subscription.grace_days`)
 * ve metne kopyalansaydı ikisi ilk değişiklikte ayrışırdı.
 */
final class RefundPolicy
{
    public static function document(): LegalDocument
    {
        return new LegalDocument(
            key: 'refund-policy',
            version: '0.2',
            effectiveDate: '2026-09-08',
            title: 'Cancellation and Refund Policy',
            summary: 'How a paid Zabuno plan is cancelled, when the cancellation takes effect, and in which cases fees are refunded.',
            sections: [
                new LegalSection('Scope', [
                    'This policy applies to paid Zabuno plans bought under the Distance Sales Agreement. It is issued by {company.legal_name}. Using the free parts of the service does not create a subscription and there is nothing to cancel.',
                ]),
                new LegalSection('How to cancel', [
                    'Cancel from the Billing screen of your workspace: the Subscription section shows the date your paid period ends and cancels the subscription for you. You may also write to us through the contact form on this site or by e-mail to {company.email}, from the e-mail address on the account.',
                    'You may undo a cancellation from the same screen at any time before the paid period ends, and you are not asked to pay again when you do.',
                ]),
                new LegalSection('When cancellation takes effect', [
                    'Cancellation stops the subscription from renewing. The plan stays active until the end of the period you have already paid for, and the published menus of your workspace stay online until then.',
                ]),
                new LegalSection('Moving to a different plan', [
                    'Moving up to a more expensive plan takes effect as soon as the payment for it succeeds.',
                    'Moving down to a cheaper plan is scheduled from the Billing screen and takes effect at the end of the period you have already paid for. Nothing is refunded and no part of the difference is credited, because the period you paid for runs unchanged to its end with everything it included. Before you confirm, the screen names the capabilities the cheaper plan does not include.',
                ]),
                new LegalSection('If a period is not paid for', [
                    'If a subscription period ends and no payment for the next one has been received, the plan features stay switched on for a short further period, so that a card that has expired or a payment that has failed can be sorted out without losing anything. The length of that period is shown to you on the Billing screen.',
                    'After that, the features the plan adds are switched off. This is not an account closure: your workspace, your menus and your data stay as they are, the menus you have published stay online for your guests, and a successful payment switches the features back on without you having to ask us.',
                ]),
                new LegalSection('Refunds', [
                    'Fees for a subscription period that has already started are not refunded, except in the following cases: you are a consumer exercising the statutory right of withdrawal described below; we ended or materially reduced the service before the end of a paid period for reasons not caused by you, in which case the unused part of the period is refunded; or the law otherwise requires a refund.',
                    'Refunds are returned through the payment service provider to the payment method used for the purchase. How long the amount takes to appear on your statement depends on the provider and on your bank.',
                ]),
                new LegalSection('Consumers: statutory right of withdrawal', [
                    'If you bought as a consumer under Law No. 6502, you may withdraw within fourteen days of concluding the agreement without giving a reason, as the Distance Contracts Regulation provides. Because the service starts immediately at your request, the right ends once the service has begun. A valid withdrawal is refunded in full through the payment service provider.',
                ]),
                new LegalSection('What your guests see', [
                    'Nothing in this policy changes what a guest sees. A menu you have already published keeps being served exactly as you published it, including from QR codes you have already printed; guests are never shown anything about your subscription, your payments or this policy.',
                ]),
                new LegalSection('Contact', [
                    'Questions about cancellation and refunds: the contact form on this site, or {company.email}.',
                ]),
            ],
        );
    }
}
