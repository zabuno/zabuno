<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\GrantsPlanEntitlements;
use Tests\TestCase;

/**
 * S1-WP05b1 RED — real basic analytics ledger contract:
 *
 *   - Append-only tenant/location/QR/menu-scoped event ledger
 *     (`analytics_events`: workspace_id, location_id, qr_code_id, menu_id,
 *     event_type in {qr_resolve, menu_open}, occurred_at). No IP, user
 *     agent, or fingerprint column exists on it.
 *   - Stage-1 raw counts only, never unique/visitor claims.
 *   - GET /q/{token} records exactly one `qr_resolve` row (never
 *     `menu_open`) and still redirects 302, for a valid active token.
 *   - An invalid token records no ledger row at all.
 *   - Recording failure must not break the redirect.
 *   - GET /api/workspaces/{workspace}/brand/locations/{location}/analytics/summary?range=today|7d|30d
 *     returns {range, qrResolveCount, menuOpenCount, generatedAt} for an
 *     authenticated verified analytics.view workspace member, scoped
 *     exactly to that tenant+location.
 *   - An outsider (no membership) and a cross-tenant/location mismatch
 *     both get an enumeration-safe 404.
 *
 * None of the `analytics_events` table, the summary route, or ledger
 * recording exist yet, so every assertion below fails RED first against
 * the current production code.
 *
 * Requirement IDs: ANALYTICS-LEDGER-SCHEMA-01, ANALYTICS-LEDGER-NO-PII-01,
 * ANALYTICS-QR-RESOLVE-RECORD-01, ANALYTICS-QR-RESOLVE-NOT-MENU-OPEN-01,
 * ANALYTICS-INVALID-TOKEN-NO-RECORD-01, ANALYTICS-RECORD-FAILURE-SAFE-01,
 * ANALYTICS-SUMMARY-SCOPED-01, ANALYTICS-SUMMARY-OUTSIDER-404-01,
 * ANALYTICS-SUMMARY-CROSS-TENANT-404-01, ANALYTICS-SUMMARY-RANGE-01.
 */
final class AnalyticsLedgerSummaryTest extends TestCase
{
    use GrantsPlanEntitlements;
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: string} [workspaceId, locationId, menuId, token]
     */
    private function workspaceWithActiveQrCode(User $owner, string $slugSeed): array
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Restoran '.$slugSeed,
            'slug' => $slugSeed,
            'state' => 'active',
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $owner->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspaceId,
            'name' => 'Marka '.$slugSeed,
            'slug' => $slugSeed.'-brand',
            'locale' => 'tr',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId,
            'brand_id' => $brandId,
            'display_name' => 'Şube '.$slugSeed,
            'country_code' => 'TR',
            'timezone' => 'Europe/Istanbul',
            'city' => 'İstanbul',
            'address_line1' => 'Adres '.$slugSeed,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $menuId = (int) DB::table('menus')->insertGetId([
            'public_key' => Str::lower(Str::random(10)),
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'name' => 'Ana Menü',
            'state' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $categoryId = (int) DB::table('menu_categories')->insertGetId([
            'menu_id' => $menuId,
            'name' => 'Starters',
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = (int) DB::table('products')->insertGetId([
            'workspace_id' => $workspaceId,
            'name' => 'Kahve',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menu_items')->insert([
            'category_id' => $categoryId,
            'product_id' => $productId,
            'price_minor_amount' => 4250,
            'currency_code' => 'TRY',
            'position' => 0,
            'is_visible' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $publicationId = (int) DB::table('menu_publications')->insertGetId([
            'workspace_id' => $workspaceId,
            'menu_id' => $menuId,
            'location_id' => $locationId,
            'version' => 1,
            'state' => 'published',
            'snapshot' => json_encode(['categories' => [['name' => 'Starters', 'menuItems' => [['productName' => 'Kahve', 'priceMinorAmount' => 4250, 'currencyCode' => 'TRY']]]]]),
            'published_by' => $owner->id,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menu_publication_current_pointers')->insert([
            'menu_id' => $menuId,
            'current_publication_id' => $publicationId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $created = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/qr-codes",
            ['menuId' => $menuId]
        )->assertStatus(201);
        $token = (string) $created->json('token');

        // Owner 2026-08-26'da bu yeteneği plana bağladı; testler
        // yazıldığında ücretsizdi. Kurulum burada, kurucunun içinde yapılır ki
        // her test kendi plan kurgusunu tekrar yazmasın.
        $this->grantEntitlements($workspaceId);

        return [$workspaceId, $locationId, $menuId, $token];
    }

    private function summaryUrl(int $workspaceId, int $locationId, string $range): string
    {
        return "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/analytics/summary?range={$range}";
    }

    // --- ANALYTICS-LEDGER-SCHEMA-01 & ANALYTICS-LEDGER-NO-PII-01 --------------

    public function test_analytics_events_ledger_table_exists_append_only_scoped_and_carries_no_pii_columns(): void
    {
        self::assertTrue(
            Schema::hasTable('analytics_events'),
            'ANALYTICS-LEDGER-SCHEMA-01: analytics_events tablosu var olmalı.',
        );

        self::assertTrue(
            Schema::hasColumns('analytics_events', [
                'id', 'event_id', 'workspace_id', 'location_id', 'qr_code_id', 'menu_id', 'event_type', 'occurred_at',
            ]),
            'ANALYTICS-LEDGER-SCHEMA-01: tenant/location/QR/menu-scoped kolonlar ve idempotency için nullable unique event_id bulunmalı.',
        );

        /*
            Yasak liste HAM tanımlayıcılara karşıdır. `visitor_key` listede
            yok ve olmamalı: o bir kimlik değil, günlük dönen bir tuzla
            türetilmiş geri çevrilemez özettir (`docs/68`). Ham IP saklamakla
            özet saklamak arasındaki fark, ilkinin bir kişiyi işaret etmesi,
            ikincisinin yalnız "aynı gün aynı cihaz" diyebilmesidir.
        */
        foreach (['ip_address', 'ip', 'user_agent', 'fingerprint', 'session_id', 'visitor_id'] as $forbiddenColumn) {
            self::assertFalse(
                Schema::hasColumn('analytics_events', $forbiddenColumn),
                "ANALYTICS-LEDGER-NO-PII-01: analytics_events '{$forbiddenColumn}' kolonunu içermemeli.",
            );
        }
    }

    // --- ANALYTICS-LEDGER-EVENT-ID-IDEMPOTENCY-01 -------------------------------

    public function test_event_id_column_is_nullable_and_enforces_uniqueness_only_when_present(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId, $token] = $this->workspaceWithActiveQrCode($owner, 'analytics-event-id');
        $qrCodeId = (int) DB::table('qr_codes')->where('token', $token)->value('id');

        $row = static fn (?string $eventId): array => [
            'event_id' => $eventId,
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'qr_code_id' => $qrCodeId,
            'menu_id' => $menuId,
            'event_type' => 'menu_open',
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('analytics_events')->insert($row(null));
        DB::table('analytics_events')->insert($row(null));

        self::assertSame(
            2,
            DB::table('analytics_events')->whereNull('event_id')->count(),
            'ANALYTICS-LEDGER-EVENT-ID-IDEMPOTENCY-01: birden çok null event_id çakışma yaratmadan eklenebilmeli.',
        );

        DB::table('analytics_events')->insert($row('beacon-idempotency-key-1'));

        $duplicateRejected = false;
        try {
            DB::table('analytics_events')->insert($row('beacon-idempotency-key-1'));
        } catch (QueryException) {
            $duplicateRejected = true;
        }

        self::assertTrue(
            $duplicateRejected,
            'ANALYTICS-LEDGER-EVENT-ID-IDEMPOTENCY-01: aynı non-null event_id ikinci kez eklenince unique constraint reddetmeli.',
        );
    }

    // --- ANALYTICS-QR-RESOLVE-RECORD-01 & ANALYTICS-QR-RESOLVE-NOT-MENU-OPEN-01

    public function test_valid_q_token_get_records_exactly_one_qr_resolve_row_never_a_menu_open_and_still_redirects(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId, $token] = $this->workspaceWithActiveQrCode($owner, 'analytics-1');

        $response = $this->get("/q/{$token}");

        $response->assertStatus(302, 'ANALYTICS-QR-RESOLVE-RECORD-01: /q/{token} 302 redirect korunmalı.');
        $response->assertRedirect("/menu/{$token}");

        $qrCodeId = (int) DB::table('qr_codes')->where('token', $token)->value('id');

        $ledgerRows = DB::table('analytics_events')
            ->where('workspace_id', $workspaceId)
            ->where('location_id', $locationId)
            ->where('menu_id', $menuId)
            ->where('qr_code_id', $qrCodeId)
            ->get();

        self::assertSame(
            1,
            $ledgerRows->count(),
            'ANALYTICS-QR-RESOLVE-RECORD-01: geçerli /q/{token} GET tam olarak bir ledger satırı yazmalı.',
        );
        self::assertSame(
            'qr_resolve',
            $ledgerRows->first()->event_type,
            'ANALYTICS-QR-RESOLVE-RECORD-01: yazılan satır qr_resolve türünde olmalı.',
        );

        $menuOpenCount = DB::table('analytics_events')
            ->where('qr_code_id', $qrCodeId)
            ->where('event_type', 'menu_open')
            ->count();
        self::assertSame(
            0,
            $menuOpenCount,
            'ANALYTICS-QR-RESOLVE-NOT-MENU-OPEN-01: /q/{token} GET menu_open yazmamalı.',
        );
    }

    // --- ANALYTICS-MENU-OPEN-RECORD-01 ------------------------------------------

    public function test_valid_menu_token_get_records_exactly_one_menu_open_row_never_a_qr_resolve(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId, $token] = $this->workspaceWithActiveQrCode($owner, 'analytics-menu-open');

        $response = $this->get("/menu/{$token}");

        $response->assertStatus(200, 'ANALYTICS-MENU-OPEN-RECORD-01: /menu/{token} 200 dönmeli.');

        $qrCodeId = (int) DB::table('qr_codes')->where('token', $token)->value('id');

        $ledgerRows = DB::table('analytics_events')
            ->where('workspace_id', $workspaceId)
            ->where('location_id', $locationId)
            ->where('menu_id', $menuId)
            ->where('qr_code_id', $qrCodeId)
            ->get();

        self::assertSame(
            1,
            $ledgerRows->count(),
            'ANALYTICS-MENU-OPEN-RECORD-01: geçerli /menu/{token} GET tam olarak bir ledger satırı yazmalı.',
        );
        self::assertSame(
            'menu_open',
            $ledgerRows->first()->event_type,
            'ANALYTICS-MENU-OPEN-RECORD-01: yazılan satır menu_open türünde olmalı.',
        );

        $qrResolveCount = DB::table('analytics_events')
            ->where('qr_code_id', $qrCodeId)
            ->where('event_type', 'qr_resolve')
            ->count();
        self::assertSame(
            0,
            $qrResolveCount,
            'ANALYTICS-MENU-OPEN-RECORD-01: /menu/{token} GET qr_resolve yazmamalı.',
        );
    }

    // --- ANALYTICS-INVALID-TOKEN-NO-RECORD-01 ----------------------------------

    public function test_invalid_token_get_records_no_ledger_row(): void
    {
        $owner = $this->verifiedUser();
        $this->workspaceWithActiveQrCode($owner, 'analytics-2');

        $beforeCount = DB::table('analytics_events')->count();

        $unknownToken = str_repeat('z', 43);
        $response = $this->get("/q/{$unknownToken}");
        $response->assertStatus(404, 'ANALYTICS-INVALID-TOKEN-NO-RECORD-01: geçersiz token 404 dönmeli.');

        self::assertSame(
            $beforeCount,
            DB::table('analytics_events')->count(),
            'ANALYTICS-INVALID-TOKEN-NO-RECORD-01: geçersiz token hiçbir ledger satırı yazmamalı.',
        );
    }

    // --- ANALYTICS-RECORD-FAILURE-SAFE-01 ---------------------------------------

    public function test_analytics_recording_failure_does_not_break_the_redirect(): void
    {
        $owner = $this->verifiedUser();
        [, , , $token] = $this->workspaceWithActiveQrCode($owner, 'analytics-3');

        Schema::drop('analytics_events');

        $response = $this->get("/q/{$token}");

        $response->assertStatus(
            302,
            'ANALYTICS-RECORD-FAILURE-SAFE-01: ledger yazımı başarısız olsa bile redirect kırılmamalı.',
        );
        $response->assertRedirect("/menu/{$token}");
    }

    // --- ANALYTICS-SUMMARY-SCOPED-01 & ANALYTICS-SUMMARY-RANGE-01 --------------

    public function test_authorized_member_gets_exact_scoped_summary_shape_with_raw_counts_for_each_range(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId, $token] = $this->workspaceWithActiveQrCode($owner, 'analytics-4');
        $qrCodeId = (int) DB::table('qr_codes')->where('token', $token)->value('id');

        $frozenNow = Carbon::parse('2026-08-22 12:00:00');
        $this->travelTo($frozenNow);

        try {
            // today: 3 x qr_resolve (via real /q/{token} GET) + 2 x menu_open, all "now".
            $this->get("/q/{$token}");
            $this->get("/q/{$token}");
            $this->get("/q/{$token}");

            $insertPair = function (Carbon $occurredAt) use ($workspaceId, $locationId, $qrCodeId, $menuId): void {
                DB::table('analytics_events')->insert([
                    ['workspace_id' => $workspaceId, 'location_id' => $locationId, 'qr_code_id' => $qrCodeId, 'menu_id' => $menuId, 'event_type' => 'qr_resolve', 'occurred_at' => $occurredAt, 'created_at' => $occurredAt, 'updated_at' => $occurredAt],
                    ['workspace_id' => $workspaceId, 'location_id' => $locationId, 'qr_code_id' => $qrCodeId, 'menu_id' => $menuId, 'event_type' => 'menu_open', 'occurred_at' => $occurredAt, 'created_at' => $occurredAt, 'updated_at' => $occurredAt],
                ]);
            };

            DB::table('analytics_events')->insert([
                ['workspace_id' => $workspaceId, 'location_id' => $locationId, 'qr_code_id' => $qrCodeId, 'menu_id' => $menuId, 'event_type' => 'menu_open', 'occurred_at' => $frozenNow, 'created_at' => $frozenNow, 'updated_at' => $frozenNow],
                ['workspace_id' => $workspaceId, 'location_id' => $locationId, 'qr_code_id' => $qrCodeId, 'menu_id' => $menuId, 'event_type' => 'menu_open', 'occurred_at' => $frozenNow, 'created_at' => $frozenNow, 'updated_at' => $frozenNow],
            ]);

            // Rolling boundaries: a pair inside 7d (but not today), a pair inside
            // 30d (but not 7d), and a pair outside 30d entirely.
            $insertPair($frozenNow->copy()->subDays(3));
            $insertPair($frozenNow->copy()->subDays(8));
            $insertPair($frozenNow->copy()->subDays(31));

            $expected = [
                'today' => ['qrResolveCount' => 3, 'menuOpenCount' => 2],
                '7d' => ['qrResolveCount' => 4, 'menuOpenCount' => 3],
                '30d' => ['qrResolveCount' => 5, 'menuOpenCount' => 4],
            ];

            foreach ($expected as $range => $counts) {
                $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->getJson(
                    $this->summaryUrl($workspaceId, $locationId, $range)
                );

                $response->assertStatus(200, "ANALYTICS-SUMMARY-SCOPED-01: {$range} aralığı 200 dönmeli.");
                $response->assertJson([
                    'range' => $range,
                    'qrResolveCount' => $counts['qrResolveCount'],
                    'menuOpenCount' => $counts['menuOpenCount'],
                ]);
                self::assertIsString(
                    $response->json('generatedAt'),
                    'ANALYTICS-SUMMARY-RANGE-01: generatedAt string olarak dönmeli.',
                );

                $keys = array_keys((array) $response->json());
                sort($keys);
                /*
                    ŞEKİL GENİŞLEDİ ve gerekçesi değişti.

                    Bu iddia eskiden "unique/visitor iddiası EKLENMEMELİ"
                    diyordu ve haklıydı: ölçüm yoktu, dolayısıyla böyle bir
                    alan uydurma olurdu.

                    Artık ölçüm var (`docs/68`): olay satırında günlük dönen
                    bir tuzla türetilmiş, geri çevrilemez bir ziyaretçi özeti
                    tutuluyor. Kural değişmedi — alan ancak GERÇEKTEN
                    ölçüldüğü için var; yasak, uydurmaya karşıydı.
                */
                self::assertSame(
                    [
                        'generatedAt',
                        'locations',
                        'menuOpenCount',
                        'openRate',
                        'qrCodes',
                        'qrResolveCount',
                        'range',
                        'uniqueVisitorCount',
                    ],
                    $keys,
                    'ANALYTICS-SUMMARY-SCOPED-01: yanıt tam olarak bu alanları içermeli.',
                );
            }
        } finally {
            $this->travelBack();
        }
    }

    // --- ANALYTICS-SUMMARY-OUTSIDER-404-01 --------------------------------------

    public function test_outsider_with_no_membership_gets_enumeration_safe_404(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithActiveQrCode($owner, 'analytics-5');

        $outsider = $this->verifiedUser();

        $response = $this->actingAs($outsider)->withHeaders($this->jsonHeaders())->getJson(
            $this->summaryUrl($workspaceId, $locationId, 'today')
        );

        $response->assertStatus(404, 'ANALYTICS-SUMMARY-OUTSIDER-404-01: outsider 404 almalı.');
    }

    // --- ANALYTICS-SUMMARY-CROSS-TENANT-404-01 ----------------------------------

    public function test_cross_tenant_and_cross_location_mismatch_get_enumeration_safe_404(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithActiveQrCode($owner, 'analytics-6a');

        $otherOwner = $this->verifiedUser();
        [$otherWorkspaceId, $otherLocationId] = $this->workspaceWithActiveQrCode($otherOwner, 'analytics-6b');

        $crossTenant = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->getJson(
            $this->summaryUrl($otherWorkspaceId, $otherLocationId, 'today')
        );
        $crossTenant->assertStatus(404, 'ANALYTICS-SUMMARY-CROSS-TENANT-404-01: yanlış workspace 404 almalı.');

        $locationMismatch = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->getJson(
            $this->summaryUrl($workspaceId, $otherLocationId, 'today')
        );
        $locationMismatch->assertStatus(
            404,
            'ANALYTICS-SUMMARY-CROSS-TENANT-404-01: doğru workspace ama başka tenant\'ın location\'ı 404 almalı.',
        );
    }
}
