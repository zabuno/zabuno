<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal\Documents;

use App\Domain\Legal\LegalDocument;
use App\Domain\Legal\LegalSection;

/**
 * KVKK Aydınlatma Metni — 6698 sayılı Kanun madde 10 kapsamı, İNGİLİZCE
 * yazıldı (FF-198, `docs/118` E4). Adres `/kvkk` kalır: altbilgi ve kütük
 * ona bağlı. Türkçe sürüm çeviri kilidine tabidir (`docs/124`).
 */
final class KvkkDisclosure
{
    public static function document(): LegalDocument
    {
        return new LegalDocument(
            key: 'kvkk',
            version: '0.1',
            effectiveDate: '2026-09-06',
            title: 'Personal Data Protection Disclosure (KVKK)',
            summary: 'The disclosure required by Article 10 of the Turkish Personal Data Protection Law No. 6698: who processes your data, why, on what legal basis, and your rights.',
            sections: [
                new LegalSection('Data controller', [
                    'This disclosure is made by {company.legal_name}, registered at {company.address}, MERSIS number {company.mersis}, as data controller under Law No. 6698 on the Protection of Personal Data (the "Law").',
                ]),
                new LegalSection('Personal data we process', [
                    'Identity and contact data: the name and e-mail address you give when creating an account, and the business contact details you choose to enter in your workspace.',
                    'Transaction security data: network address, browser identifier and session records while you use the service, and the same data at the moment you accept a legal text.',
                    'Customer transaction data: the plan you subscribe to and the payment confirmations returned by the payment service provider. Card details are entered on the provider\'s systems and are not processed by us.',
                    'Visual data: the images you upload to your menu.',
                    'Marketing data: your commercial electronic message preference, only if you give it.',
                    'Guest data: pseudonymous visit, order and rating events on published menus, keyed by a daily-rotating visitor key that does not identify a person.',
                ]),
                new LegalSection('Purposes of processing', [
                    'Performing the service contract: creating and running your workspace, publishing your menu, serving it to guests, sending verification, invitation and notification e-mails.',
                    'Keeping accounts and the service secure, detecting and preventing abuse.',
                    'Meeting our legal obligations, including commercial and tax record-keeping and proving that consent was given.',
                    'Sending commercial electronic messages and loading measurement tools, in each case only with your explicit consent.',
                ]),
                new LegalSection('Method and legal basis of collection', [
                    'Personal data is collected electronically through the registration form, the workspace screens, the published menu pages, the contact form and the payment step.',
                    'The legal bases are those in Article 5 of the Law: processing necessary for the performance of a contract to which you are a party (Article 5/2-c); compliance with a legal obligation (Article 5/2-ç); processing necessary for our legitimate interests provided it does not harm your fundamental rights and freedoms (Article 5/2-f); and, for commercial messages and measurement tools, your explicit consent (Article 5/1).',
                ]),
                new LegalSection('To whom and why data is transferred', [
                    'Data is transferred to processors acting on our instructions, to the extent needed for the purposes above: the e-mail delivery provider (Mailgun) for e-mails we send you; the payment service provider (Iyzico) for payments; the AI provider configured for the service, where you use an AI-assisted feature, for the menu text or images you submit; and measurement tools loaded through Google Tag Manager after your consent.',
                    'Where a provider operates its systems outside Turkey, the transfer abroad is made on the conditions set by Article 9 of the Law. Data is disclosed to public authorities only where the law requires it.',
                ]),
                new LegalSection('Retention', [
                    'Account and workspace data are kept while the account exists, and afterwards only for as long as the law requires. Consent records are kept for as long as we must be able to prove consent. Pseudonymous guest events are not linked to a person and are kept for operating and improving the service.',
                ]),
                new LegalSection('Your rights under Article 11', [
                    'Under Article 11 of the Law you have the right to learn whether your personal data is processed; to request information if it has been; to learn the purpose of processing and whether the data is used in line with it; to know the third parties in Turkey or abroad to whom it is transferred; to request correction of incomplete or inaccurate data; to request deletion or destruction under Article 7; to request that correction, deletion or destruction be notified to the third parties to whom the data was transferred; to object to a result that is to your detriment and was produced exclusively by automated analysis; and to claim compensation for damage caused by unlawful processing.',
                    'You can exercise these rights through the contact form on this site, by writing to {company.address}, or by e-mail to {company.email}. We answer within the period set by Article 13 of the Law.',
                ]),
            ],
        );
    }
}
