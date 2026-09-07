<?php

declare(strict_types=1);

namespace Tests\Feature\PlatformAdmin;

use App\Domain\Platform\PlatformRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * DESTEK GÖRÜNÜMÜ — `docs/122` §3 boşluk 3, dalga Y7.
 *
 * Ölçülen cümle: *"Müşteri arıyor, ekranında ne var?" sorusunun cevabı yok.*
 *
 * BU UCUN VAROLUŞ SEBEBİ IMPERSONATION'I GEREKSİZ KILMAKTIR. Aşağıdaki her
 * test, kiracının gözüne HİÇ GİRMEDEN cevaplanan bir destek sorusudur:
 *
 *  - "menüm görünmüyor" → karekod hangi menüyü gösteriyor, o menü yayında mı
 *  - "karekod boş sayfa açıyor" → karekodun hedefi var mı, kapalı mı
 *  - "siparişler gelmiyor" → şubenin sipariş şalteri açık mı
 *  - "hesabım çalışmıyor" → çalışma alanı ve abonelik durumu
 *  - "geçen hafta yazmıştım" → bu kiracının açık destek talepleri
 *  - "bana kim baktı" → bakış geçmişi, sebepleriyle
 *
 * BULGULAR UYDURULMAZ. `findings` listesindeki her kod bir sorgunun
 * sonucudur; hiçbiri tahmin ya da öneri taşımaz. Cevabı bilinmeyen bir soru
 * listeye hiç girmez.
 *
 * KİRACININ İÇERİĞİ ÇIKMAZ: ürün adı, fiyat, misafir yorumu ve sipariş
 * içeriği bu cevapta yoktur. Sorulan şey "ne var", "ne yazıyor" değil.
 */
final class TenantSupportViewApiTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        DB::table('platform_role_assignments')->insert([
            'user_id' => $user->getKey(),
            'role' => PlatformRole::SuperAdmin->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    /** @return array{workspace:int, location:int, menu:int, owner:User} */
    private function tenant(string $seed, string $state = 'active'): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);

        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Kebapçı '.$seed,
            'slug' => $seed,
            'state' => $state,
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
            'name' => 'Marka '.$seed,
            'slug' => $seed.'-brand',
            'locale' => 'tr',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId,
            'brand_id' => $brandId,
            'display_name' => 'Şube '.$seed,
            'country_code' => 'TR',
            'timezone' => 'Europe/Istanbul',
            'city' => 'İstanbul',
            'address_line1' => 'Adres '.$seed,
            'accepts_orders' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $menuId = (int) DB::table('menus')->insertGetId([
            'public_key' => Str::lower(Str::random(10)),
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'name' => 'Ana Menü '.$seed,
            'state' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['workspace' => $workspaceId, 'location' => $locationId, 'menu' => $menuId, 'owner' => $owner];
    }

    private function qrCode(array $tenant, string $state = 'active', ?int $menuId = null): string
    {
        $token = Str::random(43);

        $codeId = (int) DB::table('qr_codes')->insertGetId([
            'workspace_id' => $tenant['workspace'],
            'location_id' => $tenant['location'],
            'token' => $token,
            'state' => $state,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($menuId !== null) {
            $destinationId = (int) DB::table('qr_destinations')->insertGetId([
                'qr_code_id' => $codeId,
                'destination_type' => 'menu',
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('qr_code_current_destinations')->insert([
                'qr_code_id' => $codeId,
                'qr_destination_id' => $destinationId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $token;
    }

    private function publish(array $tenant): void
    {
        $publicationId = (int) DB::table('menu_publications')->insertGetId([
            'workspace_id' => $tenant['workspace'],
            'menu_id' => $tenant['menu'],
            'location_id' => $tenant['location'],
            'version' => 3,
            'state' => 'published',
            'snapshot' => json_encode(['categories' => []], JSON_THROW_ON_ERROR),
            'published_by' => $tenant['owner']->id,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menu_publication_current_pointers')->insert([
            'menu_id' => $tenant['menu'],
            'current_publication_id' => $publicationId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function uri(int $workspaceId): string
    {
        return "/api/admin/workspaces/{$workspaceId}/support-view";
    }

    // --- yetki -------------------------------------------------------------

    #[Test]
    public function a_guest_never_reads_the_support_view(): void
    {
        $tenant = $this->tenant('misafir');

        $this->getJson($this->uri($tenant['workspace']))->assertUnauthorized();
    }

    #[Test]
    public function a_tenant_owner_gets_a_plain_404(): void
    {
        // Enumeration-safe: bu yüzey kiracının değil, platformun yüzeyidir.
        $tenant = $this->tenant('sahip');

        $this->actingAs($tenant['owner'])->getJson($this->uri($tenant['workspace']))->assertNotFound();
    }

    #[Test]
    public function an_unknown_tenant_is_a_plain_404(): void
    {
        $this->actingAs($this->superAdmin())->getJson($this->uri(987654))->assertNotFound();
    }

    // --- "menüm görünmüyor" ------------------------------------------------

    #[Test]
    public function a_healthy_tenant_reports_the_guest_address_and_the_published_version(): void
    {
        $admin = $this->superAdmin();
        $tenant = $this->tenant('saglikli');
        $token = $this->qrCode($tenant, 'active', $tenant['menu']);
        $this->publish($tenant);

        $response = $this->actingAs($admin)->getJson($this->uri($tenant['workspace']));

        $response->assertOk();
        /*
            MİSAFİR ADRESİ AÇIKÇA VERİLİR ve bu bir sızıntı değildir: karekod
            zaten masada, adres zaten herkese açık. "Müşterinin ekranında ne
            var" sorusunun en dürüst cevabı, müşterinin ekranının kendisidir
            — ve ona bakmak oturum açmayı değil, bir bağlantıya tıklamayı
            gerektirir.
        */
        $response->assertJsonPath('locations.0.qrCodes.0.guestUrl', url('/q/'.$token));
        $response->assertJsonPath('locations.0.qrCodes.0.menuName', $tenant['menu'] ? 'Ana Menü saglikli' : null);
        $response->assertJsonPath('locations.0.qrCodes.0.publishedVersion', 3);
        $response->assertJsonPath('locations.0.acceptsOrders', false);
    }

    #[Test]
    public function a_qr_pointing_at_a_menu_that_was_never_published_is_named(): void
    {
        /*
            EN SIK ÇAĞRI BUDUR. Karekod var, menü var, hedef doğru — ama menü
            hiç yayına çıkmamış, yani masadaki karekod boş bir sayfa açıyor.
            Bu bulgu olmadan destek görevlisi, "menüm görünmüyor" diyen
            sahibin hesabına bakmadan cevap veremezdi.
        */
        $admin = $this->superAdmin();
        $tenant = $this->tenant('yayinlanmamis');
        $this->qrCode($tenant, 'active', $tenant['menu']);

        $codes = array_column(
            $this->actingAs($admin)->getJson($this->uri($tenant['workspace']))->json('findings'),
            'code',
        );

        $this->assertContains('qr_menu_never_published', $codes);
    }

    #[Test]
    public function a_qr_without_a_destination_and_a_disabled_qr_are_separate_findings(): void
    {
        $admin = $this->superAdmin();

        $hedefsiz = $this->tenant('hedefsiz');
        $this->qrCode($hedefsiz, 'active', null);
        $this->assertContains(
            'qr_has_no_destination',
            array_column($this->actingAs($admin)->getJson($this->uri($hedefsiz['workspace']))->json('findings'), 'code'),
        );

        $kapali = $this->tenant('kapali');
        $this->qrCode($kapali, 'disabled', $kapali['menu']);
        $this->assertContains(
            'qr_disabled',
            array_column($this->actingAs($admin)->getJson($this->uri($kapali['workspace']))->json('findings'), 'code'),
        );
    }

    #[Test]
    public function a_location_without_a_qr_is_a_finding_of_its_own(): void
    {
        $admin = $this->superAdmin();
        $tenant = $this->tenant('karekodsuz');

        $findings = $this->actingAs($admin)->getJson($this->uri($tenant['workspace']))->json('findings');

        $this->assertContains('location_has_no_qr', array_column($findings, 'code'));
        // Bulgu HANGİ ŞUBE olduğunu söyler: üç şubeli bir işletmede
        // "karekod yok" tek başına hangi şubeyi arayacağını söylemez.
        $row = collect($findings)->firstWhere('code', 'location_has_no_qr');
        $this->assertSame($tenant['location'], $row['locationId']);
    }

    #[Test]
    public function a_suspended_tenant_stops_the_list_at_the_finding_that_explains_everything(): void
    {
        $admin = $this->superAdmin();
        $tenant = $this->tenant('askida', 'suspended');
        $this->qrCode($tenant, 'active', $tenant['menu']);

        $findings = array_column(
            $this->actingAs($admin)->getJson($this->uri($tenant['workspace']))->json('findings'),
            'code',
        );

        // Sıra kasıtlı: en yukarıdaki bulgu, aşağıdaki her şeyi anlamsız
        // kılan bulgudur.
        $this->assertSame('workspace_not_serving', $findings[0]);
    }

    #[Test]
    public function a_tenant_with_no_location_says_so_and_stops(): void
    {
        $admin = $this->superAdmin();

        $owner = User::factory()->create(['email_verified_at' => now()]);
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Yeni', 'slug' => 'yeni-kiraci', 'state' => 'onboarding',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $findings = array_column(
            $this->actingAs($admin)->getJson($this->uri($workspaceId))->json('findings'),
            'code',
        );

        $this->assertContains('no_location', $findings);
        // Şubesi olmayan bir kiracıda karekod bulgusu aramanın anlamı yok.
        $this->assertNotContains('location_has_no_qr', $findings);
    }

    // --- diğer destek soruları ---------------------------------------------

    #[Test]
    public function the_tenants_open_support_requests_are_listed_without_their_bodies(): void
    {
        $admin = $this->superAdmin();
        $tenant = $this->tenant('talepli');

        DB::table('support_requests')->insert([
            'reference' => 'ZB-3F7K2',
            'workspace_id' => $tenant['workspace'],
            'user_id' => $tenant['owner']->id,
            'name' => 'Hüseyin',
            'email' => 'huseyin@example.com',
            'subject' => 'Menüm görünmüyor',
            'message' => 'Karekodu okutunca boş sayfa açılıyor.',
            'channel' => 'panel',
            'status' => 'received',
            'received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson($this->uri($tenant['workspace']));

        $response->assertJsonPath('supportRequests.0.reference', 'ZB-3F7K2');
        $response->assertJsonPath('supportRequests.0.subject', 'Menüm görünmüyor');
        /*
            GÖVDE YOK. Talebin metni kuyruk ekranında okunur; burada sorulan
            soru "bu restoranın açık bir talebi var mı", "ne yazmış" değil.
            Taşınmayan alan sızmaz.
        */
        $response->assertJsonMissing(['message' => 'Karekodu okutunca boş sayfa açılıyor.']);
    }

    #[Test]
    public function the_access_history_is_part_of_the_support_view(): void
    {
        $admin = $this->superAdmin();
        $tenant = $this->tenant('gecmis');

        DB::table('support_access_sessions')->insert([
            'workspace_id' => $tenant['workspace'],
            'actor_user_id' => $admin->id,
            'reason' => 'Sahip aradı: karekod boş sayfa açıyor.',
            'started_at' => now()->subDay(),
            'expires_at' => now()->subDay()->addMinutes(15),
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($admin)->getJson($this->uri($tenant['workspace']));

        // Süperadmin de "bu kiracıya daha önce kim, neden baktı?" sorusunu
        // aynı ekrandan sorabilmeli — kiracının gördüğü listenin aynısı.
        $response->assertJsonPath('accessHistory.0.reason', 'Sahip aradı: karekod boş sayfa açıyor.');
        $response->assertJsonPath('accessHistory.0.active', false);
    }

    #[Test]
    public function the_support_view_needs_no_impersonation_session(): void
    {
        /*
            BU TESTİN TAMAMI BİR CÜMLEDİR: yukarıdaki her soru, açık bir
            destek oturumu OLMADAN cevaplandı. `support_access_sessions`
            tablosu boş ve ekran dolu — impersonation son çaredir, ilk
            hamle değil.
        */
        $admin = $this->superAdmin();
        $tenant = $this->tenant('oturumsuz');
        $this->qrCode($tenant, 'active', $tenant['menu']);
        $this->publish($tenant);

        $this->actingAs($admin)->getJson($this->uri($tenant['workspace']))->assertOk();

        $this->assertSame(0, DB::table('support_access_sessions')->count());
    }
}
