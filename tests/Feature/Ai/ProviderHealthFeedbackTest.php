<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Application\Ai\Exception\ProviderCallException;
use App\Application\Ai\Port\AiRequest;
use App\Application\Platform\Port\PlatformConnectionAdminPort;
use App\Domain\Ai\Capability;
use App\Domain\Platform\Credential\CredentialProvider;
use App\Domain\Platform\Credential\CredentialScope;
use App\Infrastructure\Ai\GeminiVisionProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SAĞLIK GERİ BESLEMESİ — `docs/14` §2a, `docs/95` Faz 3 §Sağlık.
 *
 * "Sağlıksız hesap havuzdan düşer" kuralı, bir yerden GERÇEK bilgi almadan
 * yalnız bir tablo sütunudur. Bu paket o bilgiyi çağrının kendisinden alır:
 * adaptör bir hesabı kullandığını bilir, o hesabın ulaşılamadığını ya da
 * anahtarının reddedildiğini gördüğünde işaretler.
 *
 * KRİTİK AYRIM: her hata hesabın suçu değildir. Gövdemiz bozuk olduğu için
 * (400) çalışan bir hesabı düşürmek, sahibin ödediği bir hesabı kullanılamaz
 * kılardı — ve sıradaki hesap da aynı 400'ü alırdı.
 */
final class ProviderHealthFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private int $workspaceId;

    private int $connectionId;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('ai.enabled', true);
        Config::set('ai.budget.monthly_minor_per_tenant', 100000);

        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => 'health-'.$user->id, 'state' => 'active',
            'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->connectionId = $this->app->make(PlatformConnectionAdminPort::class)->createConnection(
            CredentialProvider::Gemini,
            'Gemini — Görüntü',
            CredentialScope::PlatformOwned,
            null,
            ['api_key' => 'gm-test-key'],
            null,
        );
    }

    private function request(): AiRequest
    {
        return new AiRequest(
            capability: Capability::MenuExtract,
            workspaceId: $this->workspaceId,
            instruction: 'Menüyü oku.',
        );
    }

    private function health(): string
    {
        return $this->app->make(PlatformConnectionAdminPort::class)
            ->connection($this->connectionId)?->health->value ?? 'missing';
    }

    private function extract(): void
    {
        $this->app->make(GeminiVisionProvider::class)->extract($this->request(), []);
    }

    #[Test]
    public function a_successful_call_marks_the_connection_healthy(): void
    {
        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode(['rows' => [[
                            'category' => 'Çorbalar',
                            'product' => 'Mercimek',
                            'price_minor_amount' => 5000,
                            'currency_code' => 'TRY',
                            'confidence' => 0.95,
                        ]]]),
                    ]]],
                ]],
            ], 200),
        ]);

        $this->extract();

        self::assertSame('healthy', $this->health());
    }

    #[Test]
    public function a_rejected_key_drops_the_connection_from_the_pool(): void
    {
        Http::fake(['*' => Http::response(['error' => 'invalid api key'], 401)]);

        try {
            $this->extract();
            self::fail('401 bir ProviderCallException fırlatmalıydı.');
        } catch (ProviderCallException) {
            // beklenen
        }

        self::assertSame('unhealthy', $this->health());
    }

    #[Test]
    public function an_exhausted_quota_drops_the_connection_from_the_pool(): void
    {
        // 429, bu mekanizmanın var oluş sebebi: kotası dolan hesap geçici
        // olarak düşer, ikinci hesap devralır.
        Http::fake(['*' => Http::response(['error' => 'rate limited'], 429)]);

        try {
            $this->extract();
            self::fail('429 bir ProviderCallException fırlatmalıydı.');
        } catch (ProviderCallException) {
            // beklenen
        }

        self::assertSame('unhealthy', $this->health());
    }

    #[Test]
    public function our_own_bad_request_does_not_punish_the_account(): void
    {
        Http::fake(['*' => Http::response(['error' => 'bad request'], 400)]);

        try {
            $this->extract();
            self::fail('400 bir ProviderCallException fırlatmalıydı.');
        } catch (ProviderCallException) {
            // beklenen
        }

        // Hesap gayet çalışıyor — sorun bizim gövdemizde. Onu düşürmek,
        // sıradaki hesabın da aynı 400'ü almasına yol açardı.
        self::assertSame('unknown', $this->health());
    }

    #[Test]
    public function a_provider_side_error_drops_the_connection(): void
    {
        Http::fake(['*' => Http::response(['error' => 'upstream'], 503)]);

        try {
            $this->extract();
            self::fail('503 bir ProviderCallException fırlatmalıydı.');
        } catch (ProviderCallException) {
            // beklenen
        }

        self::assertSame('unhealthy', $this->health());
    }
}
