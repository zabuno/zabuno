<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal\Documents;

use App\Domain\Legal\LegalDocument;
use App\Domain\Legal\LegalSection;
use App\Domain\Legal\SubprocessorInventory;

/**
 * Veri İşleme Sözleşmesi (DPA) — İNGİLİZCE KAYNAK METİN (FF-228,
 * `docs/107` Faz 3.2, `docs/140`).
 *
 * ═══ KİME YAZILDI ═══
 *
 * Bir zincirin satın alma ya da hukuk birimine. Onların üç sorusu vardır ve
 * bu belge üçünü de bir sayfada cevaplar: *veri nerede tutuluyor, kim
 * erişebiliyor, silmek istersek ne oluyor* (`docs/107` Faz 3 "bitti ne
 * demek" satırı).
 *
 * ═══ ALT İŞLEYEN LİSTESİ ÖLÇÜLÜR, YAZILMAZ ═══
 *
 * 6. bölüm sabit metin DEĞİLDİR: `SubprocessorInventory` çizim anında
 * kasadan, ölçüm yapılandırmasından ve barındırma olgusundan üretilir
 * (`MeasuredSubprocessors`). Sebebi tek cümle: elle yazılmış bir alt işleyen
 * listesi, bir sağlayıcı açıldığı gün hiçbir uyarı vermeden yanlışa döner.
 *
 * ═══ NE YAZILMADI ═══
 *
 * Sertifika yok (ISO 27001 ya da SOC 2 alınmadı ve dış denetim yapılmadı).
 * Çalışma süresi oranı yok (ölçülmüyor — `docs/107` Faz 3.4). "Yedekler ayrı
 * bir konumda saklanır" cümlesi YOK ve olamaz: `docs/124` (yedek tatbikatı)
 * §7.4 ve §8 ölçtü — kopya aynı sunucudadır, sunucu dışına kopya yoktur.
 * Sekizinci bölüm bunu okuyucuya kendisi söyler; bir müşterinin bu olguyu
 * kesinti gününde öğrenmesi, sözleşmede okumasından çok daha kötüdür.
 */
final class DataProcessingAgreement
{
    public static function document(SubprocessorInventory $subprocessors): LegalDocument
    {
        return new LegalDocument(
            key: 'data-processing',
            version: '0.1',
            effectiveDate: '2026-09-08',
            title: 'Data Processing Agreement',
            summary: 'How {company.legal_name} processes the personal data you put into Zabuno on your behalf: what is processed and why, who else touches it, where it is kept, how it is protected, and what happens when you ask for it back or ask for it to be deleted.',
            sections: array_merge(
                [
                    new LegalSection('The parties and what this agreement is', [
                        'This Data Processing Agreement (the "DPA") is concluded between you, the business that uses Zabuno (the "controller"), and {company.legal_name}, registered at {company.address}, MERSIS number {company.mersis}, tax office {company.tax_office}, tax number {company.tax_number} (the "processor"). You can reach the processor at {company.email} or {company.phone}.',
                        'It applies whenever the processor handles personal data on your behalf while providing the service described in the Terms of Service. It forms part of that agreement and does not replace it. For your own personal data as an account holder the processor is the controller, and the Privacy Policy and the Personal Data Protection Disclosure apply instead.',
                        'This text is written to satisfy Article 12 of the Turkish Personal Data Protection Law No. 6698 and, where the General Data Protection Regulation applies to you, Article 28 of that Regulation. Where the two require different wording, the stricter requirement applies.',
                    ]),
                    new LegalSection('Subject matter, nature, purpose and duration', [
                        'Subject matter and nature: hosting and running your workspace, storing and publishing the menu you build, serving the published menu to your guests through a stable QR address, delivering the e-mails the service sends on your behalf, and, where you switch them on, ordering, guest ratings, team membership and AI-assisted menu import.',
                        'Purpose: providing the service you subscribed to, and nothing else. The processor does not use the data you put into the service for its own purposes, does not sell it, and does not use it to train models of its own.',
                        'Duration: for as long as your account exists, and afterwards only for the period described in section 11.',
                    ]),
                    new LegalSection('Categories of data subjects and of personal data', [
                        'Data subjects: the people in your team whom you invite to your workspace, the guests who open a menu you have published, and the people who write to you or to the processor through the contact form.',
                        'Team members: name, e-mail address, the role you give them, and the network address and browser identifier recorded while they are logged in.',
                        'Business contact details you enter yourself: the name, address, contact e-mail, phone number and opening hours of the business, which you publish deliberately.',
                        'Menu content you enter yourself: categories, items, prices, allergens, descriptions and the images you upload. Where you choose to put a person into that content — a photograph of a member of staff, for example — that person becomes a data subject too, and it is your decision that put them there.',
                        'Guests: which menu was opened, which items were viewed or searched for, and, where you switch those features on, the order or the rating. Guests are not asked for a name or an account. Visits are counted with a pseudonymous key derived from the network address, the browser identifier and the date; the key cannot be turned back into an address and it changes every day.',
                        'No special category of personal data is asked for by the service, and none should be entered into it.',
                    ]),
                    new LegalSection('Your instructions', [
                        'The processor processes personal data only on your documented instructions. Using the service is itself an instruction: what you build, publish, export, delete or switch on in your workspace is what the processor does with the data. Anything beyond that needs an instruction in writing.',
                        'The processor will tell you if it believes an instruction breaks data protection law, and may refuse to carry it out. Where the law obliges the processor to process data in another way — a court order, for example — it will tell you before doing so unless the law forbids that.',
                    ]),
                    new LegalSection('Confidentiality and who can see your data', [
                        'Inside your workspace, who can see and change what is decided by the roles you give your team; the processor does not widen them for you.',
                        'On the processor\'s side, the people who can reach production data are the ones who operate the service. They are bound to confidentiality. Access happens for support, for fixing a fault and for keeping the service running, and it is limited to what the task needs.',
                        'This service does not yet keep an account-level audit trail that you can read yourself. Media operations inside a workspace are recorded and can be reviewed there; a wider trail is planned but is not in place today, and this text does not pretend otherwise.',
                    ]),
                ],
                self::subprocessorSections($subprocessors),
                [
                    new LegalSection('Where data is processed', [
                        'The data you put into the service is stored on the server described in the subprocessor list above. Where that server is outside Turkey, the transfer abroad is made on the conditions set by Article 9 of Law No. 6698, and, where the General Data Protection Regulation applies to you, on the conditions set by its Chapter V.',
                        'Some subprocessors named above operate their own systems, and where those systems are is set out in their own terms rather than measured by this service. That is stated plainly in the list rather than guessed at.',
                        'There is no arrangement today under which data is kept inside Turkey only. If your organisation needs that, say so before you subscribe: it is a question about where the service runs, not a setting inside it.',
                    ]),
                    new LegalSection('Security measures in place today', [
                        'Passwords are stored only as cryptographic hashes and cannot be read back. Connections to the service are encrypted in transit.',
                        'Provider credentials (e-mail, payment and AI keys) are held encrypted, and the part of the application that serves web pages cannot read them back at all; only the components that make the calls can.',
                        'Each workspace is isolated from every other, and what a member may do inside one is decided by their role.',
                        'Uploaded files are quarantined and scanned before they are published. A file that fails the scan is rejected. A file that cannot be scanned — because the scanner is not available in that environment — is held and is not published; it is never marked as scanned.',
                        'Public endpoints are rate limited, so a form or a QR address cannot be hammered without being slowed down.',
                        'Files that are subject to a dispute can be put under a legal hold inside a workspace, which blocks deletion including by bulk operations, and the reason for the hold is recorded with it.',
                        'Consent records are append-only: they are written when consent is given and are not rewritten afterwards, so which text was accepted, and when, stays provable.',
                        'No third-party measurement tool is loaded until a visitor accepts it, and the content security policy blocks tools that are not switched on.',
                    ]),
                    new LegalSection('Security measures that are NOT in place — read this before you rely on the section above', [
                        'There is no ISO 27001 certificate, no SOC 2 report and no external security audit. If a supplier questionnaire asks for one, the answer today is that there is none.',
                        'Backups are kept on the same server as the service itself. There is no copy outside that server today. This is a deliberate, temporary decision by the operator, and its consequence has to be said plainly: if that server were lost entirely, the backups would be lost with it. Restoring from a backup after a normal fault is exercised by an automated drill; surviving the loss of the whole machine is not something this service can claim today.',
                        'There is no point-in-time recovery. The best that a restore can recover to is the moment of the last dump.',
                        'There is no availability measurement and no public status page, so no availability figure is committed anywhere. The Service Level Terms explain what would have to exist before one could be.',
                        'These gaps are listed here rather than left out because a supplier questionnaire answered from the section above alone would be answered wrongly.',
                    ]),
                    new LegalSection('Helping you answer the people whose data it is', [
                        'If a team member or a guest exercises a right under Article 11 of Law No. 6698 — or Chapter III of the General Data Protection Regulation — and writes to the processor instead of to you, the processor will not answer for you. It will pass the request on to you and help you answer it, using the access you already have.',
                        'Much of that help needs no request at all: you can read, correct and delete the content of your workspace yourself, and you can export your menu as a CSV file from the workspace whenever you want.',
                    ]),
                    new LegalSection('Personal data breaches', [
                        'If the processor becomes aware of a breach affecting personal data it processes for you, it will tell you without undue delay, describe what is known, what is affected and what is being done, and keep you informed as more becomes known.',
                        'No notification deadline in hours is stated here, because none is measured or promised today. The obligations that Law No. 6698 and, where it applies, the General Data Protection Regulation place on you as controller are unaffected by that and are yours to meet.',
                    ]),
                    new LegalSection('Return and deletion of data', [
                        'You can export your menu as a CSV file from your workspace at any time, without asking anyone.',
                        'You can ask for your account to be deleted through the contact form. What the processor is not legally required to keep is then removed. What the law requires it to keep — commercial and tax records, and the consent records that prove what was accepted — is kept for the period the law sets and for no other purpose.',
                        'No deletion period in days is stated here, because none is measured today. When a deletion timetable is committed, it will be written into this text with its own version number, and this sentence will say so.',
                        'When a subscription ends without the account being closed, nothing is deleted: the workspace, the menus and the data stay as they are and the published menus keep being served, as the Cancellation and Refund Policy sets out.',
                    ]),
                    new LegalSection('Information and audit', [
                        'The processor will give you the information you reasonably need to show that this DPA is being met, and will answer a supplier questionnaire from what is actually measured — including the gaps in section 8.',
                        'An on-site audit is not offered as a standing right today, because there is no audit programme to receive one. If your organisation requires one, raise it before you subscribe so that it can be agreed separately rather than assumed.',
                    ]),
                    new LegalSection('Changes, order of precedence and law', [
                        'This DPA is part of the Terms of Service. If a term of the Terms of Service conflicts with this DPA about the processing of personal data, this DPA prevails.',
                        'The version number and effective date at the top of this page identify the current text. A change to the subprocessor list is not a change to this text: that list is generated from the running configuration of the service each time this page is opened, so what you read is what is in use at that moment.',
                        'This DPA is governed by the laws of the Republic of Turkey, together with the Terms of Service.',
                    ]),
                ],
            ),
            requiresSellerIdentity: true,
        );
    }

    /**
     * Alt işleyen bölümü — ÖLÇÜLEN listeden üretilir.
     *
     * Envanter okunamadıysa (kasa düştü) bölüm bunu SÖYLER ve kısa bir liste
     * göstermez: eksik bir alt işleyen listesi, hiç liste olmamasından daha
     * yanıltıcıdır.
     *
     * @return list<LegalSection>
     */
    private static function subprocessorSections(SubprocessorInventory $inventory): array
    {
        $paragraphs = [
            'The processor uses the following subprocessors. This list is not typed by hand: it is generated from the configuration this deployment is actually running when you open this page, so a provider that has no credentials in the vault is not listed, because it is not processing anything today.',
        ];

        if ($inventory->vaultUnreadable) {
            /*
                KASA OKUNAMADI. Kısa bir liste göstermek, "başka kimse yok"
                demekle aynı kapıya çıkardı — ve bir DPA'da söylenebilecek en
                yanlış cümle budur.
            */
            $paragraphs[] = 'The credential store could not be read while this page was being generated, so the list below is incomplete: it names only what can be determined without it. This is stated rather than hidden. Reload the page, and if it says the same thing again, ask the processor for the list in writing before you rely on it.';
        }

        foreach ($inventory->active as $subprocessor) {
            $paragraphs[] = $subprocessor->sentence();
        }

        $paragraphs[] = 'The processor will tell you before adding a subprocessor that handles the personal data it processes for you, so that you can object. If you object on reasonable data protection grounds and no alternative can be agreed, you may end the paid plan; what is refunded is governed by the Cancellation and Refund Policy, and nothing in this DPA adds to or takes away from it.';
        $paragraphs[] = 'The processor stays responsible to you for what a subprocessor does with the data it processes on your behalf. Whether a written data processing agreement has been concluded with a particular subprocessor is a question the processor will answer in writing on request; this text does not assert one that has not been signed.';

        return [new LegalSection('Subprocessors', $paragraphs)];
    }
}
