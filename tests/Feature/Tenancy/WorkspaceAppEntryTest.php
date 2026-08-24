<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Blind RED test candidate for S1-WP02C. Frozen scope hash
 * a1676c2ab292fc622a46b12717edf1d8afa1a0cdbb026733902edb0bdd5f3f65: GET /app
 * is protected by auth+verified (guest and unverified alike are rejected,
 * never a bare 404), and login / email-verification both settle on /app as
 * their destination. No routes/web.php GET /app route exists in this
 * snapshot, so every request below hits an undefined route and every
 * assertion is expected to fail RED until S1-WP02C is implemented.
 */
final class WorkspaceAppEntryTest extends TestCase
{
    use RefreshDatabase;

    private const APP_URI = '/app';

    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    // --- guarded entry --------------------------------------------------

    public function test_guest_is_rejected_from_app_route_not_with_a_bare_404(): void
    {
        $response = $this->withHeaders($this->jsonHeaders())->get(self::APP_URI);

        self::assertNotSame(404, $response->getStatusCode(), 'GET /app: rota var olmalı; 404 kabul edilmez.');
        self::assertContains($response->getStatusCode(), [302, 401], 'GET /app: kimliksiz istek 302 (login redirect) veya 401 ile reddedilmeli.');
    }

    public function test_unverified_authenticated_user_is_rejected_from_app_route(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->withHeaders($this->jsonHeaders())->get(self::APP_URI);

        self::assertNotSame(404, $response->getStatusCode(), 'GET /app: rota var olmalı; 404 kabul edilmez.');
        self::assertContains($response->getStatusCode(), [302, 403], 'GET /app: doğrulanmamış kullanıcı 302 (verify-notice redirect) veya 403 ile reddedilmeli.');
    }

    public function test_verified_authenticated_user_receives_the_app_shell(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->get(self::APP_URI);

        $response->assertSuccessful();
        $response->assertSee('id="app"', false);
    }

    // --- login / verification destination --------------------------------

    public function test_login_success_response_points_the_client_at_the_app_destination(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->withHeaders($this->jsonHeaders())->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSuccessful();
        self::assertSame('/app', $response->json('redirect') ?? config('fortify.home'), 'Login sonrası varış noktası /app olmalı.');
    }

    public function test_email_verification_success_redirects_to_the_app_destination(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        $response->assertRedirect('/app');
    }
}
