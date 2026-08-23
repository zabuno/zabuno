<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domain\Identity\Exception\EmailAlreadyRegisteredException;
use App\Domain\Identity\ValueObject\EmailAddress;
use App\Domain\Identity\ValueObject\HashedPassword;
use App\Infrastructure\Identity\Persistence\EloquentUserRepository;
use App\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Blind RED test candidate for S1-WP02A CORE-01 (docs/33 §11). Frozen scope:
 * register -> verification-pending -> signed/expiring/single-use email
 * verification -> authenticated Sanctum-stateful cookie session -> logout.
 * No S1-WP02A production code exists yet (composer.json carries no
 * laravel/fortify or laravel/sanctum, routes/web.php defines only
 * foundation.status and health); every request below hits a route that does
 * not exist, so every assertion here is expected to fail RED until CORE-01
 * is implemented. Non-signature routes (register/login/logout/resend) follow
 * Laravel Fortify/Sanctum's documented default path conventions (docs/33 §7)
 * rather than route() name lookups, so RED fails as a clean HTTP assertion
 * instead of a RouteNotFoundException. The verification link is the one
 * exception: it is built via URL::temporarySignedRoute('verification.verify',
 * ...) so the signature is cryptographically real; until CORE-01 registers
 * that named route, URL generation itself throws
 * RouteNotFoundException — a RED failure at construction time rather than
 * assertion time, which is still an intentional, unambiguous RED signal.
 *
 * Requirement IDs covered (docs/33 §11): S1WP02A-REG-01/02/03,
 * S1WP02A-VERIFY-01/02/03, S1WP02A-SESSION-01/02, S1WP02A-CSRF-01,
 * S1WP02A-RATE-01, S1WP02A-LOGOUT-01, S1WP02A-MAIL-01, S1WP02A-HOST-01,
 * S1WP02A-AI-01. Non-goals (docs/33 §3 — tenant/RBAC/admin/password-reset/
 * SMS/social/billing/menu/QR) are out of scope and not tested here.
 */
final class IdentitySessionJourneyTest extends TestCase
{
    use RefreshDatabase;

    private const REGISTER_URI = '/register';

    private const LOGIN_URI = '/login';

    private const LOGOUT_URI = '/logout';

    private const VERIFY_RESEND_URI = '/email/verification-notification';

    private const CSRF_COOKIE_URI = '/sanctum/csrf-cookie';

    private const AUTHENTICATED_PROBE_URI = '/api/user';

    private const VALID_PASSWORD = 'correct-horse-battery-staple-1';

    /**
     * Fixed, non-secret test-local key (32 raw bytes of 0x2A, base64-encoded)
     * — same convention as FoundationStatusDeliveryArchitectureTest. phpunit.xml
     * carries no APP_KEY; session/cookie encryption needs one to exercise the
     * 'web' middleware group end-to-end.
     */
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

    private function validVerificationUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );
    }

    private function expiredVerificationUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->subMinutes(1),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );
    }

    private function tamperedVerificationUrl(User $user): string
    {
        $valid = $this->validVerificationUrl($user);
        $fakeSignature = str_repeat('0', 64);

        return (string) preg_replace('/signature=[^&]+/', "signature={$fakeSignature}", $valid);
    }

    private function wrongHashVerificationUrl(User $owner, User $attacker): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $owner->id, 'hash' => sha1($attacker->getEmailForVerification())]
        );
    }

    // --- S1WP02A-REG-01 ------------------------------------------------

    public function test_register_endpoint_creates_pending_verification_account_with_generic_response(): void
    {
        $response = $this->withHeaders($this->jsonHeaders())->post(self::REGISTER_URI, [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
        ]);

        $response->assertSuccessful();
        $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);

        $user = User::query()->where('email', 'ada@example.com')->firstOrFail();
        self::assertNull($user->email_verified_at, 'REG-01: yeni hesap pending_verification olmalı (email_verified_at null).');
    }

    // --- S1WP02A-REG-02 --------------------------------------------------

    public function test_duplicate_email_with_different_case_is_rejected_as_a_single_normalized_account(): void
    {
        Mail::fake();

        $this->withHeaders($this->jsonHeaders())->post(self::REGISTER_URI, [
            'name' => 'Ada Lovelace',
            'email' => 'Ada@Example.COM',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
        ]);

        $second = $this->withHeaders($this->jsonHeaders())->post(self::REGISTER_URI, [
            'name' => 'Impersonator',
            'email' => ' ada@example.com ',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
        ]);

        self::assertSame(
            1,
            User::query()->count(),
            'REG-02: case/whitespace farklı aynı normalize email iki kez kaydolmamalı.'
        );
        $second->assertStatus(422);
        self::assertStringNotContainsString(
            'has already been taken',
            (string) $second->getContent(),
            'REG-02: enumeration-safe generic hata gerekir (docs/15 §1 madde 3), Laravel default unique mesajı sızmamalı.'
        );
    }

    // --- S1WP02A-REG-03 --------------------------------------------------

    public function test_password_is_hashed_and_never_returned_or_stored_as_plaintext(): void
    {
        $response = $this->withHeaders($this->jsonHeaders())->post(self::REGISTER_URI, [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
        ]);

        self::assertStringNotContainsString(self::VALID_PASSWORD, (string) $response->getContent());

        $user = User::query()->where('email', 'ada@example.com')->first();
        self::assertNotNull($user, 'REG-03: hash doğrulanabilmesi için kullanıcı kaydı gerekir.');
        self::assertNotSame(self::VALID_PASSWORD, $user->password);
        self::assertTrue(
            Str::startsWith($user->password, ['$2y$', '$argon2']),
            'REG-03: şifre framework güçlü hash sürücüsüyle saklanmalı, düz metin olmamalı.'
        );
    }

    // --- S1WP02A-VERIFY-01 -------------------------------------------------

    public function test_valid_signed_verification_link_marks_email_verified_and_dispatches_verified_event(): void
    {
        Event::fake([Verified::class]);

        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->withHeaders($this->jsonHeaders())->get($this->validVerificationUrl($user));

        $response->assertSuccessful();
        self::assertNotNull($user->fresh()->email_verified_at, 'VERIFY-01: geçerli imzalı link email_verified_at set etmeli.');
        Event::assertDispatched(Verified::class);
    }

    // --- S1WP02A-VERIFY-02 (expiry + tamper) --------------------------------

    public function test_expired_signed_verification_link_is_rejected_without_state_mutation(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get($this->expiredVerificationUrl($user));

        $response->assertStatus(403);
        self::assertNull($user->fresh()->email_verified_at, 'VERIFY-02: süresi dolmuş link hiçbir state mutasyonu yapmamalı.');
    }

    public function test_tampered_verification_signature_is_rejected_without_state_mutation(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get($this->tamperedVerificationUrl($user));

        $response->assertStatus(403);
        self::assertNull($user->fresh()->email_verified_at, 'VERIFY-02: tampered imza hiçbir state mutasyonu yapmamalı.');
    }

    // --- S1WP02A-VERIFY-03 (replay + wrong-user) ----------------------------

    public function test_replayed_verification_link_is_idempotent_and_does_not_duplicate_verified_event(): void
    {
        Event::fake([Verified::class]);

        $user = User::factory()->unverified()->create();
        $link = $this->validVerificationUrl($user);

        $this->actingAs($user)->withHeaders($this->jsonHeaders())->get($link);
        $replay = $this->actingAs($user)->withHeaders($this->jsonHeaders())->get($link);

        $replay->assertSuccessful();
        Event::assertDispatchedTimes(Verified::class, 1);
    }

    public function test_verification_hash_for_a_different_user_is_rejected(): void
    {
        $owner = User::factory()->unverified()->create();
        $attacker = User::factory()->unverified()->create();

        $response = $this->actingAs($owner)->get($this->wrongHashVerificationUrl($owner, $attacker));

        $response->assertStatus(403);
        self::assertNull($owner->fresh()->email_verified_at, 'wrong-user: id/hash uyuşmazlığında state mutasyonu yasak.');
    }

    // --- S1WP02A-SESSION-01 (+ verified-only route) -------------------------

    public function test_verified_only_route_rejects_unverified_authenticated_session(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->withHeaders($this->jsonHeaders())->get(self::AUTHENTICATED_PROBE_URI);

        $response->assertStatus(403);
    }

    public function test_authenticated_requests_are_accepted_via_stateful_cookie_without_a_bearer_token(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->withHeaders($this->jsonHeaders())->get(self::AUTHENTICATED_PROBE_URI);

        $response->assertSuccessful();
        self::assertArrayNotHasKey('access_token', (array) $response->json());
        self::assertArrayNotHasKey('token', (array) $response->json());
    }

    // --- S1WP02A-SESSION-02 -------------------------------------------------

    public function test_session_id_rotates_across_the_login_state_transition(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->get('/'); // establish a pre-auth session id
        $before = $this->app['session']->getId();

        $response = $this->withHeaders($this->jsonHeaders())->post(self::LOGIN_URI, [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSuccessful();
        self::assertNotSame($before, $this->app['session']->getId(), 'SESSION-02: login durum geçişinde session ID rotate edilmeli (fixation).');
    }

    // --- S1WP02A-CSRF-01 -----------------------------------------------------
    // VerifyCsrfToken::runningUnitTests() bypasses CSRF enforcement whenever
    // app()->environment() reports 'testing', which is always true under
    // phpunit.xml — a naive test would pass even with zero CSRF protection.
    // These two tests temporarily rebind the 'env' container entry away from
    // 'testing' so the real middleware path executes, then restore it in a
    // finally block so no other test observes the override.

    public function test_missing_csrf_token_is_rejected_when_csrf_protection_is_active(): void
    {
        $originalEnv = $this->app['env'];
        $this->app->instance('env', 'production');

        try {
            $this->get(self::CSRF_COOKIE_URI)->assertSuccessful();

            $response = $this->withHeaders($this->jsonHeaders())->post(self::REGISTER_URI, [
                'name' => 'No Csrf',
                'email' => 'nocsrf@example.com',
                'password' => self::VALID_PASSWORD,
                'password_confirmation' => self::VALID_PASSWORD,
            ]);

            $response->assertStatus(419);
        } finally {
            $this->app->instance('env', $originalEnv);
        }
    }

    public function test_csrf_protected_request_succeeds_with_a_real_session_token_header_round_trip(): void
    {
        $originalEnv = $this->app['env'];
        $this->app->instance('env', 'production');

        try {
            $this->get(self::CSRF_COOKIE_URI)->assertSuccessful();
            $token = $this->app['session']->token();

            $response = $this->withHeaders(array_merge($this->jsonHeaders(), [
                'X-CSRF-TOKEN' => $token,
            ]))->post(self::REGISTER_URI, [
                'name' => 'Has Csrf',
                'email' => 'hascsrf@example.com',
                'password' => self::VALID_PASSWORD,
                'password_confirmation' => self::VALID_PASSWORD,
            ]);

            $response->assertSuccessful();
        } finally {
            $this->app->instance('env', $originalEnv);
        }
    }

    // --- login enumeration safety (docs/33 §6, docs/15 §1 madde 3) ----------

    public function test_login_error_is_indistinguishable_between_unknown_account_and_wrong_password(): void
    {
        $existing = User::factory()->create(['email_verified_at' => now()]);

        $unknown = $this->withHeaders($this->jsonHeaders())->post(self::LOGIN_URI, [
            'email' => 'no-such-account@example.com',
            'password' => 'whatever-password',
        ]);
        $wrongPassword = $this->withHeaders($this->jsonHeaders())->post(self::LOGIN_URI, [
            'email' => $existing->email,
            'password' => 'definitely-wrong-password',
        ]);

        self::assertNotSame(404, $unknown->getStatusCode(), 'login enumeration: /login route yok, davranış henüz test edilemiyor.');
        $unknown->assertStatus($wrongPassword->getStatusCode());
        self::assertSame($unknown->getContent(), $wrongPassword->getContent(), 'login enumeration: hesap yok / şifre yanlış ayırt edilemez olmalı.');
    }

    // --- S1WP02A-RATE-01 -------------------------------------------------

    public function test_repeated_failed_login_attempts_are_rate_limited(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $last = null;
        for ($i = 0; $i < 10; $i++) {
            $last = $this->withHeaders($this->jsonHeaders())->post(self::LOGIN_URI, [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $last->assertStatus(429);
    }

    public function test_verification_resend_action_is_rate_limited(): void
    {
        $user = User::factory()->unverified()->create();

        $last = null;
        for ($i = 0; $i < 10; $i++) {
            $last = $this->actingAs($user)->withHeaders($this->jsonHeaders())->post(self::VERIFY_RESEND_URI);
        }

        $last->assertStatus(429);
    }

    // --- S1WP02A-LOGOUT-01 -------------------------------------------------

    public function test_logout_invalidates_session_and_rejects_a_replayed_old_cookie(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->withHeaders($this->jsonHeaders())->get(self::AUTHENTICATED_PROBE_URI)->assertSuccessful();

        $logout = $this->actingAs($user)->withHeaders($this->jsonHeaders())->post(self::LOGOUT_URI);
        $logout->assertSuccessful();

        $replay = $this->withHeaders($this->jsonHeaders())->get(self::AUTHENTICATED_PROBE_URI);
        $replay->assertStatus(401);
    }

    // --- S1WP02A-MAIL-01 -------------------------------------------------

    public function test_verification_email_is_captured_deterministically_via_mail_fake(): void
    {
        Mail::fake();

        $this->withHeaders($this->jsonHeaders())->post(self::REGISTER_URI, [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
        ]);

        Mail::assertSentCount(1);
    }

    // --- S1WP02A-HOST-01 -------------------------------------------------

    public function test_verification_email_is_delivered_through_sync_queue_without_a_worker_process(): void
    {
        Config::set('queue.default', 'sync');
        Mail::fake();

        $this->withHeaders($this->jsonHeaders())->post(self::REGISTER_URI, [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
        ]);

        Mail::assertSentCount(1);
    }

    // --- S1WP02A-AI-01 -------------------------------------------------

    public function test_auth_decision_path_is_identical_with_ai_kill_switch_and_zero_credit_engaged(): void
    {
        $baseline = $this->withHeaders($this->jsonHeaders())->post(self::REGISTER_URI, [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
        ]);

        User::query()->delete();
        Config::set('ai.enabled', false);
        Config::set('ai.credit', 0);

        $aiOff = $this->withHeaders($this->jsonHeaders())->post(self::REGISTER_URI, [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
        ]);

        self::assertNotSame(404, $baseline->getStatusCode(), 'AI-01: /register route yok, karar yolu henüz test edilemiyor.');
        self::assertSame($baseline->getStatusCode(), $aiOff->getStatusCode(), 'AI-01: AI kill-switch/no-credit karar yolunu değiştirmemeli.');
        self::assertSame($baseline->getContent(), $aiOff->getContent(), 'AI-01: yanıt AI mevcutken ile bit-bit aynı olmalı.');
    }

    // --- review-correction RED: exactly one VerifyEmailMail on resend ------

    public function test_authenticated_unverified_resend_sends_exactly_one_verify_email_mail(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->withHeaders($this->jsonHeaders())->post(self::VERIFY_RESEND_URI);

        Mail::assertSent(VerifyEmailMail::class, 1);
    }

    // --- review-correction RED: register rate-limited by normalized-email/IP,
    // not a global lock that N+1's a victim's own attempts -----------------

    public function test_register_rate_limit_is_scoped_to_normalized_email_and_ip_not_globally_shared(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->withHeaders($this->jsonHeaders())->post(self::REGISTER_URI, [
                'name' => 'Attacker',
                'email' => "attacker{$i}@example.com",
                'password' => self::VALID_PASSWORD,
                'password_confirmation' => self::VALID_PASSWORD,
            ]);
        }

        $victim = $this->withHeaders($this->jsonHeaders())->post(self::REGISTER_URI, [
            'name' => 'Victim',
            'email' => 'victim@example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
        ]);

        self::assertNotSame(
            429,
            $victim->getStatusCode(),
            "RATE-01: register rate-limit normalized-email/IP başına olmalı; başka bir hesabın denemeleri victim'ı N+1 kilitlememeli."
        );
    }

    public function test_register_rate_limit_returns_429_after_repeated_attempts_for_same_normalized_email_and_ip(): void
    {
        $last = null;
        for ($i = 0; $i < 10; $i++) {
            $last = $this->withHeaders($this->jsonHeaders())->post(self::REGISTER_URI, [
                'name' => 'Repeat',
                'email' => ' Repeat@Example.com ',
                'password' => self::VALID_PASSWORD,
                'password_confirmation' => self::VALID_PASSWORD,
            ]);
        }

        $last->assertStatus(429);
    }

    // --- review-correction RED: EloquentUserRepository maps a raw unique
    // constraint violation (check/insert race) to the domain exception,
    // without leaking the raw email into the exception message -------------

    public function test_repository_maps_duplicate_insert_race_to_domain_exception_without_leaking_email(): void
    {
        $email = new EmailAddress('race@example.com');
        $password = new HashedPassword(bcrypt(self::VALID_PASSWORD));
        $repository = new EloquentUserRepository;

        $repository->createPendingUser($email, 'First', $password);

        $this->expectException(EmailAlreadyRegisteredException::class);

        try {
            $repository->createPendingUser($email, 'Second', $password);
        } catch (EmailAlreadyRegisteredException $exception) {
            self::assertStringNotContainsString(
                'race@example.com',
                $exception->getMessage(),
                'EmailAlreadyRegisteredException mesajı ham email adresini içermemeli.'
            );

            throw $exception;
        }
    }

    // --- final-correction RED: a high, independent per-IP register ceiling
    // (60 req/min) rejects a varied-email spray from a single IP; the
    // existing 10-distinct-email victim/attacker test above stays well
    // below this ceiling and is unaffected. A dedicated documentation-range
    // IP (RFC 5737, 203.0.113.0/24) isolates this test's cache/limiter
    // state from every other rate-limit test in this class. ------------

    private const RATE_CEILING_TEST_IP = '203.0.113.60';

    public function test_register_rate_limit_freezes_a_60_per_minute_per_ip_ceiling_for_varied_emails(): void
    {
        $last = null;
        for ($i = 0; $i < 61; $i++) {
            $last = $this
                ->withServerVariables(['REMOTE_ADDR' => self::RATE_CEILING_TEST_IP])
                ->withHeaders($this->jsonHeaders())
                ->post(self::REGISTER_URI, [
                    'name' => 'Spray',
                    'email' => "spray{$i}@example.com",
                    'password' => 'x',
                    'password_confirmation' => 'y',
                ]);
        }

        $last->assertStatus(
            429,
            'RATE-CEILING: aynı IP\'den 61. istek (varied email, cheap invalid payload) 60/dakika tavanını aşmalı ve 429 dönmeli.'
        );
    }
}
