<?php

declare(strict_types=1);

namespace Tests\Feature\DataRights;

use App\Application\DataRights\Port\DataRequestRepositoryPort;
use App\Application\DataRights\Port\DataRightsNotifierPort;
use App\Application\DataRights\Port\WorkspaceDataExporterPort;
use App\Domain\DataRights\DataRequestState;
use App\Jobs\BuildWorkspaceDataExportJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * DIŞA AKTARMA — "verimizin bir kopyasını istiyoruz" (FF-226, `docs/138` §3).
 *
 * Bir zincirin hukuk birimi bu soruyu sorduğunda cevap bir düğme olmalı,
 * bir e-posta yazışması değil. Bu paketten önce ürünün içinde hiçbir yol
 * yoktu: gizlilik politikası menünün CSV'sini gösteriyordu ve geri kalan
 * her şey için iletişim formuna yönlendiriyordu.
 *
 * Gereksinim: DATA-EXPORT-QUEUED-01, DATA-EXPORT-BOTH-FORMATS-02,
 * DATA-EXPORT-README-SAYS-WHAT-IS-MISSING-03, DATA-EXPORT-TENANT-ONLY-04,
 * DATA-EXPORT-PERMISSION-05, DATA-EXPORT-SIGNED-DOWNLOAD-06,
 * DATA-EXPORT-EXPIRES-07, DATA-EXPORT-NO-TRANSPORT-IS-NOT-A-FAILURE-08.
 */
final class WorkspaceDataExportTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function workspaceFor(User $owner, string $slug, string $role = 'owner'): int
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin Restoranları',
            'slug' => $slug,
            'state' => 'active',
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $owner->id,
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $workspaceId;
    }

    private function seedMenu(int $workspaceId, string $productName): void
    {
        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspaceId,
            'name' => 'Zeytin',
            'slug' => 'zeytin-'.$workspaceId,
            'locale' => 'tr',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId,
            'brand_id' => $brandId,
            'display_name' => 'Kadıköy',
            'country_code' => 'TR',
            'city' => 'İstanbul',
            'address_line1' => 'Moda Caddesi 1',
            'timezone' => 'Europe/Istanbul',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = (int) DB::table('products')->insertGetId([
            'workspace_id' => $workspaceId,
            'name' => $productName,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $menuId = (int) DB::table('menus')->insertGetId([
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'name' => 'Akşam',
            'state' => 'draft',
            'public_key' => substr(md5((string) $workspaceId), 0, 10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $categoryId = (int) DB::table('menu_categories')->insertGetId([
            'menu_id' => $menuId,
            'name' => 'Ana yemek',
            'position' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menu_items')->insert([
            'category_id' => $categoryId,
            'product_id' => $productId,
            'price_minor_amount' => 45000,
            'currency_code' => 'TRY',
            'is_visible' => true,
            'position' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function runExport(int $workspaceId, int $requestId): void
    {
        (new BuildWorkspaceDataExportJob($workspaceId, $requestId))->handle(
            app(DataRequestRepositoryPort::class),
            app(WorkspaceDataExporterPort::class),
            app(DataRightsNotifierPort::class),
        );
    }

    public function test_the_request_is_queued_and_the_screen_does_not_wait(): void
    {
        Queue::fake();

        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceFor($owner, 'zeytin-export-queued');

        $response = $this->actingAs($owner)->postJson("/api/workspaces/{$workspaceId}/data-rights/exports");

        // 202: iş HENÜZ BİTMEDİ ve cevap bunu söylüyor.
        $response->assertStatus(202)->assertJsonPath('state', DataRequestState::Queued->value);

        Queue::assertPushed(BuildWorkspaceDataExportJob::class);

        $this->assertSame(1, DB::table('workspace_data_requests')->where('workspace_id', $workspaceId)->count());
    }

    public function test_a_second_request_does_not_open_a_second_job(): void
    {
        Queue::fake();

        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceFor($owner, 'zeytin-export-once');

        $first = $this->actingAs($owner)->postJson("/api/workspaces/{$workspaceId}/data-rights/exports");
        $second = $this->actingAs($owner)->postJson("/api/workspaces/{$workspaceId}/data-rights/exports");

        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertSame(1, DB::table('workspace_data_requests')->count());
    }

    public function test_the_archive_carries_the_same_rows_in_both_a_machine_and_a_human_readable_form(): void
    {
        Storage::fake('local');

        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceFor($owner, 'zeytin-export-formats');
        $this->seedMenu($workspaceId, 'Kuzu pirzola');

        $requestId = (int) $this->actingAs($owner)
            ->postJson("/api/workspaces/{$workspaceId}/data-rights/exports")
            ->json('id');

        $this->runExport($workspaceId, $requestId);

        $row = DB::table('workspace_data_requests')->where('id', $requestId)->first();
        $this->assertNotNull($row);
        $this->assertSame(DataRequestState::Ready->value, $row->state);
        $this->assertNotNull($row->artifact_checksum_sha256);
        $this->assertGreaterThan(0, (int) $row->artifact_bytes);

        $files = $this->readArchive(Storage::disk('local')->path((string) $row->artifact_path));

        $productsJson = $this->fileEndingWith($files, 'data/products.json');
        $productsCsv = $this->fileEndingWith($files, 'data/products.csv');

        $this->assertStringContainsString('Kuzu pirzola', $productsJson);
        $this->assertStringContainsString('Kuzu pirzola', $productsCsv);

        // ÇOCUK TABLO DA GELİR: `menu_items` üzerinde `workspace_id` yok,
        // kategori üzerinden bulunur. Bulunamasaydı sahip fiyatlarını
        // arşivde göremezdi.
        $this->assertStringContainsString('45000', $this->fileEndingWith($files, 'data/menu_items.json'));
    }

    public function test_the_archive_says_what_it_does_not_contain_and_why(): void
    {
        Storage::fake('local');

        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceFor($owner, 'zeytin-export-readme');

        $requestId = (int) $this->actingAs($owner)
            ->postJson("/api/workspaces/{$workspaceId}/data-rights/exports")
            ->json('id');

        $this->runExport($workspaceId, $requestId);

        $path = (string) DB::table('workspace_data_requests')->where('id', $requestId)->value('artifact_path');
        $files = $this->readArchive(Storage::disk('local')->path($path));

        $readme = $this->fileEndingWith($files, 'README.txt');
        $manifest = json_decode($this->fileEndingWith($files, 'manifest.json'), true);

        // Arşiv e-postayla üçüncü bir kişiye gittiğinde ekrandaki açıklama
        // orada değildir; dosyanın kendisi anlatmalı.
        $this->assertStringContainsString('What is NOT in here', $readme);
        $this->assertStringContainsString('platform_audits', $readme);
        $this->assertIsArray($manifest);
        $this->assertArrayHasKey('outOfScope', $manifest);
        $this->assertArrayHasKey('platform_audits', $manifest['outOfScope']);
        $this->assertSame('metadata-only', $manifest['mediaFiles']);
        // Barındırma olgusu arşivde de yazılı: veri Türkiye dışında.
        $this->assertSame('Germany', $manifest['hosting']['country']);
    }

    public function test_the_archive_never_carries_another_workspaces_rows(): void
    {
        Storage::fake('local');

        $owner = $this->verifiedUser();
        $stranger = $this->verifiedUser();

        $mine = $this->workspaceFor($owner, 'zeytin-export-mine');
        $theirs = $this->workspaceFor($stranger, 'zeytin-export-theirs');

        $this->seedMenu($mine, 'Benim pirzolam');
        $this->seedMenu($theirs, 'Komsunun pirzolasi');

        $requestId = (int) $this->actingAs($owner)
            ->postJson("/api/workspaces/{$mine}/data-rights/exports")
            ->json('id');

        $this->runExport($mine, $requestId);

        $path = (string) DB::table('workspace_data_requests')->where('id', $requestId)->value('artifact_path');
        $files = $this->readArchive(Storage::disk('local')->path($path));

        $everything = implode("\n", $files);

        $this->assertStringContainsString('Benim pirzolam', $everything);
        $this->assertStringNotContainsString('Komsunun pirzolasi', $everything);
    }

    public function test_a_member_without_the_permission_never_reaches_the_export(): void
    {
        $owner = $this->verifiedUser();
        $member = $this->verifiedUser();

        $workspaceId = $this->workspaceFor($owner, 'zeytin-export-permission');

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $member->id,
            'role' => 'manager',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Yönetici çalışma alanını YÜRÜTÜR, ona SAHİP DEĞİLDİR.
        $this->actingAs($member)
            ->postJson("/api/workspaces/{$workspaceId}/data-rights/exports")
            ->assertStatus(403)
            ->assertJsonPath('requiredPermission', 'workspace.data.export');

        $stranger = $this->verifiedUser();

        // Hiç üye olmayan için kayıt HİÇ YOKTUR.
        $this->actingAs($stranger)
            ->postJson("/api/workspaces/{$workspaceId}/data-rights/exports")
            ->assertStatus(404);
    }

    public function test_the_archive_is_only_reachable_through_a_signed_address(): void
    {
        Storage::fake('local');

        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceFor($owner, 'zeytin-export-signed');

        $requestId = (int) $this->actingAs($owner)
            ->postJson("/api/workspaces/{$workspaceId}/data-rights/exports")
            ->json('id');

        $this->runExport($workspaceId, $requestId);

        /*
            İMZASIZ ADRES, oturum açık olsa bile açılmaz.

            Adres `/workspaces/...` değil `/data-export/...`: her üst düzey
            yol rezerve edilmek zorunda (URL-RESERVED-COVERS-ROUTES-13) ve
            `workspaces` gibi geniş bir sözcüğü işletme slug'ı olmaktan
            çıkarmak bu paketin kararı değildi.
        */
        $this->actingAs($owner)
            ->get("/data-export/{$workspaceId}/{$requestId}")
            ->assertStatus(403);

        $signed = (string) $this->actingAs($owner)
            ->getJson("/api/workspaces/{$workspaceId}/data-rights")
            ->json('requests.0.downloadUrl');

        $this->assertNotSame('', $signed);
        $this->get($signed)->assertOk()->assertHeader('Content-Type', 'application/zip');
    }

    public function test_an_expired_archive_says_it_expired_instead_of_pretending_it_never_existed(): void
    {
        Storage::fake('local');

        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceFor($owner, 'zeytin-export-expired');

        $requestId = (int) $this->actingAs($owner)
            ->postJson("/api/workspaces/{$workspaceId}/data-rights/exports")
            ->json('id');

        $this->runExport($workspaceId, $requestId);

        $signed = (string) $this->actingAs($owner)
            ->getJson("/api/workspaces/{$workspaceId}/data-rights")
            ->json('requests.0.downloadUrl');

        DB::table('workspace_data_requests')->where('id', $requestId)->update([
            'available_until' => now()->subDay(),
        ]);

        // 410, 404 değil: "süresi doldu" ile "hiç yoktu" aynı şey değildir.
        $this->get($signed)->assertStatus(410);
    }

    public function test_a_missing_mail_transport_does_not_turn_a_finished_export_into_a_failure(): void
    {
        Storage::fake('local');
        config()->set('mail.default', 'log');

        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceFor($owner, 'zeytin-export-nomail');

        $requestId = (int) $this->actingAs($owner)
            ->postJson("/api/workspaces/{$workspaceId}/data-rights/exports")
            ->json('id');

        $this->runExport($workspaceId, $requestId);

        $row = DB::table('workspace_data_requests')->where('id', $requestId)->first();

        $this->assertNotNull($row);
        // Arşiv hazır ve ekranda duruyor…
        $this->assertSame(DataRequestState::Ready->value, $row->state);
        // …ama "gönderildi" damgası ATILMADI (`docs/93`).
        $this->assertNull($row->notified_at);
        $this->assertNull($row->notification_failure);
    }

    /**
     * @return array<string, string>
     */
    private function readArchive(string $path): array
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'Arşiv açılamadı.');

        $files = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);
            $files[$name] = (string) $zip->getFromIndex($index);
        }

        $zip->close();

        return $files;
    }

    /**
     * @param  array<string, string>  $files
     */
    private function fileEndingWith(array $files, string $suffix): string
    {
        foreach ($files as $name => $contents) {
            if (str_ends_with($name, $suffix)) {
                return $contents;
            }
        }

        $this->fail("Arşivde bulunamadı: {$suffix} (var olanlar: ".implode(', ', array_keys($files)).')');
    }
}
