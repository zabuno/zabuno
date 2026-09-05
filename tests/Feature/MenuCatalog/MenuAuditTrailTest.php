<?php

declare(strict_types=1);

namespace Tests\Feature\MenuCatalog;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\GrantsPlanEntitlements;
use Tests\TestCase;

/**
 * FF-154 — MENÜ denetim izi.
 *
 * MÜŞTERİ SORUNU. Depoda MEDYA için tam bir denetim izi var ("bu fotoğrafı
 * kim sildi?") ama MENÜ için yok. Asimetri ters yönde: misafirin ÖDEDİĞİ
 * fiyatı değiştiren yüzey menüdür, dosya kütüphanesi değil. Ekipte artık
 * dört rol var (sahip, yönetici, editör, mutfak) ve menüye birden fazla
 * kişi dokunuyor; *"dün kebabın fiyatını kim değiştirdi?"* bugün hiçbir
 * yerden cevaplanamıyor.
 *
 * KAYITTA ÖNCESİ ŞART. "Fiyat değişti" cümlesi tek başına işe yaramaz:
 * sahip 380'den 420'ye mi çıkıldığını sorar, "bir şey değişti"yi değil.
 * Bu yüzden her satır öncesi/sonrası taşır.
 *
 * İZ APPEND-ONLY: satır bir kez yazılır, `updated_at` yoktur ve varlık
 * silindikten sonra da yaşar — asıl değeri olan an, ürünün artık menüde
 * olmadığı andır.
 *
 * Gereksinim: MENU-AUDIT-PRICE-01, MENU-AUDIT-BEFORE-02,
 * MENU-AUDIT-SURVIVES-DELETE-03, MENU-AUDIT-TENANT-04,
 * MENU-AUDIT-APPEND-ONLY-05, MENU-AUDIT-NO-NOISE-06,
 * MENU-AUDIT-NONBLOCKING-07, MENU-AUDIT-BULK-08.
 */
final class MenuAuditTrailTest extends TestCase
{
    use GrantsPlanEntitlements;
    use RefreshDatabase;

    /** @return array{owner: User, workspace: int, location: int, menu: int} */
    private function restaurant(string $seed): array
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
            'locale' => 'tr', 'timezone' => 'Europe/Istanbul', 'currency' => 'TRY',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId, 'brand_id' => $brandId,
            'display_name' => 'Kadıköy', 'country_code' => 'TR', 'timezone' => 'Europe/Istanbul',
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

    /**
     * @param  array{owner: User, workspace: int, location: int, menu: int}  $r
     * @param  list<string>  $allergens
     */
    private function addItem(array $r, int $categoryId, string $name, string $price = '45.00', array $allergens = []): int
    {
        return (int) $this->actingAs($r['owner'])->postJson(
            "/api/workspaces/{$r['workspace']}/menu-categories/{$categoryId}/menu-entries",
            ['productName' => $name, 'price' => $price, 'currency' => 'TRY', 'allergens' => $allergens],
        )->assertStatus(201)->json('id');
    }

    /**
     * İzin okunması gereken her şeyi tek bir dizide verir.
     *
     * @return list<array{action:string, subjectType:string, subjectId:int, subjectLabel:?string, before:?string, after:?string, actor:?int, menuId:?int}>
     */
    private function trail(int $workspaceId): array
    {
        return DB::table('menu_audits')
            ->where('workspace_id', $workspaceId)
            ->orderBy('id')
            ->get()
            ->map(static fn (object $row): array => [
                'action' => (string) $row->action,
                'subjectType' => (string) $row->subject_type,
                'subjectId' => (int) $row->subject_id,
                'subjectLabel' => $row->subject_label === null ? null : (string) $row->subject_label,
                'before' => $row->before_value === null ? null : (string) $row->before_value,
                'after' => $row->after_value === null ? null : (string) $row->after_value,
                'actor' => $row->actor_user_id === null ? null : (int) $row->actor_user_id,
                'menuId' => $row->menu_id === null ? null : (int) $row->menu_id,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{action:string, subjectType:string, subjectId:int, subjectLabel:?string, before:?string, after:?string, actor:?int, menuId:?int}>  $trail
     * @return list<array{action:string, subjectType:string, subjectId:int, subjectLabel:?string, before:?string, after:?string, actor:?int, menuId:?int}>
     */
    private function only(array $trail, string $action): array
    {
        return array_values(array_filter($trail, static fn (array $row): bool => $row['action'] === $action));
    }

    // --- MENU-AUDIT-PRICE-01 / MENU-AUDIT-BEFORE-02 -------------------------

    /**
     * "Dün kebabın fiyatını kim değiştirdi?" — paketin var oluş sebebi.
     *
     * Sonrası tek başına yetmez: sahip 380'den 420'ye mi çıkıldığını sorar.
     */
    public function test_a_price_change_records_who_changed_it_and_what_the_price_was_before(): void
    {
        $r = $this->restaurant('audit-price');
        $categoryId = $this->addCategory($r, 'Kebaplar');
        $itemId = $this->addItem($r, $categoryId, 'Adana Kebap', '380.00');

        $this->actingAs($r['owner'])->putJson(
            "/api/workspaces/{$r['workspace']}/menu-items/{$itemId}/price",
            ['price' => '420.00', 'currency' => 'TRY'],
        )->assertOk();

        $priceRows = $this->only($this->trail($r['workspace']), 'item_price_changed');

        self::assertCount(1, $priceRows, 'MENU-AUDIT-PRICE-01: fiyat değişimi ize yazılmalı.');
        self::assertSame('menu_item', $priceRows[0]['subjectType']);
        self::assertSame($itemId, $priceRows[0]['subjectId']);
        self::assertSame('Adana Kebap', $priceRows[0]['subjectLabel'], 'MENU-AUDIT-PRICE-01: satır hangi ürün olduğunu söylemeli.');
        self::assertSame('380.00 TRY', $priceRows[0]['before'], 'MENU-AUDIT-BEFORE-02: öncesi olmadan fiyat kaydı işe yaramaz.');
        self::assertSame('420.00 TRY', $priceRows[0]['after']);
        self::assertSame((int) $r['owner']->getKey(), $priceRows[0]['actor'], 'MENU-AUDIT-PRICE-01: "kim" kayıtta olmalı.');
        self::assertSame($r['menu'], $priceRows[0]['menuId']);

        $at = DB::table('menu_audits')->where('action', 'item_price_changed')->value('created_at');
        self::assertNotNull($at, 'MENU-AUDIT-PRICE-01: "ne zaman" kayıtta olmalı.');
    }

    /**
     * Görünürlük ve alerjen de öncesi/sonrasıyla yazılır.
     *
     * Alerjen YASAL SORUMLULUKTUR: "fındık" işareti kaldırıldığında bunun
     * kim tarafından ve ne zaman yapıldığı sorulacak sorudur.
     */
    public function test_visibility_and_allergen_changes_carry_both_sides(): void
    {
        $r = $this->restaurant('audit-visibility');
        $categoryId = $this->addCategory($r, 'Tatlılar');
        $itemId = $this->addItem($r, $categoryId, 'Fıstıklı Baklava', '160.00', ['gluten', 'fındık']);

        // Yeni ürün GÖRÜNÜR doğar (`docs/74`); değişim onu gizlemektir.
        $this->actingAs($r['owner'])->putJson(
            "/api/workspaces/{$r['workspace']}/menu-items/{$itemId}/visibility",
            ['isVisible' => false],
        )->assertOk();

        $this->actingAs($r['owner'])->putJson(
            "/api/workspaces/{$r['workspace']}/menu-items/{$itemId}/allergens",
            ['allergens' => ['gluten']],
        )->assertOk();

        $trail = $this->trail($r['workspace']);

        $visibility = $this->only($trail, 'item_visibility_changed');
        self::assertCount(1, $visibility);
        self::assertSame('visible', $visibility[0]['before']);
        self::assertSame('hidden', $visibility[0]['after']);

        $allergens = $this->only($trail, 'item_allergens_changed');
        self::assertCount(1, $allergens, 'MENU-AUDIT-BEFORE-02: alerjen değişimi ize yazılmalı.');
        self::assertSame('fındık, gluten', $allergens[0]['before'], 'Öncesi ALFABETİK yazılır; sıra bir değişiklik sanılmamalı.');
        self::assertSame('gluten', $allergens[0]['after']);
    }

    /**
     * Aynı fiyat yeniden gönderildiğinde İZ BÜYÜMEZ.
     *
     * Arayüz bir formu iki kez kaydedebilir. Değişmeyen bir değeri
     * "değişti" diye yazmak, izin kendisini gürültüye çevirir.
     */
    public function test_writing_the_same_price_again_does_not_grow_the_trail(): void
    {
        $r = $this->restaurant('audit-noop');
        $categoryId = $this->addCategory($r, 'Kebaplar');
        $itemId = $this->addItem($r, $categoryId, 'Urfa Kebap', '380.00');

        $this->actingAs($r['owner'])->putJson(
            "/api/workspaces/{$r['workspace']}/menu-items/{$itemId}/price",
            ['price' => '380.00', 'currency' => 'TRY'],
        )->assertOk();

        self::assertSame([], $this->only($this->trail($r['workspace']), 'item_price_changed'));
    }

    // --- MENU-AUDIT-SURVIVES-DELETE-03 --------------------------------------

    /**
     * Silinen ürünün ADI kayıtta kalır.
     *
     * Kayıt varlığa yabancı anahtarla bağlansaydı, sahibin en çok sorduğu
     * an — ürünün artık orada olmadığı an — cevapsız kalırdı.
     */
    public function test_the_trail_survives_the_item_and_keeps_its_name(): void
    {
        $r = $this->restaurant('audit-delete-item');
        $categoryId = $this->addCategory($r, 'Çorbalar');
        $itemId = $this->addItem($r, $categoryId, 'Mercimek Çorbası', '90.00');

        $this->actingAs($r['owner'])
            ->deleteJson("/api/workspaces/{$r['workspace']}/menu-items/{$itemId}")
            ->assertOk();

        self::assertSame(0, DB::table('menu_items')->where('id', $itemId)->count());

        $removed = $this->only($this->trail($r['workspace']), 'item_removed');

        self::assertCount(1, $removed, 'MENU-AUDIT-SURVIVES-DELETE-03: silme ize yazılmalı.');
        self::assertSame($itemId, $removed[0]['subjectId']);
        self::assertSame('Mercimek Çorbası', $removed[0]['subjectLabel']);
        self::assertSame('90.00 TRY', $removed[0]['before'], 'Silinen satırın fiyatı da kayıtta kalmalı.');
        self::assertNull($removed[0]['after']);
    }

    /**
     * Menünün ve kategorinin yaşam döngüsü de izlidir.
     *
     * Kategori silmek İÇİNDEKİ HER SATIRI götürür; bu, tek bir tıkla
     * yapılan en yıkıcı menü işlemidir ve failsiz bırakılamaz.
     */
    public function test_menu_and_category_lifecycle_is_recorded(): void
    {
        $r = $this->restaurant('audit-lifecycle');
        $categoryId = $this->addCategory($r, 'Yaz Menüsü');
        $this->addItem($r, $categoryId, 'Limonata', '60.00');

        $this->actingAs($r['owner'])->putJson(
            "/api/workspaces/{$r['workspace']}/menu-categories/{$categoryId}",
            ['name' => 'Yaz Serinlikleri'],
        )->assertOk();

        $this->actingAs($r['owner'])
            ->deleteJson("/api/workspaces/{$r['workspace']}/menu-categories/{$categoryId}")
            ->assertOk();

        $this->actingAs($r['owner'])->putJson(
            "/api/workspaces/{$r['workspace']}/menu/{$r['menu']}",
            ['name' => 'Kış Menüsü'],
        )->assertOk();

        $actions = array_column($this->trail($r['workspace']), 'action');

        self::assertSame([
            'menu_created',
            'category_added',
            'item_added',
            'category_renamed',
            'category_removed',
            'menu_renamed',
        ], $actions);

        $renamed = $this->only($this->trail($r['workspace']), 'category_renamed');
        self::assertSame('Yaz Menüsü', $renamed[0]['before']);
        self::assertSame('Yaz Serinlikleri', $renamed[0]['after']);
    }

    /** Menü silindiğinde de fail ve ad kayıtta kalır. */
    public function test_deleting_a_menu_is_recorded_with_its_name(): void
    {
        $r = $this->restaurant('audit-delete-menu');

        // Şubenin SON menüsü silinemez; silmeyi denemeden önce ikinci bir
        // menü açılır (`DeleteMenuController`).
        $second = (int) $this->actingAs($r['owner'])->postJson(
            "/api/workspaces/{$r['workspace']}/brand/locations/{$r['location']}/menu",
            ['name' => 'Ramazan Menüsü'],
        )->assertStatus(201)->json('id');

        $this->actingAs($r['owner'])
            ->deleteJson("/api/workspaces/{$r['workspace']}/menu/{$second}")
            ->assertOk();

        $deleted = $this->only($this->trail($r['workspace']), 'menu_deleted');

        self::assertCount(1, $deleted);
        self::assertSame($second, $deleted[0]['subjectId']);
        self::assertSame('Ramazan Menüsü', $deleted[0]['subjectLabel']);
        self::assertSame('Ramazan Menüsü', $deleted[0]['before']);
    }

    // --- MENU-AUDIT-NO-NOISE-06 ---------------------------------------------

    /**
     * SIRALAMA ve "bugün bitti" İZE YAZILMAZ.
     *
     * Ölçüt "sahip bunu sorar mı?" idi. Sıralama misafire verilmiş bir söz
     * değildir ve menüyü düzenlerken onlarca kez değişir; "bugün bitti"
     * servis sırasında mutfağın attığı ve ertesi gün kendiliğinden silinen
     * bir tebeşir notudur. İkisini de yazmak, fiyat sorusunu gürültünün
     * altına gömerdi.
     */
    public function test_reordering_and_out_of_stock_are_deliberately_not_recorded(): void
    {
        $r = $this->restaurant('audit-noise');
        $categoryId = $this->addCategory($r, 'Kebaplar');
        $first = $this->addItem($r, $categoryId, 'Adana', '380.00');
        $second = $this->addItem($r, $categoryId, 'Urfa', '380.00');

        $this->actingAs($r['owner'])->putJson(
            "/api/workspaces/{$r['workspace']}/menu-categories/{$categoryId}/item-order",
            ['menuItemIds' => [$second, $first]],
        )->assertOk();

        $this->actingAs($r['owner'])->putJson(
            "/api/workspaces/{$r['workspace']}/menu-items/{$first}/stock",
            ['outOfStock' => true],
        )->assertOk();

        $actions = array_column($this->trail($r['workspace']), 'action');

        self::assertSame(['menu_created', 'category_added', 'item_added', 'item_added'], $actions);
    }

    // --- MENU-AUDIT-TENANT-04 -----------------------------------------------

    public function test_one_workspace_never_sees_another_workspaces_menu_trail(): void
    {
        $mine = $this->restaurant('audit-tenant-mine');
        $theirs = $this->restaurant('audit-tenant-theirs');

        $this->addCategory($mine, 'Kebaplar');
        $this->addCategory($theirs, 'Pizzalar');
        $this->addCategory($theirs, 'Makarnalar');

        self::assertCount(2, $this->trail($mine['workspace']), 'MENU-AUDIT-TENANT-04: iz kiracı sınırında kalmalı.');
        self::assertCount(3, $this->trail($theirs['workspace']));
    }

    // --- MENU-AUDIT-APPEND-ONLY-05 ------------------------------------------

    /**
     * Düzeltilebilen bir denetim izi, denetim izi değildir: satırın
     * `updated_at` sütunu YOKTUR.
     */
    public function test_the_trail_has_no_update_column(): void
    {
        self::assertTrue(Schema::hasColumn('menu_audits', 'created_at'));
        self::assertFalse(
            Schema::hasColumn('menu_audits', 'updated_at'),
            'MENU-AUDIT-APPEND-ONLY-05: iz düzeltilemez olmalı.'
        );
    }

    // --- MENU-AUDIT-NONBLOCKING-07 ------------------------------------------

    /**
     * İZ YAZILAMADI DİYE SAHİBİN FİYAT DEĞİŞİKLİĞİ GERİ ALINMAZ.
     *
     * Denetim izi asıl işin yardımcısıdır, şartı değil. Tabloyu düşürüp
     * yazmayı imkânsız kılıyoruz: fiyat yine de değişmeli ve istek 200
     * dönmeli. (PostgreSQL'de başarısız bir INSERT içinde bulunduğu işlemin
     * TAMAMINI zehirler; yazıcının kendi savepoint'i olmasaydı bu test
     * SQLite'ta geçer, PG'de düşerdi.)
     */
    public function test_a_failing_audit_write_never_undoes_the_owners_change(): void
    {
        $r = $this->restaurant('audit-nonblocking');
        $categoryId = $this->addCategory($r, 'Kebaplar');
        $itemId = $this->addItem($r, $categoryId, 'Adana Kebap', '380.00');

        Schema::drop('menu_audits');

        $this->actingAs($r['owner'])->putJson(
            "/api/workspaces/{$r['workspace']}/menu-items/{$itemId}/price",
            ['price' => '420.00', 'currency' => 'TRY'],
        )->assertOk();

        self::assertSame(
            42000,
            (int) DB::table('menu_items')->where('id', $itemId)->value('price_minor_amount'),
            'MENU-AUDIT-NONBLOCKING-07: iz yazılamasa da fiyat değişmeli.'
        );
    }

    // --- MENU-AUDIT-BULK-08 -------------------------------------------------

    /**
     * CSV aktarımı TEK bir özet satırı yazar.
     *
     * Aktarım, menünün her fiyatını tek dosyayla değiştirebilen yoldur;
     * izsiz bırakılırsa paketin cevapladığı soru bir kaçış yolu bulur.
     * Satır başına kayıt ise 60 kalemlik bir menüde izi tek başına
     * doldururdu — bu yüzden özet.
     */
    public function test_a_csv_import_is_recorded_as_a_single_summary_row(): void
    {
        $r = $this->restaurant('audit-import');

        $csv = "category,product,price,currency,allergens,description,visible\n"
            ."Kebaplar,Adana,380.00,TRY,,,yes\n"
            .'Kebaplar,Urfa,380.00,TRY,,,yes';

        $this->actingAs($r['owner'])->post(
            "/api/workspaces/{$r['workspace']}/menu/{$r['menu']}/import",
            ['file' => UploadedFile::fake()->createWithContent('menu.csv', $csv)],
        )->assertStatus(200);

        $imported = $this->only($this->trail($r['workspace']), 'menu_imported');

        self::assertCount(1, $imported, 'MENU-AUDIT-BULK-08: aktarım tek satırla izlenmeli.');
        self::assertSame('menu', $imported[0]['subjectType']);
        self::assertSame($r['menu'], $imported[0]['subjectId']);
        self::assertSame('1 kategori · 2 ürün', $imported[0]['after']);
        self::assertSame((int) $r['owner']->getKey(), $imported[0]['actor']);
    }
}
