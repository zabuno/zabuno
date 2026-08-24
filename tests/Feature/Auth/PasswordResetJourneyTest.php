<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Blind RED test candidate for S1-WP02D (password-reset follow-on to
 * WP02A's CORE-01 identity/session journey; docs task instructions PRD-01
 * through PRD-09). No forgot-password/reset-password route, controller,
 * or view exists in this snapshot (routes/web.php defines no
 * forgot-password/reset-password endpoint, and grep confirms zero
 * ResetPassword/forgot-password/reset-password references outside the
 * framework's own vendor code), so every request below is expected to
 * fail RED — either as a clean 404/405 HTTP assertion, or (for the
 * Password::createTokenRepository()/broker helpers used to construct a
 * deterministic token in setup) as a RouteNotFoundException/BindingResolution
 * failure at construction time — until S1-WP02D is implemented.
 *
 * This test does not touch S1-WP02A's IdentitySessionJourneyTest coverage
 * (register/verify/login/logout) and must not regress its historical
 * bounded-green baseline; it only adds forgot-password/reset-password
 * journey coverage.
 */
final class PasswordResetJourneyTest extends TestCase
{
    use RefreshDatabase;

    private const FORGOT_PASSWORD_URI = '/forgot-password';

    private const RESET_PASSWORD_URI = '/reset-password';

    private const LOGIN_URI = '/login';

    private const VALID_PASSWORD = 'correct-horse-battery-staple-1';

    private const NEW_PASSWORD = 'new-correct-horse-battery-2';

    private const TEST_APP_KEY = 'base64:KioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKio=';

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => self::TEST_APP_KEY]);
    }

    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    private function verifiedUser(string $email = 'ada@example.com'): User
    {
        return User::factory()->create([
            'email' => $email,
            'email_verified_at' => now(),
            'password' => Hash::make(self::VALID_PASSWORD),
        ]);
    }

    private function resetTokenFor(User $user): string
    {
        return Password::broker()->createToken($user);
    }

    // --- PRD-01: guest view contracts ---------------------------------

    public function test_forgot_password_view_is_reachable_by_a_guest(): void
    {
        $response = $this->get(self::FORGOT_PASSWORD_URI);

        $response->assertSuccessful();
    }

    public function test_reset_password_view_is_reachable_by_a_guest_with_a_token(): void
    {
        $user = $this->verifiedUser();
        $token = $this->resetTokenFor($user);

        $response = $this->get(self::RESET_PASSWORD_URI.'/'.$token.'?email='.urlencode($user->email));

        $response->assertSuccessful();
    }

    public function test_forgot_password_view_is_unreachable_by_an_authenticated_user(): void
    {
        $user = $this->verifiedUser();

        $response = $this->actingAs($user)->get(self::FORGOT_PASSWORD_URI);

        $response->assertRedirect();
        self::assertNotSame(200, $response->getStatusCode(), 'PRD-01: authenticated kullanıcı guest reset route\'undan uzaklaştırılmalı.');
    }

    // --- PRD-02: enumeration-safe generic response --------------------

    public function test_forgot_password_request_returns_identical_generic_response_for_known_and_unknown_email(): void
    {
        $user = $this->verifiedUser();

        $known = $this->withHeaders($this->jsonHeaders())->post(self::FORGOT_PASSWORD_URI, [
            'email' => $user->email,
        ]);
        $unknown = $this->withHeaders($this->jsonHeaders())->post(self::FORGOT_PASSWORD_URI, [
            'email' => 'no-such-account@example.com',
        ]);

        $known->assertStatus($unknown->getStatusCode());
        self::assertSame(
            $known->getContent(),
            $unknown->getContent(),
            'PRD-02: known/unknown email için account-enumeration önleyici aynı generic yanıt gerekir.'
        );
    }

    public function test_forgot_password_request_dispatches_reset_notification_only_for_known_email(): void
    {
        Notification::fake();

        $user = $this->verifiedUser();

        $this->withHeaders($this->jsonHeaders())->post(self::FORGOT_PASSWORD_URI, [
            'email' => $user->email,
        ]);
        $this->withHeaders($this->jsonHeaders())->post(self::FORGOT_PASSWORD_URI, [
            'email' => 'no-such-account@example.com',
        ]);

        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertCount(1);
    }

    // --- PRD-03: token generation, expiry/throttle config, rate limit --

    public function test_forgot_password_request_endpoint_is_rate_limited_at_six_per_minute(): void
    {
        $user = $this->verifiedUser();

        $baselineFromDistinctIp = $this
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
            ->withHeaders($this->jsonHeaders())
            ->post(self::FORGOT_PASSWORD_URI, [
                'email' => 'baseline-probe@example.com',
            ]);
        $genericSuccessStatus = $baselineFromDistinctIp->getStatusCode();
        self::assertNotSame(429, $genericSuccessStatus, 'PRD-03: baseline probe kendi tek isteğinde throttle edilmemeli.');

        $last = null;
        for ($i = 0; $i < 7; $i++) {
            $last = $this
                ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
                ->withHeaders($this->jsonHeaders())
                ->post(self::FORGOT_PASSWORD_URI, [
                    'email' => $user->email,
                ]);
        }

        $last->assertStatus(429, 'PRD-03: password request endpoint 6/minute named IP limiter taşımalı.');

        $fromDistinctIp = $this
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
            ->withHeaders($this->jsonHeaders())
            ->post(self::FORGOT_PASSWORD_URI, [
                'email' => $user->email,
            ]);

        self::assertNotSame(
            429,
            $fromDistinctIp->getStatusCode(),
            'PRD-03: limiter IP başına olmalı; ayrı bir IP\'den gelen istek ilk IP\'nin throttle\'ından etkilenmemeli.'
        );
        $fromDistinctIp->assertStatus($genericSuccessStatus);
    }

    public function test_reset_token_expiry_and_email_throttle_config_are_preserved(): void
    {
        self::assertSame(60, (int) config('auth.passwords.users.expire'), 'PRD-03: reset token expiry 60dk korunmalı.');
        self::assertSame(60, (int) config('auth.passwords.users.throttle'), 'PRD-03: reset email throttle 60sn korunmalı.');
    }

    // --- PRD-04: valid/invalid/expired/replayed token -------------------

    public function test_valid_token_with_matching_password_and_confirmation_resets_the_password(): void
    {
        $user = $this->verifiedUser();
        $token = $this->resetTokenFor($user);

        $response = $this->withHeaders($this->jsonHeaders())->post(self::RESET_PASSWORD_URI, [
            'token' => $token,
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        $response->assertSuccessful();
        self::assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password), 'PRD-04: geçerli token ile şifre değişmeli.');
    }

    public function test_reset_is_rejected_when_password_confirmation_does_not_match(): void
    {
        $user = $this->verifiedUser();
        $token = $this->resetTokenFor($user);

        $response = $this->withHeaders($this->jsonHeaders())->post(self::RESET_PASSWORD_URI, [
            'token' => $token,
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => 'mismatched-confirmation',
        ]);

        $response->assertStatus(422);
        self::assertTrue(Hash::check(self::VALID_PASSWORD, $user->fresh()->password), 'PRD-04: confirmation uyuşmazlığında şifre değişmemeli.');
    }

    public function test_reset_is_rejected_with_an_invalid_token(): void
    {
        $user = $this->verifiedUser();

        $response = $this->withHeaders($this->jsonHeaders())->post(self::RESET_PASSWORD_URI, [
            'token' => 'not-a-real-token',
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        $response->assertStatus(422);
        self::assertTrue(Hash::check(self::VALID_PASSWORD, $user->fresh()->password), 'PRD-04: invalid token şifreyi değiştirmemeli.');
    }

    public function test_reset_is_rejected_with_an_expired_token(): void
    {
        $user = $this->verifiedUser();
        $token = $this->resetTokenFor($user);

        DB::table('password_reset_tokens')->where('email', $user->email)->update([
            'created_at' => now()->subMinutes(config('auth.passwords.users.expire') + 5),
        ]);

        $response = $this->withHeaders($this->jsonHeaders())->post(self::RESET_PASSWORD_URI, [
            'token' => $token,
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        $response->assertStatus(422);
        self::assertTrue(Hash::check(self::VALID_PASSWORD, $user->fresh()->password), 'PRD-04: süresi dolmuş token şifreyi değiştirmemeli.');
    }

    public function test_reset_token_cannot_be_replayed_after_a_successful_reset(): void
    {
        $user = $this->verifiedUser();
        $token = $this->resetTokenFor($user);

        $this->withHeaders($this->jsonHeaders())->post(self::RESET_PASSWORD_URI, [
            'token' => $token,
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        $replay = $this->withHeaders($this->jsonHeaders())->post(self::RESET_PASSWORD_URI, [
            'token' => $token,
            'email' => $user->email,
            'password' => 'yet-another-password-3',
            'password_confirmation' => 'yet-another-password-3',
        ]);

        $replay->assertStatus(422, 'PRD-04: tüketilmiş token replay edilememeli.');
        self::assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password), 'PRD-04: replay ikinci kez şifreyi değiştirmemeli.');
    }

    // --- PRD-05: post-reset login, session invalidation, no auto-login --

    public function test_old_password_is_rejected_and_new_password_is_accepted_after_reset(): void
    {
        $user = $this->verifiedUser();
        $token = $this->resetTokenFor($user);

        $resetResponse = $this->withHeaders($this->jsonHeaders())->post(self::RESET_PASSWORD_URI, [
            'token' => $token,
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        $resetResponse->assertSuccessful();
        self::assertGuest('web', 'PRD-05: reset isteği başarıyla tamamlandıktan hemen sonra requester guest kalmalı (otomatik login yok).');

        $oldLogin = $this->withHeaders($this->jsonHeaders())->post(self::LOGIN_URI, [
            'email' => $user->email,
            'password' => self::VALID_PASSWORD,
        ]);
        $oldLogin->assertStatus(422);

        $newLogin = $this->withHeaders($this->jsonHeaders())->post(self::LOGIN_URI, [
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
        ]);
        $newLogin->assertSuccessful();
    }

    public function test_reset_rotates_remember_token(): void
    {
        $user = $this->verifiedUser();
        $user->forceFill(['remember_token' => 'stable-remember-token'])->save();
        $token = $this->resetTokenFor($user);

        $this->withHeaders($this->jsonHeaders())->post(self::RESET_PASSWORD_URI, [
            'token' => $token,
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        self::assertNotSame(
            'stable-remember-token',
            $user->fresh()->remember_token,
            'PRD-05: reset sonrası remember_token rotate edilmeli.'
        );
    }

    public function test_reset_invalidates_all_prior_database_sessions_for_the_user(): void
    {
        config(['session.driver' => 'database']);

        $user = $this->verifiedUser();

        DB::table('sessions')->insert([
            'id' => 'prior-session-id',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test-agent',
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->timestamp,
        ]);

        $token = $this->resetTokenFor($user);

        $this->withHeaders($this->jsonHeaders())->post(self::RESET_PASSWORD_URI, [
            'token' => $token,
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        self::assertSame(
            0,
            DB::table('sessions')->where('user_id', $user->id)->count(),
            'PRD-05: reset sonrası kullanıcının önceki database session kayıtları silinmeli (fail-closed iptal).'
        );
    }

    public function test_forgot_password_request_does_not_auto_authenticate_the_requester(): void
    {
        $user = $this->verifiedUser();

        $response = $this->withHeaders($this->jsonHeaders())->post(self::FORGOT_PASSWORD_URI, [
            'email' => $user->email,
        ]);

        self::assertNotSame(404, $response->getStatusCode(), 'PRD-05: /forgot-password route yok, auto-login davranışı henüz test edilemiyor.');
        self::assertGuest();
    }

    // --- PRD-09: no token leakage into response/log --------------------

    public function test_forgot_password_response_never_contains_the_raw_reset_token(): void
    {
        Notification::fake();

        $user = $this->verifiedUser();

        $response = $this->withHeaders($this->jsonHeaders())->post(self::FORGOT_PASSWORD_URI, [
            'email' => $user->email,
        ]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($response) {
            self::assertStringNotContainsString(
                $notification->token,
                (string) $response->getContent(),
                'PRD-09: reset token response body\'sine sızmamalı.'
            );

            return true;
        });
    }
}
