<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * ASVS V7/V11/V12/V13 doğrulaması — yapılandırma ve oturum sertliği.
 *
 * Bu testler dosyaları okur, çalışan bir üretim sunucusunu değil. Bunu açıkça
 * söylüyoruz: bir `.env.example` doğru olduğu için üretimin doğru olduğu
 * SONUCU ÇIKMAZ. Burada dondurulan şey, ekibin dağıtırken kopyaladığı örneğin
 * güvenli varsayılanı taşımasıdır — yanlış varsayılan, sessizce üretime gider.
 *
 * Requirement ID'leri: ASVS-V7-COOKIE-07, ASVS-V13-DEBUG-08,
 * ASVS-V11-HASH-09, ASVS-V12-TLS-10.
 */
final class SecureConfigurationTest extends TestCase
{
    /** @return array<string, string> */
    private function envExample(string $file): array
    {
        $values = [];

        foreach (file(base_path($file), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (str_starts_with(trim($line), '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $values[trim($key)] = trim($value);
        }

        return $values;
    }

    // --- ASVS-V7-COOKIE-07 ------------------------------------------------

    public function test_deployed_environments_ship_a_secure_encrypted_session_cookie(): void
    {
        foreach (['.env.production.example', '.env.staging.example'] as $file) {
            $env = $this->envExample($file);

            self::assertSame('true', $env['SESSION_SECURE_COOKIE'] ?? null, "ASVS-V7-COOKIE-07: {$file} oturum çerezini HTTPS'e bağlamalı.");
            self::assertSame('true', $env['SESSION_ENCRYPT'] ?? null, "ASVS-V7-COOKIE-07: {$file} oturum içeriğini şifrelemeli — veritabanına düz metin oturum yazılmamalı.");
        }
    }

    public function test_the_session_cookie_is_http_only_and_same_site_by_default(): void
    {
        self::assertTrue(config('session.http_only'), 'ASVS-V7-COOKIE-07: JavaScript oturum çerezini okuyamamalı.');
        self::assertContains(config('session.same_site'), ['lax', 'strict'], 'ASVS-V7-COOKIE-07: çerez siteler arası isteklerde gönderilmemeli.');
    }

    // --- ASVS-V13-DEBUG-08 ------------------------------------------------

    public function test_no_deployed_environment_example_turns_debug_on(): void
    {
        foreach (['.env.production.example', '.env.staging.example'] as $file) {
            $env = $this->envExample($file);

            self::assertSame('false', $env['APP_DEBUG'] ?? null, "ASVS-V13-DEBUG-08: {$file} hata sayfasında yığın izi ve yapılandırma sızdırmamalı.");
        }
    }

    public function test_no_environment_example_carries_a_real_secret(): void
    {
        foreach (['.env.example', '.env.staging.example', '.env.production.example'] as $file) {
            $env = $this->envExample($file);

            foreach (['APP_KEY', 'IYZICO_SANDBOX_API_KEY', 'IYZICO_SANDBOX_SECRET_KEY'] as $secret) {
                self::assertContains(
                    $env[$secret] ?? '',
                    ['', 'null'],
                    "ASVS-V13-DEBUG-08: {$file} içindeki {$secret} boş olmalı; örnek dosyaya yazılan bir sır, sır değildir."
                );
            }
        }
    }

    // --- ASVS-V11-HASH-09 -------------------------------------------------

    public function test_passwords_are_hashed_with_a_deliberate_cost(): void
    {
        self::assertSame('bcrypt', config('hashing.driver'));

        foreach (['.env.production.example', '.env.staging.example'] as $file) {
            $rounds = (int) ($this->envExample($file)['BCRYPT_ROUNDS'] ?? 0);

            self::assertGreaterThanOrEqual(
                12,
                $rounds,
                "ASVS-V11-HASH-09: {$file} maliyeti düşürmemeli — düşük maliyet, çalınan bir tabloyu kırılabilir yapar."
            );
        }
    }

    // --- ASVS-V12-TLS-10 --------------------------------------------------

    public function test_deployed_environments_are_declared_over_https(): void
    {
        foreach (['.env.production.example', '.env.staging.example'] as $file) {
            $url = $this->envExample($file)['APP_URL'] ?? '';

            self::assertStringStartsWith(
                'https://',
                $url,
                "ASVS-V12-TLS-10: {$file} içindeki APP_URL HTTPS olmalı; üretilen bağlantılar (doğrulama e-postası, QR hedefi) buradan türer."
            );
        }
    }
}
