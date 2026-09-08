<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal\Documents;

use App\Domain\Legal\LegalDocument;
use App\Domain\Legal\LegalSection;
use App\Domain\Legal\ServiceLevelCommitment;

/**
 * Hizmet Seviyesi Koşulları (SLA) — İNGİLİZCE KAYNAK METİN (FF-228,
 * `docs/107` Faz 3.2, `docs/140` §4).
 *
 * ═══ BU PAKETİN DÜRÜSTLÜK SINAVI ═══
 *
 * Bir SLA bir TAAHHÜTTÜR. Bugün elimizde çalışma süresi ölçümü yok — durum
 * sayfası kurulmadı (`docs/107` Faz 3.4) ve üretimde tek bir
 * kullanılabilirlik kaydı bulunmuyor. Bu koşulda "%99,9" yazmak, ilk
 * kesintide sözleşmeye aykırılık üretecek bir sayı uydurmaktır.
 *
 * Bu yüzden belge İKİ HÂLDE yaşar ve hâli `config/sla.php`'nin doluluğu
 * belirler (`ServiceLevelCommitment`):
 *
 * 1. **RAKAM YOK (bugün).** Belge yapıyı kurar — kapsam, ölçüm yöntemi,
 *    hariç tutulanlar, bildirim, telafi — ve ikinci bölümde neyin taahhüt
 *    EDİLMEDİĞİNİ, hangi yapılandırma anahtarının boş olduğunu ve o
 *    anahtarı kimin doldurması gerektiğini adıyla sayar. Sayfa hiçbir yerde
 *    yüzde işareti taşımaz.
 * 2. **RAKAM VAR.** Aynı bölümler, sahibin girdiği değerlerle taahhüt
 *    cümlesine dönüşür.
 *
 * `SUPPORT_RESPONSE_COMMITMENT_HOURS` ile birebir aynı desen
 * (`ResponseCommitment`, `docs/125` §3): değer yoksa cümle de yoktur, yedek
 * bir cümle bilerek yazılmaz — çünkü yedek cümle de bir vaattir ve kimse
 * onu vermedi.
 *
 * Başlık "Agreement" değil "Terms": rakamsız bir belgeyi "Service Level
 * Agreement" diye adlandırmak, adının kendisiyle olmayan bir taahhüdü ima
 * ederdi. Adres `/sla` KALIR — bir zincirin satın alma birimi o kelimeyi
 * arar.
 */
final class ServiceLevelTerms
{
    public static function document(ServiceLevelCommitment $commitment): LegalDocument
    {
        return new LegalDocument(
            key: 'sla',
            version: '0.1',
            effectiveDate: '2026-09-08',
            title: 'Service Level Terms',
            summary: $commitment->isComplete()
                ? 'What availability {company.legal_name} commits to for paid Zabuno plans, how it is measured, what is excluded, how you are told about an incident and what you get back when the target is missed.'
                : 'How availability would be measured, what would be excluded, and how an incident would be notified and compensated — together with a plain statement that no availability figure is committed today, and what has to exist before one can be.',
            sections: array_merge(
                [
                    new LegalSection('Scope', [
                        'These Service Level Terms are issued by {company.legal_name} and apply to paid Zabuno plans. They cover the workspace application and the published menu pages that the service serves at their public addresses.',
                        'They do not cover the free parts of the service, a self-hosted installation of this software, your own internet connection or device, or a third-party system the service depends on but does not operate.',
                        'These terms are part of the Terms of Service and do not replace them. Cancellation and refunds stay in the Cancellation and Refund Policy.',
                    ]),
                ],
                self::commitmentSections($commitment),
                [
                    new LegalSection('How availability is measured', [
                        'Availability is measured over a calendar month, as the share of the month in which the service answers requests to its public addresses correctly.',
                        'A period counts as unavailable when the service does not answer, or answers with a server error, for reasons within the operator\'s control. A single failed request is not an outage; a period in which the service is not answering is.',
                        'The measurement has to be readable by you, not only by the operator. A figure that a customer cannot check is a statement, not a commitment, which is why these terms name the public source the figure is read from — and why, until that source exists, no figure is committed.',
                    ]),
                    new LegalSection('What is excluded', [
                        'Planned maintenance that is announced in advance.',
                        'Failures of something you control: your internet connection, your device, your browser, or a change you made in your own workspace.',
                        'Failures at a third party the service depends on — the hosting provider, the payment service provider, the e-mail delivery provider, a configured AI provider or a network operator — where the cause is that party\'s and not the operator\'s.',
                        'Suspension of an account for a breach of the Terms of Service or the Acceptable Use Policy, and any period in which a payment for the plan has not been received.',
                        'Events outside reasonable control, including natural disaster, war, government action, strike or a general failure of the internet.',
                    ]),
                    new LegalSection('Incidents and how you are told about them', [
                        'When the operator becomes aware that the service is unavailable, it works to restore it and tells the affected customers what is known, what is affected and what is being done.',
                        'Notice is sent to the contact e-mail address on the account. There is no public status page today, so an incident cannot be looked up anywhere; that gap is named here rather than left for you to discover during one.',
                    ]),
                    new LegalSection('What you get when the target is missed', [
                        'The remedy under these terms is a service credit against the fee for the affected month, applied to a following period. It is not a cash refund, and it does not change what the Cancellation and Refund Policy says about refunds.',
                        'A service credit is claimed by writing to {company.email} from the address on the account, within thirty days of the end of the month in question, saying which month is claimed for. The operator answers with the measurement for that month.',
                        'A service credit is the only remedy these terms provide for missing the availability target. Rights you have under law, and the liability terms in the Terms of Service, are unaffected.',
                    ]),
                    new LegalSection('Support', [
                        'Support is reached through the contact form on this site and by e-mail. Where a response time has been committed, it is shown on the contact page and in the acknowledgement e-mail you receive; where none is shown, none has been committed, and these terms do not create one.',
                    ]),
                    new LegalSection('Changes to these terms', [
                        'The version number and effective date at the top of this page identify the current text. If a figure is set, added or changed, the version number changes with it, so what applied to a given month stays provable.',
                    ]),
                ],
            ),
            requiresSellerIdentity: true,
        );
    }

    /**
     * Taahhüt bölümü — rakam varsa taahhüt, yoksa EKSİKLİĞİN ifadesi.
     *
     * Bu metot bilerek metnin İKİNCİ bölümünü üretir: bir okuyucu kapsamı
     * okuduktan hemen sonra, telafi tablosunu aramaya gitmeden önce, neyin
     * taahhüt edilip edilmediğini görmeli.
     *
     * @return list<LegalSection>
     */
    private static function commitmentSections(ServiceLevelCommitment $commitment): array
    {
        if (! $commitment->isComplete()) {
            return [new LegalSection('No availability figure is committed today', [
                'Read this section before any other: no availability percentage, no incident notification time and no service credit is committed by these terms today. The sections that follow describe how each of those would work; they do not create one.',
                'The reason is not caution, it is measurement. This service does not measure its own availability: there is no public status page and no availability record, so there is no figure that either side could check. A percentage written without a measurement behind it would be a promise nobody could tell had been kept, and the first outage would turn it into a breach of contract.',
                'Four values have to be decided before a commitment can be published, and each is a commercial decision for the operator of this service, taken with legal advice — not something an engineer can fill in. Still to be decided: '.self::missingSentence($commitment),
                'Until all four are set, this page shows no figure at all, not even the ones already decided. That is deliberate: a page carrying a percentage in one sentence and a disclaimer in another is read as a commitment by everyone who skims it.',
                'If your organisation requires an availability commitment before it can buy, say so before you subscribe. It is a question about what the operator is willing to promise and to measure, and it is answered by a person, not by this page.',
            ])];
        }

        return [new LegalSection('The availability commitment', [
            'The operator commits to making the service available for at least '.$commitment->availabilityTargetPercent().'% of each calendar month, measured as described in the next section.',
            'The measurement is published at: '.$commitment->measurementSource().'. You can read it yourself; you do not have to ask for it.',
            'Where the service is unavailable, the operator will tell the affected customers within '.$commitment->incidentNotificationHours().' hours of becoming aware of it.',
            'Where the target is missed in a calendar month, a service credit of '.$commitment->serviceCreditPercent().'% of that month\'s fee for the affected plan is applied to a following period, on the terms in the section on remedies below.',
        ])];
    }

    /**
     * Eksik alanların ADI — "bir şeyler eksik" bir bilgi değildir.
     *
     * Yapılandırma anahtarını ve ne anlama geldiğini birlikte yazar: sahip
     * sayfayı okuyup hangi değeri nereye gireceğini bilebilmeli.
     */
    private static function missingSentence(ServiceLevelCommitment $commitment): string
    {
        $labels = [
            'availability_target_percent' => 'the availability percentage to be committed for a calendar month',
            'measurement_source' => 'the public address at which that percentage can be read and checked',
            'incident_notification_hours' => 'how quickly affected customers are told about an outage',
            'service_credit_percent' => 'the share of the month\'s fee credited when the target is missed',
        ];

        $missing = [];

        foreach ($commitment->missing() as $field) {
            $missing[] = $labels[$field].' ('.$field.')';
        }

        return implode('; ', $missing).'.';
    }
}
