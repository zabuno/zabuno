<?php

declare(strict_types=1);

namespace Tests\Feature\PlatformAdmin;

use App\Domain\Platform\PlatformRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Mühendislik kabuğu `/platform` altına taşınır (FF-248).
 *
 * Sahibin sorusu şuydu: *"'/platform' haricinde '/engineering' sayfası var.
 * '/platform/engineering' olsa, fena mı olurdu?"* Fena olmazdı — iyi olurdu.
 * İki kabuk da AYNI kapıdan geçiyor (`auth:web`, `verified`,
 * `EnsurePlatformSuperAdmin`); adres alanı bunu söylemiyordu. Kök seviyesinde
 * duran `/engineering`, kendi başına bir üçüncü uygulama gibi görünüyor ve
 * `docs/38`'in her yeni kökü rezerve etmeyi zorunlu kılan kuralına bir kalem
 * daha yazıyordu. Yetki bir tanesi ise, adres de bir tanesi olmalı.
 *
 * DONMUŞ SÖZLEŞME:
 *
 * 1. `/platform/engineering` VE alt bölümleri superadmin'e 200 döner —
 *    ve `/platform/{section}` rotası tarafından YUTULMAZ. Bu ikinci cümle
 *    testin asıl sebebidir: `/platform/{section}` deseni `engineering`
 *    kelimesiyle eşleşir, yani sıralama yanlışsa mühendislik kabuğu yerine
 *    platform kabuğu çizilir ve kimse fark etmez.
 * 2. `/engineering` ve `/engineering/{section}` **301** ile yeni adrese
 *    gider. Kalıcıdır: yer imi, ekran görüntüsü, iç wiki bağlantısı
 *    kırılmasın diye. 302 olsaydı tarayıcı eski adresi hiç unutmazdı.
 * 3. Yetkisiz kullanıcı İKİSİNDEN DE içeri giremez. Yönlendirme bir kapı
 *    değildir — sadece bir adres tercümesidir; kapı hedeftedir ve orada
 *    yine enumeration-safe 404 verir.
 */
final class EngineeringAddressMoveTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_URI = '/platform/engineering';

    private const OLD_URI = '/engineering';

    private function verifiedUser(string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'email_verified_at' => now(),
        ]);
    }

    private function grantSuperAdmin(User $user): void
    {
        DB::table('platform_role_assignments')->insert([
            'user_id' => $user->getKey(),
            'role' => PlatformRole::SuperAdmin->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function grantTenantOwnerMembership(User $user): void
    {
        $workspaceId = DB::table('workspaces')->insertGetId([
            'name' => 'Engineering Move Test Workspace',
            'slug' => 'engineering-move-test-workspace-'.$user->getKey(),
            'state' => 'active',
            'created_by' => $user->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $user->getKey(),
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // --- (a) yeni adres --------------------------------------------------

    public function test_the_new_address_serves_the_engineering_shell_to_a_super_admin(): void
    {
        $admin = $this->verifiedUser('engineering-move-admin@example.com');
        $this->grantSuperAdmin($admin);

        $response = $this->actingAs($admin)->get(self::NEW_URI);

        $response->assertSuccessful();
        $response->assertSee('id="engineering-app"', false);
        $response->assertSee('engineering.tsx', false);
    }

    /**
     * `/platform/{section}` deseni `engineering` ile de eşleşir. Rota
     * sıralaması yanlış olursa istek sessizce PLATFORM kabuğuna düşer:
     * 200 döner, test yeşil görünür, ekran yanlış olur. Bu yüzden gövde
     * kontrol edilir, durum kodu değil.
     */
    public function test_the_platform_section_route_does_not_swallow_the_engineering_shell(): void
    {
        $admin = $this->verifiedUser('engineering-move-not-swallowed@example.com');
        $this->grantSuperAdmin($admin);

        $body = (string) $this->actingAs($admin)->get(self::NEW_URI)->getContent();

        self::assertStringNotContainsString(
            'id="platform-admin-app"',
            $body,
            'FF-248: /platform/engineering platform kabuğuna düşmüş — rota sırası bozuk.',
        );
    }

    public function test_the_new_address_serves_a_section_below_the_engineering_shell(): void
    {
        $admin = $this->verifiedUser('engineering-move-section@example.com');
        $this->grantSuperAdmin($admin);

        foreach (['release-readiness', 'ai-audit', 'modules'] as $section) {
            $response = $this->actingAs($admin)->get(self::NEW_URI.'/'.$section);

            $response->assertSuccessful();
            $response->assertSee('id="engineering-app"', false);
        }
    }

    // --- (b) eski adres kalıcı yönlendirir --------------------------------

    public function test_the_old_root_address_permanently_redirects_to_the_new_one(): void
    {
        $this->get(self::OLD_URI)
            ->assertStatus(301)
            ->assertRedirect(self::NEW_URI);
    }

    public function test_every_old_section_address_permanently_redirects_to_its_new_one(): void
    {
        foreach (['release-readiness', 'ai-audit', 'modules', 'gelecekte-eklenecek'] as $section) {
            $this->get(self::OLD_URI.'/'.$section)
                ->assertStatus(301)
                ->assertRedirect(self::NEW_URI.'/'.$section);
        }
    }

    /**
     * Alt yol da korunur. Bugün kabuk tek segmentli bölüm kullanıyor ama
     * yarın `/platform/engineering/settings/brand` gibi bir adres doğarsa,
     * eski kökten gelen bağlantı o gün de kırılmamalı. Eğik çizgi
     * KAÇIRILMADAN taşınmalı: `%2F` olarak kodlanan bir yol, var olmayan
     * tek bir bölüm adına dönüşür ve sessizce yanlış ekranı açardı.
     */
    public function test_a_deeper_old_address_keeps_its_shape_while_moving(): void
    {
        $this->get(self::OLD_URI.'/settings/brand')
            ->assertStatus(301)
            ->assertRedirect(self::NEW_URI.'/settings/brand');
    }

    /**
     * Yönlendirme bir kapı değildir: superadmin de aynı 301'i alır ve
     * hedefte kabuğu görür. Aksi hâlde giriş yapmış bir kullanıcının yer
     * imi çalışır, çıkış yapmışınki çalışmaz olurdu.
     */
    public function test_the_redirect_is_the_same_for_a_super_admin(): void
    {
        $admin = $this->verifiedUser('engineering-move-redirect-admin@example.com');
        $this->grantSuperAdmin($admin);

        $this->actingAs($admin)->get(self::OLD_URI)
            ->assertStatus(301)
            ->assertRedirect(self::NEW_URI);
    }

    // --- (c) yetkisiz kullanıcı ikisinden de giremez -----------------------

    public function test_a_tenant_owner_is_denied_at_the_new_address_enumeration_safely(): void
    {
        $owner = $this->verifiedUser('engineering-move-owner@example.com');
        $this->grantTenantOwnerMembership($owner);

        $this->actingAs($owner)->get(self::NEW_URI)->assertNotFound();
        $this->actingAs($owner)->get(self::NEW_URI.'/modules')->assertNotFound();
    }

    /**
     * Eski adres 301 verir — ama o 301 kimseyi içeri almaz. Kiracı sahibi
     * yönlendirmeyi izlediğinde yine çıplak 404 görür; yani eski adresin
     * korunmuş olması yetkiyi bir milim genişletmez.
     */
    public function test_a_tenant_owner_following_the_old_address_still_lands_on_a_bare_404(): void
    {
        $owner = $this->verifiedUser('engineering-move-owner-follows@example.com');
        $this->grantTenantOwnerMembership($owner);

        $this->actingAs($owner)->get(self::OLD_URI)
            ->assertStatus(301)
            ->assertRedirect(self::NEW_URI);

        $this->actingAs($owner)->followingRedirects()->get(self::OLD_URI)->assertNotFound();
    }

    public function test_a_guest_cannot_enter_either_address(): void
    {
        $this->get(self::NEW_URI)->assertRedirect('/login');

        $this->followingRedirects()->get(self::OLD_URI)->assertSee('data-auth-view="login"', false);
    }

    public function test_an_unverified_user_cannot_enter_the_new_address(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->withHeaders(['Accept' => 'application/json'])->get(self::NEW_URI);

        self::assertNotSame(404, $response->getStatusCode(), 'FF-248: rota var olmalı; 404 kabul edilmez.');
        self::assertContains($response->getStatusCode(), [302, 403], 'FF-248: doğrulanmamış kullanıcı 302 veya 403 ile reddedilmeli.');
    }
}
