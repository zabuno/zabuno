<?php

declare(strict_types=1);

namespace Tests\Feature\QrDestination;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * S1-WP04b9 RED — bulk dining-area + dining-table + per-table QR creation
 * HTTP-delivery contract, mirroring the single QR create flow in
 * tests/Feature/QrDestination/QrDestinationJourneyTest.php but producing M
 * real dining areas and N real dining tables (round-robin distributed
 * across those areas) each with exactly one unique resolver token/QR.
 *
 * Frozen route + JSON contract. No "tables/bulk" route nor any
 * dining-area/dining-table schema exists yet — grep of routes/api.php and
 * database/migrations confirms this, so every request below is expected to
 * fail RED with a 404 route-not-found response, or a missing-table/column
 * DB error on any schema assertion, not a logic assertion failure:
 *
 *   POST /api/workspaces/{workspace}/brand/locations/{location}/tables/bulk
 *        body {
 *          "menuId": int,
 *          "areaSectionCount": int (1..50),
 *          "tableCount": int (1..500),
 *          "namingPrefix": string (<=10 chars, optional, default "T"),
 *          "namingSequenceStart": int (0..9999, optional, default 1),
 *          "namingRange": string "digits-digits" (each endpoint 0..9999,
 *            optional, start<=end),
 *          "seatCountPerTable": int (1..20)
 *        }
 *     -> 201 {
 *          "areas": [{"id": int, "workspaceId": int, "locationId": int, "label": string}, ...],
 *          "tables": [{"id": int, "workspaceId": int, "locationId": int, "areaId": int, "name": string, "seatCount": int}, ...],
 *          "qrCodes": [{"id": int, "tableId": int, "workspaceId": int, "locationId": int, "menuId": int, "token": string(43), "resolverUrl": string, "destinationType": "published_menu", "state": "active"}, ...]
 *        }
 *        areas persists independently of tableCount — exactly
 *        areaSectionCount rows, labelled "Area 1".."Area M", persisted in
 *        a real "dining_areas" table even when areaSectionCount exceeds
 *        tableCount. Table names are namingPrefix.sequence (default
 *        "T1".."T5" or the namingRange window), each table's areaId is
 *        assigned round-robin across the persisted dining_areas in
 *        creation order, and every table gets exactly one qr_codes row
 *        (qr_codes.dining_table_id) with a unique 43-char token. No
 *        first-class Section or Seat rows — only dining_areas and
 *        dining_tables.
 *     -> 422 + zero persisted rows (dining_areas, dining_tables, qr_codes) for:
 *          - namingRange cardinality mismatch vs tableCount
 *          - namingSequenceStart supplied together with namingRange but
 *            not equal to the range start
 *          - areaSectionCount outside 1..50
 *          - tableCount outside 1..500
 *          - namingPrefix longer than 10 chars
 *          - namingSequenceStart outside 0..9999
 *          - malformed, descending, or out-of-0..9999-range namingRange
 *            endpoints
 *          - seatCountPerTable outside 1..20
 *          - target menu has no current publication
 *          - namingSequenceStart plus tableCount derives an end sequence
 *            number greater than 9999 (e.g. namingSequenceStart=9999,
 *            tableCount=2 derives end=10000, outside 0..9999)
 *
 *   The route also enforces throttle:5,1 (5 requests/minute) per
 *   authenticated user, mirroring the existing
 *   Route::post('/workspaces', ...)->middleware('throttle:5,1') convention
 *   in routes/api.php: a 6th bulk request within the same minute for the
 *   same user gets 429, not 201/404.
 *
 * Requirement IDs: BULKQR-CREATE-01, BULKQR-AREAS-EXCEED-TABLES-01,
 * BULKQR-NAMING-RANGE-01, BULKQR-NAMING-SEQSTART-RANGE-01, BULKQR-BOUNDS-01,
 * BULKQR-NOCURRENT-01, BULKQR-NAMING-SEQSTART-DERIVED-END-01,
 * BULKQR-THROTTLE-01.
 */
final class BulkQrCreationTest extends TestCase
{
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
     * Builds workspace/brand/location/menu plus a real *current* publication,
     * mirroring QrDestinationJourneyTest::workspaceWithCurrentPublication.
     *
     * @return array{0: int, 1: int, 2: int} [workspaceId, locationId, menuId]
     */
    private function workspaceWithCurrentPublication(User $owner, string $slugSeed): array
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
            'city' => 'İstanbul',
            'address_line1' => 'Adres '.$slugSeed,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $menuId = (int) DB::table('menus')->insertGetId([
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
            'snapshot' => json_encode([
                'categories' => [
                    ['name' => 'Starters', 'menuItems' => [['productName' => 'Kahve', 'priceMinorAmount' => 4250, 'currencyCode' => 'TRY']]],
                ],
            ]),
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

        return [$workspaceId, $locationId, $menuId];
    }

    /**
     * @return array{0: int, 1: int} [workspaceId, locationId]
     */
    private function workspaceWithDraftOnlyMenu(User $owner, string $slugSeed): array
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
            'city' => 'İstanbul',
            'address_line1' => 'Adres '.$slugSeed,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menus')->insertGetId([
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'name' => 'Ana Menü',
            'state' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$workspaceId, $locationId];
    }

    private function bulkUrl(int $workspaceId, int $locationId): string
    {
        return "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/tables/bulk";
    }

    private function assertZeroBulkPersistence(): void
    {
        self::assertDatabaseCount('dining_areas', 0);
        self::assertDatabaseCount('dining_tables', 0);
        self::assertDatabaseCount('qr_codes', 0);
    }

    // --- BULKQR-CREATE-01 ------------------------------------------------

    public function test_valid_five_table_two_area_request_persists_areas_independently_and_round_robins_tables_with_unique_qr_tokens(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'bulk-create-1');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            $this->bulkUrl($workspaceId, $locationId),
            [
                'menuId' => $menuId,
                'areaSectionCount' => 2,
                'tableCount' => 5,
                'seatCountPerTable' => 4,
            ]
        );

        $response->assertStatus(201, 'BULKQR-CREATE-01: geçerli 5 masa/2 alan isteği 201 dönmeli.');

        $body = $response->json();
        $areas = $body['areas'] ?? [];
        $tables = $body['tables'] ?? [];
        $qrCodes = $body['qrCodes'] ?? [];

        self::assertCount(2, $areas, 'BULKQR-CREATE-01: tam olarak 2 alan dönmeli.');
        self::assertCount(5, $tables, 'BULKQR-CREATE-01: tam olarak 5 masa dönmeli.');
        self::assertCount(5, $qrCodes, 'BULKQR-CREATE-01: tam olarak 5 QR dönmeli.');

        $expectedAreaLabels = ['Area 1', 'Area 2'];
        $areaIdsByLabel = [];

        foreach ($areas as $index => $area) {
            self::assertSame($expectedAreaLabels[$index], $area['label'] ?? null, "BULKQR-CREATE-01: alan {$index} etiketi Area 1/Area 2 sırasıyla eşleşmeli.");
            $areaId = $area['id'] ?? null;
            self::assertIsInt($areaId, 'BULKQR-CREATE-01: area id gerçek pozitif int olmalı.');
            self::assertGreaterThan(0, $areaId);
            $areaIdsByLabel[$expectedAreaLabels[$index]] = $areaId;

            self::assertDatabaseHas('dining_areas', [
                'id' => $areaId,
                'workspace_id' => $workspaceId,
                'location_id' => $locationId,
                'label' => $expectedAreaLabels[$index],
            ]);
        }

        $expectedNames = ['T1', 'T2', 'T3', 'T4', 'T5'];
        $expectedAreaLabelsByTable = ['Area 1', 'Area 2', 'Area 1', 'Area 2', 'Area 1'];
        $tableIds = [];
        $tablesById = [];

        foreach ($tables as $index => $table) {
            self::assertSame($expectedNames[$index], $table['name'] ?? null, "BULKQR-CREATE-01: masa {$index} adı T1..T5 dizisiyle eşleşmeli.");
            self::assertSame(4, $table['seatCount'] ?? null, 'BULKQR-CREATE-01: seatCount istekle eşleşmeli.');

            $tableId = $table['id'] ?? null;
            self::assertIsInt($tableId, 'BULKQR-CREATE-01: table id gerçek pozitif int olmalı.');
            self::assertGreaterThan(0, $tableId);
            $tableIds[] = $tableId;
            $tablesById[$tableId] = $table;

            $expectedAreaId = $areaIdsByLabel[$expectedAreaLabelsByTable[$index]];
            self::assertSame($expectedAreaId, $table['areaId'] ?? null, "BULKQR-CREATE-01: masa {$index} areaId'si round-robin dağılıma göre {$expectedAreaLabelsByTable[$index]}'e ait olmalı.");

            self::assertDatabaseHas('dining_tables', [
                'id' => $tableId,
                'workspace_id' => $workspaceId,
                'location_id' => $locationId,
                'area_id' => $expectedAreaId,
                'name' => $expectedNames[$index],
                'seat_count' => 4,
            ]);
        }

        self::assertCount(5, array_unique($tableIds), 'BULKQR-CREATE-01: tableId\'ler benzersiz olmalı.');

        $tokens = [];
        $qrTableIds = [];

        foreach ($qrCodes as $qrCode) {
            $tableId = $qrCode['tableId'] ?? null;
            self::assertArrayHasKey($tableId, $tablesById, 'BULKQR-CREATE-01: her QR gerçek bir masa tableId\'sine bire-bir haritalanmalı.');
            $qrTableIds[] = $tableId;

            self::assertSame($workspaceId, $qrCode['workspaceId'] ?? null);
            self::assertSame($locationId, $qrCode['locationId'] ?? null);
            self::assertSame($menuId, $qrCode['menuId'] ?? null);
            self::assertSame('published_menu', $qrCode['destinationType'] ?? null, 'BULKQR-CREATE-01: destinationType mevcut "published_menu" değerinde kalmalı — masaya bağlanma qr_codes.dining_table_id ile ifade edilir, destinationType değişmez.');
            self::assertSame('active', $qrCode['state'] ?? null);

            $token = $qrCode['token'] ?? '';
            self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', (string) $token, 'BULKQR-CREATE-01: her masa 256-bit base64url [A-Za-z0-9_-]{43} token almalı.');
            $tokens[] = $token;

            $resolverUrl = $qrCode['resolverUrl'] ?? '';
            self::assertStringContainsString((string) $token, (string) $resolverUrl, 'BULKQR-CREATE-01: resolverUrl gerçek token\'ı içermeli.');

            self::assertDatabaseHas('qr_codes', [
                'token' => $token,
                'workspace_id' => $workspaceId,
                'location_id' => $locationId,
                'dining_table_id' => $tableId,
            ]);
        }

        self::assertCount(5, array_unique($tokens), 'BULKQR-CREATE-01: her masa için token benzersiz olmalı.');
        self::assertCount(5, array_unique($qrTableIds), 'BULKQR-CREATE-01: QR->masa haritalaması bire-bir olmalı.');
        self::assertDatabaseCount('dining_areas', 2);
        self::assertDatabaseCount('dining_tables', 5);
        self::assertDatabaseCount('qr_codes', 5);
    }

    // --- BULKQR-AREAS-EXCEED-TABLES-01 --------------------------------------

    public function test_area_section_count_exceeding_table_count_persists_all_areas_while_only_table_count_tables_and_qr_codes_exist(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'bulk-areas-exceed-1');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            $this->bulkUrl($workspaceId, $locationId),
            [
                'menuId' => $menuId,
                'areaSectionCount' => 3,
                'tableCount' => 2,
                'seatCountPerTable' => 2,
            ]
        );

        $response->assertStatus(201, 'BULKQR-AREAS-EXCEED-TABLES-01: alan sayısı masa sayısını aşabilir, 201 dönmeli.');

        $body = $response->json();
        self::assertCount(3, $body['areas'] ?? [], 'BULKQR-AREAS-EXCEED-TABLES-01: 3 alanın tamamı dönmeli.');
        self::assertCount(2, $body['tables'] ?? [], 'BULKQR-AREAS-EXCEED-TABLES-01: sadece 2 masa dönmeli.');
        self::assertCount(2, $body['qrCodes'] ?? [], 'BULKQR-AREAS-EXCEED-TABLES-01: sadece 2 QR dönmeli.');

        self::assertDatabaseCount('dining_areas', 3);
        self::assertDatabaseCount('dining_tables', 2);
        self::assertDatabaseCount('qr_codes', 2);
    }

    // --- BULKQR-NAMING-RANGE-01 -------------------------------------------

    public function test_naming_range_5_to_7_with_table_count_3_yields_names_t5_through_t7(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'bulk-range-1');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            $this->bulkUrl($workspaceId, $locationId),
            [
                'menuId' => $menuId,
                'areaSectionCount' => 1,
                'tableCount' => 3,
                'namingRange' => '5-7',
                'seatCountPerTable' => 2,
            ]
        );

        $response->assertStatus(201, 'BULKQR-NAMING-RANGE-01: namingRange 5-7 ve tableCount 3 kardinalitesi eşleştiği için 201 dönmeli.');
        $names = array_column($response->json('tables') ?? [], 'name');
        self::assertSame(['T5', 'T6', 'T7'], $names, 'BULKQR-NAMING-RANGE-01: isimler T5..T7 olmalı.');
    }

    public function test_naming_range_cardinality_mismatch_with_table_count_is_422_and_persists_nothing(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'bulk-range-2');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            $this->bulkUrl($workspaceId, $locationId),
            [
                'menuId' => $menuId,
                'areaSectionCount' => 1,
                'tableCount' => 5,
                'namingRange' => '5-7',
                'seatCountPerTable' => 2,
            ]
        );

        $response->assertStatus(422, 'BULKQR-NAMING-RANGE-01: namingRange kardinalitesi tableCount ile uyuşmuyorsa 422 dönmeli.');
        $this->assertZeroBulkPersistence();
    }

    // --- BULKQR-NAMING-SEQSTART-RANGE-01 -----------------------------------

    public function test_naming_sequence_start_matching_range_start_is_accepted(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'bulk-seqstart-1');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            $this->bulkUrl($workspaceId, $locationId),
            [
                'menuId' => $menuId,
                'areaSectionCount' => 1,
                'tableCount' => 3,
                'namingRange' => '5-7',
                'namingSequenceStart' => 5,
                'seatCountPerTable' => 2,
            ]
        );

        $response->assertStatus(201, 'BULKQR-NAMING-SEQSTART-RANGE-01: namingSequenceStart, range başlangıcına eşitse 201 dönmeli.');
    }

    public function test_naming_sequence_start_conflicting_with_range_start_is_422_and_persists_nothing(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'bulk-seqstart-2');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            $this->bulkUrl($workspaceId, $locationId),
            [
                'menuId' => $menuId,
                'areaSectionCount' => 1,
                'tableCount' => 3,
                'namingRange' => '5-7',
                'namingSequenceStart' => 1,
                'seatCountPerTable' => 2,
            ]
        );

        $response->assertStatus(422, 'BULKQR-NAMING-SEQSTART-RANGE-01: namingSequenceStart range başlangıcından farklıysa 422 dönmeli.');
        $this->assertZeroBulkPersistence();
    }

    // --- BULKQR-BOUNDS-01 ---------------------------------------------------

    /**
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function invalidBulkPayloadProvider(): iterable
    {
        yield 'areaSectionCount zero' => [['areaSectionCount' => 0, 'tableCount' => 5, 'seatCountPerTable' => 2]];
        yield 'areaSectionCount 51' => [['areaSectionCount' => 51, 'tableCount' => 5, 'seatCountPerTable' => 2]];
        yield 'tableCount zero' => [['areaSectionCount' => 1, 'tableCount' => 0, 'seatCountPerTable' => 2]];
        yield 'tableCount 501' => [['areaSectionCount' => 1, 'tableCount' => 501, 'seatCountPerTable' => 2]];
        yield 'namingPrefix 11 chars' => [['areaSectionCount' => 1, 'tableCount' => 5, 'seatCountPerTable' => 2, 'namingPrefix' => 'ABCDEFGHIJK']];
        yield 'namingSequenceStart negative' => [['areaSectionCount' => 1, 'tableCount' => 5, 'seatCountPerTable' => 2, 'namingSequenceStart' => -1]];
        yield 'namingSequenceStart 10000' => [['areaSectionCount' => 1, 'tableCount' => 5, 'seatCountPerTable' => 2, 'namingSequenceStart' => 10000]];
        yield 'namingRange malformed' => [['areaSectionCount' => 1, 'tableCount' => 5, 'seatCountPerTable' => 2, 'namingRange' => 'not-a-range']];
        yield 'namingRange descending' => [['areaSectionCount' => 1, 'tableCount' => 5, 'seatCountPerTable' => 2, 'namingRange' => '9-3']];
        yield 'namingRange end 10000 exceeds 0..9999' => [['areaSectionCount' => 1, 'tableCount' => 3, 'seatCountPerTable' => 2, 'namingRange' => '9998-10000']];
        yield 'namingRange oversized digit endpoint' => [['areaSectionCount' => 1, 'tableCount' => 5, 'seatCountPerTable' => 2, 'namingRange' => '99999999999999999999-100000000000000000005']];
        yield 'seatCountPerTable zero' => [['areaSectionCount' => 1, 'tableCount' => 5, 'seatCountPerTable' => 0]];
        yield 'seatCountPerTable 21' => [['areaSectionCount' => 1, 'tableCount' => 5, 'seatCountPerTable' => 21]];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidBulkPayloadProvider')]
    public function test_invalid_bounds_and_malformed_input_returns_422_without_persistence(array $overrides): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'bulk-bounds-'.md5(json_encode($overrides)));

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            $this->bulkUrl($workspaceId, $locationId),
            array_merge(['menuId' => $menuId], $overrides)
        );

        $response->assertStatus(422, 'BULKQR-BOUNDS-01: sınır dışı/bozuk girdi 422 dönmeli. Payload: '.json_encode($overrides));
        $this->assertZeroBulkPersistence();
    }

    // --- BULKQR-NOCURRENT-01 -------------------------------------------------

    public function test_bulk_create_without_a_current_publication_returns_exact_422_message_and_zero_writes(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithDraftOnlyMenu($owner, 'bulk-nocurrent-1');
        $menuId = (int) DB::table('menus')->where('location_id', $locationId)->value('id');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            $this->bulkUrl($workspaceId, $locationId),
            [
                'menuId' => $menuId,
                'areaSectionCount' => 1,
                'tableCount' => 5,
                'seatCountPerTable' => 2,
            ]
        );

        $response->assertStatus(422, 'BULKQR-NOCURRENT-01: current publication yoksa bulk create 422 dönmeli.');
        $response->assertJsonPath('message', 'No current publication for this menu.');
        $this->assertZeroBulkPersistence();
    }

    // --- BULKQR-NAMING-SEQSTART-DERIVED-END-01 -------------------------------

    public function test_naming_sequence_start_9999_with_table_count_2_derives_an_out_of_range_end_and_returns_422_with_zero_persistence(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'bulk-seqstart-derived-end-1');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            $this->bulkUrl($workspaceId, $locationId),
            [
                'menuId' => $menuId,
                'areaSectionCount' => 1,
                'tableCount' => 2,
                'namingSequenceStart' => 9999,
                'seatCountPerTable' => 2,
            ]
        );

        $response->assertStatus(422, 'BULKQR-NAMING-SEQSTART-DERIVED-END-01: namingSequenceStart 9999 + tableCount 2, türetilen bitiş 10000 0..9999 dışına taştığı için 422 dönmeli.');
        $this->assertZeroBulkPersistence();
    }

    // --- BULKQR-THROTTLE-01 ---------------------------------------------------

    public function test_the_sixth_bulk_request_within_a_minute_for_the_same_user_is_throttled_with_429(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'bulk-throttle-1');

        RateLimiter::clear(sha1((string) $owner->getAuthIdentifier()));

        $payload = [
            'menuId' => $menuId,
            'areaSectionCount' => 1,
            'tableCount' => 1,
            'seatCountPerTable' => 1,
        ];

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
                $this->bulkUrl($workspaceId, $locationId),
                $payload
            );

            $response->assertStatus(201, "BULKQR-THROTTLE-01: dakikadaki {$attempt}. istek tam olarak 201 dönmeli.");
        }

        $sixth = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            $this->bulkUrl($workspaceId, $locationId),
            $payload
        );

        $sixth->assertStatus(429, 'BULKQR-THROTTLE-01: aynı kullanıcı için dakikadaki 6. istek 429 dönmeli.');
    }
}
