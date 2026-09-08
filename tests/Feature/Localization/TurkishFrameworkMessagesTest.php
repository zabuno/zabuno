<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

final class TurkishFrameworkMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_public_forms_return_turkish_messages_and_english_stays_unchanged(): void
    {
        foreach (['tr', 'en'] as $locale) {
            $this->withCredentials()->withUnencryptedCookie('zbn_language', $locale);
            foreach (['/contact', '/register', '/login'] as $path) {
                $response = $this->postJson($path, [])->assertUnprocessable();
                self::assertSame($locale === 'tr' ? 'e-posta alanı zorunludur.' : 'The email field is required.', $response->json('errors.email.0'), $locale.' '.$path);
            }
        }
    }

    public function test_installed_framework_keysets_and_placeholders_are_preserved(): void
    {
        foreach (['validation', 'passwords', 'pagination', 'auth'] as $file) {
            $source = $file === 'auth' ? lang_path('en/auth.php') : base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/'.$file.'.php');
            $en = Arr::dot(require $source);
            $tr = Arr::dot(require lang_path('tr/'.$file.'.php'));
            unset($en['attributes'], $tr['attributes']);
            $tr = array_filter($tr, static fn ($key): bool => ! str_starts_with($key, 'attributes.'), ARRAY_FILTER_USE_KEY);
            self::assertSame(array_keys($en), array_keys($tr));
            foreach ($en as $key => $value) {
                preg_match_all('/:[a-z_]+/i', $value, $a);
                preg_match_all('/:[a-z_]+/i', $tr[$key], $b);
                sort($a[0]);
                sort($b[0]);
                self::assertSame($a[0], $b[0], $file.'.'.$key);
            }
        }
    }

    public function test_auth_failures_and_reset_notification_are_translated_without_sending_mail(): void
    {
        $this->withCredentials()->withUnencryptedCookie('zbn_language', 'tr')
            ->postJson('/login', ['email' => 'absent@example.test', 'password' => 'invalid-password'])
            ->assertUnprocessable()->assertJsonPath('errors.email.0', 'Bu bilgiler kayıtlarımızla eşleşmiyor.');
        app()->setLocale('tr');
        $user = new User;
        $user->email = 'preview@example.test';
        $mail = (new ResetPassword('test-token'))->toMail($user);
        self::assertSame('Şifrenizi sıfırlayın', $mail->subject);
        self::assertSame('Şifreyi Sıfırla', $mail->actionText);
        self::assertStringContainsString('dakika', implode(' ', $mail->outroLines));
        $json = json_decode(file_get_contents(lang_path('tr.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($json as $source => $translated) {
            preg_match_all('/:[a-z_]+/i', $source, $a);
            preg_match_all('/:[a-z_]+/i', $translated, $b);
            sort($a[0]);
            sort($b[0]);
            self::assertSame($a[0], $b[0], $source);
            self::assertNotSame($source, $translated);
        }
        app()->setLocale('en');
        self::assertSame('Reset your password', (new ResetPassword('test-token'))->toMail($user)->subject);
    }
}
