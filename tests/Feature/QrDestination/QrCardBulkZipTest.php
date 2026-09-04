<?php

declare(strict_types=1);

namespace Tests\Feature\QrDestination;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\GrantsPlanEntitlements;
use Tests\TestCase;
use ZipArchive;

/**
 * CARD-BULK-ZIP-01 — FF-122, sahibin talebi (2026-09-04):
 * "toplu QR oluşturabilmeli, toplu baskı için .zip export alabilirim…
 * [salon üst kat, salon içerisi, salon bahçe] gibi seçenekleri seçtikten
 * sonra export edebilmeliyim."
 *
 * Deste PDF'i (FF-111) matbaaya gitmeyen, evde kesilecek bir tabakadır. ZIP
 * ise farklı bir iştir: matbaa her kartı AYRI dosya olarak ister ve dosya adı
 * hangi masa olduğunu söylemek zorundadır — yoksa kırk dosyayı açıp tek tek
 * bakmak gerekir.
 */
final class QrCardBulkZipTest extends TestCase
{
    use GrantsPlanEntitlements;
    use RefreshDatabase;

    public function test_the_zip_holds_one_named_file_per_active_table(): void
    {
        [$owner, $workspaceId, $locationId] = $this->locationWithTables(3);

        $response = $this->actingAs($owner)->get($this->zipUrl($workspaceId, $locationId));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/zip');

        $names = $this->entriesOf($response->getContent());

        self::assertCount(3, $names);
        // Dosya adı HANGİ MASA olduğunu söyler: kırk dosyayı açıp tek tek
        // bakmak gerekmez.
        self::assertContains('T1.svg', $names);
        self::assertContains('T3.svg', $names);
    }

    public function test_each_file_in_the_zip_is_a_real_card(): void
    {
        [$owner, $workspaceId, $locationId] = $this->locationWithTables(1);

        $zip = (string) $this->actingAs($owner)->get($this->zipUrl($workspaceId, $locationId))->getContent();
        $card = $this->readEntry($zip, 'T1.svg');

        self::assertStringContainsString('<svg', $card);
        self::assertStringContainsString('Kebapçı Ali', $card);
        self::assertStringContainsString('width="105mm"', $card);
    }

    public function test_the_owner_can_print_one_area_at_a_time(): void
    {
        /*
            Sahibin cümlesi: "salon üst kat, salon içerisi, salon bahçe". Kırk
            masalık bir restoranda bahçenin kartlarını yenilemek için kırk
            kartı birden basmak, otuz kartı çöpe atmak demek.
        */
        [$owner, $workspaceId, $locationId, $areaIds] = $this->locationWithAreas();

        $names = $this->entriesOf(
            $this->actingAs($owner)
                ->get($this->zipUrl($workspaceId, $locationId).'&areaId='.$areaIds[1])
                ->getContent(),
        );

        // İkinci alan round-robin dağılımda T2 ve T4'ü aldı.
        self::assertSame(['T2.svg', 'T4.svg'], $names);
    }

    public function test_an_area_from_another_location_prints_nothing_rather_than_everything(): void
    {
        // Süzgeç tanınmıyorsa "hepsini bas" demek, sahibe istemediği kırk kartı
        // bastırmaktır.
        [$owner, $workspaceId, $locationId] = $this->locationWithTables(2);

        $this->actingAs($owner)
            ->get($this->zipUrl($workspaceId, $locationId).'&areaId=999999')
            ->assertStatus(404);
    }

    public function test_pdf_is_offered_as_well_because_a_printer_may_want_it(): void
    {
        [$owner, $workspaceId, $locationId] = $this->locationWithTables(2);

        $names = $this->entriesOf(
            $this->actingAs($owner)
                ->get($this->zipUrl($workspaceId, $locationId, 'pdf'))
                ->getContent(),
        );

        self::assertSame(['T1.pdf', 'T2.pdf'], $names);
    }

    public function test_a_location_with_no_active_code_is_a_404_not_an_empty_zip(): void
    {
        // Boş bir ZIP indirmek, kullanıcıya "oldu" demektir; olmadı.
        [$owner, $workspaceId, $locationId] = $this->locationWithTables(0);

        $this->actingAs($owner)->get($this->zipUrl($workspaceId, $locationId))->assertStatus(404);
    }

    public function test_a_foreign_workspace_location_is_a_404(): void
    {
        [, $workspaceId, $locationId] = $this->locationWithTables(1);
        $stranger = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($stranger)->get($this->zipUrl($workspaceId, $locationId))->assertStatus(404);
    }

    private function zipUrl(int $workspaceId, int $locationId, string $format = 'svg'): string
    {
        return "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/qr-cards.zip?format={$format}";
    }

    /** @return list<string> */
    private function entriesOf(?string $bytes): array
    {
        $path = tempnam(sys_get_temp_dir(), 'zabuno-zip-');
        file_put_contents($path, (string) $bytes);

        $zip = new ZipArchive;
        self::assertTrue($zip->open($path) === true, 'CARD-BULK-ZIP-01: çıktı geçerli bir ZIP değil.');

        $names = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $names[] = (string) $zip->getNameIndex($index);
        }

        $zip->close();
        @unlink($path);
        sort($names);

        return $names;
    }

    private function readEntry(string $bytes, string $name): string
    {
        $path = tempnam(sys_get_temp_dir(), 'zabuno-zip-');
        file_put_contents($path, $bytes);

        $zip = new ZipArchive;
        $zip->open($path);
        $content = (string) $zip->getFromName($name);
        $zip->close();
        @unlink($path);

        return $content;
    }

    /** @return array{0: User, 1: int, 2: int} */
    private function locationWithTables(int $tableCount): array
    {
        [$owner, $workspaceId, $locationId, $menuId] = $this->workspace();

        if ($tableCount > 0) {
            $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->postJson(
                "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/tables/bulk",
                ['menuId' => $menuId, 'areaSectionCount' => 1, 'tableCount' => $tableCount, 'seatCountPerTable' => 4],
            )->assertStatus(201);
        }

        return [$owner, $workspaceId, $locationId];
    }

    /** @return array{0: User, 1: int, 2: int, 3: list<int>} */
    private function locationWithAreas(): array
    {
        [$owner, $workspaceId, $locationId, $menuId] = $this->workspace();

        $body = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->postJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/tables/bulk",
            ['menuId' => $menuId, 'areaSectionCount' => 2, 'tableCount' => 4, 'seatCountPerTable' => 4],
        )->assertStatus(201)->json();

        return [$owner, $workspaceId, $locationId, array_column($body['areas'], 'id')];
    }

    /** @return array{0: User, 1: int, 2: int, 3: int} */
    private function workspace(): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);

        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Kebapçı', 'slug' => 'zip-'.uniqid(), 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId, 'user_id' => $owner->id, 'role' => 'owner',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspaceId, 'name' => 'Kebapçı Ali', 'slug' => 'kebap-'.uniqid(),
            'locale' => 'tr', 'timezone' => 'Europe/Istanbul', 'currency' => 'TRY',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId, 'brand_id' => $brandId, 'display_name' => 'Merkez',
            'country_code' => 'TR', 'timezone' => 'Europe/Istanbul', 'city' => 'İstanbul',
            'address_line1' => 'Adres', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $menuId = (int) DB::table('menus')->insertGetId([
            'public_key' => Str::lower(Str::random(10)), 'workspace_id' => $workspaceId,
            'location_id' => $locationId, 'name' => 'Ana Menü', 'state' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $publicationId = (int) DB::table('menu_publications')->insertGetId([
            'workspace_id' => $workspaceId, 'menu_id' => $menuId, 'location_id' => $locationId,
            'version' => 1, 'state' => 'published', 'snapshot' => json_encode(['categories' => []]),
            'published_by' => $owner->id, 'published_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('menu_publication_current_pointers')->insert([
            'menu_id' => $menuId, 'current_publication_id' => $publicationId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->grantEntitlements($workspaceId);

        return [$owner, $workspaceId, $locationId, $menuId];
    }
}
