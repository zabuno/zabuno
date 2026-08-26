<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\LocalTestAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

/**
 * Yerel test hesabı seeder'ının güvenlik sözleşmesi.
 *
 * Bu seeder BİLİNEN parolalı bir hesap oluşturur. Tek gerçek riski, yerel
 * olmayan bir ortamda çalışmasıdır — orada bilinen parolalı, doğrulanmış bir
 * hesap doğrudan bir güvenlik açığıdır. Bu yüzden fail-closed davranışı
 * burada dondurulmuştur.
 *
 * Requirement ID'leri: LOCAL-SEED-ENV-GUARD-01, LOCAL-SEED-REQUIRE-CREDS-01,
 * LOCAL-SEED-CREATES-VERIFIED-01.
 */
final class LocalTestAccountSeederTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_EMAIL = 'local-seed-probe@example.test';

    private const TEST_PASSWORD = 'correct-horse-battery-staple-1';

    protected function tearDown(): void
    {
        foreach (['LOCAL_TEST_ACCOUNT_EMAIL', 'LOCAL_TEST_ACCOUNT_PASSWORD', 'LOCAL_TEST_ACCOUNT_NAME'] as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }

        parent::tearDown();
    }

    private function setCredentials(string $email, string $password): void
    {
        foreach (['LOCAL_TEST_ACCOUNT_EMAIL' => $email, 'LOCAL_TEST_ACCOUNT_PASSWORD' => $password] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    // --- LOCAL-SEED-ENV-GUARD-01 -----------------------------------------

    public function test_it_refuses_to_create_a_known_password_account_outside_local_environments(): void
    {
        $this->setCredentials(self::TEST_EMAIL, self::TEST_PASSWORD);
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);

        try {
            (new LocalTestAccountSeeder)->run();
        } finally {
            self::assertNull(
                User::where('email', self::TEST_EMAIL)->first(),
                'LOCAL-SEED-ENV-GUARD-01: üretim ortamında hiçbir hesap yazılmamalı.'
            );
            $this->app['env'] = 'testing';
        }
    }

    // --- LOCAL-SEED-REQUIRE-CREDS-01 --------------------------------------

    public function test_it_refuses_to_run_when_the_credentials_are_not_supplied(): void
    {
        $this->expectException(RuntimeException::class);

        (new LocalTestAccountSeeder)->run();
    }

    // --- LOCAL-SEED-CI-SAFE-01 --------------------------------------------
    // DatabaseSeeder bu bayrağa bakarak hesabı atlar. Bayrak kimlik bilgisi
    // yokken true dönerse CI'daki her `--seed` çağrısı patlar.
    public function test_it_reports_itself_unconfigured_when_credentials_are_absent(): void
    {
        self::assertFalse(
            LocalTestAccountSeeder::isConfigured(),
            'LOCAL-SEED-CI-SAFE-01: kimlik bilgisi yokken seeder yapılandırılmamış sayılmalı.'
        );

        $this->setCredentials(self::TEST_EMAIL, self::TEST_PASSWORD);

        self::assertTrue(
            LocalTestAccountSeeder::isConfigured(),
            'LOCAL-SEED-CI-SAFE-01: kimlik bilgisi verildiğinde yapılandırılmış sayılmalı.'
        );
    }

    // --- LOCAL-SEED-CREATES-VERIFIED-01 -----------------------------------

    public function test_it_creates_a_verified_account_whose_password_actually_works(): void
    {
        $this->setCredentials(self::TEST_EMAIL, self::TEST_PASSWORD);

        (new LocalTestAccountSeeder)->run();

        $user = User::where('email', self::TEST_EMAIL)->first();

        self::assertNotNull($user, 'LOCAL-SEED-CREATES-VERIFIED-01: hesap oluşturulmalı.');
        self::assertTrue(
            $user->hasVerifiedEmail(),
            'LOCAL-SEED-CREATES-VERIFIED-01: doğrulanmamış hesap API\'ye giremez, bu yüzden doğrulanmış olmalı.'
        );
        self::assertTrue(
            Hash::check(self::TEST_PASSWORD, $user->password),
            'LOCAL-SEED-CREATES-VERIFIED-01: kaydedilen parola gerçekten giriş yapabilmeli.'
        );
    }
}
