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
 * DENETİM GÜNLÜĞÜ EKRANI — `docs/122` §3 boşluk 6, Y2.
 *
 * Ölçülen cümle şuydu: *"Kayıt yazılıyor, okunacak yer yok."* Dört tablo
 * 2026-09'dan beri doluyor (medya, menü, yayın, kasa) ve hiçbirinin
 * platform düzeyinde okuyucusu yok. **Okunmayan denetim izi yoktur.**
 *
 * BİRLEŞTİRME UYGULAMADA. Dört tablonun sütunları farklı; SQL `UNION`
 * kurmak, beşinci kaynak eklendiği gün sorguyu büyütürdü. Aynı sebeple
 * her satır KENDİ kaynağını taşır: "silindi" kelimesi bir fotoğrafta ve bir
 * üründe aynı şeyi anlatmaz.
 *
 * SATIR YALNIZ "KİM, NE, NE ZAMAN" TAŞIR. Menü izinin öncesi/sonrası
 * değerleri (fiyat, alerjen) bu platform ekranına ÇIKMAZ: kiracının kendi
 * ekranında yerinde olan ayrıntı, kiracılar arası bir listede gereğinden
 * fazla veridir.
 */
final class PlatformAuditLogApiTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/admin/audit-log';

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
            'name' => 'Ana Menü',
            'state' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$workspaceId, $locationId, $menuId];
    }

    private function menuAudit(int $workspaceId, int $menuId, ?int $actorId, string $label, string $at): void
    {
        DB::table('menu_audits')->insert([
            'workspace_id' => $workspaceId,
            'menu_id' => $menuId,
            'subject_type' => 'menu_item',
            'subject_id' => 1,
            'subject_label' => $label,
            'action' => 'item_price_changed',
            'before_value' => '38000',
            'after_value' => '42000',
            'actor_user_id' => $actorId,
            'created_at' => $at,
        ]);
    }

    // --- yetki -------------------------------------------------------------

    #[Test]
    public function a_guest_never_reads_the_log(): void
    {
        $this->getJson(self::URI)->assertUnauthorized();
    }

    #[Test]
    public function a_verified_user_without_the_platform_role_gets_a_plain_404(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->getJson(self::URI)->assertNotFound();
    }

    // --- içerik ------------------------------------------------------------

    #[Test]
    public function the_four_already_written_sources_are_read_in_one_place(): void
    {
        $owner = User::factory()->create(['email' => 'sahip@ornek.test', 'email_verified_at' => now()]);
        [$workspaceId, $locationId, $menuId] = $this->workspace($owner, 'gunluk');

        $this->menuAudit($workspaceId, $menuId, $owner->id, 'Adana Kebap', '2026-09-01 10:00:00');

        DB::table('media_audits')->insert([
            'workspace_id' => $workspaceId,
            'media_asset_id' => 501,
            'action' => 'deleted',
            'actor_user_id' => $owner->id,
            'created_at' => '2026-09-01 11:00:00',
        ]);

        DB::table('menu_publications')->insert([
            'workspace_id' => $workspaceId,
            'menu_id' => $menuId,
            'location_id' => $locationId,
            'version' => 4,
            'state' => 'published',
            'snapshot' => json_encode(['categories' => []]),
            'published_by' => $owner->id,
            'published_at' => '2026-09-01 12:00:00',
            'created_at' => '2026-09-01 12:00:00',
            'updated_at' => '2026-09-01 12:00:00',
        ]);

        DB::table('platform_credential_audits')->insert([
            'provider' => 'openai',
            'action' => 'set',
            'actor_user_id' => $owner->id,
            'created_at' => '2026-09-01 13:00:00',
        ]);

        $response = $this->actingAs($this->superAdmin())->getJson(self::URI)->assertOk();

        $entries = $response->json('entries');

        // En yeni üstte: bir denetim günlüğünün tek doğal sırası budur.
        self::assertSame(
            ['credential', 'publication', 'media', 'menu'],
            array_column($entries, 'source')
        );

        foreach ($entries as $entry) {
            self::assertSame('sahip@ornek.test', $entry['actor'], 'Fail e-postayla yazılır; iki "Mehmet" ayırt edilemezdi.');
            self::assertNotSame('', (string) $entry['at']);
            self::assertNotSame('', (string) $entry['action']);
        }

        $menuEntry = collect($entries)->firstWhere('source', 'menu');
        self::assertSame($workspaceId, $menuEntry['workspaceId']);
        self::assertSame('Restoran gunluk', $menuEntry['workspaceName']);
        self::assertSame('Adana Kebap', $menuEntry['subject']);

        // Kasa izi hiçbir kiracıya ait DEĞİLDİR ve öyle numara yapmaz.
        $credentialEntry = collect($entries)->firstWhere('source', 'credential');
        self::assertNull($credentialEntry['workspaceId']);
        self::assertNull($credentialEntry['workspaceName']);
    }

    #[Test]
    public function a_deleted_actor_leaves_the_row_standing_with_an_empty_name(): void
    {
        /*
            Faili bilinmeyen kaydı GİZLEMEK, denetim izini kısaltmaktır.
            Dürüst olan, olayın olduğunu ama failin artık bilinmediğini
            söylemektir (`EloquentWorkspaceAuditTrail` ile aynı kural).
        */
        $owner = User::factory()->create(['email_verified_at' => now()]);
        [$workspaceId, , $menuId] = $this->workspace($owner, 'failsiz');

        $this->menuAudit($workspaceId, $menuId, null, 'Silinmiş ürün', '2026-09-02 09:00:00');

        $response = $this->actingAs($this->superAdmin())->getJson(self::URI)->assertOk();

        self::assertNull($response->json('entries.0.actor'));
        self::assertSame('Silinmiş ürün', $response->json('entries.0.subject'));
    }

    #[Test]
    public function filtering_by_workspace_shows_that_tenant_only(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        [$mine, , $mineMenu] = $this->workspace($owner, 'benim');
        [$theirs, , $theirsMenu] = $this->workspace($owner, 'baskasi');

        $this->menuAudit($mine, $mineMenu, $owner->id, 'Benim ürünüm', '2026-09-03 09:00:00');
        $this->menuAudit($theirs, $theirsMenu, $owner->id, 'Başkasının ürünü', '2026-09-03 10:00:00');

        DB::table('platform_credential_audits')->insert([
            'provider' => 'mailgun',
            'action' => 'set',
            'actor_user_id' => $owner->id,
            'created_at' => '2026-09-03 11:00:00',
        ]);

        $response = $this->actingAs($this->superAdmin())
            ->getJson(self::URI.'?workspace='.$mine)
            ->assertOk();

        self::assertSame(['Benim ürünüm'], array_column($response->json('entries'), 'subject'));
    }

    #[Test]
    public function filtering_by_source_narrows_the_log_and_an_unknown_source_is_refused(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        [$workspaceId, , $menuId] = $this->workspace($owner, 'suzgec');

        $this->menuAudit($workspaceId, $menuId, $owner->id, 'Menü olayı', '2026-09-04 09:00:00');

        DB::table('media_audits')->insert([
            'workspace_id' => $workspaceId,
            'media_asset_id' => 9,
            'action' => 'deleted',
            'actor_user_id' => $owner->id,
            'created_at' => '2026-09-04 10:00:00',
        ]);

        $admin = $this->superAdmin();

        $onlyMenu = $this->actingAs($admin)->getJson(self::URI.'?source=menu')->assertOk();
        self::assertSame(['menu'], array_unique(array_column($onlyMenu->json('entries'), 'source')));

        // Bilinmeyen bir kaynak SESSİZCE "hepsi" anlamına gelmez: filtresi
        // yok sayılan bir denetim ekranı, gördüğünü sandığından fazlasını
        // gizler.
        $this->actingAs($admin)->getJson(self::URI.'?source=uydurma')->assertStatus(422);
    }

    #[Test]
    public function pages_continue_each_other_without_repeating_or_dropping_a_row(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        [$workspaceId, , $menuId] = $this->workspace($owner, 'sayfa');

        for ($i = 1; $i <= 7; $i++) {
            $this->menuAudit($workspaceId, $menuId, $owner->id, 'Olay '.$i, sprintf('2026-09-05 %02d:00:00', $i));
        }

        $admin = $this->superAdmin();

        $first = $this->actingAs($admin)->getJson(self::URI.'?perPage=3&page=1')->assertOk();
        $second = $this->actingAs($admin)->getJson(self::URI.'?perPage=3&page=2')->assertOk();
        $third = $this->actingAs($admin)->getJson(self::URI.'?perPage=3&page=3')->assertOk();

        self::assertSame(['Olay 7', 'Olay 6', 'Olay 5'], array_column($first->json('entries'), 'subject'));
        self::assertSame(['Olay 4', 'Olay 3', 'Olay 2'], array_column($second->json('entries'), 'subject'));
        self::assertSame(['Olay 1'], array_column($third->json('entries'), 'subject'));

        self::assertTrue($first->json('hasMore'));
        self::assertTrue($second->json('hasMore'));
        self::assertFalse($third->json('hasMore'), 'Son sayfada "devamı var" demek, olmayan bir sayfayı vaat eder.');
    }

    // --- taşınmayanlar -----------------------------------------------------

    #[Test]
    public function the_log_carries_neither_before_after_values_nor_any_secret(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        [$workspaceId, , $menuId] = $this->workspace($owner, 'sirsiz');

        $this->menuAudit($workspaceId, $menuId, $owner->id, 'Adana Kebap', '2026-09-06 09:00:00');

        $body = $this->actingAs($this->superAdmin())->getJson(self::URI)->assertOk()->getContent() ?: '';

        foreach (['38000', '42000', 'before', 'after', 'password', 'secret', 'payload'] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $body,
                "Platform günlüğü kiracının ayrıntısını taşımaz: {$forbidden}."
            );
        }
    }

    #[Test]
    public function the_log_offers_no_write_verb(): void
    {
        $admin = $this->superAdmin();

        foreach (['post', 'put', 'patch', 'delete'] as $verb) {
            $this->actingAs($admin)->json(strtoupper($verb), self::URI)->assertStatus(405);
        }
    }
}
