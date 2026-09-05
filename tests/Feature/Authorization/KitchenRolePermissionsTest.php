<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Domain\Authorization\Permission;
use App\Domain\Authorization\RolePermissions;
use App\Domain\Tenancy\MembershipRole;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * MUTFAK ROLÜ — kanonik kaynak `docs/reference/panel-v3/panel.dc.html`
 * (`data-screen-label="Takım"`), cümlesi `docs/109` §6.4:
 *
 *   "Mutfak — Alerjen ve 'bugün bitti'. Başka bir şey görmez."
 *
 * NEDEN KIRMIZI: `MembershipRole` böyle bir değer tanımıyor
 * (`owner|manager|editor|member`) ve `Permission` alerjen/stok için DAR bir
 * izin tanımıyor — bugün ikisi de geniş `menu.manage` iznine bağlı. Yani
 * "alerjeni düzeltebilsin ama fiyata dokunamasın" cümlesi bu depoda
 * SÖYLENEMİYOR: `menu.manage` veren, aynı anda fiyatı, ürün eklemeyi ve
 * silmeyi de vermiş oluyor.
 *
 * MÜŞTERİ SORUNU. Akşam servisinde balık bitti ve bir misafir fıstık
 * alerjisini soruyor. Bu iki gerçeği bilen tek kişi mutfaktadır. Bugün
 * sahibin iki seçeneği var: ya kendi telefonundan girip işaretleyecek (yani
 * servisin ortasında mutfağın işini yapacak), ya da aşçıya `editor` verip
 * bütün menünün fiyatlarını da açacak. İkisi de yanlış.
 *
 * DAR İZİN ESKİ ROLLERDEN ALINMAZ. Owner/Manager/Editor bugün alerjen ve
 * "bugün bitti" işaretleyebiliyor; yeni izinler onlara da verilir. Aksi
 * hâlde bu paket, bir rolü eklerken üç rolden bir yetenek çalardı.
 *
 * Requirement IDs: KITCHEN-ROLE-EXISTS-01, KITCHEN-NARROW-PERMS-01,
 * KITCHEN-SEES-NOTHING-ELSE-01, KITCHEN-NO-REGRESSION-01,
 * KITCHEN-INVITABLE-01, KITCHEN-OWNERSHIP-NOT-INVITED-01.
 */
final class KitchenRolePermissionsTest extends TestCase
{
    // --- KITCHEN-ROLE-EXISTS-01 ------------------------------------------

    public function test_kitchen_is_a_real_membership_role(): void
    {
        // Değer kaynağın kendi anahtarıdır (`panel.dc.html`:
        // `<option value="kitchen">Mutfak</option>`). Yeni bir ad uydurmak,
        // aynı rolün iki adı olması demekti.
        $this->assertSame('kitchen', MembershipRole::Kitchen->value);
    }

    // --- KITCHEN-NARROW-PERMS-01 / KITCHEN-SEES-NOTHING-ELSE-01 ----------

    public function test_kitchen_can_only_touch_allergens_and_sold_out(): void
    {
        $granted = RolePermissions::for(MembershipRole::Kitchen);

        /*
            TAM EŞİTLİK, "içerir" DEĞİL.

            "İçerir" biçiminde bir sınama, bir gün fazladan verilen bir izni
            hiç fark etmezdi — ve bu rolün TEK sözü zaten "başka bir şey
            görmez". Fazlalık burada bir hatadır, ayrıntı değil.
        */
        $this->assertEqualsCanonicalizing([
            // Çalışma alanına girebilmeli: kabuk, menü uçları ve kiracı
            // sınırı bu izne dayanıyor. Bu olmadan aşçı hiç oturum açamaz.
            Permission::WorkspaceView,
            // Menüyü GÖRMELİ: işaretleyeceği ürünü bulmasının başka yolu yok.
            Permission::MenuView,
            Permission::MenuAllergensManage,
            Permission::MenuStockManage,
        ], $granted);
    }

    /**
     * @return list<array{0:Permission}>
     */
    public static function forbiddenForKitchen(): array
    {
        return [
            'fiyat/ürün/kategori — geniş menü yönetimi' => [Permission::MenuManage],
            'yayınlama' => [Permission::MenuPublish],
            'karekod' => [Permission::QrView],
            'ölçüm' => [Permission::AnalyticsView],
            'fatura' => [Permission::BillingView],
            'takım ve marka ayarları' => [Permission::WorkspaceManage],
            'medya yükleme' => [Permission::MediaManage],
            'aslını indirme' => [Permission::MediaDownloadOriginal],
        ];
    }

    #[DataProvider('forbiddenForKitchen')]
    public function test_kitchen_never_holds_a_wider_permission(Permission $permission): void
    {
        $this->assertNotContains(
            $permission,
            RolePermissions::for(MembershipRole::Kitchen),
            "Mutfak {$permission->value} iznini taşımamalı — kaynağın cümlesi \"başka bir şey görmez\"."
        );
    }

    // --- KITCHEN-NO-REGRESSION-01 ----------------------------------------

    /**
     * @return list<array{0:MembershipRole}>
     */
    public static function rolesThatAlreadyRanTheMenu(): array
    {
        return [
            'sahip' => [MembershipRole::Owner],
            'yönetici' => [MembershipRole::Manager],
            'editör' => [MembershipRole::Editor],
        ];
    }

    #[DataProvider('rolesThatAlreadyRanTheMenu')]
    public function test_existing_menu_roles_keep_allergens_and_sold_out(MembershipRole $role): void
    {
        $granted = RolePermissions::for($role);

        // Bu üç rol dünkü üründe de alerjen düzeltip "bitti" işaretleyebiliyordu
        // (`menu.manage` üzerinden). Dar izinler doğarken onlardan alınamaz.
        $this->assertContains(Permission::MenuAllergensManage, $granted);
        $this->assertContains(Permission::MenuStockManage, $granted);
    }

    public function test_the_legacy_read_only_role_gains_nothing(): void
    {
        $granted = RolePermissions::for(MembershipRole::Member);

        // `member` SALT OKUNURDUR. Yeni bir yazma izni, eski kayıtların
        // taşıdığı bir role sessizce yazma yetkisi vermek olurdu.
        $this->assertNotContains(Permission::MenuAllergensManage, $granted);
        $this->assertNotContains(Permission::MenuStockManage, $granted);
    }

    // --- KITCHEN-INVITABLE-01 / KITCHEN-OWNERSHIP-NOT-INVITED-01 ---------

    public function test_kitchen_can_be_invited_but_ownership_still_cannot(): void
    {
        $invitable = MembershipRole::invitable();

        $this->assertContains(MembershipRole::Kitchen, $invitable);
        // Sahiplik davetle verilmez, DEVREDİLİR — ayrı akış, ayrı sonuç.
        $this->assertNotContains(MembershipRole::Owner, $invitable);
        // Salt okunur eski rol yeni kimseye verilmez.
        $this->assertNotContains(MembershipRole::Member, $invitable);
    }
}
