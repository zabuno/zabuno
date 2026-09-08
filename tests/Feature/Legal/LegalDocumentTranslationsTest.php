<?php

declare(strict_types=1);

namespace Tests\Feature\Legal;

use App\Application\Legal\Port\LegalLibraryPort;
use App\Application\Legal\Port\SubprocessorRegistryPort;
use App\Domain\Legal\CompanyProfile;
use App\Domain\Legal\DependencyInventory;
use App\Domain\Legal\DependencyLicense;
use App\Domain\Legal\ServiceLevelCommitment;
use App\Domain\Legal\SubprocessorInventory;
use App\Infrastructure\Legal\Documents\Turkish\DataProcessingAgreement;
use App\Infrastructure\Legal\Documents\Turkish\ServiceLevelTerms;
use App\Infrastructure\Legal\Documents\Turkish\ThirdPartyLicenses;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class LegalDocumentTranslationsTest extends TestCase
{
    public static function documents(): array
    {
        return array_map(static fn (string $key): array => [$key], LegalLibraryPort::KEYS);
    }

    #[DataProvider('documents')]
    public function test_turkish_documents_preserve_identity_structure_and_company_tokens(string $key): void
    {
        $library = app(LegalLibraryPort::class);
        $en = $library->find($key);
        $tr = $library->find($key, 'tr');
        self::assertSame('tr', $tr->language);
        self::assertSame('en', $en->language);
        foreach (['key', 'version', 'effectiveDate', 'requiresSellerIdentity'] as $field) {
            self::assertSame($en->$field, $tr->$field);
        }
        self::assertSame($en, $library->find($key, 'en'));
        self::assertSame($tr, $library->find($key, 'tr'));
        self::assertSame($en, $library->find($key, 'de'));
        self::assertNotSame($en->title, $tr->title);
        self::assertNotSame($en->summary, $tr->summary);
        self::assertCount(count($en->sections), $tr->sections);
        foreach ($en->sections as $i => $section) {
            self::assertCount(count($section->paragraphs), $tr->sections[$i]->paragraphs);
        }
        $tokens = static function ($document): array {
            preg_match_all('/\{company\.[a-z_]+\}/', json_encode($document), $matches);
            sort($matches[0]);

            return $matches[0];
        };
        self::assertSame($tokens($en), $tokens($tr));
        $filled = $tr->withCompany(CompanyProfile::fromArray([]));
        self::assertStringNotContainsString('{company.', json_encode($filled));
        self::assertStringNotContainsString(CompanyProfile::NOT_PROVIDED, json_encode($filled));
    }

    #[DataProvider('documents')]
    public function test_turkish_legal_routes_use_the_selected_language_and_keep_review_warning(string $key): void
    {
        config(['i18n.shipped_locales' => ['en', 'tr'], 'legal.reviewed_at' => null]);
        $document = app(LegalLibraryPort::class)->find($key, 'tr');
        $this->withHeader('Accept-Language', 'tr')->get('/'.$key)->assertOk()
            ->assertSee('lang="tr"', false)
            ->assertSee($document->title)
            ->assertSee('data-legal-review="pending"', false);
    }

    public function test_turkish_seller_identity_warning_and_noindex_remain_when_facts_are_missing(): void
    {
        config(['i18n.shipped_locales' => ['en', 'tr'], 'legal.company' => [], 'legal.reviewed_at' => null]);
        $this->withHeader('Accept-Language', 'tr')->get('/distance-sales')->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('data-legal-incomplete="seller-identity"', false)
            ->assertSee('henüz belirtilmedi')
            ->assertSee('data-legal-review="pending"', false);
    }

    public function test_dynamic_sla_values_are_preserved_and_partial_configuration_promises_nothing(): void
    {
        config(['sla.availability_target_percent' => '98.75', 'sla.measurement_source' => 'https://status.example.test',
            'sla.incident_notification_minutes' => 37, 'sla.service_credit_percent' => 12]);
        $commitment = ServiceLevelCommitment::fromConfig();
        $tr = ServiceLevelTerms::document($commitment);
        $text = json_encode($tr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        foreach (['98.75', 'https://status.example.test', '37 dakika', '%12'] as $value) {
            self::assertStringContainsString($value, $text);
        }
        config(['sla.service_credit_percent' => 0]);
        self::assertStringContainsString('hizmet kredisi ödenmez', ServiceLevelTerms::creditSentence(ServiceLevelCommitment::fromConfig()));
        config(['sla.measurement_source' => null]);
        $partial = ServiceLevelCommitment::fromConfig();
        $text = json_encode(ServiceLevelTerms::document($partial), JSON_UNESCAPED_UNICODE);
        self::assertStringNotContainsString('98.75', $text);
        self::assertStringContainsString('measurement_source', $text);
        self::assertStringContainsString('taahhüt edilmiyor', $text);
    }

    public function test_license_inventory_preserves_identifiers_and_translates_missing_information(): void
    {
        $inventory = new DependencyInventory('PHP', 'composer.json', [
            new DependencyLicense('vendor/known', '1.2.3', 'MIT'),
            new DependencyLicense('vendor/unknown', '', null),
        ], 42);
        $document = ThirdPartyLicenses::document([$inventory]);
        $text = json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        foreach (['vendor/known 1.2.3 — MIT', 'composer.json', '42', 'vendor/unknown sürüm kaydedilmemiş — kilit dosyasında lisans belirtilmemiş'] as $value) {
            self::assertStringContainsString($value, $text);
        }
        $unreadable = new DependencyInventory('PHP', 'composer.json', [], 0, false);
        self::assertStringContainsString('okunamadığı', json_encode(ThirdPartyLicenses::document([$unreadable]), JSON_UNESCAPED_UNICODE));
    }

    public function test_dynamic_hosting_facts_survive_localization_without_translating_the_configured_values(): void
    {
        config(['legal.hosting.provider' => 'Configured Host', 'legal.hosting.location' => 'Test City, Country']);
        $registry = app(SubprocessorRegistryPort::class);
        $en = $registry->inventory();
        $tr = $registry->inventory('tr');
        self::assertCount(count($en->active), $tr->active);
        self::assertSame('Configured Host', $tr->active[0]->name);
        self::assertSame('Test City, Country', $tr->active[0]->location);
        self::assertStringStartsWith('Barındırma:', $tr->active[0]->role);
        self::assertStringStartsWith('Hosting:', $en->active[0]->role);
        $document = DataProcessingAgreement::document(
            new SubprocessorInventory($tr->active, true),
        );
        $text = json_encode($document, JSON_UNESCAPED_UNICODE);
        self::assertStringContainsString('Görülen veriler:', $text);
        self::assertStringContainsString('liste eksiktir', $text);
        self::assertStringContainsString('Configured Host', $text);
        self::assertStringNotContainsString('Data seen:', $text);
    }

    public function test_every_library_entry_is_available_in_turkish_and_source_language_is_still_default(): void
    {
        $library = app(LegalLibraryPort::class);
        self::assertSame(LegalLibraryPort::KEYS, array_map(static fn ($document) => $document->key, $library->all('tr')));
        self::assertSame(['tr'], array_values(array_unique(array_map(static fn ($document) => $document->language, $library->all('tr')))));
        self::assertSame(['en'], array_values(array_unique(array_map(static fn ($document) => $document->language, $library->all()))));
        self::assertNull($library->find('unknown', 'tr'));
        foreach (['distance-sales', 'pre-information', 'delivery'] as $key) {
            $en = $library->find($key);
            $last = $en->sections[array_key_last($en->sections)];
            self::assertStringContainsString('A Turkish translation is also available.', $last->paragraphs[0]);
            self::assertStringNotContainsString('not published yet', $last->paragraphs[0]);
        }
        self::assertStringContainsString('recorded as accepted', json_encode($library->find('distance-sales')));
        self::assertStringContainsString('recorded as confirmed', json_encode($library->find('pre-information')));
    }

    public function test_localized_missing_company_value_preserves_configured_facts(): void
    {
        $company = CompanyProfile::fromArray(['legal_name' => 'Örnek A.Ş.']);
        self::assertSame('Örnek A.Ş. / henüz belirtilmedi', $company->fill('{company.legal_name} / {company.address}', 'tr'));
        self::assertSame('Örnek A.Ş. / not yet provided', $company->fill('{company.legal_name} / {company.address}'));
    }
}
