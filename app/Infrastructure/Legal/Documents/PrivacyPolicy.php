<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal\Documents;

use App\Domain\Legal\LegalDocument;
use App\Domain\Legal\LegalSection;

/**
 * Gizlilik Politikası — İNGİLİZCE KAYNAK METİN (FF-198).
 *
 * Her cümle depodan ÖLÇÜLDÜ: kayıt verisi (`users`, `sessions`), çalışma
 * alanı verisi, misafir olayları ve günlük dönen takma kimlik
 * (`VisitorKey`), ölçüm onayı (`MeasurementConsent`), e-posta sağlayıcısı
 * (`VaultMailTransportSelector` → Mailgun), ödeme sağlayıcısı
 * (`IyzipaySandboxGateway` → Iyzico), AI sağlayıcıları
 * (`CredentialProvider`). Olmayan bir işlem yazılmadı.
 */
final class PrivacyPolicy
{
    public static function document(): LegalDocument
    {
        return new LegalDocument(
            key: 'privacy',
            version: '0.1',
            effectiveDate: '2026-09-06',
            title: 'Privacy Policy',
            summary: 'What personal data Zabuno collects, why it is collected, who receives it and how long it is kept.',
            sections: [
                new LegalSection('Who is responsible for your data', [
                    'The data controller is {company.legal_name}, {company.address}. You can reach us about personal data at {company.email}.',
                    'For people in Turkey, the Personal Data Protection Disclosure (KVKK) published on this site is the notice required by Law No. 6698. This Privacy Policy explains the same processing in plain language.',
                ]),
                new LegalSection('Data we collect when you create an account', [
                    'Your name, your e-mail address and your password. The password is stored only as a cryptographic hash; we cannot read it.',
                    'The moment you accepted the Terms of Service and the Privacy Policy, together with the version of each text, your network address and your browser identifier at that moment. If you tick the optional commercial message box, that consent is recorded the same way.',
                    'Whether your e-mail address has been verified, and session data (network address, browser identifier) while you are logged in.',
                ]),
                new LegalSection('Data you enter while running your workspace', [
                    'The business details you choose to enter (name, address, contact e-mail and phone, opening hours), the menu itself (categories, items, prices, allergens, descriptions), the images you upload, and the e-mail addresses and roles of the team members you invite.',
                    'Messages you send through the contact form: your name, your e-mail address and the message.',
                ]),
                new LegalSection('Data about the guests who open your menu', [
                    'When a guest opens a published menu we record which menu was opened, which items were viewed or searched for, and, if ordering or rating is switched on for that menu, the order or rating itself. Guests are not asked for a name or an account.',
                    'To count visitors without identifying them we derive a pseudonymous visitor key from the network address, the browser identifier and the date. The key cannot be turned back into an address and it changes every day.',
                    'The language a guest chooses for a menu is kept in a cookie named zabuno_guest_locale.',
                ]),
                new LegalSection('Measurement tools and cookies', [
                    'We do not load any third-party measurement tool until the visitor explicitly accepts it on the cookie choice bar. The choice is stored for one year in a cookie named zabuno_measurement_consent and can be changed at any time on the Cookie Policy page.',
                    'A list of every cookie the site sets, with its purpose and lifetime, is in the Cookie Policy.',
                ]),
                new LegalSection('Why we process data', [
                    'To provide the service you signed up for, under our contract with you: running your workspace, publishing your menu, serving it to your guests, sending verification and notification e-mails.',
                    'To keep the service secure: detecting abuse, limiting request rates, scanning uploaded files.',
                    'To meet legal duties, such as keeping commercial and tax records and proving that consent was given.',
                    'With your consent only: sending commercial electronic messages, and loading measurement tools.',
                    'For our legitimate interest in understanding how the service is used, using pseudonymous data that does not identify a person.',
                ]),
                new LegalSection('Who receives data', [
                    'We use service providers that process data on our behalf and under our instructions: an e-mail delivery provider (Mailgun) for verification, invitation and notification e-mails; the payment service provider Iyzico when you pay for a plan, where card details are entered on the provider\'s own systems and never reach us; and, where AI-assisted features are enabled, the AI provider configured for the service, which receives the menu text or images you submit for that feature.',
                    'Measurement tools loaded through Google Tag Manager receive data only after you accept them; which tools are active is listed in the Cookie Policy.',
                    'We do not sell personal data and we do not pass it to third parties for their own purposes. We disclose data to authorities only where the law requires it.',
                ]),
                new LegalSection('How long we keep data', [
                    'Account and workspace data are kept while the account exists. When you ask us to delete your account we remove the data we are not legally required to keep.',
                    'Consent records are kept for as long as the law requires us to be able to prove that consent was given.',
                    'Pseudonymous guest events and server logs are kept for operating and improving the service and are not linked back to a person.',
                ]),
                new LegalSection('Your rights', [
                    'You can ask what data we hold about you, ask for it to be corrected or deleted, object to processing based on our legitimate interest, and withdraw a consent you have given. Withdrawing consent does not affect processing that took place before.',
                    'You can download your menu as a CSV file from your workspace at any time; you do not need to ask us for it. For everything else, write to us through the contact form or at {company.email}.',
                ]),
                new LegalSection('Security', [
                    'Passwords are hashed, connections are encrypted, uploaded files are scanned before they are published, and access inside a workspace is controlled by roles. No method of storage or transmission is perfectly secure; if you notice a problem, tell us.',
                ]),
                new LegalSection('Changes to this policy', [
                    'We may update this policy. The version number and effective date at the top of the page identify the current text. The version you accepted when you created your account is recorded with your account.',
                ]),
            ],
        );
    }
}
