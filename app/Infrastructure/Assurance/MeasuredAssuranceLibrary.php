<?php

declare(strict_types=1);

namespace App\Infrastructure\Assurance;

use App\Application\Assurance\Port\AssuranceLibraryPort;
use App\Application\Legal\Port\SubprocessorRegistryPort;
use App\Domain\Assurance\AssuranceClaim;
use App\Domain\Assurance\AssuranceSection;
use App\Domain\Assurance\AssuranceStatement;
use App\Domain\Assurance\ClaimState;
use App\Domain\Legal\ServiceLevelCommitment;
use App\Infrastructure\Legal\Documents\ServiceLevelTerms;
use App\Infrastructure\Legal\Documents\Turkish\ServiceLevelTerms as TurkishServiceLevelTerms;
use App\Support\Contact\ResponseCommitment;

/**
 * Güven merkezi ve erişilebilirlik beyanı — ÖLÇÜLENDEN DERLENİR (FF-252,
 * `docs/107` Faz 3).
 *
 * ═══ İKİ SAYFA, TEK DERLEYİCİ ═══
 *
 * İkisi de aynı soruyu farklı bir alıcıya sorar: *"neyi biliyorsunuz, neyi
 * bilmiyorsunuz, ve hangisi hangisi?"* Birincisini bir zincirin satın alma
 * birimi sorar, ikincisini bir erişilebilirlik denetçisi — ama cevabın
 * DÜRÜSTLÜK KURALI aynıdır ve tek bir yerde durması, ikisinin bir gün
 * ayrışmasını imkânsız kılar.
 *
 * ═══ NE ELLE YAZILDI, NE YAZILMADI ═══
 *
 * ELLE YAZILMAYAN:
 *   · Alt işleyen listesi — `SubprocessorRegistryPort` (kasadan ölçülür).
 *     Bu sayfa İKİNCİ BİR LİSTE TUTMAZ; DPA ile aynı porttan aynı cümleleri
 *     çizer (`Subprocessor::sentence()`).
 *   · Barındırma — aynı portun ilk satırı (`config/legal.php#hosting`).
 *   · Hizmet seviyesi — `config/sla.php` + `ServiceLevelCommitment`, ve
 *     cümleleri `ServiceLevelTerms`ten (aynı sözcükler, tek kaynak).
 *   · Destek yanıt süresi — `config/support.php` + `ResponseCommitment`.
 *
 * ELLE YAZILAN: kapıların ADLARI ve ne ölçtükleri. Bunlar bir insan
 * kararıdır; ama UYDURULAMAZLAR, çünkü `AssuranceHonestyGateTest` her
 * kanıtın depoda gerçekten var olduğunu ölçer.
 *
 * ═══ NE YAZILMADI VE NEDEN ═══
 *
 * Sertifika, denetim raporu, rozet, müşteri adı, çalışma süresi rakamı,
 * "yakında" vaadi. Hiçbiri ölçülemez ve hiçbirine sahip değiliz. Sahip
 * OLMADIĞIMIZI söyleyen cümle ise yazıldı ve `ClaimState::NotHeld` taşıyor:
 * bir standardın adının geçebildiği tek hâl odur.
 */
final class MeasuredAssuranceLibrary implements AssuranceLibraryPort
{
    public function __construct(
        private readonly SubprocessorRegistryPort $subprocessors,
        private readonly ResponseCommitment $support,
    ) {}

    public function trustCentre(string $locale = 'en'): AssuranceStatement
    {
        $locale = $locale === 'tr' ? 'tr' : 'en';
        $commitment = ServiceLevelCommitment::fromConfig();

        return new AssuranceStatement(
            key: 'trust',
            language: $locale === 'tr' ? 'tr' : 'en',
            title: $this->text('Trust centre', $locale),
            summary: $this->text('What this service can show you before you buy: where your data is kept, who else touches it, what is committed about availability, how the backup drill is measured, and what is not measured at all.', $locale),
            sections: [
                $this->trustReadingSection($locale),
                $this->certificationSection($locale),
                $this->hostingSection($locale),
                $this->subprocessorSection($locale),
                $this->serviceLevelSection($commitment, $locale),
                $this->backupSection($locale),
                $this->securityEvidenceSection($locale),
                $this->beforeYouBuySection($locale),
            ],
        );
    }

    public function accessibilityStatement(string $locale = 'en'): AssuranceStatement
    {
        $locale = $locale === 'tr' ? 'tr' : 'en';

        return new AssuranceStatement(
            key: 'accessibility',
            language: $locale === 'tr' ? 'tr' : 'en',
            title: $this->text('Accessibility statement', $locale),
            summary: $this->text('What is measured about the accessibility of this service and by which gate, what is known to be wrong, what has not been measured at all, and how to tell us about something we missed.', $locale),
            sections: [
                $this->accessibilityReadingSection($locale),
                $this->accessibilityMeasuredSection($locale),
                $this->accessibilityKnownGapSection($locale),
                $this->accessibilityUnmeasuredSection($locale),
                $this->accessibilityFeedbackSection($locale),
            ],
        );
    }

    // ── GÜVEN MERKEZİ ────────────────────────────────────────────────────

    private function trustReadingSection(string $locale = 'en'): AssuranceSection
    {
        return new AssuranceSection(
            heading: $this->text('How to read this page', $locale),
            paragraphs: [
                $this->text('Every answer on this page is drawn from what this installation is actually running, or names the command in the source code that produces it. Nothing on it is a summary somebody typed once and stopped maintaining: the subprocessor list, the hosting row and the service level figures are read again each time you open the page.', $locale),
                $this->text('Where something has not been measured, the page says that in those words. It does not soften the gap with a hedging adjective, because a hedged gap is how an unmeasured thing comes to be read as a measured one.', $locale),
                $this->text('This page is not a contract. The contracts are the Terms of Service, the Data Processing Agreement and the Service Level Terms, each of which has its own version number and effective date. Where this page and one of those differ, the contract is what applies.', $locale),
            ],
        );
    }

    private function certificationSection(string $locale = 'en'): AssuranceSection
    {
        return new AssuranceSection(
            heading: $this->text('Certificates, audit reports and badges', $locale),
            claims: [
                new AssuranceClaim(
                    subject: $this->text('A third-party security certificate or audit report', $locale),
                    state: ClaimState::NotHeld,
                    detail: $this->text('This service holds none. There is no ISO 27001 certificate, no SOC 2 report, no PCI DSS attestation and no equivalent third-party audit, and no badge for one is shown anywhere on this site. If your procurement process requires one, that is a question to settle before you subscribe rather than after.', $locale),
                ),
                new AssuranceClaim(
                    subject: $this->text('Public endpoint monitoring', $locale),
                    state: ClaimState::Measured,
                    detail: $this->text('Public endpoint checks are published at https://zabuno.github.io/status/. Five endpoints have been monitored since 2026-09-08; the figures describe only the recorded period and those endpoint checks. They do not measure complete user journeys or create a contractual availability guarantee. The service level section below identifies the commercial values still missing.', $locale),
                    evidence: 'docs/123-DEVIR-TESLIM-ENVANTERI.md',
                ),
            ],
        );
    }

    private function hostingSection(string $locale = 'en'): AssuranceSection
    {
        $inventory = $this->subprocessors->inventory($locale);

        /*
            BARINDIRMA SATIRI, ALT İŞLEYEN ENVANTERİNİN İLK SATIRIDIR ve
            burada YENİDEN YAZILMAZ. `MeasuredSubprocessors::hosting()` onu
            envanterin başına koyuyor; ikinci bir `config('legal.hosting')`
            okuması, bir gün biçimi değiştiğinde iki farklı cümle üretirdi.
        */
        $hosting = $inventory->active[0] ?? null;

        if ($hosting === null) {
            /*
                ENVANTER BOŞ DÖNEMEZ — barındırma her zaman ilk satırdır.
                Yine de bir gün dönerse sayfa SUSMAZ ve uydurmaz: bilinmeyen
                bir sonuç yeşil gösterilmez.
            */
            return new AssuranceSection(
                heading: $this->text('Where your data is kept', $locale),
                claims: [new AssuranceClaim(
                    subject: $this->text('Hosting location', $locale),
                    state: ClaimState::NotMeasured,
                    detail: $this->text('The hosting row could not be read from this installation\'s configuration when this page was drawn, so this page does not state where the data is kept. Ask, and it will be answered in writing.', $locale),
                )],
            );
        }

        /*
            DEĞER GİRİLMEMİŞ OLABİLİR. `MeasuredSubprocessors` boş bir
            yapılandırma alanını "not yet provided" diye yazar — bu bir
            konum değil, bir eksikliktir ve iddianın hâli onu söylemeli.
        */
        $measuredHosting = $locale === 'tr' ? ($this->subprocessors->inventory()->active[0] ?? null) : $hosting;
        $entered = $measuredHosting !== null
            && ! str_contains($measuredHosting->name, 'not yet provided')
            && ! str_contains($measuredHosting->location, 'not yet provided');

        return new AssuranceSection(
            heading: $this->text('Where your data is kept', $locale),
            paragraphs: [
                $this->text('This is the one processing location this service can measure: the server the application, the database and the uploaded files actually live on. Where a third party keeps the data it receives is set out in that party\'s own terms and is not measured here — the subprocessor list below says so on every row rather than guessing.', $locale),
            ],
            claims: [
                new AssuranceClaim(
                    subject: $this->text('Hosting', $locale),
                    state: $entered ? ClaimState::Measured : ClaimState::NotMeasured,
                    detail: $entered
                        ? $hosting->sentence($locale)
                        : $this->text('The hosting provider or location has not been entered for this installation, so this page names neither. It reads them from the deployment\'s own configuration and shows what is there, which today is nothing.', $locale),
                    evidence: $entered ? 'config/legal.php' : null,
                ),
            ],
        );
    }

    private function subprocessorSection(string $locale = 'en'): AssuranceSection
    {
        $inventory = $this->subprocessors->inventory($locale);

        $paragraphs = [
            $this->text('The list below is not typed by hand. It is generated from the credentials and the measurement settings this installation is running when you open the page, so a provider with no active credential is not listed — because it is not processing anything today. Adding a provider to the software without describing it here breaks the build.', $locale),
        ];

        if ($inventory->vaultUnreadable) {
            /*
                OKUNAMADI, "YOK" DEĞİL. `SubprocessorInventory` bu ayrımı
                taşıyor ve DPA'da da aynı cümle kuruluyor: bir veri işleme
                listesinde "hiçbiri" ile "bakamadık" karıştırılamaz.
            */
            $paragraphs[] = $this->text('The credential store could not be read while this page was drawn, so the list below may be incomplete. That is stated rather than hidden: an empty list and an unread list are not the same sentence, and treating them as one would be the most expensive mistake this page could make.', $locale);
        }

        foreach ($inventory->active as $subprocessor) {
            $paragraphs[] = $subprocessor->sentence($locale);
        }

        $paragraphs[] = $this->text('The measurement container in that list runs in the visitor\'s browser and only after the visitor allows measurement in the cookie choice. This service\'s own servers do not send visitor data to it.', $locale);
        $paragraphs[] = $this->text('The same list, with the contractual wording around it, is in the Data Processing Agreement at /data-processing.', $locale);

        return new AssuranceSection(
            heading: $this->text('Who else touches your data', $locale),
            paragraphs: $paragraphs,
        );
    }

    private function serviceLevelSection(ServiceLevelCommitment $commitment, string $locale = 'en'): AssuranceSection
    {
        $claims = [];

        if ($commitment->isComplete()) {
            $claims[] = new AssuranceClaim(
                subject: $this->text('Availability target', $locale),
                state: ClaimState::Measured,
                detail: $locale === 'tr'
                    ? TurkishAssuranceContent::availability((string) $commitment->availabilityTargetPercent(), (string) $commitment->measurementSource())
                    : 'The Service Level Terms commit to '.$commitment->availabilityTargetPercent().'% availability per calendar month, read from '.$commitment->measurementSource().'.',
                evidence: 'config/sla.php',
            );
        } else {
            $claims[] = new AssuranceClaim(
                subject: $this->text('Availability target', $locale),
                state: ClaimState::NotMeasured,
                detail: $this->text('No availability percentage is committed today. Public endpoint monitoring is available at https://zabuno.github.io/status/, but its recorded observations are not a contractual SLA or a measurement of complete user journeys. Four commercial values must be decided by the operator with legal advice before an availability commitment is stated. Still to be decided: ', $locale).($locale === 'tr' ? TurkishServiceLevelTerms::missingSentence($commitment) : ServiceLevelTerms::missingSentence($commitment)),
            );
        }

        $notification = $commitment->incidentNotificationMinutes();

        $claims[] = new AssuranceClaim(
            subject: $this->text('Incident notification', $locale),
            state: $notification === null ? ClaimState::NotMeasured : ClaimState::Measured,
            detail: $notification === null
                ? $this->text('No notification time has been entered for this installation (incident_notification_minutes), so this page states none. Public endpoint observations can be checked at https://zabuno.github.io/status/; they do not establish a customer notification deadline.', $locale)
                : ($locale === 'tr'
                    ? TurkishAssuranceContent::incident($notification)
                    : 'Once the operator is aware that the service is unavailable, affected customers are told within '.$notification.' minutes, by e-mail to the address on the account. Public endpoint observations can be checked at https://zabuno.github.io/status/; those observations do not measure whether this notification deadline was met.'),
            evidence: $notification === null ? null : 'config/sla.php',
        );

        /*
            HİZMET KREDİSİ CÜMLESİ SAKLANMAZ.

            Sahibin kararı sıfır (`docs/140`) ve sıfır bir boşluk değil bir
            karardır. Söylenmemiş bir "yok" her zaman müşterinin aleyhine
            çalışır: okuyan taraf bir telafi olduğunu varsayar. Cümle
            `ServiceLevelTerms`ten geliyor — güven merkezinin sözleşmeden
            farklı bir şey söylemesi mümkün değil.
        */
        $credit = $commitment->serviceCreditPercent();

        $claims[] = new AssuranceClaim(
            subject: $this->text('Service credit when the target is missed', $locale),
            state: match (true) {
                $credit === null => ClaimState::NotMeasured,
                $credit === 0 => ClaimState::NotHeld,
                default => ClaimState::Measured,
            },
            detail: $locale === 'tr' ? TurkishServiceLevelTerms::creditSentence($commitment) : ServiceLevelTerms::creditSentence($commitment),
            evidence: ($credit === null || $credit === 0) ? null : 'config/sla.php',
        );

        return new AssuranceSection(
            heading: $this->text('Service level', $locale),
            paragraphs: [
                $this->text('The full text, including what is excluded and how availability would be measured, is in the Service Level Terms at /sla. What follows is the same set of facts, in short.', $locale),
            ],
            claims: $claims,
        );
    }

    private function backupSection(string $locale = 'en'): AssuranceSection
    {
        return new AssuranceSection(
            heading: $this->text('Backups: how the drill is measured', $locale),
            paragraphs: [
                $this->text('The operator\'s own rule is that an untested backup is not a backup, so what this service has is not a backup setting but a drill: a command that takes a backup, restores it somewhere isolated, compares what came back with what went in, and records the result.', $locale),
                $this->text('For the database, the drill opens a repeatable-read transaction, exports a snapshot, counts the rows and digests their content from that snapshot, then archives the same snapshot. It restores the archive into a throwaway database created for the run, compares table list, row counts and content digest against the source, then drops the copy and deletes the archive. Nothing is ever written to the source database.', $locale),
                $this->text('For uploaded files, the drill archives the media root, unpacks it somewhere isolated and compares the files that came back with the files that went in. A corrupted archive fails the drill rather than passing it quietly.', $locale),
                $this->text('A drill has three outcomes and all three are recorded. "Passed" means the comparison held. "Failed" means a step broke or the comparison did not hold. "Unknown" means the drill could not be attempted at all — the tool was missing, the client was older than the server, the source could not be read. "Unknown" is never reported as a pass, and the command does not exit successfully while claiming it.', $locale),
            ],
            claims: [
                new AssuranceClaim(
                    subject: $this->text('The backup and restore drill', $locale),
                    state: ClaimState::Measured,
                    detail: $this->text('One command runs both drills and appends one evidence record each, recording the driver, the row counts, the digest of what was backed up, the digest of what was restored, the archive size and the timings. It exits successfully only when every selected drill passed.', $locale),
                    evidence: 'artisan security:evidence:backup-restore',
                ),
                new AssuranceClaim(
                    subject: $this->text('A drill result for the installation you would be buying', $locale),
                    state: ClaimState::NotMeasured,
                    detail: $this->text('This page publishes no drill result. A drill writes its record into the installation it runs in, and this page does not read that record — a result printed here would be a snapshot of one moment, with no way for you to tell when it was taken. Ask for the latest record in writing and you will be told what it says, including if it says "unknown".', $locale),
                ),
            ],
        );
    }

    private function securityEvidenceSection(string $locale = 'en'): AssuranceSection
    {
        return new AssuranceSection(
            heading: $this->text('Security evidence you can run yourself', $locale),
            paragraphs: [
                $this->text('This software is open source, so the checks below are not claims you have to take on trust: they are commands in the repository, and you can read what they do and run them against an installation of your own.', $locale),
            ],
            claims: [
                new AssuranceClaim(
                    subject: $this->text('Tenant isolation', $locale),
                    state: ClaimState::Measured,
                    detail: $this->text('Runs the frozen tenant-isolation suite — the tests that check one workspace cannot read, list or act on another workspace\'s data — and appends one evidence record with the outcome. It exits unsuccessfully when the suite fails, so it cannot record a pass it did not get.', $locale),
                    evidence: 'artisan security:evidence:tenant-isolation',
                ),
                new AssuranceClaim(
                    subject: $this->text('Host capability', $locale),
                    state: ClaimState::Measured,
                    detail: $this->text('Probes the server the software is installed on for the capabilities the product depends on, records one evidence row and prints the degradation plan — which features run in reduced form on that host and which do not run at all. A missing capability is reported as a planned degradation rather than as a failure, because a host that lacks one is a fact to state, not a fault to hide.', $locale),
                    evidence: 'artisan platform:evidence:host-capability',
                ),
                new AssuranceClaim(
                    subject: $this->text('The results of those commands, published here', $locale),
                    state: ClaimState::NotMeasured,
                    detail: $this->text('This page names what each command measures. It does not print what they returned on any particular installation, for the same reason the backup section does not: a result with no date on it is read as a permanent property, and it is not one.', $locale),
                ),
            ],
        );
    }

    private function beforeYouBuySection(string $locale = 'en'): AssuranceSection
    {
        return new AssuranceSection(
            heading: $this->text('Questions this page cannot answer', $locale),
            paragraphs: [
                $this->text('Three things are decided by a person, not by this page, and each of them is worth settling before you subscribe rather than after: an availability commitment with a figure in it, a data processing agreement signed with your own organisation named in it, and any certificate or audit report your procurement process requires.', $locale),
                $this->text('The identity of the seller — the legal entity, the registered address and the tax details — is on the About page at /about, drawn from the same configuration as the contracts. The way to ask any of the above is the contact form at /contact, which stores your message and gives you a reference to quote.', $locale),
            ],
        );
    }

    // ── ERİŞİLEBİLİRLİK BEYANI ───────────────────────────────────────────

    private function accessibilityReadingSection(string $locale = 'en'): AssuranceSection
    {
        return new AssuranceSection(
            heading: $this->text('What this statement says, and what it refuses to say', $locale),
            paragraphs: [
                $this->text('This statement is split three ways on purpose: what is measured and by which gate, what is known to be wrong, and what has not been measured at all. Anything in the third group is unknown. It is not "probably fine", and this page will not describe it that way.', $locale),
                $this->text('Every measured line below names the file or the command that measures it. This software is open source: you can read the gate and run it yourself rather than take the line on trust.', $locale),
            ],
            claims: [
                new AssuranceClaim(
                    subject: $this->text('A conformance claim against an accessibility standard', $locale),
                    state: ClaimState::NotHeld,
                    detail: $this->text('None is made here. A sentence such as "WCAG 2.1 AA compliant" means that the whole product has been audited against that standard by someone qualified to do it, and recorded. That has not happened for this service. Writing it anyway is the most common untruth on pages of this kind, and it is the one thing this page exists to avoid.', $locale),
                ),
            ],
        );
    }

    private function accessibilityMeasuredSection(string $locale = 'en'): AssuranceSection
    {
        return new AssuranceSection(
            heading: $this->text('What is measured, and by which gate', $locale),
            paragraphs: [
                $this->text('These run against a real layout engine or a real browser, not a simulated one. That distinction matters: the repository\'s unit tests run in an environment that computes no layout at all, where no box has a height, nothing overflows and no touch target can be measured. Questions about size and overflow cannot be asked there and this statement does not pretend they were.', $locale),
            ],
            claims: [
                new AssuranceClaim(
                    subject: $this->text('Touch target size, horizontal overflow and clipping on a narrow screen', $locale),
                    state: ClaimState::Measured,
                    detail: $this->text('Pages are opened in a real browser at 320 by 480 and again at wider sizes, and checked for: horizontal overflow, any interactive element clipped past the edge, touch targets smaller than 44 by 44, neighbouring targets closer than 8 pixels apart, silently truncated text, and content squeezed into a narrow strip by nested padding. The same run is repeated with the layout reversed, for the two supported languages that read right to left. Every interface component is measured this way on every change; the assembled corporate pages are measured with the same tool when they change, which is not the same thing and is why it is said here rather than rounded up.', $locale),
                    evidence: 'scripts/mobile-ux-audit',
                ),
                new AssuranceClaim(
                    subject: $this->text('Reduced motion — the rule', $locale),
                    state: ClaimState::Measured,
                    detail: $this->text('When the device asks for reduced motion, the page has to be complete rather than diminished: the same sections, the same links, the same headings, and nothing hidden behind an entrance animation. A gate reads the stylesheets and fails if any declaration that starts motion sits outside the reduced-motion guard, so a single forgotten line cannot slip through by looking harmless.', $locale),
                    evidence: 'tests/Feature/PublicSite/SceneContractTest.php',
                ),
                new AssuranceClaim(
                    subject: $this->text('Reduced motion, increased contrast and forced colours — visual signatures', $locale),
                    state: ClaimState::Measured,
                    detail: $this->text('The visual gate captures selected pages and display states in a browser, with screenshots 900 milliseconds apart. It compares coarse 16×12 brightness signatures: static checks fail when signature movement exceeds zero, while reference comparisons allow a 12/255 difference. These checks detect some motion and visual regressions; they do not compare every pixel or prove that every accessibility requirement is met.', $locale),
                    evidence: 'scripts/scene-visual-gate',
                ),
                new AssuranceClaim(
                    subject: $this->text('Text contrast in the corporate palette', $locale),
                    state: ClaimState::Measured,
                    detail: $this->text('Configured palette pairs are computed from the stylesheet and checked against their specified minimum ratios, including 7:1 for the configured body-text pairs. This does not cover every rendered combination or every local desktop colour token. Semi-transparent colours depend on the background behind them and are not covered by these opaque-token checks.', $locale),
                    evidence: 'tests/Feature/PublicSite/CorporateIdentityContrastTest.php',
                ),
                new AssuranceClaim(
                    subject: $this->text('Right-to-left layout', $locale),
                    state: ClaimState::Measured,
                    detail: $this->text('Two of the nine languages the software supports are written right to left, and every physical direction in a stylesheet is reversed for them: a left padding lands at the end of the sentence rather than the start. A gate scans the stylesheets, the templates and the help articles for physical direction properties and classes, and requires the logical equivalents instead.', $locale),
                    evidence: 'scripts/logical-direction-gate',
                ),
                new AssuranceClaim(
                    subject: $this->text('Roles, names and keyboard behaviour of the interface components', $locale),
                    state: ClaimState::Measured,
                    detail: $this->text('Every story of every catalogue component is rendered and run through an accessibility rule engine, and the build fails on any violation. Colour contrast is switched off in that run and measured separately at token level, because the environment it runs in computes no colour.', $locale),
                    evidence: 'resources/js/design-system/a11y.guard.test.tsx',
                ),
                new AssuranceClaim(
                    subject: $this->text('Minimum touch target in the component catalogue', $locale),
                    state: ClaimState::Measured,
                    detail: $this->text('A separate contract holds the interface components to the 44 by 44 minimum, so a control does not fall below it between the browser measurements.', $locale),
                    evidence: 'resources/js/design-system/touch-target.contract.test.tsx',
                ),
            ],
        );
    }

    private function accessibilityKnownGapSection(string $locale = 'en'): AssuranceSection
    {
        return new AssuranceSection(
            heading: $this->text('What is known to be wrong', $locale),
            paragraphs: [
                $this->text('These are measured findings that are still open. They are listed with the same weight as the measured lines above, because a gap that is only recorded in an internal file is a gap the reader never learns about.', $locale),
            ],
            claims: [
                new AssuranceClaim(
                    subject: $this->text('Links inside a sentence are smaller than 44 by 44', $locale),
                    state: ClaimState::KnownGap,
                    detail: $this->text('Links written into the middle of a sentence are the height of the line of text they sit in rather than 44 pixels: "Contact us" in the shared block that shows the plans, which appears on every page carrying that block, and "Write to us" on the help page. Making them 44 pixels tall would break the paragraph they are part of. The finding is left open and named here rather than closed by a rule that hides it — and it is written as a shape rather than a list of pages, because a list would be out of date the next time that block appears somewhere new.', $locale),
                    evidence: 'scripts/mobile-ux-audit',
                ),
                new AssuranceClaim(
                    subject: $this->text('Two links sit closer together than the minimum gap', $locale),
                    state: ClaimState::KnownGap,
                    detail: $this->text('On the pricing page, two neighbouring links are 7 pixels apart on a narrow screen where the minimum is 8. Seven pixels rather than eight is a small number, and it is written here for the same reason the larger gaps are: a threshold that is quietly rounded down once is a threshold that stops being one.', $locale),
                    evidence: 'scripts/mobile-ux-audit',
                ),
                new AssuranceClaim(
                    subject: $this->text('Small targets frozen in the component catalogue', $locale),
                    state: ClaimState::KnownGap,
                    detail: $this->text('A checkbox control and a text link in the component catalogue are recorded as below the minimum, together with some layouts that waste horizontal space on a narrow screen. They are held in a baseline file so the count can only go down, never up — but they are debt, and the baseline is a record of debt rather than a pass.', $locale),
                    evidence: 'scripts/mobile-ux-audit.baseline.json',
                ),
                new AssuranceClaim(
                    subject: $this->text('Limits of automated coverage', $locale),
                    state: ClaimState::KnownGap,
                    detail: $this->text('The CI workflow runs the scene visual gate for selected pages and states, alongside automated checks, the logical-direction scan and component catalogue checks. That coverage is bounded: it does not exercise every page, every browser or every user journey, and a passing run does not prove that all visual or accessibility defects are absent.', $locale),
                    evidence: '.github/workflows/ci.yml',
                ),
            ],
        );
    }

    private function accessibilityUnmeasuredSection(string $locale = 'en'): AssuranceSection
    {
        return new AssuranceSection(
            heading: $this->text('What has not been measured', $locale),
            paragraphs: [
                $this->text('Nothing in this section should be read as working. It should be read as unknown.', $locale),
            ],
            claims: [
                new AssuranceClaim(
                    subject: $this->text('Use with a screen reader by a person who uses one', $locale),
                    state: ClaimState::NotMeasured,
                    detail: $this->text('No session with a screen reader user has been run against this service. Automated rule engines check the parts of a screen reader experience that a machine can see — that a control has a name, a role and a reachable label. They do not check whether the resulting page is usable, and nothing here has checked that.', $locale),
                ),
                new AssuranceClaim(
                    subject: 'iOS, Safari and VoiceOver',
                    state: ClaimState::NotMeasured,
                    detail: $this->text('Every browser measurement behind this page was taken in one browser engine, on one machine, and there is no Apple device in that setup. Several things this interface relies on behave differently there. Nobody has looked, so the answer is unknown rather than likely.', $locale),
                ),
                new AssuranceClaim(
                    subject: $this->text('Speech input, switch access and screen magnification', $locale),
                    state: ClaimState::NotMeasured,
                    detail: $this->text('None of these has been exercised against this service.', $locale),
                ),
                new AssuranceClaim(
                    subject: $this->text('The rest of the corporate site', $locale),
                    state: ClaimState::NotMeasured,
                    detail: $this->text('The browser measurements cover the pages that are published today. The site map holds several hundred further addresses that are written but not published; none of them has been measured, and none of them is reachable, so this statement covers what you can actually open and says nothing about the rest.', $locale),
                ),
                new AssuranceClaim(
                    subject: $this->text('The workspace application and the guest menu', $locale),
                    state: ClaimState::NotMeasured,
                    detail: $this->text('The component catalogue behind those screens is measured, component by component. The assembled screens are not measured as whole pages in a real browser the way the corporate pages are. A page can fail where none of its parts does.', $locale),
                ),
            ],
        );
    }

    private function accessibilityFeedbackSection(string $locale = 'en'): AssuranceSection
    {
        $commitment = ServiceLevelCommitment::fromConfig();
        $notification = $commitment->incidentNotificationMinutes();
        $supportHours = $this->support->hours();

        $claims = [];

        $claims[] = new AssuranceClaim(
            subject: $this->text('How to report something', $locale),
            state: ClaimState::Measured,
            detail: $this->text('Use the contact form at /contact. It needs no account, because somebody who cannot use the interface may not be able to sign in to report that. It stores the message and gives you a reference to quote. If the form itself is the thing that is broken for you, the seller\'s e-mail address is on the About page at /about.', $locale),
            evidence: 'app/Http/Controllers/PublicSite/StoreContactMessageController.php',
        );

        $claims[] = new AssuranceClaim(
            subject: $this->text('How quickly you are answered', $locale),
            state: $supportHours === null ? ClaimState::NotHeld : ClaimState::Measured,
            detail: $supportHours === null
                ? $this->text('No support response time is committed by this service, so this statement does not promise one. A phrase such as "as soon as possible" would be a promise nobody made, and it is left out for the same reason the availability figure is.', $locale)
                : ($locale === 'tr'
                    ? TurkishAssuranceContent::support($supportHours)
                    : 'Support answers within '.$supportHours.' hours. That commitment is set for this installation and is the same figure shown on the contact page and in the acknowledgement e-mail you receive.'),
            evidence: $supportHours === null ? null : 'config/support.php',
        );

        $claims[] = new AssuranceClaim(
            subject: $this->text('The one timing this service does commit to', $locale),
            state: $notification === null ? ClaimState::NotMeasured : ClaimState::Measured,
            detail: $notification === null
                ? $this->text('The incident notification time has not been entered for this installation, so there is no committed timing to point at here either.', $locale)
                : ($locale === 'tr'
                    ? TurkishAssuranceContent::accessibilityIncident($notification)
                    : 'When the service is unavailable, affected customers are told within '.$notification.' minutes of the operator becoming aware of it. That is a commitment about an outage and this statement does not stretch it to cover an accessibility report — the two are different kinds of problem and pretending otherwise would be borrowing a promise that was made for something else.'),
            evidence: $notification === null ? null : 'config/sla.php',
        );

        return new AssuranceSection(
            heading: $this->text('Tell us what is broken', $locale),
            paragraphs: [
                $this->text('A report that names the page, the device and what happened is worth more than any gate on this list, because the gates only measure what somebody thought to measure.', $locale),
            ],
            claims: $claims,
        );
    }

    private function text(string $english, string $locale): string
    {
        return $locale === 'tr' ? TurkishAssuranceContent::text($english) : $english;
    }
}
