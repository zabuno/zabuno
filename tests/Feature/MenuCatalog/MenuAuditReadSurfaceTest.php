<?php

declare(strict_types=1);

namespace Tests\Feature\MenuCatalog;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\GrantsPlanEntitlements;
use Tests\TestCase;

/**
 * FF-163 — İZ ARTIK OKUNABİLİR.
 *
 * MÜŞTERİ SORUNU. FF-154 ve FF-156 menü denetim izini KURDU ama bilerek
 * ekran çizmedi: o an tabloda veri yoktu ve bu depoda veri olmadan ekran
 * çizilmez. Sonuç şuydu — sahibin sorusu (*"dün kebabın fiyatını kim
 * değiştirdi?"*) veritabanında cevaplı, üründe cevapsızdı. Kaydı tutup
 * göstermemek, tutmamaktan yalnız bir adım iyidir.
 *
 * BU DOSYA OKUMA UCUNU DONDURUR. Yazma yolunun sözleşmesi
 * `MenuAuditTrailTest` ve `AiApplyMenuAuditTest`'te duruyor; burada
 * sorulan tek şey, YAZILMIŞ bir izin sahibe nasıl döndüğüdür.
 *
 * SATIRIN OKUNUR OLMASI DÖRT PARÇADIR: kim · ne zaman · neyi · neyden neye.
 * Dördünden biri eksikse satır sahibin sorusunu kapatmaz — "fiyat değişti"
 * cümlesi bir cevap değil, ikinci bir sorudur.
 *
 * Gereksinim: MENU-AUDIT-READ-WHO-01, MENU-AUDIT-READ-DELETED-02,
 * MENU-AUDIT-READ-AI-03, MENU-AUDIT-READ-PERM-04, MENU-AUDIT-READ-TENANT-05,
 * MENU-AUDIT-READ-PAGE-06, MENU-AUDIT-READ-INSTANT-07,
 * MENU-AUDIT-READ-EMPTY-08.
 */
final class MenuAuditReadSurfaceTest extends TestCase
{
    use GrantsPlanEntitlements;
    use RefreshDatabase;

    /** @return array{owner: User, workspace: int, location: int, menu: int} */
    private function restaurant(string $seed, string $timezone = 'Europe/Istanbul'): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);

        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => $seed, 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId, 'user_id' => $owner->id, 'role' => 'owner',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspaceId, 'name' => 'Zeytin', 'slug' => $seed.'-brand',
            'locale' => 'tr', 'timezone' => $timezone, 'currency' => 'TRY',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId, 'brand_id' => $brandId,
            'display_name' => 'Kadıköy', 'country_code' => 'TR', 'timezone' => $timezone,
            'city' => 'İstanbul', 'address_line1' => 'Bahariye Cd. 1',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->grantEntitlements($workspaceId);

        $menuId = (int) $this->actingAs($owner)->postJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/menu",
            ['name' => 'Ana Menü'],
        )->assertStatus(201)->json('id');

        return ['owner' => $owner, 'workspace' => $workspaceId, 'location' => $locationId, 'menu' => $menuId];
    }

    /** @param array{owner: User, workspace: int, location: int, menu: int} $r */
    private function addCategory(array $r, string $name): int
    {
        return (int) $this->actingAs($r['owner'])->postJson(
            "/api/workspaces/{$r['workspace']}/menu/{$r['menu']}/categories",
            ['name' => $name],
        )->assertStatus(201)->json('id');
    }

    /** @param array{owner: User, workspace: int, location: int, menu: int} $r */
    private function addItem(array $r, int $categoryId, string $name, string $price = '45.00'): int
    {
        return (int) $this->actingAs($r['owner'])->postJson(
            "/api/workspaces/{$r['workspace']}/menu-categories/{$categoryId}/menu-entries",
            ['productName' => $name, 'price' => $price, 'currency' => 'TRY', 'allergens' => []],
        )->assertStatus(201)->json('id');
    }

    /**
     * Bir kullanıcıyı çalışma alanına verilen rolle katar.
     *
     * @param  array{owner: User, workspace: int, location: int, menu: int}  $r
     */
    private function member(array $r, string $role): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $r['workspace'], 'user_id' => $user->id, 'role' => $role,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $user;
    }

    /**
     * İzin bir sayfasını okur.
     *
     * @param  array{owner: User, workspace: int, location: int, menu: int}  $r
     * @return array{data: list<array<string, mixed>>, page: int, pageCount: int}
     */
    private function readTrail(array $r, ?User $as = null, int $page = 1): array
    {
        /** @var array{data: list<array<string, mixed>>, page: int, pageCount: int} $body */
        $body = $this->actingAs($as ?? $r['owner'])
            ->getJson("/api/workspaces/{$r['workspace']}/menu/audits?page={$page}")
            ->assertOk()
            ->json();

        return $body;
    }

    /**
     * @param  array{data: list<array<string, mixed>>, page: int, pageCount: int}  $body
     * @return list<array<string, mixed>>
     */
    private function only(array $body, string $action): array
    {
        return array_values(array_filter(
            $body['data'],
            static fn (array $row): bool => $row['action'] === $action,
        ));
    }

    // --- MENU-AUDIT-READ-WHO-01 ---------------------------------------------

    /**
     * Paketin var oluş sebebi: satır DÖRT parçayı da taşıyor mu?
     *
     * Fail E-POSTAYLA döner, kimlikle değil: ekranda "17 numaralı kullanıcı"
     * yazması, sorunun cevabını bir tabloya daha bakmaya bağlardı. Bir
     * ekipte iki "Mehmet" olabileceği için ad da yetmez.
     */
    public function test_a_price_change_is_readable_as_who_when_what_and_from_what_to_what(): void
    {
        $r = $this->restaurant('read-price');
        $categoryId = $this->addCategory($r, 'Kebaplar');
        $itemId = $this->addItem($r, $categoryId, 'Adana Kebap', '380.00');

        $this->actingAs($r['owner'])->putJson(
            "/api/workspaces/{$r['workspace']}/menu-items/{$itemId}/price",
            ['price' => '420.00', 'currency' => 'TRY'],
        )->assertOk();

        $rows = $this->only($this->readTrail($r), 'item_price_changed');

        self::assertCount(1, $rows, 'MENU-AUDIT-READ-WHO-01: yazılmış bir fiyat değişimi okunabilmeli.');
        self::assertSame('Adana Kebap', $rows[0]['subjectLabel'], 'NEYİ: satır hangi ürün olduğunu söylemeli.');
        self::assertSame('380.00 TRY', $rows[0]['before'], 'NEYDEN: öncesi olmadan fiyat satırı işe yaramaz.');
        self::assertSame('420.00 TRY', $rows[0]['after'], 'NEYE.');
        self::assertSame($r['owner']->email, $rows[0]['actor'], 'KİM: fail e-postayla okunur.');
        self::assertNotNull($rows[0]['at'], 'NE ZAMAN.');
        self::assertSame('menu_item', $rows[0]['subjectType']);
    }

    /** En yeni önce: sahip son değişikliği aramak için listeyi taramaz. */
    public function test_the_newest_change_is_first(): void
    {
        $r = $this->restaurant('read-order');
        $categoryId = $this->addCategory($r, 'Kebaplar');
        $this->addItem($r, $categoryId, 'Adana Kebap', '380.00');

        $body = $this->readTrail($r);

        self::assertSame('item_added', $body['data'][0]['action']);
        self::assertSame('menu_created', $body['data'][2]['action']);
    }

    // --- MENU-AUDIT-READ-DELETED-02 -----------------------------------------

    /**
     * SİLİNMİŞ ÜRÜNÜN SATIRI DA OKUNUR.
     *
     * İzin en değerli olduğu an, ürünün artık menüde olmadığı andır. Satır
     * "137 numaralı ürün" derse o an hiçbir işe yaramaz: kayıt olay
     * anındaki adı zaten saklıyor ve ekran onu okur.
     */
    public function test_a_deleted_items_row_still_reads_by_its_name(): void
    {
        $r = $this->restaurant('read-deleted');
        $categoryId = $this->addCategory($r, 'Çorbalar');
        $itemId = $this->addItem($r, $categoryId, 'Mercimek Çorbası', '90.00');

        $this->actingAs($r['owner'])
            ->deleteJson("/api/workspaces/{$r['workspace']}/menu-items/{$itemId}")
            ->assertOk();

        $rows = $this->only($this->readTrail($r), 'item_removed');

        self::assertCount(1, $rows);
        self::assertSame(
            'Mercimek Çorbası',
            $rows[0]['subjectLabel'],
            'MENU-AUDIT-READ-DELETED-02: silinmiş satır kimliğini korumalı.'
        );
        self::assertSame('90.00 TRY', $rows[0]['before'], 'Silinen satırın fiyatı da okunmalı.');
    }

    // --- MENU-AUDIT-READ-AI-03 ----------------------------------------------

    /**
     * "Bunu ben mi yazdım, yoksa makine mi okudu?"
     *
     * Fotoğraftan aktarım ayrı bir eylem olarak KAYITLI; ekranın onu ayırt
     * edebilmesi için uç da ayrı döndürmek zorunda. Ayrımı özet metnine
     * gömmek, izi okuyan her yüzeyi bir gün metin ayrıştırmaya zorlardı.
     */
    public function test_a_photo_import_is_a_different_action_than_a_csv_import(): void
    {
        $r = $this->restaurant('read-ai');

        // Yazma yollarının kendi testleri var; burada okunan şey AYRIMDIR.
        DB::table('menu_audits')->insert([
            [
                'workspace_id' => $r['workspace'], 'menu_id' => $r['menu'],
                'subject_type' => 'menu', 'subject_id' => $r['menu'], 'subject_label' => 'Ana Menü',
                'action' => 'menu_imported', 'before_value' => null, 'after_value' => '12 satır',
                'actor_user_id' => $r['owner']->id, 'created_at' => now(),
            ],
            [
                'workspace_id' => $r['workspace'], 'menu_id' => $r['menu'],
                'subject_type' => 'menu', 'subject_id' => $r['menu'], 'subject_label' => 'Ana Menü',
                'action' => 'menu_ai_imported', 'before_value' => null, 'after_value' => '9 satır',
                'actor_user_id' => $r['owner']->id, 'created_at' => now(),
            ],
        ]);

        $body = $this->readTrail($r);

        self::assertCount(1, $this->only($body, 'menu_imported'));
        self::assertCount(
            1,
            $this->only($body, 'menu_ai_imported'),
            'MENU-AUDIT-READ-AI-03: makine okuması kendi eylemiyle dönmeli.'
        );
    }

    // --- MENU-AUDIT-READ-PERM-04 --------------------------------------------

    /**
     * FİYAT GEÇMİŞİ TİCARİ BİR BİLGİDİR.
     *
     * Mutfak rolü alerjen düzeltir ve "bugün bitti" der; menünün fiyatına
     * hiç dokunmaz (`RolePermissions`, `docs/109` §6.4 — "başka bir şey
     * görmez"). Kim hangi fiyatı ne zaman değiştirdi sorusu onun işi değil.
     * Kapı bu yüzden `menu.manage`: menüyü DEĞİŞTİREBİLENLER, kimin
     * değiştirdiğini de görür.
     */
    public function test_the_kitchen_role_cannot_read_the_price_history(): void
    {
        $r = $this->restaurant('read-kitchen');
        $kitchen = $this->member($r, 'kitchen');

        $this->actingAs($kitchen)
            ->getJson("/api/workspaces/{$r['workspace']}/menu/audits")
            ->assertStatus(403);
    }

    /** Salt okunur eski üye de görmez: menüyü değiştiremeyen, geçmişini de görmez. */
    public function test_a_read_only_member_cannot_read_the_trail(): void
    {
        $r = $this->restaurant('read-member');
        $member = $this->member($r, 'member');

        $this->actingAs($member)
            ->getJson("/api/workspaces/{$r['workspace']}/menu/audits")
            ->assertStatus(403);
    }

    /** Menüyü düzenleyen editör görür: kendi değişikliğinin de kaydı vardır. */
    public function test_an_editor_reads_the_trail(): void
    {
        $r = $this->restaurant('read-editor');
        $editor = $this->member($r, 'editor');

        $this->readTrail($r, $editor);
    }

    /**
     * Çalışma alanına hiç üye olmayan için 404 — 403 DEĞİL.
     *
     * 403 "böyle bir çalışma alanı var ama sana kapalı" der ve bu da bir
     * bilgidir. Medya izinde (`ListMediaAuditsController`) aynı ayrım
     * yapılıyor; ikinci bir desen kurulmuyor.
     */
    public function test_a_stranger_gets_not_found(): void
    {
        $r = $this->restaurant('read-stranger');
        $stranger = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($stranger)
            ->getJson("/api/workspaces/{$r['workspace']}/menu/audits")
            ->assertStatus(404);
    }

    // --- MENU-AUDIT-READ-TENANT-05 ------------------------------------------

    /**
     * Kiracı sınırı SORGUNUN İÇİNDE.
     *
     * Ekran kuralı olsaydı, izi okuyan ikinci bir yüzey yazıldığı gün sınır
     * sessizce kaybolurdu.
     */
    public function test_one_workspace_never_reads_another_workspaces_trail(): void
    {
        $mine = $this->restaurant('read-tenant-mine');
        $theirs = $this->restaurant('read-tenant-theirs');

        $this->addCategory($theirs, 'Pizzalar');

        $labels = array_column($this->readTrail($mine)['data'], 'subjectLabel');

        self::assertNotContains('Pizzalar', $labels, 'MENU-AUDIT-READ-TENANT-05: iz kiracı sınırında kalmalı.');
        self::assertCount(1, $this->readTrail($mine)['data']);
    }

    // --- MENU-AUDIT-READ-PAGE-06 --------------------------------------------

    /**
     * İZ BÜYÜR — sayfalanır.
     *
     * Menüsünü bir yıl işleten bir restoranda binlerce satır olur; hepsini
     * tek istekte göndermek, ekranı açan sahibi bekletmenin en sessiz yolu.
     * Sayfa boyutu SUNUCUDA sabit: istemciye bırakılsaydı `?page` ile
     * gelen bir `perPage=100000` aynı yükü geri getirirdi.
     */
    public function test_the_trail_is_paginated_and_the_second_page_continues_the_first(): void
    {
        $r = $this->restaurant('read-page');
        $categoryId = $this->addCategory($r, 'Kebaplar');

        // 2 (menü + kategori) + 25 = 27 satır; sayfa boyutu 20.
        for ($index = 1; $index <= 25; $index++) {
            $this->addItem($r, $categoryId, 'Ürün '.$index, '10.00');
        }

        $first = $this->readTrail($r);
        $second = $this->readTrail($r, page: 2);

        self::assertCount(20, $first['data'], 'MENU-AUDIT-READ-PAGE-06: ilk sayfa sabit boyutta olmalı.');
        self::assertSame(2, $first['pageCount']);
        self::assertCount(7, $second['data']);
        self::assertSame(2, $second['page']);

        $firstIds = array_column($first['data'], 'id');
        $secondIds = array_column($second['data'], 'id');

        self::assertSame([], array_intersect($firstIds, $secondIds), 'Sayfalar aynı satırı iki kez göstermemeli.');
        self::assertCount(27, array_unique([...$firstIds, ...$secondIds]));
    }

    /**
     * Var olmayan bir sayfa BOŞ döner, hata değil.
     *
     * Sahip elindeki bağlantıyı yenilediğinde ya da liste kısaldığında
     * "sayfa bulunamadı" görmek, kaydın kaybolduğunu düşündürürdü.
     */
    public function test_a_page_beyond_the_end_is_empty_and_still_reports_the_page_count(): void
    {
        $r = $this->restaurant('read-page-beyond');

        $body = $this->readTrail($r, page: 9);

        self::assertSame([], $body['data']);
        self::assertSame(1, $body['pageCount']);
    }

    // --- MENU-AUDIT-READ-INSTANT-07 -----------------------------------------

    /**
     * ZAMAN MUTLAK BİR AN OLARAK DÖNER, ŞUBENİN DİLİMİ AYRI BİR ALANDA.
     *
     * Bu depoda az önce bir hata bunun tersinden çıktı: zamanlanmış yayın
     * sabit `Europe/Istanbul` ile yazılıyordu ve Berlin şubesinde ekrandaki
     * saat ile gerçekte olan an ayrışıyordu (`docs/62`,
     * `BuildScheduleOptions`). Sunucu bu yüzden bir SAAT değil, bir AN
     * gönderir; hangi duvar saatiyle okunacağını şubenin kendi dilimi
     * söyler ve ekran yalnız biçimlendirir.
     *
     * Menüsü silinmiş bir satırda şube bilinmez ve dilim `null` döner —
     * uydurma bir şehir yazmaktansa okuyanın kendi saatine düşmek dürüsttür.
     */
    public function test_time_comes_back_as_an_absolute_instant_with_the_branch_clock_beside_it(): void
    {
        $r = $this->restaurant('read-instant', 'Europe/Berlin');
        $this->addCategory($r, 'Kebaplar');

        $rows = $this->only($this->readTrail($r), 'category_added');

        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?Z$/',
            (string) $rows[0]['at'],
            'MENU-AUDIT-READ-INSTANT-07: zaman mutlak bir an olarak dönmeli.'
        );
        self::assertSame('Europe/Berlin', $rows[0]['timeZone'], 'Saat dilimi ŞUBENİNDİR, sabit değil.');
    }

    /** Menüsü silinmiş satırda şube bilinmez; dilim uydurulmaz. */
    public function test_a_row_whose_menu_is_gone_reports_no_branch_clock(): void
    {
        $r = $this->restaurant('read-instant-orphan');

        DB::table('menu_audits')->insert([
            'workspace_id' => $r['workspace'], 'menu_id' => null,
            'subject_type' => 'menu_item', 'subject_id' => 4242, 'subject_label' => 'Adana Kebap',
            'action' => 'item_removed', 'before_value' => '380.00 TRY', 'after_value' => null,
            'actor_user_id' => null, 'created_at' => now(),
        ]);

        $rows = $this->only($this->readTrail($r), 'item_removed');

        self::assertCount(1, $rows);
        self::assertNull($rows[0]['timeZone']);
        self::assertNull($rows[0]['actor'], 'Faili bilinmeyen kayıt SİLİNMEZ; bilinmediği söylenir.');
    }

    // --- MENU-AUDIT-READ-EMPTY-08 -------------------------------------------

    /**
     * KAYIT YOKSA UYDURULMAZ.
     *
     * Hiç değişiklik yapılmamış bir çalışma alanında uç boş bir liste
     * döner; sıfırlar, örnek satırlar ya da "yakında" diyen bir gövde
     * göndermez.
     */
    public function test_a_workspace_with_no_recorded_change_returns_an_empty_list(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);

        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Boş', 'slug' => 'read-empty', 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId, 'user_id' => $owner->id, 'role' => 'owner',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $body = $this->actingAs($owner)
            ->getJson("/api/workspaces/{$workspaceId}/menu/audits")
            ->assertOk()
            ->json();

        self::assertSame([], $body['data']);
        self::assertSame(1, $body['pageCount']);
    }
}
