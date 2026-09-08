<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use App\Application\Assurance\Port\AssuranceLibraryPort;
use App\Application\Legal\Port\SubprocessorRegistryPort;
use App\Domain\Assurance\ClaimState;
use App\Domain\Legal\ServiceLevelCommitment;
use App\Infrastructure\Legal\Documents\Turkish\ServiceLevelTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TurkishAssuranceContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_turkish_accessibility_preserves_all_claim_states_and_evidence(): void
    {
        $library = app(AssuranceLibraryPort::class);
        $english = $library->accessibilityStatement();
        $turkish = $library->accessibilityStatement('tr');
        self::assertSame('Erişilebilirlik beyanı', $turkish->title);
        self::assertSame('Accessibility statement', $english->title);
        self::assertSame(count($english->sections), count($turkish->sections));
        foreach ($english->sections as $index => $section) {
            $translated = $turkish->sections[$index];
            self::assertNotSame($section->heading, $translated->heading);
            self::assertCount(count($section->paragraphs), $translated->paragraphs);
            foreach ($section->paragraphs as $i => $paragraph) {
                self::assertNotSame($paragraph, $translated->paragraphs[$i]);
            }
        }
        self::assertCount(count($english->claims()), $turkish->claims());
        foreach ($english->claims() as $index => $claim) {
            $translated = $turkish->claims()[$index];
            self::assertSame($claim->state, $translated->state);
            self::assertSame($claim->evidence, $translated->evidence);
            self::assertNotSame($claim->detail, $translated->detail);
        }
    }

    public function test_both_documents_keep_english_default_and_unknown_locale_fallback(): void
    {
        $library = app(AssuranceLibraryPort::class);
        foreach (['trustCentre', 'accessibilityStatement'] as $method) {
            self::assertEquals($library->$method(), $library->$method('en'));
            self::assertEquals($library->$method(), $library->$method('fr'));
            self::assertSame('tr', $library->$method('tr')->language);
            foreach ($library->$method()->claims() as $index => $claim) {
                $translated = $library->$method('tr')->claims()[$index];
                self::assertSame($claim->state, $translated->state);
                self::assertSame($claim->evidence, $translated->evidence);
                self::assertNotSame($claim->detail, $translated->detail);
            }
        }
    }

    public function test_dynamic_turkish_commitments_keep_numbers_and_canonical_legal_wording(): void
    {
        config(['sla.availability_target_percent' => '99.5', 'sla.measurement_source' => 'https://status.example.test', 'sla.incident_notification_minutes' => 45, 'support.response_commitment_hours' => 12]);
        $library = app(AssuranceLibraryPort::class);
        foreach ([null, 0, 15] as $credit) {
            config(['sla.service_credit_percent' => $credit]);
            $commitment = ServiceLevelCommitment::fromConfig();
            $trust = $library->trustCentre('tr');
            $details = implode(' ', array_map(fn ($claim) => $claim->detail, $trust->claims()));
            self::assertStringContainsString(ServiceLevelTerms::creditSentence($commitment), $details);
            self::assertStringContainsString('45 dakika', $details);
            if ($commitment->isComplete()) {
                self::assertStringContainsString('%99.5', $details);
                self::assertStringContainsString('https://status.example.test', $details);
            } else {
                self::assertStringContainsString(ServiceLevelTerms::missingSentence($commitment), $details);
                self::assertStringNotContainsString('%99.5', $details);
            }
            $accessibility = implode(' ', array_map(fn ($claim) => $claim->detail, $library->accessibilityStatement('tr')->claims()));
            self::assertStringContainsString('12 saat', $accessibility);
            self::assertStringContainsString('45 dakika', $accessibility);
            self::assertStringNotContainsString('minutes', $details.$accessibility);
            self::assertStringNotContainsString('hours', $accessibility);
        }
    }

    public function test_turkish_routes_render_the_document_language_and_translated_body(): void
    {
        config(['i18n.shipped_locales' => ['en', 'tr']]);
        foreach (['/trust' => 'trustCentre', '/accessibility' => 'accessibilityStatement'] as $path => $method) {
            $statement = app(AssuranceLibraryPort::class)->$method('tr');
            $response = $this->withHeader('Accept-Language', 'tr')->get($path);
            $response->assertOk()->assertSee('lang="tr"', false)->assertSee($statement->title)->assertSee($statement->summary);
            foreach ($statement->claims() as $claim) {
                $response->assertSee($claim->detail);
            }
        }
    }

    public function test_hosting_state_uses_the_same_facts_and_translates_provider_sentences(): void
    {
        $library = app(AssuranceLibraryPort::class);
        foreach ([[null, null], ['Example Hosting', 'Frankfurt, Germany']] as [$provider, $location]) {
            config(['legal.hosting.provider' => $provider, 'legal.hosting.location' => $location]);
            $english = $library->trustCentre();
            $turkish = $library->trustCentre('tr');
            $hosting = array_values(array_filter($turkish->claims(), fn ($claim) => $claim->subject === 'Barındırma'));
            self::assertCount(1, $hosting);
            self::assertSame($provider === null ? ClaimState::NotMeasured : ClaimState::Measured, $hosting[0]->state);
            foreach ($english->claims() as $index => $claim) {
                self::assertSame($claim->state, $turkish->claims()[$index]->state);
                self::assertSame($claim->evidence, $turkish->claims()[$index]->evidence);
            }
            $inventory = app(SubprocessorRegistryPort::class)->inventory('tr');
            $paragraphs = implode(' ', array_merge(...array_map(fn ($section) => $section->paragraphs, $turkish->sections)));
            foreach ($inventory->active as $processor) {
                self::assertStringContainsString($processor->sentence('tr'), $paragraphs);
            }
            self::assertStringNotContainsString('Data seen:', $paragraphs);
            self::assertStringNotContainsString('Processing location:', $paragraphs);
        }
    }

    public function test_assurance_describes_endpoint_monitoring_and_coarse_ci_checks_without_guarantees(): void
    {
        $library = app(AssuranceLibraryPort::class);
        foreach (['en', 'tr'] as $locale) {
            $trust = implode(' ', array_map(fn ($claim) => $claim->detail, $library->trustCentre($locale)->claims()));
            $accessibility = implode(' ', array_map(fn ($claim) => $claim->detail, $library->accessibilityStatement($locale)->claims()));
            self::assertStringContainsString('https://zabuno.github.io/status/', $trust);
            self::assertStringContainsString('2026-09-08', $trust);
            self::assertStringContainsString('16×12', $accessibility);
            self::assertStringContainsString('12/255', $accessibility);
            self::assertStringContainsString('900', $accessibility);
            self::assertStringNotContainsString('identical pixel for pixel', $accessibility);
            self::assertStringNotContainsString('There is no status page', $trust);
            self::assertStringNotContainsString('Every ink-on-surface pairing', $accessibility);
            self::assertStringNotContainsString('has not been taken', $accessibility);
        }
    }
}
