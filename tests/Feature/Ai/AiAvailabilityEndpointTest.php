<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * AI KULLANILABİLİRLİĞİ, TIKLAMADAN ÖNCE — `docs/97` R9 / AIV-07.
 *
 * Bugüne kadar ekran ancak DÜĞMEYE BASTIKTAN sonra 503 alıp "kullanılamıyor"
 * diyebiliyordu: kullanıcı bir sağlayıcıya para ödemediğimiz için var
 * olmayan bir işi denemek zorunda kalıyordu. Bu uç nokta ekranın, eylemi
 * hiç göstermeden önce sormasını sağlar — ve "neden" bilgisini de taşır,
 * çünkü `docs/47` Kural 5'in AI karşılığı: engellenen durum sebebini söyler.
 */
final class AiAvailabilityEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private int $workspaceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['email_verified_at' => now()]);

        $this->workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => 'av-'.Str::lower(Str::random(6)), 'state' => 'active',
            'created_by' => $this->owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $this->workspaceId, 'user_id' => $this->owner->id,
            'role' => 'owner', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function url(): string
    {
        return "/api/workspaces/{$this->workspaceId}/ai/availability";
    }

    /**
     * Yetenek adları NOKTA içerir (`menu.extract`) — bu yüzden cevap bir
     * nesne değil LİSTE'dir ve bu yardımcı satırı adıyla bulur. Noktalı bir
     * anahtarı JSON yoluna gömmek, `ConfiguredAvailability`'nin docblock'unda
     * kayıtlı arızanın (dotted-config-key) test tarafındaki aynısı olurdu.
     *
     * @return array<string, mixed>
     */
    private function row(array $payload, string $capability): array
    {
        foreach ($payload['capabilities'] as $entry) {
            if ($entry['capability'] === $capability) {
                return $entry;
            }
        }

        self::fail("Cevapta '{$capability}' yeteneği yok.");
    }

    #[Test]
    public function it_reports_every_screen_capability_as_available_when_ai_is_on(): void
    {
        config(['ai.enabled' => true]);
        config(['ai.capabilities' => [
            'menu.extract' => ['candidates' => ['fake'], 'confidence_threshold' => 0.60],
            'product.description' => ['candidates' => ['fake'], 'confidence_threshold' => 0.60],
            'embedding.text' => ['candidates' => ['fake'], 'confidence_threshold' => 0.60],
        ]]);
        config(['ai.budget.monthly_minor_per_tenant' => 100000]);

        $response = $this->actingAs($this->owner)->getJson($this->url());

        $response->assertOk();
        $payload = $response->json();

        self::assertTrue($this->row($payload, 'menu.extract')['available']);
        self::assertTrue($this->row($payload, 'product.description')['available']);
        self::assertTrue($this->row($payload, 'embedding.text')['available']);
    }

    #[Test]
    public function a_closed_kill_switch_is_reported_with_its_reason_not_as_an_error(): void
    {
        config(['ai.enabled' => false]);

        $response = $this->actingAs($this->owner)->getJson($this->url());

        // 200 — "AI kapalı" bir HATA değil, geçerli bir durumdur. Ekran bunu
        // okuyup eylemi hiç göstermez; kırmızı bir hata kutusu göstermez.
        $response->assertOk();
        $row = $this->row($response->json(), 'menu.extract');

        self::assertFalse($row['available']);
        self::assertSame('kill_switch', $row['reason']);
    }

    #[Test]
    public function an_exhausted_budget_is_reported_separately_from_a_missing_route(): void
    {
        config(['ai.enabled' => true]);
        config(['ai.capabilities' => [
            'menu.extract' => ['candidates' => ['fake'], 'confidence_threshold' => 0.60],
            'product.description' => ['candidates' => [], 'confidence_threshold' => 0.60],
            'embedding.text' => ['candidates' => [], 'confidence_threshold' => 0.60],
        ]]);
        config(['ai.budget.monthly_minor_per_tenant' => 0]);

        $response = $this->actingAs($this->owner)->getJson($this->url());

        $response->assertOk();
        $payload = $response->json();

        // Sıfır bütçe = kapalı (sınırsız DEĞİL) — `AiBudgetLedger` kuralı.
        self::assertSame('budget_exhausted', $this->row($payload, 'menu.extract')['reason']);
        // Adayı olmayan yetenek bütçeden ÖNCE elenir: farklı sebep, farklı çözüm.
        self::assertSame('no_route', $this->row($payload, 'product.description')['reason']);
    }

    #[Test]
    public function a_stranger_cannot_read_another_workspaces_ai_state(): void
    {
        $stranger = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($stranger)->getJson($this->url())->assertNotFound();
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->getJson($this->url())->assertUnauthorized();
    }
}
