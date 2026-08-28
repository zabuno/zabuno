<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization;

use App\Domain\Authorization\Permission;
use App\Domain\Authorization\RolePermissions;
use App\Domain\Tenancy\MembershipRole;
use PHPUnit\Framework\TestCase;

/**
 * Rol sınırları — `docs/70`.
 *
 * Bu sınırların hiçbiri testle donmuş DEĞİLDİ: `Editor` rolü uzun süre
 * `Member` ile aynı izinleri taşıyordu, yani "editör" olarak davet edilen
 * kişi hiçbir şeyi düzenleyemiyordu — ve tam takım koşusu bunu fark etmiyordu.
 *
 * Bir iznin YOKLUĞU en az varlığı kadar önemlidir ve burada ikisi de
 * donduruluyor.
 */
final class RoleBoundariesTest extends TestCase
{
    /** @return list<string> */
    private function permissions(MembershipRole $role): array
    {
        return array_map(
            static fn (Permission $permission): string => $permission->value,
            RolePermissions::for($role),
        );
    }

    /** Adının söylediği şey: içerik düzenler. */
    public function test_an_editor_can_actually_edit_content(): void
    {
        self::assertContains(Permission::MenuManage->value, $this->permissions(MembershipRole::Editor));
    }

    /**
     * İçerik düzenlemek geri alınabilir bir iştir; yayınlamak misafirin
     * gördüğü menüyü değiştirir. İkisini aynı role vermek, en kolay yetkiyi
     * en geniş sonuçla eşleştirmek olurdu.
     */
    public function test_an_editor_cannot_publish(): void
    {
        self::assertNotContains(Permission::MenuPublish->value, $this->permissions(MembershipRole::Editor));
    }

    public function test_an_editor_cannot_manage_the_workspace_or_see_billing(): void
    {
        $editor = $this->permissions(MembershipRole::Editor);

        self::assertNotContains(Permission::WorkspaceManage->value, $editor);
        self::assertNotContains(Permission::BillingView->value, $editor);
        self::assertNotContains(Permission::BillingManage->value, $editor);
    }

    /** Manager günlük operasyonu yürütür: menü, şube, karekod. */
    public function test_a_manager_runs_daily_operations(): void
    {
        $manager = $this->permissions(MembershipRole::Manager);

        foreach ([
            Permission::WorkspaceManage,
            Permission::MenuManage,
            Permission::MenuPublish,
            Permission::QrCreate,
            Permission::AnalyticsView,
        ] as $permission) {
            self::assertContains($permission->value, $manager);
        }
    }

    /** Planın tek yasağı: faturayı YÖNETEMEZ. */
    public function test_a_manager_cannot_manage_billing(): void
    {
        self::assertNotContains(Permission::BillingManage->value, $this->permissions(MembershipRole::Manager));
    }

    /**
     * Güvenlik kanıtı yalnız sahibindir: kiracı ekibinin işi değil, ve
     * yanlışlıkla dağıtılırsa geri alınması zor bir görünürlüktür.
     */
    public function test_only_the_owner_sees_security_evidence(): void
    {
        foreach ([MembershipRole::Manager, MembershipRole::Editor, MembershipRole::Member] as $role) {
            self::assertNotContains(Permission::SecurityEvidenceView->value, $this->permissions($role));
        }

        self::assertContains(Permission::SecurityEvidenceView->value, $this->permissions(MembershipRole::Owner));
    }

    /** Eski salt okunur rol OLDUĞU GİBİ kalır; genişletmek sessiz bir yetki artışı olurdu. */
    public function test_the_legacy_member_role_stays_read_only(): void
    {
        $member = $this->permissions(MembershipRole::Member);

        self::assertNotContains(Permission::MenuManage->value, $member);
        self::assertNotContains(Permission::WorkspaceManage->value, $member);
    }

    /**
     * Sahiplik DAVETLE verilmez, devredilir — ayrı bir akışı ve ayrı bir
     * sonucu vardır.
     */
    public function test_ownership_is_never_invitable(): void
    {
        self::assertNotContains(MembershipRole::Owner, MembershipRole::invitable());
        self::assertNotContains(MembershipRole::Member, MembershipRole::invitable());
        self::assertSame(
            [MembershipRole::Editor, MembershipRole::Manager],
            MembershipRole::invitable(),
        );
    }
}
