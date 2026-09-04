<?php

declare(strict_types=1);

namespace Tests\Feature\QrDestination;

use App\Application\QrDestination\Dto\QrPrintCard;
use App\Application\QrDestination\Dto\QrRenderedImage;
use App\Application\QrDestination\Port\QrPrintSheetPort;
use App\Domain\QrDestination\QrPrintSheet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\GrantsPlanEntitlements;
use Tests\TestCase;

/**
 * QRSHEET-ENDPOINT-01 — FF-111, `docs/104` Döngü 8.
 *
 * Basılabilir DESTE: kesilip masalara dağıtılacak kartlar. Bugüne kadar tek
 * çıktı, A4'ün ortasında tek bir çıplak kareydi — 40 masa için 40 ayrı sayfa,
 * her biri %97 beyaz ve baskıdan sonra birbirinden ayırt edilemez.
 *
 *   GET /api/workspaces/{w}/brand/locations/{l}/qr-codes/print.pdf
 *     -> 200 application/pdf, ek olarak indirilir; yalnız ETKİN kodlar,
 *        kartta masa adı ve alan etiketiyle.
 *     -> 404: üye değil, başka kiracının şubesi, hiç etkin kod yok.
 *     -> 403: qr.view var ama qr.design.manage yok.
 */
final class QrPrintSheetTest extends TestCase
{
    use GrantsPlanEntitlements;
    use RefreshDatabase;

    /** @return array{0: int, 1: int, 2: int} */
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
            'name' => 'Kebapçı '.$slugSeed,
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

        $publicationId = (int) DB::table('menu_publications')->insertGetId([
            'workspace_id' => $workspaceId,
            'menu_id' => $menuId,
            'location_id' => $locationId,
            'version' => 1,
            'state' => 'published',
            'snapshot' => json_encode(['categories' => []]),
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

        $this->grantEntitlements($workspaceId);

        return [$workspaceId, $locationId, $menuId];
    }

    private function sheetUrl(int $workspaceId, int $locationId): string
    {
        return "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/qr-codes/print.pdf";
    }

    private function createTables(User $owner, int $workspaceId, int $locationId, int $menuId, int $count): void
    {
        $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->postJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/tables/bulk",
            ['menuId' => $menuId, 'areaSectionCount' => 1, 'tableCount' => $count, 'seatCountPerTable' => 4]
        )->assertStatus(201);
    }

    public function test_owner_downloads_one_pdf_holding_a_card_per_active_table(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'sheet-ok');
        $this->createTables($owner, $workspaceId, $locationId, $menuId, 3);

        $captured = null;

        $this->app->bind(QrPrintSheetPort::class, function () use (&$captured): QrPrintSheetPort {
            return new class($captured) implements QrPrintSheetPort
            {
                /** @param  mixed  $captured */
                public function __construct(private &$captured) {}

                public function renderSheet(array $cards, string $caption, string $brandName): QrRenderedImage
                {
                    $this->captured = ['cards' => $cards, 'caption' => $caption, 'brandName' => $brandName];

                    return new QrRenderedImage(bytes: '%PDF-1.7 fake %%EOF', mimeType: 'application/pdf');
                }
            };
        });

        $response = $this->actingAs($owner)->get($this->sheetUrl($workspaceId, $locationId));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        self::assertStringContainsString('attachment;', (string) $response->headers->get('Content-Disposition'));
        $response->assertHeader('X-Qr-Sheet-Cards', '3');
        $response->assertHeader('X-Qr-Sheet-Total', '3');

        self::assertIsArray($captured);
        /** @var list<QrPrintCard> $cards */
        $cards = $captured['cards'];
        self::assertCount(3, $cards, 'QRSHEET-ENDPOINT-01: her etkin masa bir kart almalı.');

        $titles = array_map(static fn (QrPrintCard $card): string => $card->title, $cards);
        sort($titles);
        self::assertSame(['T1', 'T2', 'T3'], $titles, 'QRSHEET-ENDPOINT-01: kartın başlığı masanın GERÇEK adı olmalı.');

        self::assertSame('Area 1', $cards[0]->subtitle);
        self::assertNotSame('', $cards[0]->pngBytes, 'QRSHEET-ENDPOINT-01: kart gerçek bir PNG taşımalı.');

        // Kartın dili RESTORANIN dilidir: markanın yerel ayarı `tr`.
        self::assertSame('Menü için okutun', $captured['caption']);
        self::assertSame('Kebapçı sheet-ok', $captured['brandName']);
    }

    public function test_disabled_codes_are_never_printed(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'sheet-disabled');
        $this->createTables($owner, $workspaceId, $locationId, $menuId, 2);

        $firstId = (int) DB::table('qr_codes')->where('location_id', $locationId)->orderBy('id')->value('id');

        $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->putJson("/api/workspaces/{$workspaceId}/qr-codes/{$firstId}/disable")
            ->assertStatus(200);

        $captured = null;
        $this->bindRecorder($captured);

        $this->actingAs($owner)->get($this->sheetUrl($workspaceId, $locationId))
            ->assertStatus(200)
            ->assertHeader('X-Qr-Sheet-Cards', '1');

        self::assertIsArray($captured);
        self::assertCount(1, $captured['cards'], 'QRSHEET-ENDPOINT-01: kapatılmış kod basılmaz — sahibi ölü bir kart bastırmaya davet etmek olurdu.');
    }

    public function test_a_location_with_no_active_code_is_a_404_not_an_empty_pdf(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        [$workspaceId, $locationId] = $this->workspaceWithCurrentPublication($owner, 'sheet-empty');

        $this->actingAs($owner)->get($this->sheetUrl($workspaceId, $locationId))->assertStatus(404);
    }

    public function test_a_second_chunk_beyond_the_deck_is_a_404(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'sheet-chunk');
        $this->createTables($owner, $workspaceId, $locationId, $menuId, 2);

        $this->actingAs($owner)->get($this->sheetUrl($workspaceId, $locationId).'?chunk=2')->assertStatus(404);
    }

    public function test_a_member_without_qr_design_manage_is_denied_with_403(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'sheet-403');
        $this->createTables($owner, $workspaceId, $locationId, $menuId, 1);

        $member = User::factory()->create(['email_verified_at' => now()]);
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $member->id,
            'role' => 'member',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 403, 404 değil: kullanıcı bu kiracının üyesi — varlığı gizlemenin
        // anlamı yok, eksik olan YETKİ.
        $this->actingAs($member)->get($this->sheetUrl($workspaceId, $locationId))->assertStatus(403);
    }

    public function test_a_foreign_workspace_location_is_a_404(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $stranger = User::factory()->create(['email_verified_at' => now()]);

        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'sheet-tenant-a');
        $this->createTables($owner, $workspaceId, $locationId, $menuId, 1);

        $this->actingAs($stranger)->get($this->sheetUrl($workspaceId, $locationId))->assertStatus(404);
    }

    public function test_the_request_is_bounded_so_a_five_hundred_table_deck_cannot_time_out(): void
    {
        // Sınır keyfi değil: her kart ayrı bir PNG üretir. Aşıldığında ürün
        // sessizce kırpmaz — `chunk` ile sayfa sayfa indirilir.
        self::assertSame(48, QrPrintSheet::CARDS_PER_REQUEST);
    }

    /** @param  mixed  $captured */
    private function bindRecorder(&$captured): void
    {
        $this->app->bind(QrPrintSheetPort::class, function () use (&$captured): QrPrintSheetPort {
            return new class($captured) implements QrPrintSheetPort
            {
                /** @param  mixed  $captured */
                public function __construct(private &$captured) {}

                public function renderSheet(array $cards, string $caption, string $brandName): QrRenderedImage
                {
                    $this->captured = ['cards' => $cards, 'caption' => $caption, 'brandName' => $brandName];

                    return new QrRenderedImage(bytes: '%PDF-1.7 fake %%EOF', mimeType: 'application/pdf');
                }
            };
        });
    }

    public function test_the_real_adapter_produces_a_multi_page_pdf(): void
    {
        // Sahte port değil GERÇEK mPDF: yazıcıya giden şey gerçekten bir PDF
        // olmalı ve 13 kart iki sayfa etmeli.
        $owner = User::factory()->create(['email_verified_at' => now()]);
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'sheet-real');
        $this->createTables($owner, $workspaceId, $locationId, $menuId, 13);

        $response = $this->actingAs($owner)->get($this->sheetUrl($workspaceId, $locationId));

        $response->assertStatus(200);
        $body = (string) $response->getContent();

        self::assertStringStartsWith('%PDF-', $body);
        self::assertStringContainsString('%%EOF', $body);
        self::assertGreaterThanOrEqual(
            2,
            (int) preg_match_all('/\/Type\s*\/Page[^s]/', $body),
            'QRSHEET-ENDPOINT-01: on üç kart iki sayfaya taşmalı — sayfa başına on iki kart.',
        );
    }
}
