<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Domain\Support\SupportReference;
use App\Mail\SupportRequestAcknowledged;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * FF-201 RED — panelden destek isteme (`docs/125` §4).
 *
 * MÜŞTERİ SORUNU. Oturum açmış bir restoran sahibinin panelden destek
 * istemesinin yolu yoktu; kamu formuna gidip adını ve e-postasını yeniden
 * yazması gerekiyordu ve talebi hangi çalışma alanına aitti, bilinmiyordu.
 *
 * KARAR: `GET/POST /api/workspaces/{w}/support-requests`. Ad ve e-posta
 * HESAPTAN gelir; talep çalışma alanına ve kişiye bağlanır; liste yalnız o
 * çalışma alanının taleplerini döner. Yetki `workspace.manage`: destek
 * talebi bir yönetim kanalıdır (plan, fatura, hesap) ve editörün göreceği
 * bir liste, sahibin fatura sorusunu da açığa çıkarırdı.
 *
 * Requirement IDs: SUPPORT-PANEL-STORE-01, SUPPORT-PANEL-LIST-01,
 * SUPPORT-PANEL-TENANT-ESCAPE-01, SUPPORT-PANEL-MANAGE-ONLY-01,
 * SUPPORT-PANEL-AUTH-01, SUPPORT-PANEL-VALIDATED-01, SUPPORT-PANEL-THROTTLE-01,
 * SUPPORT-PANEL-ACK-01.
 */
final class WorkspaceSupportRequestTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    private function verifiedUser(string $name, string $email): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'email_verified_at' => now(),
        ]);
    }

    private function workspaceOwnedBy(User $owner, string $slug): int
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin Kebap '.$slug,
            'slug' => $slug,
            'state' => 'active',
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->addMember($workspaceId, $owner, 'owner');

        return $workspaceId;
    }

    private function addMember(int $workspaceId, User $user, string $role): void
    {
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $user->id,
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function uri(int $workspaceId): string
    {
        return "/api/workspaces/{$workspaceId}/support-requests";
    }

    /** @return array<string, string> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'subject' => 'Menüm görünmüyor',
            'message' => 'Karekodu okutunca boş sayfa açılıyor.',
        ], $overrides);
    }

    // --- SUPPORT-PANEL-STORE-01 / SUPPORT-PANEL-ACK-01 --------------------

    public function test_an_owner_opens_a_request_and_name_and_email_come_from_the_account(): void
    {
        Mail::fake();
        config(['mail.default' => 'mailgun']);

        $owner = $this->verifiedUser('Mehmet Usta', 'mehmet-store-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'zeytin-store-01');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->uri($workspaceId), $this->payload([
                // Gövdede gelen ad/e-posta YOK SAYILIR: kimlik hesaptan gelir.
                'name' => 'Sahte Ad',
                'email' => 'sahte@example.test',
            ]));

        $response->assertStatus(201);

        $row = DB::table('support_requests')->first();

        self::assertNotNull($row, 'SUPPORT-PANEL-STORE-01: talep durmalı.');
        self::assertSame($workspaceId, (int) $row->workspace_id);
        self::assertSame($owner->id, (int) $row->user_id);
        self::assertSame('Mehmet Usta', (string) $row->name);
        self::assertSame('mehmet-store-01@example.test', (string) $row->email);
        self::assertSame('Menüm görünmüyor', (string) $row->subject);
        self::assertSame('panel', (string) $row->channel);
        self::assertSame('received', (string) $row->status);
        self::assertMatchesRegularExpression(SupportReference::PATTERN, (string) $row->reference);

        // Yanıt yalnız güvenli alanları taşır — mesaj gövdesi ve gönderim
        // sebebi değil.
        self::assertSame(
            ['id', 'reference', 'subject', 'status', 'received_at', 'first_response_at', 'acknowledgement'],
            array_keys($response->json()),
        );
        self::assertSame((string) $row->reference, $response->json('reference'));
        self::assertSame('sent', $response->json('acknowledgement'), 'SUPPORT-PANEL-ACK-01: alındı e-postasının hâli yanıtta.');

        Mail::assertSent(SupportRequestAcknowledged::class, fn (SupportRequestAcknowledged $mail): bool => $mail->hasTo('mehmet-store-01@example.test'));
    }

    // --- SUPPORT-PANEL-LIST-01 / SUPPORT-PANEL-TENANT-ESCAPE-01 -----------

    public function test_the_list_shows_only_this_workspaces_requests_in_order(): void
    {
        Mail::fake();

        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-list-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'zeytin-list-01');

        $otherOwner = $this->verifiedUser('Kemal Demir', 'kemal-list-01@example.test');
        $otherWorkspaceId = $this->workspaceOwnedBy($otherOwner, 'kemal-list-01');

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->uri($workspaceId), $this->payload(['subject' => 'Birinci']))->assertStatus(201);
        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->uri($workspaceId), $this->payload(['subject' => 'İkinci']))->assertStatus(201);
        $this->actingAs($otherOwner)->withHeaders($this->jsonHeaders())
            ->postJson($this->uri($otherWorkspaceId), $this->payload(['subject' => 'Başkasının']))->assertStatus(201);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->uri($workspaceId));

        $response->assertOk();

        $subjects = array_column($response->json('requests'), 'subject');

        // EN YENİ ÜSTTE: sahip son açtığı talebin durumunu arar.
        self::assertSame(['İkinci', 'Birinci'], $subjects, 'SUPPORT-PANEL-LIST-01');
        self::assertNotContains('Başkasının', $subjects, 'SUPPORT-PANEL-TENANT-ESCAPE-01: başka çalışma alanının talebi sızmamalı.');

        $first = $response->json('requests.0');
        self::assertSame(
            ['id', 'reference', 'subject', 'status', 'received_at', 'first_response_at'],
            array_keys($first),
        );
        self::assertArrayHasKey('commitment', $response->json());
    }

    public function test_a_member_of_another_workspace_gets_an_enumeration_safe_404(): void
    {
        Mail::fake();

        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-escape-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'zeytin-escape-01');

        $stranger = $this->verifiedUser('Kemal Demir', 'kemal-escape-01@example.test');
        $this->workspaceOwnedBy($stranger, 'kemal-escape-01');

        $this->actingAs($stranger)->withHeaders($this->jsonHeaders())
            ->getJson($this->uri($workspaceId))
            ->assertStatus(404);

        $this->actingAs($stranger)->withHeaders($this->jsonHeaders())
            ->postJson($this->uri($workspaceId), $this->payload())
            ->assertStatus(404);

        self::assertSame(0, DB::table('support_requests')->count());
    }

    // --- SUPPORT-PANEL-MANAGE-ONLY-01 -------------------------------------

    public function test_an_editor_cannot_read_or_open_requests(): void
    {
        Mail::fake();

        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-editor-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'zeytin-editor-01');

        $editor = $this->verifiedUser('Elif Kaya', 'elif-editor-01@example.test');
        $this->addMember($workspaceId, $editor, 'editor');

        $this->actingAs($editor)->withHeaders($this->jsonHeaders())
            ->getJson($this->uri($workspaceId))
            ->assertStatus(404);

        $this->actingAs($editor)->withHeaders($this->jsonHeaders())
            ->postJson($this->uri($workspaceId), $this->payload())
            ->assertStatus(404);

        // Yönetici AÇABİLİR: `workspace.manage` taşır.
        $manager = $this->verifiedUser('Can Öz', 'can-manager-01@example.test');
        $this->addMember($workspaceId, $manager, 'manager');

        $this->actingAs($manager)->withHeaders($this->jsonHeaders())
            ->postJson($this->uri($workspaceId), $this->payload())
            ->assertStatus(201);
    }

    // --- SUPPORT-PANEL-AUTH-01 --------------------------------------------

    public function test_the_endpoints_require_a_verified_session(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-auth-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'zeytin-auth-01');

        $this->withHeaders($this->jsonHeaders())->getJson($this->uri($workspaceId))->assertStatus(401);
        $this->withHeaders($this->jsonHeaders())->postJson($this->uri($workspaceId), $this->payload())->assertStatus(401);

        $unverified = User::factory()->unverified()->create(['email' => 'unverified-auth-01@example.test']);
        $this->addMember($workspaceId, $unverified, 'manager');

        $this->actingAs($unverified)->withHeaders($this->jsonHeaders())
            ->getJson($this->uri($workspaceId))
            ->assertStatus(403);
    }

    // --- SUPPORT-PANEL-VALIDATED-01 ---------------------------------------

    public function test_subject_and_message_are_required_and_bounded(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-valid-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'zeytin-valid-01');

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->uri($workspaceId), $this->payload(['subject' => '   ']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('subject');

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->uri($workspaceId), $this->payload(['message' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->uri($workspaceId), $this->payload(['message' => str_repeat('a', 4001)]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');

        self::assertSame(0, DB::table('support_requests')->count());
    }

    // --- SUPPORT-PANEL-THROTTLE-01 ----------------------------------------

    public function test_the_sixth_request_within_a_minute_is_throttled(): void
    {
        Mail::fake();

        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-throttle-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'zeytin-throttle-01');

        RateLimiter::clear(sha1((string) $owner->getAuthIdentifier()));

        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($owner)->withHeaders($this->jsonHeaders())
                ->postJson($this->uri($workspaceId), $this->payload(['subject' => "Talep {$i}"]))
                ->assertStatus(201);
        }

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->uri($workspaceId), $this->payload(['subject' => 'Talep 6']))
            ->assertStatus(429, 'SUPPORT-PANEL-THROTTLE-01: dakikada 6. istek throttle:5,1 ile 429 almalı.');
    }
}
