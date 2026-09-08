<?php

declare(strict_types=1);

namespace Tests\Feature\Legal;

use App\Infrastructure\Legal\Documents\CookiePolicy;
use App\Infrastructure\Legal\Documents\Turkish\CookiePolicy as TurkishCookiePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SiteLanguageCookieDisclosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_disclosures_describe_the_optional_language_preference_and_its_lifetime(): void
    {
        foreach ([CookiePolicy::document(), TurkishCookiePolicy::document()] as $document) {
            $rows = array_values(array_filter($document->sections[1]->paragraphs, static fn (string $row): bool => str_starts_with($row, 'zbn_language:')));
            self::assertCount(1, $rows);
            foreach (['365', 'English', 'Türkçe'] as $value) {
                self::assertStringContainsString($value, $rows[0]);
            }
            self::assertStringContainsString($document->language === 'tr' ? 'isteğe bağlı' : 'optional', $rows[0]);
            self::assertStringContainsString($document->language === 'tr' ? 'ölçüm' : 'measurement', $rows[0]);
        }
    }

    public function test_disclosed_cookie_matches_the_native_form_response_on_http_and_https(): void
    {
        config(['i18n.shipped_locales' => ['en', 'tr']]);
        foreach (['http', 'https'] as $scheme) {
            $before = time();
            $response = $this->post($scheme.'://localhost/language', ['language' => 'tr', 'return_to' => '/cookies']);
            $response->assertRedirect('/cookies')->assertPlainCookie('zbn_language', 'tr');
            $cookie = $response->getCookie('zbn_language', false);
            self::assertSame('/', $cookie->getPath());
            self::assertNull($cookie->getDomain());
            self::assertTrue($cookie->isHttpOnly());
            self::assertSame('lax', $cookie->getSameSite());
            self::assertSame($scheme === 'https', $cookie->isSecure());
            self::assertGreaterThanOrEqual($before + 365 * 86400, $cookie->getExpiresTime());
            self::assertLessThanOrEqual(time() + 365 * 86400, $cookie->getExpiresTime());
        }
    }
}
