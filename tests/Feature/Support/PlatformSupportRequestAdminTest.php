<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Domain\Platform\PlatformRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * FF-201 RED — süperadmin destek uçları (`docs/125` §5).
 *
 * EKRAN BU PAKETTE YOK: `PlatformApp.tsx` başka bir pakette değişiyor.
 * Uçlar var ki ekran geldiğinde sunucu tarafı hazır olsun ve durum
 * geçişinin kuralı (ilk yanıt zamanı yalnız bir kez damgalanır) şimdiden
 * kilitlensin.
 *
 * Requirement IDs: SUPPORT-ADMIN-LIST-01, SUPPORT-ADMIN-SUPERADMIN-ONLY-01,
 * SUPPORT-ADMIN-STATUS-01, SUPPORT-ADMIN-FIRST-RESPONSE-ONCE-01,
 * SUPPORT-ADMIN-STATUS-VALIDATED-01.
 */
final class PlatformSupportRequestAdminTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        DB::table('platform_role_assignments')->insert([
            'user_id' => $user->getKey(),
            'role' => PlatformRole::SuperAdmin->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    private function seedPublicRequest(string $subject, string $status = 'received'): int
    {
        return (int) DB::table('support_requests')->insertGetId([
            'reference' => 'ZB-'.strtoupper(substr(str_shuffle('23456789ABCDEFGHJKMNPQRSTUVWXYZ'), 0, 5)),
            'workspace_id' => null,
            'user_id' => null,
            'name' => 'Hüseyin',
            'email' => 'huseyin@example.com',
            'subject' => $subject,
            'message' => 'Menüm görünmüyor.',
            'channel' => 'public_contact',
            'status' => $status,
            'locale' => 'tr',
            'received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // --- SUPPORT-ADMIN-SUPERADMIN-ONLY-01 ---------------------------------

    public function test_a_verified_user_without_the_platform_role_gets_an_enumeration_safe_404(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $id = $this->seedPublicRequest('Menüm görünmüyor');

        $this->actingAs($user)->withHeaders($this->jsonHeaders())
            ->getJson('/api/admin/support-requests')
            ->assertStatus(404);

        $this->actingAs($user)->withHeaders($this->jsonHeaders())
            ->putJson("/api/admin/support-requests/{$id}/status", ['status' => 'answered'])
            ->assertStatus(404);

        self::assertSame('received', DB::table('support_requests')->where('id', $id)->value('status'));
    }

    // --- SUPPORT-ADMIN-LIST-01 --------------------------------------------

    public function test_the_platform_list_shows_every_channel_and_filters_by_status(): void
    {
        Mail::fake();

        $admin = $this->superAdmin();
        $this->seedPublicRequest('Kamu talebi');
        $closed = $this->seedPublicRequest('Kapanmış', 'closed');

        $response = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->getJson('/api/admin/support-requests');

        $response->assertOk();
        self::assertCount(2, $response->json());
        self::assertSame(
            ['id', 'reference', 'workspace_id', 'name', 'email', 'subject', 'message', 'channel', 'status', 'received_at', 'first_response_at', 'acknowledgement'],
            array_keys($response->json('0')),
        );

        $filtered = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->getJson('/api/admin/support-requests?status=closed');

        $filtered->assertOk();
        self::assertCount(1, $filtered->json());
        self::assertSame($closed, $filtered->json('0.id'));
    }

    // --- SUPPORT-ADMIN-STATUS-01 / SUPPORT-ADMIN-FIRST-RESPONSE-ONCE-01 ---

    public function test_answering_stamps_the_first_response_exactly_once(): void
    {
        $admin = $this->superAdmin();
        $id = $this->seedPublicRequest('Menüm görünmüyor');

        $answered = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->putJson("/api/admin/support-requests/{$id}/status", ['status' => 'answered']);

        $answered->assertOk();
        self::assertSame('answered', $answered->json('status'));

        $firstResponseAt = DB::table('support_requests')->where('id', $id)->value('first_response_at');
        self::assertNotNull($firstResponseAt, 'SUPPORT-ADMIN-FIRST-RESPONSE-ONCE-01: ilk yanıt damgalanmalı.');

        /*
            İLK YANIT BİR KEZ DAMGALANIR. Talep kapanıp yeniden açılsa ve
            tekrar cevaplansa bile ilk yanıt zamanı değişmez — yoksa
            "kaç saatte cevap verdik" ölçümü her geçişte sıfırlanırdı.
        */
        $this->travel(2)->hours();

        $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->putJson("/api/admin/support-requests/{$id}/status", ['status' => 'closed'])
            ->assertOk();
        $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->putJson("/api/admin/support-requests/{$id}/status", ['status' => 'answered'])
            ->assertOk();

        self::assertSame(
            $firstResponseAt,
            DB::table('support_requests')->where('id', $id)->value('first_response_at'),
        );

        $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->putJson('/api/admin/support-requests/999999/status', ['status' => 'answered'])
            ->assertStatus(404);
    }

    // --- SUPPORT-ADMIN-STATUS-VALIDATED-01 --------------------------------

    public function test_an_unknown_status_is_refused(): void
    {
        $admin = $this->superAdmin();
        $id = $this->seedPublicRequest('Menüm görünmüyor');

        $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->putJson("/api/admin/support-requests/{$id}/status", ['status' => 'deleted'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        self::assertSame('received', DB::table('support_requests')->where('id', $id)->value('status'));
    }
}
