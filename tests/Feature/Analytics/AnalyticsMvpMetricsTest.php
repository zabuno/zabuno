<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Domain\Analytics\AnalyticsEventType;
use App\Models\User;
use App\Support\Analytics\VisitorKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\GrantsPlanEntitlements;
use Tests\TestCase;

/**
 * MVP analitik metrikleri — `docs/68`.
 *
 * Plan üç şeyi ZORUNLU sayıyor ve üçü de yoktu: yaklaşık benzersiz ziyaretçi,
 * lokasyon kırılımı ve karekod kırılımı. Toplam sayı tek başına iki şubesi
 * olan bir işletmede birinin hiç taranmadığını GİZLER.
 */
final class AnalyticsMvpMetricsTest extends TestCase
{
    use GrantsPlanEntitlements;
    use RefreshDatabase;

    /** @return array{user: User, workspace: int, locations: array<int, int>, qrCodes: array<int, int>, menu: int} */
    private function workspaceWithTwoLocations(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => 'zeytin-'.uniqid(), 'state' => 'active',
            'created_by' => $user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId, 'user_id' => $user->id, 'role' => 'owner',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspaceId, 'name' => 'Zeytin', 'slug' => 'z-'.uniqid(),
            'locale' => 'tr', 'timezone' => 'Europe/Istanbul', 'currency' => 'TRY',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $locations = [];
        $qrCodes = [];
        $menus = [];

        foreach (['Kadıköy', 'Beşiktaş'] as $index => $name) {
            $locationId = (int) DB::table('locations')->insertGetId([
                'workspace_id' => $workspaceId, 'brand_id' => $brandId, 'display_name' => $name,
                'country_code' => 'TR', 'timezone' => 'Europe/Istanbul', 'city' => 'İstanbul',
                'address_line1' => 'Adres '.$name,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $menuId = (int) DB::table('menus')->insertGetId([
                'public_key' => Str::lower(Str::random(10)),
                'workspace_id' => $workspaceId, 'location_id' => $locationId,
                'name' => 'Menü '.$name, 'state' => 'draft',
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $qrCodeId = (int) DB::table('qr_codes')->insertGetId([
                'workspace_id' => $workspaceId, 'location_id' => $locationId,
                'token' => str_repeat((string) ($index + 1), 43), 'state' => 'active',
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $locations[] = $locationId;
            $menus[] = $menuId;
            $qrCodes[] = $qrCodeId;
        }

        // Analitik raporlama plana bağlı bir yetenektir; kurulum burada
        // yapılır ki her test kendi plan kurgusunu tekrar yazmasın.
        $this->grantEntitlements($workspaceId);

        return [
            'user' => $user,
            'workspace' => $workspaceId,
            'locations' => $locations,
            'qrCodes' => $qrCodes,
            'menu' => $menus[0],
            'menus' => $menus,
        ];
    }

    private function recordResolve(array $scope, int $index, ?string $visitorKey): void
    {
        DB::table('analytics_events')->insert([
            'workspace_id' => $scope['workspace'],
            'location_id' => $scope['locations'][$index],
            'qr_code_id' => $scope['qrCodes'][$index],
            'menu_id' => $scope['menus'][$index],
            'event_type' => AnalyticsEventType::QrResolve->value,
            'visitor_key' => $visitorKey,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Aynı masadaki bir müşterinin menüyü altı kez açması altı müşteri
     * demek değildir; ham sayaç bu iki durumu ayırt edemez.
     */
    public function test_unique_visitor_count_collapses_repeat_scans_from_the_same_visitor(): void
    {
        $scope = $this->workspaceWithTwoLocations();

        $this->recordResolve($scope, 0, 'visitor-a');
        $this->recordResolve($scope, 0, 'visitor-a');
        $this->recordResolve($scope, 0, 'visitor-a');
        $this->recordResolve($scope, 0, 'visitor-b');

        $response = $this->actingAs($scope['user'])
            ->getJson("/api/workspaces/{$scope['workspace']}/analytics/summary?range=today");

        $response->assertOk();
        self::assertSame(4, $response->json('qrResolveCount'));
        self::assertSame(2, $response->json('uniqueVisitorCount'));
    }

    /**
     * Bu ölçüm eklenmeden ÖNCE yazılmış olayların anahtarı yoktur. Onları
     * "bir kişi" saymak, bilinmeyeni bilinen gibi göstermek olurdu.
     */
    public function test_events_recorded_before_the_measurement_existed_are_not_counted_as_visitors(): void
    {
        $scope = $this->workspaceWithTwoLocations();

        $this->recordResolve($scope, 0, null);
        $this->recordResolve($scope, 0, null);

        $response = $this->actingAs($scope['user'])
            ->getJson("/api/workspaces/{$scope['workspace']}/analytics/summary?range=today");

        self::assertSame(2, $response->json('qrResolveCount'));
        self::assertSame(0, $response->json('uniqueVisitorCount'));
    }

    /**
     * İki şubesi olan bir işletmede toplam, birinin hiç taranmadığını gizler.
     */
    public function test_the_breakdown_shows_which_location_was_scanned(): void
    {
        $scope = $this->workspaceWithTwoLocations();

        $this->recordResolve($scope, 0, 'visitor-a');
        $this->recordResolve($scope, 0, 'visitor-b');
        $this->recordResolve($scope, 1, 'visitor-c');

        $response = $this->actingAs($scope['user'])
            ->getJson("/api/workspaces/{$scope['workspace']}/analytics/summary?range=today");

        $locations = $response->json('locations');

        self::assertCount(2, $locations);
        // En çok taranan önce.
        self::assertSame('Kadıköy', $locations[0]['label']);
        self::assertSame(2, $locations[0]['qrResolveCount']);
        self::assertSame('Beşiktaş', $locations[1]['label']);
        self::assertSame(1, $locations[1]['qrResolveCount']);
    }

    public function test_the_breakdown_shows_which_qr_code_was_scanned(): void
    {
        $scope = $this->workspaceWithTwoLocations();

        $this->recordResolve($scope, 1, 'visitor-a');

        $response = $this->actingAs($scope['user'])
            ->getJson("/api/workspaces/{$scope['workspace']}/analytics/summary?range=today");

        $qrCodes = $response->json('qrCodes');

        self::assertCount(1, $qrCodes);
        self::assertSame(str_repeat('2', 43), $qrCodes[0]['label']);
    }

    /**
     * Şube kapsamı istendiğinde kırılım da o şubeyle SINIRLI kalır; aksi
     * hâlde ekran "bu şube" derken markanın tamamını gösterirdi.
     */
    public function test_a_location_scoped_request_stays_inside_that_location(): void
    {
        $scope = $this->workspaceWithTwoLocations();

        $this->recordResolve($scope, 0, 'visitor-a');
        $this->recordResolve($scope, 1, 'visitor-b');

        $response = $this->actingAs($scope['user'])->getJson(
            "/api/workspaces/{$scope['workspace']}/brand/locations/{$scope['locations'][0]}/analytics/summary?range=today",
        );

        self::assertSame(1, $response->json('qrResolveCount'));
        self::assertCount(1, $response->json('locations'));
        self::assertSame('Kadıköy', $response->json('locations.0.label'));
    }

    /**
     * Tarama yoksa oran YOKTUR. Sıfır döndürmek "kimse açmadı" der; oysa
     * doğrusu "kimse taramadı"dır.
     */
    public function test_open_rate_is_absent_rather_than_zero_when_nothing_was_scanned(): void
    {
        $scope = $this->workspaceWithTwoLocations();

        $response = $this->actingAs($scope['user'])
            ->getJson("/api/workspaces/{$scope['workspace']}/analytics/summary?range=today");

        self::assertNull($response->json('openRate'));
    }

    /**
     * Ziyaretçi anahtarı GERİ ÇEVRİLEMEZ ve her gün DÖNER: bir ziyaretçi
     * günler boyunca izlenemez.
     */
    public function test_the_visitor_key_hides_the_address_and_rotates_daily(): void
    {
        $request = Request::create('/q/abc', 'GET', server: ['REMOTE_ADDR' => '203.0.113.9']);
        $request->headers->set('User-Agent', 'Mozilla/5.0');

        $monday = VisitorKey::forRequest($request, 41, Carbon::parse('2026-08-24'));
        $tuesday = VisitorKey::forRequest($request, 41, Carbon::parse('2026-08-25'));
        $otherTenant = VisitorKey::forRequest($request, 42, Carbon::parse('2026-08-24'));

        self::assertStringNotContainsString('203.0.113.9', $monday);
        self::assertStringNotContainsString('Mozilla', $monday);
        self::assertNotSame($monday, $tuesday, 'Tuz her gün dönmeli.');
        self::assertNotSame($monday, $otherTenant, 'Kiracılar arasında eşleştirme yapılamamalı.');

        // Aynı gün, aynı kiracı, aynı ziyaretçi: aynı anahtar — yoksa sayım
        // benzersizi değil isteği sayardı.
        self::assertSame($monday, VisitorKey::forRequest($request, 41, Carbon::parse('2026-08-24')));
    }
}
