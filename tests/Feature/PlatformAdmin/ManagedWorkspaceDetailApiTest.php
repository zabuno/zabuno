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
 * KİRACI AYRINTISI — `docs/122` §3 boşluk 1, Y2.
 *
 * Ölçülen durum şuydu: `/platform` bir çalışma alanı LİSTESİ çiziyor ve
 * satıra tıklayınca hiçbir şey olmuyordu. Süperadminin ilk günkü sorusu ise
 * tek satırlık değil: *"Bu restoranın kaç şubesi var, hangi menüleri var,
 * aboneliği ne durumda, dün orada ne oldu?"* Bugün bu dört soru dört ayrı
 * tabloya elle SQL atmakla cevaplanıyor.
 *
 * BU UÇ YENİ VERİ ÜRETMEZ. Hepsi zaten yazılan kayıtlardır; eksik olan tek
 * şey onları bir arada okuyan bir yerdi.
 *
 * YIKICI FİİL YOK. Bu pakette süperadmin hiçbir kiracı verisini değiştiremez
 * ve kiracı olarak oturum AÇAMAZ (o `docs/122` Y7'dir ve bilerek zordur).
 * Aşağıdaki testlerin yarısı, ucun NE YAPMADIĞINI dondurur.
 *
 * SIR YOK. Parola özeti, `remember_token`, sağlayıcı anahtarı ya da oturum
 * yükü bu cevaba ÇIKMAZ — bir destek ekranının tutması gereken en küçük veri
 * kümesi "kim, ne, ne zaman"dır.
 */
final class ManagedWorkspaceDetailApiTest extends TestCase
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

    /** @return array{0:int, 1:int, 2:int} workspace, location, menu */
    private function workspace(User $owner, string $seed): array
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Restoran '.$seed,
            'slug' => $seed,
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

        return [$workspaceId, $locationId, $menuId];
    }

    private function uri(int $workspaceId): string
    {
        return "/api/admin/workspaces/{$workspaceId}";
    }

    // --- yetki -------------------------------------------------------------

    #[Test]
    public function a_guest_never_reads_a_tenant(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        [$workspaceId] = $this->workspace($owner, 'guest-denied');

        $this->getJson($this->uri($workspaceId))->assertUnauthorized();
    }

    #[Test]
    public function a_verified_user_without_the_platform_role_gets_a_plain_404(): void
    {
        /*
            Enumeration-safe: "yetkin yok" demek yüzeyin VAR olduğunu
            söylemektir. Kiracının kendi sahibi bile buradan giremez — bu
            yüzey kiracının değil, platformun yüzeyidir.
        */
        $owner = User::factory()->create(['email_verified_at' => now()]);
        [$workspaceId] = $this->workspace($owner, 'role-denied');

        $this->actingAs($owner)->getJson($this->uri($workspaceId))->assertNotFound();
    }

    #[Test]
    public function an_unknown_workspace_is_a_plain_404_too(): void
    {
        $this->actingAs($this->superAdmin())->getJson($this->uri(4242))->assertNotFound();
    }

    // --- içerik ------------------------------------------------------------

    #[Test]
    public function one_screen_answers_branches_menus_usage_subscription_and_members(): void
    {
        $owner = User::factory()->create(['name' => 'Ayşe Yılmaz', 'email' => 'ayse@ornek.test', 'email_verified_at' => now()]);
        [$workspaceId, $locationId, $menuId] = $this->workspace($owner, 'detay');

        $planId = (int) DB::table('plans')->insertGetId([
            'code' => 'growth',
            'name' => 'Growth',
            'version' => 3,
            'entitlements' => json_encode(['menu.rich-media']),
            'amount_minor' => 99900,
            'currency' => 'TRY',
            'sort_order' => 2,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('subscriptions')->insert([
            'workspace_id' => $workspaceId,
            'plan_id' => $planId,
            'state' => 'active',
            'ends_at' => now()->addMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->superAdmin())->getJson($this->uri($workspaceId))->assertOk();

        self::assertSame($workspaceId, $response->json('workspace.id'));
        self::assertSame('Restoran detay', $response->json('workspace.name'));
        self::assertSame('detay', $response->json('workspace.slug'));
        self::assertSame('active', $response->json('workspace.state'));

        self::assertSame('Marka detay', $response->json('brand.name'));
        self::assertSame('TRY', $response->json('brand.currency'));

        // Şube: destek çağrısının ilk sorusu "hangi şube?"dir.
        self::assertSame([$locationId], array_column($response->json('locations'), 'id'));
        self::assertSame('Şube detay', $response->json('locations.0.displayName'));
        self::assertSame('İstanbul', $response->json('locations.0.city'));

        // Menü: hangi şubede olduğu menünün YANINDA durur; üç şubeli bir
        // işletmede "Ana Menü" tek başına hiçbir şey söylemez.
        self::assertSame([$menuId], array_column($response->json('menus'), 'id'));
        self::assertSame('draft', $response->json('menus.0.state'));
        self::assertSame('Şube detay', $response->json('menus.0.locationName'));

        // Kullanım SAYIMDIR, listenin uzunluğu değil: liste kırpılır, sayı
        // kırpılmaz (bkz. aşağıdaki kırpma testi).
        self::assertSame(1, $response->json('usage.locations'));
        self::assertSame(1, $response->json('usage.menus'));
        self::assertSame(1, $response->json('usage.members'));

        self::assertSame('active', $response->json('subscription.state'));
        self::assertSame('growth', $response->json('subscription.plan_code'));
        self::assertSame(3, $response->json('subscription.plan_version'));

        // Ekip: rol ve e-posta birlikte. Bir ekipte iki "Mehmet" olabilir;
        // "Mehmet sahibi" cümlesi hiçbir destek çağrısını kapatmaz.
        self::assertSame('owner', $response->json('members.0.role'));
        self::assertSame('ayse@ornek.test', $response->json('members.0.email'));
    }

    #[Test]
    public function a_workspace_without_a_subscription_says_none_instead_of_guessing(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        [$workspaceId] = $this->workspace($owner, 'abonesiz');

        $response = $this->actingAs($this->superAdmin())->getJson($this->uri($workspaceId))->assertOk();

        self::assertSame('none', $response->json('subscription.state'));
        self::assertNull($response->json('subscription.plan_code'));
    }

    #[Test]
    public function the_last_events_are_the_ones_already_recorded_and_carry_who_did_them(): void
    {
        /*
            Olay akışı UYDURULMAZ: var olan `WorkspaceAuditTrailPort` okunur.
            İkinci bir birleştirici yazmak, bir gün iki farklı "son olaylar"
            listesi üretirdi.
        */
        $owner = User::factory()->create(['email' => 'sahip@ornek.test', 'email_verified_at' => now()]);
        [$workspaceId, $locationId, $menuId] = $this->workspace($owner, 'olaylar');

        DB::table('menu_publications')->insert([
            'workspace_id' => $workspaceId,
            'menu_id' => $menuId,
            'location_id' => $locationId,
            'version' => 7,
            'state' => 'published',
            'snapshot' => json_encode(['categories' => []]),
            'published_by' => $owner->id,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->superAdmin())->getJson($this->uri($workspaceId))->assertOk();

        $events = $response->json('recentEvents');
        self::assertNotSame([], $events, 'Yayınlanmış bir menü varken olay akışı boş olamaz.');
        self::assertSame('publication', $events[0]['source']);
        self::assertSame('published', $events[0]['action']);
        self::assertSame('sahip@ornek.test', $events[0]['actor']);
        self::assertStringContainsString('v7', (string) $events[0]['subject']);
    }

    #[Test]
    public function long_lists_are_capped_while_the_count_stays_true(): void
    {
        /*
            KIRPILAN LİSTE, YANLIŞ SAYI DEĞİLDİR. Elli şubeli bir zincirde
            ekranın elli satır çizmesi gerekmez; ama "kaç şube var" sorusuna
            "yirmi" demek yalan olurdu. Bu yüzden liste kırpılır, `usage`
            sayımı GERÇEK sayıyı taşır.
        */
        $owner = User::factory()->create(['email_verified_at' => now()]);
        [$workspaceId] = $this->workspace($owner, 'kirpma');

        $brandId = (int) DB::table('brands')->where('workspace_id', $workspaceId)->value('id');

        for ($i = 0; $i < 60; $i++) {
            DB::table('locations')->insert([
                'workspace_id' => $workspaceId,
                'brand_id' => $brandId,
                'display_name' => 'Ek şube '.$i,
                'country_code' => 'TR',
                'timezone' => 'Europe/Istanbul',
                'city' => 'İzmir',
                'address_line1' => 'Adres '.$i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $response = $this->actingAs($this->superAdmin())->getJson($this->uri($workspaceId))->assertOk();

        self::assertSame(61, $response->json('usage.locations'));
        self::assertLessThanOrEqual(50, count($response->json('locations')));
        self::assertTrue($response->json('listsTruncated.locations'));
    }

    // --- taşınmayanlar -----------------------------------------------------

    #[Test]
    public function no_secret_and_no_personal_surplus_reaches_the_payload(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        [$workspaceId] = $this->workspace($owner, 'sirsiz');

        $body = $this->actingAs($this->superAdmin())->getJson($this->uri($workspaceId))->assertOk()->getContent() ?: '';

        foreach (['password', 'remember_token', 'api_token', 'secret', 'payload', 'ip_address', 'user_agent'] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $body,
                "Destek ekranı gerekenden fazlasını taşımaz: {$forbidden}."
            );
        }
    }

    #[Test]
    public function this_surface_is_read_only_and_carries_no_impersonation_door(): void
    {
        /*
            `docs/122` §5: kiracı olarak bakmak EN TEHLİKELİ süperadmin
            yeteneğidir ve Y7'ye bırakılmıştır. Bu paketin kaynağında ona
            açılan bir kapı olmamalı — kolay bir impersonation, bir gün
            kimsenin hatırlamadığı bir erişim olur.
        */
        $owner = User::factory()->create(['email_verified_at' => now()]);
        [$workspaceId] = $this->workspace($owner, 'salt-okunur');
        $admin = $this->superAdmin();

        foreach (['post', 'put', 'patch', 'delete'] as $verb) {
            $this->actingAs($admin)->json(strtoupper($verb), $this->uri($workspaceId))->assertStatus(405);
        }

        $sources = [
            'app/Http/Controllers/PlatformAdmin/ShowManagedWorkspaceController.php',
            'app/Http/Controllers/PlatformAdmin/ListManagedUsersController.php',
            'app/Http/Controllers/PlatformAdmin/ListPlatformAuditLogController.php',
        ];

        foreach ($sources as $source) {
            $code = file_get_contents(base_path($source));
            self::assertIsString($code, "{$source} okunamadı.");

            foreach (['Auth::login', 'loginUsingId', 'Auth::guard', '->delete(', '->update(', '->insert('] as $forbidden) {
                self::assertStringNotContainsString(
                    $forbidden,
                    $code,
                    "{$source}: Y2 salt okunurdur; `{$forbidden}` burada olamaz."
                );
            }
        }
    }
}
