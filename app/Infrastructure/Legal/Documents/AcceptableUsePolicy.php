<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal\Documents;

use App\Domain\Legal\LegalDocument;
use App\Domain\Legal\LegalSection;

/**
 * Kabul Edilebilir Kullanım Politikası (AUP) — İNGİLİZCE KAYNAK METİN
 * (FF-228, `docs/107` Faz 3.2, `docs/140` §6).
 *
 * ═══ NEDEN AYRI BİR BELGE ═══
 *
 * Hizmet Koşullarında "Acceptable use" başlıklı TEK paragraf var ve o
 * paragraf bir kural listesi değil, bir cümledir. Bir zincirin hukuk birimi
 * ise iki şeyi ayrı ayrı sorar: *neyi yasaklıyorsunuz* ve *ihlalde ne
 * yapıyorsunuz*. İkincisi Hizmet Koşullarında hiç yok.
 *
 * Bu belge o paragrafı GENİŞLETİR, onunla ÇELİŞMEZ: Hizmet Koşulları hâlâ
 * sözleşmedir, bu metin onun kabul edilebilir kullanım maddesinin ayrıntısıdır.
 *
 * ═══ UYGULANAMAYAN YAPTIRIM YAZILMADI ═══
 *
 * Yaptırım bölümü depodan ÖLÇÜLDÜ. Ürünün bugün gerçekten yapabildikleri:
 * hız sınırlama (`throttle:` ara katmanları), karantina ve tarama
 * (`ScanQuarantinedMediaAsset` — taranamayan dosya YAYINLANMAZ), medya
 * yasal saklama kilidi (`UpdateMediaLegalHoldController`), yayından
 * kaldırma, ve işletmecinin elle yaptığı hesap kapatma.
 *
 * ÜRÜNDE OLMAYAN: panelden tek tıkla çalışma alanı askıya alma. `WorkspaceState`
 * enum'unda `suspended` durumu VAR ama onu YAZAN hiçbir yüzey yok (ölçüldü:
 * `grep -r "WorkspaceState::Suspended"` yalnız enum tanımını buluyor). Bu
 * yüzden metin "hesabınızı askıya alırız" diye bir düğme vaat etmez; askıya
 * almanın bugün işletmecinin elle yaptığı bir iş olduğunu söyler.
 */
final class AcceptableUsePolicy
{
    public static function document(): LegalDocument
    {
        return new LegalDocument(
            key: 'acceptable-use',
            version: '0.1',
            effectiveDate: '2026-09-08',
            title: 'Acceptable Use Policy',
            summary: 'What may not be done with Zabuno, what happens when it is done anyway, and what this service can and cannot actually enforce today.',
            sections: [
                new LegalSection('Who this applies to', [
                    'This policy is issued by {company.legal_name} and applies to everyone who uses Zabuno: the business that holds the account, every member it invites to a workspace, and anyone using the published menu pages.',
                    'It is part of the Terms of Service and expands their acceptable use clause. Where a term of the Terms of Service and this policy say the same thing differently, they are read together and neither cancels the other.',
                ]),
                new LegalSection('Content you must not publish', [
                    'Content that is unlawful under Turkish law or under the law of the place it is published to, including content that incites hatred or violence, or that is obscene.',
                    'Content you do not hold the rights to. Images and text you upload must be yours or licensed to you. Taking a competitor\'s photographs, or a photograph found on the internet, is the most common way this rule is broken.',
                    'Content that impersonates another business or person, or that presents your business as connected to one it is not.',
                    'Menu information you know to be false in a way that can harm a guest. Allergen information is the clearest example: a guest may make an eating decision on it.',
                    'Personal data about someone who has not agreed to it being published. A menu is a public page; a photograph of a member of staff on it is published to everyone.',
                ]),
                new LegalSection('Things you must not do to the service', [
                    'Attempting to reach data that is not yours: another workspace, another account, another business\'s menu in a state it has not published.',
                    'Probing, scanning or attacking the service, or trying to get around its rate limits, its authentication, its file scanning or its permission model.',
                    'Automated traffic that is not a normal use of the service — bulk scraping of published menus, or hammering the QR addresses to enumerate them.',
                    'Uploading a file intended to harm: malware, or a file crafted to exploit the software that processes images.',
                    'Reselling the service, or sharing one account across separate businesses in order to avoid paying for each. Workspaces are how separate businesses are kept apart, and each is priced on its own.',
                ]),
                new LegalSection('Messages', [
                    'The e-mails the service sends on your behalf — team invitations and notifications — are for people who expect them. Using invitations to send unsolicited messages is a breach of this policy and of Law No. 6563 on the regulation of electronic commerce.',
                    'Commercial electronic messages from this service are sent only to people who have given consent for them, as the Commercial Electronic Message Consent text sets out. This service does not offer a way to send marketing messages to your own guest list, so nothing here permits one.',
                ]),
                new LegalSection('AI-assisted features', [
                    'Where you use AI-assisted menu import, submit only menu material you hold the rights to. What you submit is sent to the AI provider configured for the service, as the Data Processing Agreement sets out.',
                    'Output from an AI-assisted feature is a draft. Publishing it is your decision and your responsibility, and prices and allergens in particular should be checked by a person before a guest reads them.',
                ]),
                new LegalSection('What actually happens when this policy is broken — and what this service can enforce today', [
                    'Some of this is automatic and needs no one to notice anything. Public addresses are rate limited, so traffic that hammers a form or a QR address is slowed down or refused without a decision being taken by anyone.',
                    'Every uploaded file is quarantined and scanned before it can be published. A file that fails the scan is rejected. A file that cannot be scanned, because the scanner is not available in that environment, is held and never published; it is not silently treated as clean.',
                    'The rest is done by a person. There is no automatic content moderation in this service: nothing reads your menu text and judges it. A breach is acted on when it is reported or noticed, and the operator may remove the offending content, unpublish a menu, or close the account, as the Terms of Service provide.',
                    'Where a file is subject to a dispute, it can be put under a legal hold inside the workspace so that it cannot be deleted, including by a bulk operation, and the reason is recorded with the hold.',
                    'The workspace management screens do not today carry a one-click suspension for an account: suspending or closing one is an operator action taken on the server. This is written down rather than dressed up, because a policy that describes enforcement the product cannot perform is a policy nobody can rely on.',
                    'Where the law requires it, the operator reports unlawful content to the authorities and complies with an order it receives.',
                ]),
                new LegalSection('Proportion, and getting it wrong', [
                    'Action is taken in proportion to what happened. A menu photograph uploaded without the rights to it is asked about before anything is closed; an attack on the service is not.',
                    'Where the operator has acted and you believe it was wrong, write through the contact form on this site or to {company.email} from the address on the account. There is no automated appeal, and none is claimed here.',
                    'Closing an account for a breach of this policy does not by itself create a refund. What is refunded is governed by the Cancellation and Refund Policy.',
                ]),
                new LegalSection('Reporting a problem', [
                    'To report content published through this service that breaks this policy, or a security weakness you have found, write through the contact form or to {company.email}. Say what you saw and where.',
                    'There is no bug bounty programme and no published disclosure timetable. A report is read by a person; if a timetable is committed later, it will be written here with its own version number.',
                ]),
                new LegalSection('Changes to this policy', [
                    'The version number and effective date at the top of this page identify the current text. Where a change adds a restriction that materially affects how you already use the service, it is announced before it takes effect.',
                ]),
            ],
        );
    }
}
