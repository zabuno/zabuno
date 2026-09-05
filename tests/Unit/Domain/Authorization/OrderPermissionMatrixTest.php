<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Authorization;

use App\Domain\Authorization\Permission;
use App\Domain\Authorization\RolePermissions;
use App\Domain\Tenancy\MembershipRole;
use PHPUnit\Framework\TestCase;

/**
 * SİPARİŞİN İZİN EKSENİ — `docs/115` §4 (FF-179).
 *
 * Plan tek cümleyle donduruyor: "Yeni bir izin ekseni gerekiyor; yeni bir rol
 * GEREKMİYOR." Bu test o matrisin kendisidir ve bilerek KATIDIR — `assertSame`
 * ile tam küme sınanır, `assertContains` ile değil. Sebep: bu matriste asıl
 * tehlike eksik izin değil, FAZLA izindir. `assertContains` bir gün Editor'a
 * sessizce eklenen `order.confirm`'ü görmezdi.
 *
 * İki satır, ürünün iki insani kararıdır:
 *
 * - **Mutfak ONAYLAYAMAZ.** Onay bir servis kararıdır: masada kimin
 *   oturduğunu gören kişi verir. Aşçı onaylayabilseydi, dışarıdan karekodu
 *   okutan birinin talebi mutfağa doğrudan iş açardı.
 * - **Editör siparişi HİÇ GÖRMEZ.** Editör içerik düzenler; servis anının
 *   işi değildir ve bir sipariş listesi ona masadaki misafirin ne yediğini
 *   gösterirdi.
 */
final class OrderPermissionMatrixTest extends TestCase
{
    /**
     * @return list<Permission>
     */
    private function orderPermissionsOf(MembershipRole $role): array
    {
        return array_values(array_filter(
            RolePermissions::for($role),
            static fn (Permission $permission): bool => str_starts_with($permission->value, 'order.'),
        ));
    }

    public function test_owner_holds_every_order_permission_including_the_settings_switch(): void
    {
        self::assertSame(
            [
                Permission::OrderView,
                Permission::OrderConfirm,
                Permission::OrderKitchen,
                Permission::OrderSettings,
            ],
            $this->orderPermissionsOf(MembershipRole::Owner),
        );
    }

    public function test_manager_runs_the_service_but_does_not_own_the_ordering_switch(): void
    {
        // Sipariş almayı açmak/kapatmak bir işletme kararıdır (`docs/115` Y1)
        // ve tarifede yalnız Sahip'te. Yöneticiye vermek, akşam servisinde
        // kapatılan bir hizmetin sahibinden habersiz açılabilmesi demekti.
        self::assertSame(
            [
                Permission::OrderView,
                Permission::OrderConfirm,
                Permission::OrderKitchen,
            ],
            $this->orderPermissionsOf(MembershipRole::Manager),
        );
    }

    public function test_kitchen_sees_and_advances_orders_but_cannot_confirm_them(): void
    {
        self::assertSame(
            [
                Permission::OrderView,
                Permission::OrderKitchen,
            ],
            $this->orderPermissionsOf(MembershipRole::Kitchen),
        );
    }

    public function test_editor_and_member_see_no_orders_at_all(): void
    {
        self::assertSame([], $this->orderPermissionsOf(MembershipRole::Editor));
        self::assertSame([], $this->orderPermissionsOf(MembershipRole::Member));
    }

    public function test_the_kitchen_role_keeps_everything_it_already_had(): void
    {
        /*
            Yeni bir eksen eklerken en sessiz hata, var olan bir yeteneği
            düşürmektir. Mutfak rolü dünkü üründe alerjen düzeltip "bugün
            bitti" işaretleyebiliyordu; sipariş izinleri eklenirken o dört
            satır yerinde kalmalı.
        */
        $permissions = RolePermissions::for(MembershipRole::Kitchen);

        foreach ([
            Permission::WorkspaceView,
            Permission::MenuView,
            Permission::MenuAllergensManage,
            Permission::MenuStockManage,
        ] as $kept) {
            self::assertContains($kept, $permissions);
        }
    }
}
