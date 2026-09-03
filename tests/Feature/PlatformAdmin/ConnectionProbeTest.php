<?php

declare(strict_types=1);

namespace Tests\Feature\PlatformAdmin;

use App\Application\Platform\Port\CredentialResolverPort;
use App\Application\Platform\Port\PlatformConnectionAdminPort;
use App\Domain\Platform\Credential\CredentialProvider;
use App\Domain\Platform\Credential\CredentialScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * UYUMLULUK YOKLAMASI — `docs/95` Faz 3, `docs/51` §4.5.
 *
 * Superadmin bugüne kadar bir anahtarı kaydedip "kaydedildi" görüyor, ama
 * anahtarın yanlış olduğunu ancak ilk MÜŞTERİ isteğinde öğreniyordu — yani
 * en kötü anda. Bu paket o soruyu kaydetme anında yanıtlar.
 *
 * Ve doktrinin sert kuralını uygular: özel bir uç nokta, sınanmadan
 * yönlendirme adayı OLMAZ. Bilinen bir sağlayıcının adresini ve sözleşmesini
 * biliyoruz; superadmin'in yazdığı keyfi bir adresi bilmiyoruz.
 */
final class ConnectionProbeTest extends TestCase
{
    use RefreshDatabase;

    private function headers(): array
    {
        return ['Accept' => 'application/json'];
    }

    private function admin(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        DB::table('platform_role_assignments')->insert([
            'user_id' => $user->id, 'role' => 'super_admin',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $user;
    }

    private function connections(): PlatformConnectionAdminPort
    {
        return $this->app->make(PlatformConnectionAdminPort::class);
    }

    private function connect(CredentialProvider $provider, array $values): int
    {
        return $this->connections()->createConnection(
            $provider,
            'Test bağlantısı',
            CredentialScope::PlatformOwned,
            null,
            $values,
            null,
        );
    }

    private function health(int $id): string
    {
        return $this->connections()->connection($id)?->health->value ?? 'missing';
    }

    // --- PROBE-REACHABLE-01 -----------------------------------------------

    #[Test]
    public function a_working_key_turns_the_connection_healthy_without_spending_a_token(): void
    {
        $id = $this->connect(CredentialProvider::OpenAi, ['api_key' => 'sk-good-1111']);

        Http::fake(['*' => Http::response(['data' => [['id' => 'gpt-4o-mini']]], 200)]);

        $response = $this->actingAs($this->admin())->withHeaders($this->headers())
            ->postJson("/api/admin/connections/{$id}/probe")->assertStatus(200);

        self::assertSame('reachable', $response->json('probe.outcome'));
        self::assertSame('healthy', $this->health($id));

        Http::assertSent(function ($request): bool {
            // Model LİSTESİ — tamamlama değil: bir "merhaba" istemek de
            // anahtarı doğrulardı ama her denemede fatura üretirdi.
            self::assertSame('GET', $request->method());

            return str_contains($request->url(), '/models');
        });
    }

    #[Test]
    public function a_rejected_key_turns_the_connection_unhealthy_and_says_the_status(): void
    {
        $id = $this->connect(CredentialProvider::Anthropic, ['api_key' => 'sk-ant-wrong']);

        Http::fake(['*' => Http::response(['error' => 'invalid x-api-key'], 401)]);

        $response = $this->actingAs($this->admin())->withHeaders($this->headers())
            ->postJson("/api/admin/connections/{$id}/probe")->assertStatus(200);

        self::assertSame('rejected', $response->json('probe.outcome'));
        self::assertSame(401, $response->json('probe.httpStatus'));
        self::assertSame('unhealthy', $this->health($id));
    }

    #[Test]
    public function the_probe_response_never_carries_the_secret(): void
    {
        $secret = 'sk-super-secret-must-not-leak-9f42';
        $id = $this->connect(CredentialProvider::OpenAi, ['api_key' => $secret]);

        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $response = $this->actingAs($this->admin())->withHeaders($this->headers())
            ->postJson("/api/admin/connections/{$id}/probe")->assertStatus(200);

        self::assertStringNotContainsString($secret, $response->getContent());
        self::assertStringNotContainsString(
            $secret,
            (string) json_encode(DB::table('platform_credential_audits')->get()),
        );
    }

    // --- PROBE-UNSUPPORTED-01 ---------------------------------------------

    /**
     * YOKLANAMAYAN BİR BAĞLANTI SAĞLIKSIZ DEĞİLDİR.
     *
     * Mailgun'un bir "model listesi" yok. Onu yoklanamadı diye sağlıksız
     * işaretlemek, çalışan bir posta hesabını havuzdan düşürmek olurdu.
     */
    #[Test]
    public function a_provider_with_nothing_to_probe_is_reported_as_unsupported_and_keeps_its_health(): void
    {
        $id = $this->connect(CredentialProvider::Mailgun, [
            'domain' => 'mg.zabuno.com', 'secret' => 'mg-key-1111',
        ]);

        Http::fake();

        $response = $this->actingAs($this->admin())->withHeaders($this->headers())
            ->postJson("/api/admin/connections/{$id}/probe")->assertStatus(200);

        self::assertSame('unsupported', $response->json('probe.outcome'));
        self::assertSame('unknown', $this->health($id));
        Http::assertNothingSent();
    }

    // --- PROBE-CUSTOM-GATE-01 ---------------------------------------------

    /**
     * SINANMAMIŞ ÖZEL UÇ NOKTA ADAY DEĞİLDİR — `docs/51` §4.5.
     *
     * Bu, yoklamanın asıl sebebi: superadmin'in yazdığı keyfi bir adrese,
     * ne konuştuğunu bilmeden müşterinin menüsünü göndermek olmaz.
     */
    #[Test]
    public function an_unproven_custom_endpoint_is_not_a_routing_candidate(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => 'pb-'.Str::lower(Str::random(6)), 'state' => 'active',
            'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $id = $this->connect(CredentialProvider::CustomEndpoint, [
            'base_url' => 'https://qwen.internal.example/v1',
        ]);

        $resolver = $this->app->make(CredentialResolverPort::class);

        // Henüz sınanmadı → aday değil.
        self::assertNull(
            $resolver->resolveFor($workspaceId, CredentialProvider::CustomEndpoint)->connectionId,
        );

        Http::fake(['*' => Http::response(['data' => [['id' => 'qwen2.5-7b-instruct']]], 200)]);

        $this->actingAs($this->admin())->withHeaders($this->headers())
            ->postJson("/api/admin/connections/{$id}/probe")->assertStatus(200);

        // Sınandı ve cevap verdi → artık aday.
        self::assertSame(
            $id,
            $resolver->resolveFor($workspaceId, CredentialProvider::CustomEndpoint)->connectionId,
        );
    }

    /**
     * BİLİNEN BİR SAĞLAYICI SINANMADAN DA ADAYDIR.
     *
     * OpenAI'ın adresini ve sözleşmesini biliyoruz; "henüz yoklanmadı"
     * onu kullanılamaz kılmaz — yoksa yeni girilen doğru bir anahtar,
     * yoklanana kadar işe yaramazdı.
     */
    #[Test]
    public function a_known_provider_is_a_candidate_before_any_probe(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => 'pb2-'.Str::lower(Str::random(6)), 'state' => 'active',
            'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $id = $this->connect(CredentialProvider::OpenAi, ['api_key' => 'sk-fresh-1111']);

        self::assertSame(
            $id,
            $this->app->make(CredentialResolverPort::class)
                ->resolveFor($workspaceId, CredentialProvider::OpenAi)->connectionId,
        );
    }

    // --- PROBE-AUTHZ-01 ---------------------------------------------------

    #[Test]
    public function a_non_superadmin_cannot_probe_and_a_missing_connection_is_404(): void
    {
        $id = $this->connect(CredentialProvider::OpenAi, ['api_key' => 'sk-1111']);
        $stranger = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($stranger)->withHeaders($this->headers())
            ->postJson("/api/admin/connections/{$id}/probe")->assertStatus(404);

        $this->actingAs($this->admin())->withHeaders($this->headers())
            ->postJson('/api/admin/connections/99999/probe')->assertStatus(404);
    }
}
