<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
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

        $this->command?->info("Yerel test hesabı hazır: {$user->email} (id={$user->id}, doğrulanmış).");
    }
}
