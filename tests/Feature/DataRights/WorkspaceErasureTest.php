<?php

declare(strict_types=1);

namespace Tests\Feature\DataRights;

use App\Domain\DataRights\DataRequestState;
use App\Domain\Tenancy\WorkspaceState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * SİLME — "bizim verimizi silin" (FF-226, `docs/138` §4).
 *
 * Bu, hukuk biriminin üçüncü sorusudur ve bu paketten önce cevabı yoktu.
 * İki şey ayrıdır ve karıştırılmamalı: hesabı KAPATMAK hizmetin durmasıdır,
 * veriyi SİLMEK verinin yok olmasıdır. Silme geri alınamaz; bu yüzden
 * gecikmeli ve geri alınabilir bir pencereden geçer.
 *
 * VE HER ŞEY SİLİNMEZ. Mali kayıtlar yasal saklamaya tabidir ve bir silme
 * isteği onları yok edemez. Ekran bunu dürüstçe söyler; "her şey silinir"
 * demek yalan olurdu.
 *
 * Gereksinim: DATA-ERASE-WINDOW-01, DATA-ERASE-REVERSIBLE-02,
 * DATA-ERASE-NAME-CONFIRMATION-03, DATA-ERASE-REALLY-DELETES-04,
 * DATA-ERASE-KEEPS-FINANCIAL-RECORDS-05, DATA-ERASE-TENANT-ONLY-06,
 * DATA-ERASE-LEGAL-HOLD-BLOCKS-07, DATA-ERASE-PERMISSION-08,
 * DATA-ERASE-FILES-GO-TOO-09, DATA-ERASE-DOES-NOT-CLOSE-THE-ACCOUNT-10.
 */
final class WorkspaceErasureTest extends TestCase
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

    private function requestErasure(User $owner, int $workspaceId): int
    {
        return (int) $this->actingAs($owner)
            ->postJson("/api/workspaces/{$workspaceId}/data-rights/erasure", [
                'confirmation' => 'Zeytin Restoranları',
            ])
            ->assertStatus(202)
            ->json('id');
    }

    private function seedContent(int $workspaceId, string $productName): int
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
            'public_key' => substr(md5((string) $workspaceId.$productName), 0, 10),
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

        return $menuId;
    }

    private function seedInvoiceAndConsent(int $workspaceId, User $owner): void
    {
        $planId = (int) DB::table('plans')->insertGetId([
            'name' => 'Pro',
            'code' => 'pro-'.$workspaceId,
            'version' => 1,
            'is_active' => true,
            'sort_order' => 1,
            'entitlements' => json_encode([]),
            'amount_minor' => 100000,
            'currency' => 'TRY',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
            GERÇEK UUID. `idempotency_key` ve `conversation_id` sütunları
            `uuid` tipindedir; PostgreSQL bir "test-1" dizesini kabul etmez
            ve testi CI'da düşürür. SQLite bu farkı göstermez — bu depoda
            aynı ders bu hafta iki kez öğrenildi.
        */
        $transactionId = (int) DB::table('payment_transactions')->insertGetId([
            'workspace_id' => $workspaceId,
            'actor_user_id' => $owner->id,
            'plan_id' => $planId,
            'mode' => 'sandbox',
            'idempotency_key' => Str::uuid()->toString(),
            'conversation_id' => Str::uuid()->toString(),
            'amount_minor' => 118000,
            'currency' => 'TRY',
            'period_days' => 30,
            'state' => 'succeeded',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('invoices')->insert([
            'workspace_id' => $workspaceId,
            'payment_transaction_id' => $transactionId,
            'kind' => 'invoice',
            'series' => 'ZBN',
            'number' => 1,
            'document_number' => 'ZBN2026000001-'.$workspaceId,
            'issued_at' => now(),
            'currency' => 'TRY',
            'amount_minor' => 118000,
            'vat_rate_basis_points' => 2000,
            'net_minor' => 100000,
            'vat_minor' => 18000,
            'plan_name' => 'Pro',
            'period_days' => 30,
            /*
                ALICI KİMLİĞİ FATURAYA DONDURULMUŞ HÂLDE yazılır: fatura
                kesildiği andaki gerçeği taşır. Bu yüzden `billing_profiles`
                silinebilir ve fatura yine de tarafını söyleyebilir.
            */
            'buyer_legal_name' => 'Zeytin Gıda A.Ş.',
            'buyer_tax_number' => '1234567890',
            'buyer_tax_office' => 'Kadıköy',
            'buyer_address' => 'Moda Caddesi 1',
            'buyer_city' => 'İstanbul',
            'buyer_country' => 'TR',
            'buyer_email' => 'muhasebe@example.test',
            'buyer_phone' => '02160000000',
            'created_at' => now(),
        ]);

        DB::table('billing_profiles')->insert([
            'workspace_id' => $workspaceId,
            'legal_name' => 'Zeytin Gıda A.Ş.',
            'tax_number' => '1234567890',
            'tax_office' => 'Kadıköy',
            'address' => 'Moda Caddesi 1',
            'city' => 'İstanbul',
            'country' => 'TR',
            'email' => 'muhasebe@example.test',
            'phone' => '02160000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('ledger_entries')->insert([
            'workspace_id' => $workspaceId,
            'reference' => 'ZBN-'.$workspaceId,
            'debit_account' => '120',
            'credit_account' => '600',
            'amount_minor' => 118000,
            'currency_code' => 'TRY',
            'occurred_at' => now(),
            'created_at' => now(),
        ]);

        DB::table('consent_records')->insert([
            'user_id' => $owner->id,
            'workspace_id' => $workspaceId,
            'kind' => 'registration',
            'document_key' => 'terms',
            'document_version' => '0.1',
            'granted' => true,
            'recorded_at' => now(),
        ]);
    }

    public function test_the_erasure_is_delayed_and_the_window_comes_from_configuration(): void
    {
        config()->set('data-rights.erasure.grace_days', 14);

        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceFor($owner, 'zeytin-erase-window');

        $requestId = $this->requestErasure($owner, $workspaceId);

        $row = DB::table('workspace_data_requests')->where('id', $requestId)->first();

        $this->assertNotNull($row);
        $this->assertSame(DataRequestState::Scheduled->value, $row->state);
        $this->assertSame(
            now()->addDays(14)->format('Y-m-d'),
            Carbon::parse((string) $row->scheduled_for)->format('Y-m-d'),
        );

        // Ekran süreyi yapılandırmadan okur; kendi rakamını uydurmaz.
        $this->actingAs($owner)
            ->getJson("/api/workspaces/{$workspaceId}/data-rights")
            ->assertJsonPath('graceDays', 14);
    }

    public function test_an_unreadable_window_falls_back_instead_of_erasing_immediately(): void
    {
        // Bir yazım hatası, pencereyi kapatıp silmeyi anında yürütecek
        // olsaydı, yapılandırma hatası doğrudan veri kaybına dönüşürdü.
        config()->set('data-rights.erasure.grace_days', 'otuz');

        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceFor($owner, 'zeytin-erase-badconfig');

        $this->requestErasure($owner, $workspaceId);

        $this->actingAs($owner)
            ->getJson("/api/workspaces/{$workspaceId}/data-rights")
            ->assertJsonPath('graceDays', 30);
    }

    public function test_the_erasure_can_be_taken_back_while_the_window_is_open(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceFor($owner, 'zeytin-erase-cancel');
        $this->seedContent($workspaceId, 'Kuzu pirzola');

        $requestId = $this->requestErasure($owner, $workspaceId);

        $this->actingAs($owner)
            ->deleteJson("/api/workspaces/{$workspaceId}/data-rights/erasure/{$requestId}")
            ->assertOk()
            ->assertJsonPath('cancelled', true);

        $this->artisan('zabuno:run-due-erasures')->assertExitCode(0);

        // Vazgeçilen bir silme, günü gelse de yürümez.
        $this->assertSame(1, DB::table('products')->where('workspace_id', $workspaceId)->count());

        // İPTAL SATIRI SİLMEZ: "bir gün istendi ve vazgeçildi" de bir kayıttır.
        $this->assertSame(
            DataRequestState::Cancelled->value,
            DB::table('workspace_data_requests')->where('id', $requestId)->value('state'),
        );
    }

    public function test_the_confirmation_is_the_workspace_name_not_a_checkbox(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceFor($owner, 'zeytin-erase-confirm');

        // "Eminim" kutusu refleksle işaretlenir; adı yazmak, kullanıcıyı
        // hangi paneli sildiğine BAKMAYA zorlar.
        $this->actingAs($owner)
            ->postJson("/api/workspaces/{$workspaceId}/data-rights/erasure", ['confirmation' => 'baska bir yer'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('confirmation');

        $this->assertSame(0, DB::table('workspace_data_requests')->count());
    }

    public function test_when_the_day_comes_the_data_really_goes_and_the_rows_are_counted(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceFor($owner, 'zeytin-erase-runs');
        $this->seedContent($workspaceId, 'Kuzu pirzola');

        $requestId = $this->requestErasure($owner, $workspaceId);

        DB::table('workspace_data_requests')->where('id', $requestId)->update([
            'scheduled_for' => now()->subMinute(),
        ]);

        $this->artisan('zabuno:run-due-erasures')->assertExitCode(0);

        // ÖLÇÜLDÜ: satırlar gerçekten yok.
        $this->assertSame(0, DB::table('products')->where('workspace_id', $workspaceId)->count());
        $this->assertSame(0, DB::table('menus')->where('workspace_id', $workspaceId)->count());
        $this->assertSame(0, DB::table('menu_categories')->count());
        $this->assertSame(0, DB::table('menu_items')->count());
        $this->assertSame(0, DB::table('brands')->where('workspace_id', $workspaceId)->count());
        $this->assertSame(0, DB::table('workspace_memberships')->where('workspace_id', $workspaceId)->count());

        $row = DB::table('workspace_data_requests')->where('id', $requestId)->first();
        $this->assertNotNull($row);
        $this->assertSame(DataRequestState::Completed->value, $row->state);

        /** @var array<string, int> $counts */
        $counts = json_decode((string) $row->deleted_counts, true);

        // "Her şey silindi" cümlesi ancak sayılabildiği kadar doğrudur.
        $this->assertSame(1, $counts['products']);
        $this->assertSame(1, $counts['menu_items']);
        $this->assertGreaterThan(0, array_sum($counts));
    }

    public function test_financial_records_and_consent_survive_and_the_workspace_row_stays_as_a_headstone(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceFor($owner, 'zeytin-erase-keeps');
        $this->seedContent($workspaceId, 'Kuzu pirzola');
        $this->seedInvoiceAndConsent($workspaceId, $owner);

        $requestId = $this->requestErasure($owner, $workspaceId);
        DB::table('workspace_data_requests')->where('id', $requestId)->update(['scheduled_for' => now()->subMinute()]);

        $this->artisan('zabuno:run-due-erasures')->assertExitCode(0);

        // Kesilmiş fatura yasal bir belgedir ve numara serisi boşluksuzdur.
        $this->assertSame(1, DB::table('invoices')->where('workspace_id', $workspaceId)->count());
        $this->assertSame(1, DB::table('ledger_entries')->where('workspace_id', $workspaceId)->count());
        // Tahsilatın kendi kaydı da kalır: faturanın dayanağıdır.
        $this->assertSame(1, DB::table('payment_transactions')->where('workspace_id', $workspaceId)->count());
        // Onayın kanıtı, en çok hesabın artık olmadığı gün gerekir.
        $this->assertSame(1, DB::table('consent_records')->where('workspace_id', $workspaceId)->count());
        // FATURA PROFİLİ SİLİNİR ve bu bir çelişki değil: alıcının kimliği
        // faturaya dondurulmuş hâlde yazılıdır, yani belge tarafını hâlâ
        // söyleyebilir. Yaşayan profil ise kiracının kendi verisidir.
        $this->assertSame(0, DB::table('billing_profiles')->where('workspace_id', $workspaceId)->count());
        $this->assertSame(
            'Zeytin Gıda A.Ş.',
            DB::table('invoices')->where('workspace_id', $workspaceId)->value('buyer_legal_name'),
        );

        // Silme talebinin kendi kaydı da kalır.
        $this->assertSame(1, DB::table('workspace_data_requests')->where('id', $requestId)->count());

        // MEZAR TAŞI: satır kalır, hâli değişir. Silinseydi faturanın
        // sahibi belirsiz kalırdı.
        $this->assertSame(
            WorkspaceState::Deleted->value,
            DB::table('workspaces')->where('id', $workspaceId)->value('state'),
        );
    }

    public function test_the_erasure_never_reaches_another_workspace(): void
    {
        $owner = $this->verifiedUser();
        $neighbour = $this->verifiedUser();

        $mine = $this->workspaceFor($owner, 'zeytin-erase-mine');
        $theirs = $this->workspaceFor($neighbour, 'zeytin-erase-theirs');

        $this->seedContent($mine, 'Benim pirzolam');
        $this->seedContent($theirs, 'Komsunun pirzolasi');

        $requestId = $this->requestErasure($owner, $mine);
        DB::table('workspace_data_requests')->where('id', $requestId)->update(['scheduled_for' => now()->subMinute()]);

        $this->artisan('zabuno:run-due-erasures')->assertExitCode(0);

        $this->assertSame(0, DB::table('products')->where('workspace_id', $mine)->count());
        $this->assertSame(1, DB::table('products')->where('workspace_id', $theirs)->count());
        $this->assertSame(1, DB::table('menu_items')->count());
        $this->assertSame(
            'active',
            DB::table('workspaces')->where('id', $theirs)->value('state'),
        );
    }

    public function test_a_file_under_legal_hold_stops_the_erasure_before_it_starts(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceFor($owner, 'zeytin-erase-hold');

        DB::table('media_assets')->insert([
            'workspace_id' => $workspaceId,
            'disk_path' => 'media/'.Str::uuid()->toString().'.jpg',
            'original_name' => 'delil.jpg',
            'alt_text' => 'Uyuşmazlık eki',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'slot' => 'logo',
            'status' => 'accepted',
            'legal_hold_reason' => 'Uyuşmazlık kaydı 2026/14',
            'legal_hold_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner)
            ->postJson("/api/workspaces/{$workspaceId}/data-rights/erasure", ['confirmation' => 'Zeytin Restoranları'])
            ->assertStatus(409)
            ->assertJsonPath('reason', 'legal_hold')
            ->assertJsonPath('count', 1);

        $this->assertSame(0, DB::table('workspace_data_requests')->count());
    }

    public function test_the_files_on_disk_go_too(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('media/delete-me.jpg', 'binary');

        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceFor($owner, 'zeytin-erase-files');

        DB::table('media_assets')->insert([
            'workspace_id' => $workspaceId,
            'disk_path' => 'media/delete-me.jpg',
            'original_name' => 'pirzola.jpg',
            'alt_text' => 'Kuzu pirzola',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 6,
            'slot' => 'logo',
            'status' => 'accepted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $requestId = $this->requestErasure($owner, $workspaceId);
        DB::table('workspace_data_requests')->where('id', $requestId)->update(['scheduled_for' => now()->subMinute()]);

        $this->artisan('zabuno:run-due-erasures')->assertExitCode(0);

        // Satırı silip diskteki fotoğrafı bırakmak, "sildik" demenin en
        // sinsi hâli olurdu.
        Storage::disk('local')->assertMissing('media/delete-me.jpg');

        /** @var array<string, int> $counts */
        $counts = json_decode((string) DB::table('workspace_data_requests')->where('id', $requestId)->value('deleted_counts'), true);
        $this->assertSame(1, $counts['storage_files']);
    }

    public function test_asking_for_erasure_does_not_close_the_account_on_the_same_day(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceFor($owner, 'zeytin-erase-open');
        $this->seedContent($workspaceId, 'Kuzu pirzola');

        $this->requestErasure($owner, $workspaceId);

        // Pencere boyunca panel AÇIK kalır — vazgeçme düğmesine
        // ulaşılabilmeli. Aksi hâlde fikrini değiştiren sahip kendi
        // vazgeçme düğmesine erişemezdi.
        $this->assertSame('active', DB::table('workspaces')->where('id', $workspaceId)->value('state'));
        $this->assertSame(1, DB::table('products')->where('workspace_id', $workspaceId)->count());

        $this->actingAs($owner)->getJson("/api/workspaces/{$workspaceId}/data-rights")->assertOk();
    }

    public function test_when_a_transport_exists_the_owner_is_told_and_the_stamp_is_written(): void
    {
        /*
            `Mail::fake()` KULLANILMADI ve bu bilinçli: sahte posta şablonu
            hiç çizmez, yani bozuk bir Blade dosyası testten geçerdi ve
            arıza yalnız üretimde, gerçek bir silme talebinde görünürdü.
            `array` sürücüsü mesajı gerçekten oluşturur ve bellekte tutar.
        */
        config()->set('mail.default', 'array');

        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceFor($owner, 'zeytin-erase-mail');

        $requestId = $this->requestErasure($owner, $workspaceId);

        /** @var ArrayTransport $transport */
        $transport = Mail::mailer('array')->getSymfonyTransport();
        $messages = $transport->messages();

        $this->assertCount(1, $messages);

        $body = (string) $messages->first()->getOriginalMessage()->getTextBody();

        // Bildirim bir GÜN SAYISI değil, kayıttan gelen TARİHİ taşır.
        $scheduledFor = (string) DB::table('workspace_data_requests')->where('id', $requestId)->value('scheduled_for');
        $this->assertStringContainsString($scheduledFor, $body);
        // Ve "her şey silinir" demez: saklananları adıyla anar.
        $this->assertStringContainsString('invoices', $body);

        $this->assertNotNull(DB::table('workspace_data_requests')->where('id', $requestId)->value('notified_at'));
    }

    public function test_a_manager_cannot_ask_for_the_erasure(): void
    {
        $owner = $this->verifiedUser();
        $manager = $this->verifiedUser();
        $workspaceId = $this->workspaceFor($owner, 'zeytin-erase-permission');

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $manager->id,
            'role' => 'manager',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($manager)
            ->postJson("/api/workspaces/{$workspaceId}/data-rights/erasure", ['confirmation' => 'Zeytin Restoranları'])
            ->assertStatus(403)
            ->assertJsonPath('requiredPermission', 'workspace.data.erase');
    }

    public function test_the_screen_reads_what_is_kept_from_the_same_source_as_the_eraser(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceFor($owner, 'zeytin-erase-retained');

        $response = $this->actingAs($owner)->getJson("/api/workspaces/{$workspaceId}/data-rights")->assertOk();

        /** @var array<string, string> $retained */
        $retained = $response->json('retained');

        // Ekran "her şey silinir" DEMEZ ve saklananları adıyla sayar.
        $this->assertArrayHasKey('invoices', $retained);
        $this->assertArrayHasKey('ledger_entries', $retained);
        $this->assertArrayHasKey('consent_records', $retained);

        foreach ($retained as $table => $reason) {
            $this->assertNotSame('', trim($reason), "Saklanan tablo sebebini söylemiyor: {$table}.");
        }
    }
}
