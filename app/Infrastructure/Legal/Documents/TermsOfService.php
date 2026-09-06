<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal\Documents;

use App\Domain\Legal\LegalDocument;
use App\Domain\Legal\LegalSection;

/**
 * Hizmet Koşulları — İNGİLİZCE KAYNAK METİN (FF-198, `docs/118` E4).
 *
 * Metin ürünün BUGÜN yaptığıyla sınırlı: çalışma alanı, menü, yayın ve
 * kalıcı karekod, isteğe bağlı sipariş ve puanlama, ekip rolleri, AI
 * destekli aktarım (`docs/107`). Ürünün yapmadığı hiçbir şey vaat
 * edilmez; süre, fiyat ve şirket bilgisi uydurulmaz. Sürüm 0.1: hukukçu
 * incelemesi bekliyor (`LegalReview`).
 */
final class TermsOfService
{
    public static function document(): LegalDocument
    {
        return new LegalDocument(
            key: 'terms',
            version: '0.1',
            effectiveDate: '2026-09-06',
            title: 'Terms of Service',
            summary: 'The terms under which {company.legal_name} provides the Zabuno service to restaurants and other food businesses.',
            sections: [
                new LegalSection('Who we are and what these terms cover', [
                    'These Terms of Service (the "Terms") govern the use of Zabuno, a web application operated by {company.legal_name} ("we", "us"), registered at {company.address}, MERSIS number {company.mersis}, tax office {company.tax_office}, tax number {company.tax_number}. You can reach us at {company.email} or {company.phone}.',
                    'By creating an account you accept these Terms and the Privacy Policy. If you accept on behalf of a business, you confirm that you are authorised to bind that business.',
                ]),
                new LegalSection('The service', [
                    'Zabuno lets a business create a workspace, build a menu (categories, items, prices, allergens and images), publish it to a public page with a stable QR code, and update it afterwards without changing the QR code.',
                    'Depending on the plan and on what the business switches on, the service also offers ordering from the published menu, guest ratings, team members with roles, and AI-assisted menu import. We describe what the service does today on the product pages and in the help centre; we do not promise features that are not yet available.',
                ]),
                new LegalSection('Accounts', [
                    'You need an account with a valid e-mail address to use the workspace. You are responsible for keeping your password confidential and for everything done under your account. Tell us at once if you believe someone else is using it.',
                    'One person may belong to several workspaces. Each workspace has an owner who controls its members and their roles.',
                ]),
                new LegalSection('Your content', [
                    'The menus, prices, texts, images and other material you put into Zabuno remain yours. You grant us the right to store, process and display that material to the extent needed to run the service, including showing your published menu to your guests.',
                    'You are responsible for the accuracy of your menu (prices, allergens, availability) and for holding the rights to the images you upload. We may remove material that is unlawful or that infringes the rights of others.',
                ]),
                new LegalSection('Acceptable use', [
                    'You must not use the service to publish unlawful content, to send unsolicited messages, to attack or overload the service, or to reach data that is not yours. Uploaded files are scanned before they are published; a file that fails the scan is not published.',
                ]),
                new LegalSection('Plans, prices and payment', [
                    'Some parts of the service are paid. The plans and their prices are the ones shown on the Pricing page and in the order summary at the moment you subscribe.',
                    'Payment terms, the right of withdrawal and refunds are set out in the Preliminary Information Form, the Distance Sales Agreement and the Cancellation and Refund Policy. You accept those documents separately when you subscribe to a paid plan.',
                ]),
                new LegalSection('Availability and changes to the service', [
                    'We work to keep the service available but do not guarantee uninterrupted access. We may change, add or remove features. If a change materially reduces what a paid plan provides, we will tell you before it takes effect.',
                ]),
                new LegalSection('Ending the agreement', [
                    'You may stop using the service at any time and may ask us to delete your account through the contact form. We may suspend or close an account that breaks these Terms or that we are legally required to close. When an account is closed, the published menus of its workspaces are no longer served.',
                ]),
                new LegalSection('Liability', [
                    'The service is provided as described in these Terms and on the product pages. To the extent permitted by law, we are not liable for indirect losses such as lost profits, and our total liability in connection with a paid plan is limited to the fees you paid for that plan during the subscription period in which the event occurred. Nothing in these Terms limits liability that cannot be limited by law.',
                ]),
                new LegalSection('Governing law and disputes', [
                    'These Terms are governed by the laws of the Republic of Turkey. Disputes are heard by the courts and enforcement offices at the registered seat of {company.legal_name}. If you use the service as a consumer, you may also apply to the consumer arbitration committee or consumer court where you live, as provided by law.',
                ]),
                new LegalSection('Changes to these terms', [
                    'We may update these Terms. The version number and effective date at the top of this page identify the current text. The version you accepted is recorded with your account at the moment of acceptance, so it is always clear which text applied to you.',
                ]),
            ],
        );
    }
}
