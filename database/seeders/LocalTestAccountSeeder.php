<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Entitlement\Entitlement;
use App\Domain\Platform\PlatformRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Yerel geliştirme/QA test hesabını yeniden kurar.
 *
 * Neden var: test hesabı yalnız yerel veritabanında yaşar. `migrate:fresh`,
 * yeni bir worktree veya herhangi bir veritabanı sıfırlaması hesabı siler ve
 * geliştirici "hesap yine kayboldu" durumuyla kalır. Bu seeder o hesabı tek
 * komutla geri getirir.
 *
 * Parola burada saklanmaz. `LOCAL_TEST_ACCOUNT_EMAIL` ve
 * `LOCAL_TEST_ACCOUNT_PASSWORD` ortam değişkenlerinden okunur;
 * `scripts/restore-local-test-account.sh` bunları macOS Keychain'den doldurur.
 *
 * Fail-closed: bilinen parolalı bir hesap üretim ortamında asla oluşturulmaz.
 */
final class LocalTestAccountSeeder extends Seeder
{
    private const ALLOWED_ENVIRONMENTS = ['local', 'testing', 'development'];

    /**
     * Kimlik bilgileri verilmiş mi? DatabaseSeeder bunu kullanarak, ortam
     * değişkenleri yokken (CI gibi) hesabı sessizce atlar; doğrudan çağrıldığında
     * seeder yine katı davranır ve eksik bilgiyle çalışmayı reddeder.
     */
    public static function isConfigured(): bool
    {
        return (string) (env('LOCAL_TEST_ACCOUNT_EMAIL') ?? '') !== ''
            && (string) (env('LOCAL_TEST_ACCOUNT_PASSWORD') ?? '') !== '';
    }

    public function run(): void
    {
        $environment = (string) app()->environment();

        if (! in_array($environment, self::ALLOWED_ENVIRONMENTS, true)) {
            throw new RuntimeException(
                "LocalTestAccountSeeder yalnız yerel ortamlarda çalışır; geçerli ortam: {$environment}."
            );
        }

        $email = (string) (env('LOCAL_TEST_ACCOUNT_EMAIL') ?? '');
        $password = (string) (env('LOCAL_TEST_ACCOUNT_PASSWORD') ?? '');

        if ($email === '' || $password === '') {
            throw new RuntimeException(
                'LOCAL_TEST_ACCOUNT_EMAIL ve LOCAL_TEST_ACCOUNT_PASSWORD tanımlı olmalı '
                .'(scripts/restore-local-test-account.sh bunları Keychain\'den doldurur).'
            );
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->name = $user->name ?: (string) (env('LOCAL_TEST_ACCOUNT_NAME') ?? 'Local Test Owner');
        $user->password = Hash::make($password);
        $user->email_verified_at = $user->email_verified_at ?: now();
        $user->save();

        $this->grantPlatformSuperAdmin((int) $user->getKey());
        $this->ensureLocalPlanAndSubscriptions();

        $this->command?->info("Yerel test hesabı hazır: {$user->email} (id={$user->id}, doğrulanmış, platform süper yöneticisi).");
    }

    /**
     * Yerel hesaba platform (geliştirici) paneli erişimi verir.
     *
     * Neden gerekliydi: `/platform` rotası her zaman vardı ama
     * `EnsurePlatformSuperAdmin` numaralandırmaya karşı güvenli davranıp
     * yetkisiz herkese ÇIPLAK 404 döndürür. Yerel veritabanında hiç kimseye
     * bu rol atanmadığı için panel "yok" gibi görünüyordu — oysa çalışıyordu
     * ve kapı kimseyi içeri almıyordu.
     *
     * 404'ün kendisi doğrudur ve değiştirilmez: 403 dönmek, panelin var
     * olduğunu yabancıya doğrulardı. Düzeltilmesi gereken şey, yerel
     * kurulumun geliştiriciye kendi panelini açmamasıydı.
     *
     * Bu metot yalnız yerel ortamlarda çalışır: `run()` başında ortam
     * kontrolü zaten fail-closed davranır.
     */
    /**
     * Yerel workspace'lere plan verir — yoksa ürünün üçte biri görünmez.
     *
     * Bulunma biçimi: sahibi Analytics ekranını açtı ve "Analytics failed to
     * load. Please try again." gördü. Sunucu aslında 402 döndürüyordu — plan
     * bu yeteneği içermiyordu. Yerel veritabanında SIFIR plan ve SIFIR
     * abonelik vardı, dolayısıyla Analytics, toplu QR ve ekip daveti kalıcı
     * olarak kapalıydı.
     *
     * Bu bir ürün kusuru değil, YEREL KURULUM eksiğiydi: geliştirme ortamı
     * ürünün tamamını çalıştırabilmeli. Aynı sınıftan bir eksik platform
     * rolünde de vardı.
     *
     * Plan `local-dev` kodunu taşır ve BÜTÜN yetenekleri içerir; bu bir
     * fiyatlandırma kararı değil, geliştirme kolaylığıdır. Üretim planları
     * geliştirici panelinden yönetilir.
     */
    private function ensureLocalPlanAndSubscriptions(): void
    {
        $entitlements = array_map(
            static fn (Entitlement $entitlement): string => $entitlement->value,
            Entitlement::cases(),
        );

        $planId = (int) (DB::table('plans')->where('code', 'local-dev')->value('id') ?? 0);

        if ($planId === 0) {
            $planId = (int) DB::table('plans')->insertGetId([
                'name' => 'Local development (all features)',
                'code' => 'local-dev',
                'version' => 1,
                'is_active' => true,
                'sort_order' => 0,
                'entitlements' => json_encode($entitlements, JSON_THROW_ON_ERROR),
                'amount_minor' => null,
                'currency' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('plans')->where('id', $planId)->update([
                'entitlements' => json_encode($entitlements, JSON_THROW_ON_ERROR),
                'is_active' => true,
                'updated_at' => now(),
            ]);
        }

        // Her workspace abone edilir: geliştirici hangi workspace'i açarsa
        // açsın ürünün tamamını görmeli.
        foreach (DB::table('workspaces')->pluck('id') as $workspaceId) {
            DB::table('subscriptions')->updateOrInsert(
                ['workspace_id' => (int) $workspaceId],
                [
                    'plan_id' => $planId,
                    'state' => 'active',
                    'ends_at' => now()->addYears(5),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        $this->command?->info('Yerel plan ve abonelikler hazır: bütün yetenekler açık.');
    }

    private function grantPlatformSuperAdmin(int $userId): void
    {
        DB::table('platform_role_assignments')->updateOrInsert(
            ['user_id' => $userId],
            [
                'role' => PlatformRole::SuperAdmin->value,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }
}
