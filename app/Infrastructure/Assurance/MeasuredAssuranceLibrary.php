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

    public function trustCentre(): AssuranceStatement
    {
        $commitment = ServiceLevelCommitment::fromConfig();

        return new AssuranceStatement(
            key: 'trust',
            title: 'Trust centre',
            summary: 'What this service can show you before you buy: where your data is kept, who else touches it, what is committed about availability, how the backup drill is measured, and what is not measured at all.',
            sections: [
                $this->trustReadingSection(),
                $this->certificationSection(),
                $this->hostingSection(),
                $this->subprocessorSection(),
                $this->serviceLevelSection($commitment),
                $this->backupSection(),
                $this->securityEvidenceSection(),
                $this->beforeYouBuySection(),
            ],
        );
    }

    public function accessibilityStatement(): AssuranceStatement
    {
        return new AssuranceStatement(
            key: 'accessibility',
            title: 'Accessibility statement',
            summary: 'What is measured about the accessibility of this service and by which gate, what is known to be wrong, what has not been measured at all, and how to tell us about something we missed.',
            sections: [
                $this->accessibilityReadingSection(),
                $this->accessibilityMeasuredSection(),
                $this->accessibilityKnownGapSection(),
                $this->accessibilityUnmeasuredSection(),
                $this->accessibilityFeedbackSection(),
            ],
        );
    }

    // ── GÜVEN MERKEZİ ────────────────────────────────────────────────────

    private function trustReadingSection(): AssuranceSection
    {
        return new AssuranceSection(
            heading: 'How to read this page',
            paragraphs: [
                'Every answer on this page is drawn from what this installation is actually running, or names the command in the source code that produces it. Nothing on it is a summary somebody typed once and stopped maintaining: the subprocessor list, the hosting row and the service level figures are read again each time you open the page.',
                'Where something has not been measured, the page says that in those words. It does not soften the gap with a hedging adjective, because a hedged gap is how an unmeasured thing comes to be read as a measured one.',
                'This page is not a contract. The contracts are the Terms of Service, the Data Processing Agreement and the Service Level Terms, each of which has its own version number and effective date. Where this page and one of those differ, the contract is what applies.',
            ],
        );
    }

    private function certificationSection(): AssuranceSection
    {
        return new AssuranceSection(
            heading: 'Certificates, audit reports and badges',
            claims: [
                new AssuranceClaim(
                    subject: 'A third-party security certificate or audit report',
                    state: ClaimState::NotHeld,
                    detail: 'This service holds none. There is no ISO 27001 certificate, no SOC 2 report, no PCI DSS attestation and no equivalent third-party audit, and no badge for one is shown anywhere on this site. If your procurement process requires one, that is a question to settle before you subscribe rather than after.',
                ),
                new AssuranceClaim(
                    subject: 'A published uptime figure',
                    state: ClaimState::NotHeld,
                    detail: 'There is no status page and no published availability percentage. The section on service level below says exactly which values are missing and who has to decide them.',
                ),
            ],
        );
    }

    private function hostingSection(): AssuranceSection
    {
        $inventory = $this->subprocessors->inventory();

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
                heading: 'Where your data is kept',
                claims: [new AssuranceClaim(
                    subject: 'Hosting location',
                    state: ClaimState::NotMeasured,
                    detail: 'The hosting row could not be read from this installation\'s configuration when this page was drawn, so this page does not state where the data is kept. Ask, and it will be answered in writing.',
                )],
            );
        }

        /*
            DEĞER GİRİLMEMİŞ OLABİLİR. `MeasuredSubprocessors` boş bir
            yapılandırma alanını "not yet provided" diye yazar — bu bir
            konum değil, bir eksikliktir ve iddianın hâli onu söylemeli.
        */
        $entered = ! str_contains($hosting->name, 'not yet provided')
            && ! str_contains($hosting->location, 'not yet provided');

        return new AssuranceSection(
            heading: 'Where your data is kept',
            paragraphs: [
                'This is the one processing location this service can measure: the server the application, the database and the uploaded files actually live on. Where a third party keeps the data it receives is set out in that party\'s own terms and is not measured here — the subprocessor list below says so on every row rather than guessing.',
            ],
            claims: [
                new AssuranceClaim(
                    subject: 'Hosting',
                    state: $entered ? ClaimState::Measured : ClaimState::NotMeasured,
                    detail: $entered
                        ? $hosting->sentence()
                        : 'The hosting provider or location has not been entered for this installation, so this page names neither. It reads them from the deployment\'s own configuration and shows what is there, which today is nothing.',
                    evidence: $entered ? 'config/legal.php' : null,
                ),
            ],
        );
    }

    private function subprocessorSection(): AssuranceSection
    {
        $inventory = $this->subprocessors->inventory();

        $paragraphs = [
            'The list below is not typed by hand. It is generated from the credentials and the measurement settings this installation is running when you open the page, so a provider with no active credential is not listed — because it is not processing anything today. Adding a provider to the software without describing it here breaks the build.',
        ];

        if ($inventory->vaultUnreadable) {
            /*
                OKUNAMADI, "YOK" DEĞİL. `SubprocessorInventory` bu ayrımı
                taşıyor ve DPA'da da aynı cümle kuruluyor: bir veri işleme
                listesinde "hiçbiri" ile "bakamadık" karıştırılamaz.
            */
            $paragraphs[] = 'The credential store could not be read while this page was drawn, so the list below may be incomplete. That is stated rather than hidden: an empty list and an unread list are not the same sentence, and treating them as one would be the most expensive mistake this page could make.';
        }

        foreach ($inventory->active as $subprocessor) {
            $paragraphs[] = $subprocessor->sentence();
        }

        $paragraphs[] = 'The measurement container in that list runs in the visitor\'s browser and only after the visitor allows measurement in the cookie choice. This service\'s own servers do not send visitor data to it.';
        $paragraphs[] = 'The same list, with the contractual wording around it, is in the Data Processing Agreement at /data-processing.';

        return new AssuranceSection(
            heading: 'Who else touches your data',
            paragraphs: $paragraphs,
        );
    }

    private function serviceLevelSection(ServiceLevelCommitment $commitment): AssuranceSection
    {
        $claims = [];

        if ($commitment->isComplete()) {
            $claims[] = new AssuranceClaim(
                subject: 'Availability target',
                state: ClaimState::Measured,
                detail: 'The Service Level Terms commit to '.$commitment->availabilityTargetPercent().'% availability per calendar month, read from '.$commitment->measurementSource().'.',
                evidence: 'config/sla.php',
            );
        } else {
            $claims[] = new AssuranceClaim(
                subject: 'Availability target',
                state: ClaimState::NotMeasured,
                detail: 'No availability percentage is committed today, and none is shown anywhere on this site. This service does not measure its own availability: there is no status page and no availability record, so there is no figure either side could check. Four values have to be decided first, each of them a commercial decision for the operator taken with legal advice. Still to be decided: '.ServiceLevelTerms::missingSentence($commitment),
            );
        }

        $notification = $commitment->incidentNotificationMinutes();

        $claims[] = new AssuranceClaim(
            subject: 'Incident notification',
            state: $notification === null ? ClaimState::NotMeasured : ClaimState::Measured,
            detail: $notification === null
                ? 'No notification time has been entered for this installation (incident_notification_minutes), so this page states none. There is also no public status page, which means an incident cannot be looked up anywhere; that gap is named here rather than left for you to find during one.'
                : 'Once the operator is aware that the service is unavailable, affected customers are told within '.$notification.' minutes, by e-mail to the address on the account. There is no public status page, so an incident cannot be looked up anywhere; that gap is named here rather than left for you to find during one.',
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
            subject: 'Service credit when the target is missed',
            state: match (true) {
                $credit === null => ClaimState::NotMeasured,
                $credit === 0 => ClaimState::NotHeld,
                default => ClaimState::Measured,
            },
            detail: ServiceLevelTerms::creditSentence($commitment),
            evidence: ($credit === null || $credit === 0) ? null : 'config/sla.php',
        );

        return new AssuranceSection(
            heading: 'Service level',
            paragraphs: [
                'The full text, including what is excluded and how availability would be measured, is in the Service Level Terms at /sla. What follows is the same set of facts, in short.',
            ],
            claims: $claims,
        );
    }

    private function backupSection(): AssuranceSection
    {
        return new AssuranceSection(
            heading: 'Backups: how the drill is measured',
            paragraphs: [
                'The operator\'s own rule is that an untested backup is not a backup, so what this service has is not a backup setting but a drill: a command that takes a backup, restores it somewhere isolated, compares what came back with what went in, and records the result.',
                'For the database, the drill opens a repeatable-read transaction, exports a snapshot, counts the rows and digests their content from that snapshot, then archives the same snapshot. It restores the archive into a throwaway database created for the run, compares table list, row counts and content digest against the source, then drops the copy and deletes the archive. Nothing is ever written to the source database.',
                'For uploaded files, the drill archives the media root, unpacks it somewhere isolated and compares the files that came back with the files that went in. A corrupted archive fails the drill rather than passing it quietly.',
                'A drill has three outcomes and all three are recorded. "Passed" means the comparison held. "Failed" means a step broke or the comparison did not hold. "Unknown" means the drill could not be attempted at all — the tool was missing, the client was older than the server, the source could not be read. "Unknown" is never reported as a pass, and the command does not exit successfully while claiming it.',
            ],
            claims: [
                new AssuranceClaim(
                    subject: 'The backup and restore drill',
                    state: ClaimState::Measured,
                    detail: 'One command runs both drills and appends one evidence record each, recording the driver, the row counts, the digest of what was backed up, the digest of what was restored, the archive size and the timings. It exits successfully only when every selected drill passed.',
                    evidence: 'artisan security:evidence:backup-restore',
                ),
                new AssuranceClaim(
                    subject: 'A drill result for the installation you would be buying',
                    state: ClaimState::NotMeasured,
                    detail: 'This page publishes no drill result. A drill writes its record into the installation it runs in, and this page does not read that record — a result printed here would be a snapshot of one moment, with no way for you to tell when it was taken. Ask for the latest record in writing and you will be told what it says, including if it says "unknown".',
                ),
            ],
        );
    }

    private function securityEvidenceSection(): AssuranceSection
    {
        return new AssuranceSection(
            heading: 'Security evidence you can run yourself',
            paragraphs: [
                'This software is open source, so the checks below are not claims you have to take on trust: they are commands in the repository, and you can read what they do and run them against an installation of your own.',
            ],
            claims: [
                new AssuranceClaim(
                    subject: 'Tenant isolation',
                    state: ClaimState::Measured,
                    detail: 'Runs the frozen tenant-isolation suite — the tests that check one workspace cannot read, list or act on another workspace\'s data — and appends one evidence record with the outcome. It exits unsuccessfully when the suite fails, so it cannot record a pass it did not get.',
                    evidence: 'artisan security:evidence:tenant-isolation',
                ),
                new AssuranceClaim(
                    subject: 'Host capability',
                    state: ClaimState::Measured,
                    detail: 'Probes the server the software is installed on for the capabilities the product depends on, records one evidence row and prints the degradation plan — which features run in reduced form on that host and which do not run at all. A missing capability is reported as a planned degradation rather than as a failure, because a host that lacks one is a fact to state, not a fault to hide.',
                    evidence: 'artisan platform:evidence:host-capability',
                ),
                new AssuranceClaim(
                    subject: 'The results of those commands, published here',
                    state: ClaimState::NotMeasured,
                    detail: 'This page names what each command measures. It does not print what they returned on any particular installation, for the same reason the backup section does not: a result with no date on it is read as a permanent property, and it is not one.',
                ),
            ],
        );
    }

    private function beforeYouBuySection(): AssuranceSection
    {
        return new AssuranceSection(
            heading: 'Questions this page cannot answer',
            paragraphs: [
                'Three things are decided by a person, not by this page, and each of them is worth settling before you subscribe rather than after: an availability commitment with a figure in it, a data processing agreement signed with your own organisation named in it, and any certificate or audit report your procurement process requires.',
                'The identity of the seller — the legal entity, the registered address and the tax details — is on the About page at /about, drawn from the same configuration as the contracts. The way to ask any of the above is the contact form at /contact, which stores your message and gives you a reference to quote.',
            ],
        );
    }

    // ── ERİŞİLEBİLİRLİK BEYANI ───────────────────────────────────────────

    private function accessibilityReadingSection(): AssuranceSection
    {
        return new AssuranceSection(
            heading: 'What this statement says, and what it refuses to say',
            paragraphs: [
                'This statement is split three ways on purpose: what is measured and by which gate, what is known to be wrong, and what has not been measured at all. Anything in the third group is unknown. It is not "probably fine", and this page will not describe it that way.',
                'Every measured line below names the file or the command that measures it. This software is open source: you can read the gate and run it yourself rather than take the line on trust.',
            ],
            claims: [
                new AssuranceClaim(
                    subject: 'A conformance claim against an accessibility standard',
                    state: ClaimState::NotHeld,
                    detail: 'None is made here. A sentence such as "WCAG 2.1 AA compliant" means that the whole product has been audited against that standard by someone qualified to do it, and recorded. That has not happened for this service. Writing it anyway is the most common untruth on pages of this kind, and it is the one thing this page exists to avoid.',
                ),
            ],
        );
    }

    private function accessibilityMeasuredSection(): AssuranceSection
    {
        return new AssuranceSection(
            heading: 'What is measured, and by which gate',
            paragraphs: [
                'These run against a real layout engine or a real browser, not a simulated one. That distinction matters: the repository\'s unit tests run in an environment that computes no layout at all, where no box has a height, nothing overflows and no touch target can be measured. Questions about size and overflow cannot be asked there and this statement does not pretend they were.',
            ],
            claims: [
                new AssuranceClaim(
                    subject: 'Touch target size, horizontal overflow and clipping on a narrow screen',
                    state: ClaimState::Measured,
                    detail: 'Pages are opened in a real browser at 320 by 480 and again at wider sizes, and checked for: horizontal overflow, any interactive element clipped past the edge, touch targets smaller than 44 by 44, neighbouring targets closer than 8 pixels apart, silently truncated text, and content squeezed into a narrow strip by nested padding. The same run is repeated with the layout reversed, for the two supported languages that read right to left. Every interface component is measured this way on every change; the assembled corporate pages are measured with the same tool when they change, which is not the same thing and is why it is said here rather than rounded up.',
                    evidence: 'scripts/mobile-ux-audit',
                ),
                new AssuranceClaim(
                    subject: 'Reduced motion — the rule',
                    state: ClaimState::Measured,
                    detail: 'When the device asks for reduced motion, the page has to be complete rather than diminished: the same sections, the same links, the same headings, and nothing hidden behind an entrance animation. A gate reads the stylesheets and fails if any declaration that starts motion sits outside the reduced-motion guard, so a single forgotten line cannot slip through by looking harmless.',
                    evidence: 'tests/Feature/PublicSite/SceneContractTest.php',
                ),
                new AssuranceClaim(
                    subject: 'Reduced motion, increased contrast and forced colours — the pixels',
                    state: ClaimState::Measured,
                    detail: 'A second gate opens each page in a real browser with reduced motion requested, then with increased contrast, then with forced colours active, and takes two screenshots 900 milliseconds apart in each state. It fails unless the two are identical pixel for pixel. Before it existed, those states were written in the stylesheet and never actually looked at — the rule was there and nobody had checked whether it worked.',
                    evidence: 'scripts/scene-visual-gate',
                ),
                new AssuranceClaim(
                    subject: 'Text contrast in the corporate palette',
                    state: ClaimState::Measured,
                    detail: 'Every ink-on-surface pairing in the corporate palette is computed from the stylesheet and checked against a minimum ratio. Body text and its surfaces are held to 7:1 rather than the 4.5:1 minimum, because these pages are read on a phone in daylight. Semi-transparent tokens — glow, glass, shadow — are not checked, because the ratio of a translucent colour depends on whatever is behind it and that is not written in the stylesheet; that is recorded as unknown rather than shown as a pass.',
                    evidence: 'tests/Feature/PublicSite/CorporateIdentityContrastTest.php',
                ),
                new AssuranceClaim(
                    subject: 'Right-to-left layout',
                    state: ClaimState::Measured,
                    detail: 'Two of the nine languages the software supports are written right to left, and every physical direction in a stylesheet is reversed for them: a left padding lands at the end of the sentence rather than the start. A gate scans the stylesheets, the templates and the help articles for physical direction properties and classes, and requires the logical equivalents instead.',
                    evidence: 'scripts/logical-direction-gate',
                ),
                new AssuranceClaim(
                    subject: 'Roles, names and keyboard behaviour of the interface components',
                    state: ClaimState::Measured,
                    detail: 'Every story of every catalogue component is rendered and run through an accessibility rule engine, and the build fails on any violation. Colour contrast is switched off in that run and measured separately at token level, because the environment it runs in computes no colour.',
                    evidence: 'resources/js/design-system/a11y.guard.test.tsx',
                ),
                new AssuranceClaim(
                    subject: 'Minimum touch target in the component catalogue',
                    state: ClaimState::Measured,
                    detail: 'A separate contract holds the interface components to the 44 by 44 minimum, so a control does not fall below it between the browser measurements.',
                    evidence: 'resources/js/design-system/touch-target.contract.test.tsx',
                ),
            ],
        );
    }

    private function accessibilityKnownGapSection(): AssuranceSection
    {
        return new AssuranceSection(
            heading: 'What is known to be wrong',
            paragraphs: [
                'These are measured findings that are still open. They are listed with the same weight as the measured lines above, because a gap that is only recorded in an internal file is a gap the reader never learns about.',
            ],
            claims: [
                new AssuranceClaim(
                    subject: 'Links inside a sentence are smaller than 44 by 44',
                    state: ClaimState::KnownGap,
                    detail: 'Links written into the middle of a sentence are the height of the line of text they sit in rather than 44 pixels: "Contact us" in the shared block that shows the plans, which appears on every page carrying that block, and "Write to us" on the help page. Making them 44 pixels tall would break the paragraph they are part of. The finding is left open and named here rather than closed by a rule that hides it — and it is written as a shape rather than a list of pages, because a list would be out of date the next time that block appears somewhere new.',
                    evidence: 'scripts/mobile-ux-audit',
                ),
                new AssuranceClaim(
                    subject: 'Two links sit closer together than the minimum gap',
                    state: ClaimState::KnownGap,
                    detail: 'On the pricing page, two neighbouring links are 7 pixels apart on a narrow screen where the minimum is 8. Seven pixels rather than eight is a small number, and it is written here for the same reason the larger gaps are: a threshold that is quietly rounded down once is a threshold that stops being one.',
                    evidence: 'scripts/mobile-ux-audit',
                ),
                new AssuranceClaim(
                    subject: 'Small targets frozen in the component catalogue',
                    state: ClaimState::KnownGap,
                    detail: 'A checkbox control and a text link in the component catalogue are recorded as below the minimum, together with some layouts that waste horizontal space on a narrow screen. They are held in a baseline file so the count can only go down, never up — but they are debt, and the baseline is a record of debt rather than a pass.',
                    evidence: 'scripts/mobile-ux-audit.baseline.json',
                ),
                new AssuranceClaim(
                    subject: 'Not every gate above runs on every change',
                    state: ClaimState::KnownGap,
                    detail: 'The automated checks, the reversed-layout scan and the browser measurement of the component catalogue run on every change. The screenshot gate for reduced motion, increased contrast and forced colours does not: it needs a browser on the machine that runs it, and wiring that into the automated pipeline is a separate decision that has not been taken. It has been run and it passed, but a gate that runs when somebody remembers is weaker than one that runs by itself, and calling both of them "measured" without saying so would flatten a real difference.',
                    evidence: '.github/workflows/ci.yml',
                ),
            ],
        );
    }

    private function accessibilityUnmeasuredSection(): AssuranceSection
    {
        return new AssuranceSection(
            heading: 'What has not been measured',
            paragraphs: [
                'Nothing in this section should be read as working. It should be read as unknown.',
            ],
            claims: [
                new AssuranceClaim(
                    subject: 'Use with a screen reader by a person who uses one',
                    state: ClaimState::NotMeasured,
                    detail: 'No session with a screen reader user has been run against this service. Automated rule engines check the parts of a screen reader experience that a machine can see — that a control has a name, a role and a reachable label. They do not check whether the resulting page is usable, and nothing here has checked that.',
                ),
                new AssuranceClaim(
                    subject: 'iOS, Safari and VoiceOver',
                    state: ClaimState::NotMeasured,
                    detail: 'Every browser measurement behind this page was taken in one browser engine, on one machine, and there is no Apple device in that setup. Several things this interface relies on behave differently there. Nobody has looked, so the answer is unknown rather than likely.',
                ),
                new AssuranceClaim(
                    subject: 'Speech input, switch access and screen magnification',
                    state: ClaimState::NotMeasured,
                    detail: 'None of these has been exercised against this service.',
                ),
                new AssuranceClaim(
                    subject: 'The rest of the corporate site',
                    state: ClaimState::NotMeasured,
                    detail: 'The browser measurements cover the pages that are published today. The site map holds several hundred further addresses that are written but not published; none of them has been measured, and none of them is reachable, so this statement covers what you can actually open and says nothing about the rest.',
                ),
                new AssuranceClaim(
                    subject: 'The workspace application and the guest menu',
                    state: ClaimState::NotMeasured,
                    detail: 'The component catalogue behind those screens is measured, component by component. The assembled screens are not measured as whole pages in a real browser the way the corporate pages are. A page can fail where none of its parts does.',
                ),
            ],
        );
    }

    private function accessibilityFeedbackSection(): AssuranceSection
    {
        $commitment = ServiceLevelCommitment::fromConfig();
        $notification = $commitment->incidentNotificationMinutes();
        $supportHours = $this->support->hours();

        $claims = [];

        $claims[] = new AssuranceClaim(
            subject: 'How to report something',
            state: ClaimState::Measured,
            detail: 'Use the contact form at /contact. It needs no account, because somebody who cannot use the interface may not be able to sign in to report that. It stores the message and gives you a reference to quote. If the form itself is the thing that is broken for you, the seller\'s e-mail address is on the About page at /about.',
            evidence: 'app/Http/Controllers/PublicSite/StoreContactMessageController.php',
        );

        $claims[] = new AssuranceClaim(
            subject: 'How quickly you are answered',
            state: $supportHours === null ? ClaimState::NotHeld : ClaimState::Measured,
            detail: $supportHours === null
                ? 'No support response time is committed by this service, so this statement does not promise one. A phrase such as "as soon as possible" would be a promise nobody made, and it is left out for the same reason the availability figure is.'
                : 'Support answers within '.$supportHours.' hours. That commitment is set for this installation and is the same figure shown on the contact page and in the acknowledgement e-mail you receive.',
            evidence: $supportHours === null ? null : 'config/support.php',
        );

        $claims[] = new AssuranceClaim(
            subject: 'The one timing this service does commit to',
            state: $notification === null ? ClaimState::NotMeasured : ClaimState::Measured,
            detail: $notification === null
                ? 'The incident notification time has not been entered for this installation, so there is no committed timing to point at here either.'
                : 'When the service is unavailable, affected customers are told within '.$notification.' minutes of the operator becoming aware of it. That is a commitment about an outage and this statement does not stretch it to cover an accessibility report — the two are different kinds of problem and pretending otherwise would be borrowing a promise that was made for something else.',
            evidence: $notification === null ? null : 'config/sla.php',
        );

        return new AssuranceSection(
            heading: 'Tell us what is broken',
            paragraphs: [
                'A report that names the page, the device and what happened is worth more than any gate on this list, because the gates only measure what somebody thought to measure.',
            ],
            claims: $claims,
        );
    }
}
