<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization;

use App\Domain\Authorization\Permission;
use App\Domain\Authorization\RolePermissions;
use App\Domain\Tenancy\MembershipRole;
use Tests\TestCase;

/**
 * Blind RED test candidate for CORE-03 RBAC baseline, extended for the
 * S1-WP04a Publication scope (scope synthesis per task instruction: the
 * one-sided menu.publish addition is accepted because modules/publication.md
 * explicitly names it under "Permissions" — Owner gets menu.publish, Member
 * does not), extended for S1-WP04b2 validated PNG QR export (frozen MASTER
 * contract: the canon permission is qr.design.manage, not an invented
 * qr.export — Owner has it, Member has qr.view only and does not have
 * qr.design.manage), and further extended for S1-WP05b1 location-scoped
 * analytics (Permission::AnalyticsView = analytics.view, granted to both
 * Owner and Member for the real analytics summary endpoint), and further
 * extended for S1-WP05 billing authorization (frozen MASTER contract:
 * Permission::BillingView = billing.view, granted to Owner only — Member
 * and Editor never carry billing.view), and further extended for
 * S1-WP01A Iyzico sandbox checkout backend (frozen MASTER contract:
 * Permission::BillingManage = billing.manage, granted to Owner only —
 * Member and Editor never carry billing.manage), and further extended for
 * the security evidence view surface (already-shipped MASTER contract:
 * Permission::SecurityEvidenceView = security.evidence.view, granted to
 * Owner only — Member and Editor never carry security.evidence.view;
 * bounded scope is now thirteen permissions).
 *
 * Proves the domain permission mapping of the implemented production code:
 *   - App\Domain\Authorization\Permission enum with cases
 *     WorkspaceView = 'workspace.view', WorkspaceManage = 'workspace.manage',
 *     MenuView = 'menu.view', MenuManage = 'menu.manage',
 *     MenuPublish = 'menu.publish', QrView = 'qr.view',
 *     QrCreate = 'qr.create', QrDisable = 'qr.disable',
 *     QrDesignManage = 'qr.design.manage', AnalyticsView = 'analytics.view',
 *     BillingView = 'billing.view', BillingManage = 'billing.manage',
 *     SecurityEvidenceView = 'security.evidence.view'.
 *   - App\Domain\Authorization\RolePermissions::for(MembershipRole) returning
 *     the permission set for a role: Owner -> {workspace.view,
 *     workspace.manage, menu.view, menu.manage, menu.publish, qr.view,
 *     qr.create, qr.disable, qr.design.manage, analytics.view,
 *     billing.view, billing.manage, security.evidence.view}, Member ->
 *     {workspace.view, menu.view, qr.view, analytics.view} (no billing.view,
 *     billing.manage or security.evidence.view).
 */
final class RolePermissionMappingTest extends TestCase
{
    // --- CORE03-PERMISSION-ENUM-01 --------------------------------------

    public function test_permission_enum_declares_the_fifteen_frozen_scope_permission_strings(): void
    {
        self::assertSame('workspace.view', Permission::WorkspaceView->value, 'PERMISSION-ENUM-01: workspace.view değeri sabit string sözleşmesiyle eşleşmeli.');
        self::assertSame('workspace.manage', Permission::WorkspaceManage->value, 'PERMISSION-ENUM-01: workspace.manage değeri sabit string sözleşmesiyle eşleşmeli.');
        self::assertSame('menu.view', Permission::MenuView->value, 'PERMISSION-ENUM-01: menu.view değeri sabit string sözleşmesiyle eşleşmeli.');
        self::assertSame('menu.manage', Permission::MenuManage->value, 'PERMISSION-ENUM-01: menu.manage değeri sabit string sözleşmesiyle eşleşmeli.');
        self::assertSame('menu.publish', Permission::MenuPublish->value, 'PERMISSION-ENUM-01: menu.publish değeri sabit string sözleşmesiyle eşleşmeli (modules/publication.md).');
        self::assertSame('qr.view', Permission::QrView->value, 'PERMISSION-ENUM-01: qr.view değeri sabit string sözleşmesiyle eşleşmeli.');
        self::assertSame('qr.create', Permission::QrCreate->value, 'PERMISSION-ENUM-01: qr.create değeri sabit string sözleşmesiyle eşleşmeli.');
        self::assertSame('qr.disable', Permission::QrDisable->value, 'PERMISSION-ENUM-01: qr.disable değeri sabit string sözleşmesiyle eşleşmeli.');
        self::assertSame('qr.design.manage', Permission::QrDesignManage->value, 'PERMISSION-ENUM-01: qr.design.manage değeri sabit string sözleşmesiyle eşleşmeli (S1-WP04b2 frozen MASTER contract, invented qr.export DEĞİL).');
        self::assertSame('analytics.view', Permission::AnalyticsView->value, 'PERMISSION-ENUM-01: analytics.view değeri sabit string sözleşmesiyle eşleşmeli (S1-WP05b1 location-scoped analytics).');
        self::assertSame('billing.view', Permission::BillingView->value, 'PERMISSION-ENUM-01: billing.view değeri sabit string sözleşmesiyle eşleşmeli (S1-WP05 billing authorization, frozen MASTER contract).');
        self::assertSame('billing.manage', Permission::BillingManage->value, 'PERMISSION-ENUM-01: billing.manage değeri sabit string sözleşmesiyle eşleşmeli (S1-WP01A Iyzico sandbox checkout backend, frozen MASTER contract).');
        self::assertSame('security.evidence.view', Permission::SecurityEvidenceView->value, 'PERMISSION-ENUM-01: security.evidence.view değeri sabit string sözleşmesiyle eşleşmeli (owner-only SecurityEvidenceView, already-shipped MASTER contract).');

        $values = array_map(static fn (Permission $permission): string => $permission->value, Permission::cases());

        self::assertSame('media.manage', Permission::MediaManage->value);
        self::assertSame('media.download_original', Permission::MediaDownloadOriginal->value);
        self::assertSame('menu.allergens.manage', Permission::MenuAllergensManage->value);
        self::assertSame('menu.stock.manage', Permission::MenuStockManage->value);
        /*
            FF-71 (docs/49 Faz 7 madde 3): iki medya izni eklendi. 13 → 15.

            Mutfak rolü (`docs/109` §6.4): `menu.manage`'in içinden iki DAR
            eksen çıkarıldı — alerjen ve "bugün bitti". 15 → 17. Yeni bir
            yetki doğmadı, var olan bir yetki bölündü: `menu.manage` taşıyan
            her rol ikisini de taşımaya devam ediyor, ama artık sahip
            "alerjeni düzeltsin, fiyata dokunmasın" diyebiliyor.
        */
        self::assertSame('order.view', Permission::OrderView->value);
        self::assertSame('order.confirm', Permission::OrderConfirm->value);
        self::assertSame('order.kitchen', Permission::OrderKitchen->value);
        self::assertSame('order.settings', Permission::OrderSettings->value);
        /*
            FF-179 (`docs/115` §4): sipariş ekseni. 17 → 21.

            Bu dört izin bir yetki BÖLÜNMESİ değil, yeni bir YÜZEY: sipariş
            akışı bu depoda dün yoktu. Plan tek cümleyle donduruyor — "Yeni
            bir izin ekseni gerekiyor; yeni bir rol GEREKMİYOR" — ve sayı
            burada büyürken rol sayısı sabit kalıyor.

            Sayının test tarafından tutulması bilerek: bu ürünün en sessiz
            kazası, bir gün birinin `order.confirm`'ü kolaylık olsun diye
            Editor'a eklemesidir. O gün bu satır kırılır ve gerekçe yazmak
            zorunda kalınır.
        */
        /*
            21 → 23 (`docs/116` §4, P5/P6): puan ekseni İKİ izin ekliyor —
            `rating.view` (ölçümü görmek) ve `rating.reply` (misafirin
            ölçümüne restoran adına cevap vermek).

            EKSENDE ÜÇÜNCÜ BİR İZİN YOK ve bu sayının kendisi kadar
            anlamlı: `rating.delete` diye bir satır olsaydı, "sahip puanı
            silemez" kuralı bir gün birinin onu bir role eklemesiyle
            sessizce ölürdü. Silmeyi kimseye vermemenin ilk şartı, onu bir
            yetenek olarak adlandırmamaktır.
        */
        self::assertCount(23, $values, 'PERMISSION-ENUM-01: bounded scope tam olarak yirmi üç permission tanımlar (on üç çekirdek + iki medya + menu.allergens.manage + menu.stock.manage + dört order.* + iki rating.* ekseni) — ek permission eklenmemeli.');
        self::assertNotContains('rating.delete', $values, 'PERMISSION-ENUM-01: puanı silme yeteneği ADLANDIRILMAZ; adı olmayan bir yetenek bir role verilemez (`docs/116` §4).');
        self::assertContains('workspace.view', $values);
        self::assertContains('workspace.manage', $values);
        self::assertContains('menu.view', $values);
        self::assertContains('menu.manage', $values);
        self::assertContains('menu.publish', $values);
        self::assertContains('qr.view', $values);
        self::assertContains('qr.create', $values);
        self::assertContains('qr.disable', $values);
        self::assertContains('qr.design.manage', $values);
        self::assertContains('analytics.view', $values);
        self::assertContains('billing.view', $values);
        self::assertContains('billing.manage', $values);
        self::assertContains('security.evidence.view', $values);
    }

    // --- CORE03-ROLE-MAP-OWNER-01 -----------------------------------------

    public function test_owner_role_maps_to_all_fifteen_view_manage_publish_qr_analytics_billing_security_evidence_and_media_permissions(): void
    {
        $permissions = RolePermissions::for(MembershipRole::Owner);

        self::assertContains(Permission::WorkspaceView, $permissions, 'ROLE-MAP-OWNER-01: Owner workspace.view yetkisine sahip olmalı.');
        self::assertContains(Permission::WorkspaceManage, $permissions, 'ROLE-MAP-OWNER-01: Owner workspace.manage yetkisine sahip olmalı.');
        self::assertContains(Permission::MenuView, $permissions, 'ROLE-MAP-OWNER-01: Owner menu.view yetkisine sahip olmalı.');
        self::assertContains(Permission::MenuManage, $permissions, 'ROLE-MAP-OWNER-01: Owner menu.manage yetkisine sahip olmalı.');
        self::assertContains(Permission::MenuPublish, $permissions, 'ROLE-MAP-OWNER-01: Owner menu.publish yetkisine sahip olmalı.');
        self::assertContains(Permission::QrView, $permissions, 'ROLE-MAP-OWNER-01: Owner qr.view yetkisine sahip olmalı.');
        self::assertContains(Permission::QrCreate, $permissions, 'ROLE-MAP-OWNER-01: Owner qr.create yetkisine sahip olmalı.');
        self::assertContains(Permission::QrDisable, $permissions, 'ROLE-MAP-OWNER-01: Owner qr.disable yetkisine sahip olmalı.');
        self::assertContains(Permission::QrDesignManage, $permissions, 'ROLE-MAP-OWNER-01: Owner qr.design.manage yetkisine sahip olmalı (S1-WP04b2 PNG export).');
        self::assertContains(Permission::AnalyticsView, $permissions, 'ROLE-MAP-OWNER-01: Owner analytics.view yetkisine sahip olmalı (S1-WP05b1 location-scoped analytics).');
        self::assertContains(Permission::BillingView, $permissions, 'ROLE-MAP-OWNER-01: Owner billing.view yetkisine sahip olmalı (S1-WP05 billing authorization).');
        self::assertContains(Permission::BillingManage, $permissions, 'ROLE-MAP-OWNER-01: Owner billing.manage yetkisine sahip olmalı (S1-WP01A Iyzico sandbox checkout backend).');
        self::assertContains(Permission::SecurityEvidenceView, $permissions, 'ROLE-MAP-OWNER-01: Owner security.evidence.view yetkisine sahip olmalı (owner-only SecurityEvidenceView, already-shipped MASTER contract).');
        self::assertContains(Permission::MediaManage, $permissions);
        self::assertContains(Permission::MediaDownloadOriginal, $permissions);
        // Mutfak rolüyle gelen iki dar menü izni Owner'da da vardır: bir rol
        // eklenirken sahibin yapabildiği hiçbir şey elinden alınmaz.
        self::assertContains(Permission::MenuAllergensManage, $permissions);
        self::assertContains(Permission::MenuStockManage, $permissions);
        // FF-179 (`docs/115` §4): sipariş ekseninin DÖRDÜ de Sahip'te. Bu
        // rolün tanımı zaten "her şeyi yapar"; `order.settings` ise yalnız
        // burada var — hizmeti açıp kapatmak bir işletme kararıdır.
        self::assertContains(Permission::OrderView, $permissions);
        self::assertContains(Permission::OrderConfirm, $permissions);
        self::assertContains(Permission::OrderKitchen, $permissions);
        self::assertContains(Permission::OrderSettings, $permissions);
        // `docs/116` §4: Sahip puanı GÖRÜR ve YANITLAR; silemez — ve
        // silecek bir izin hiç yoktur.
        self::assertContains(Permission::RatingView, $permissions);
        self::assertContains(Permission::RatingReply, $permissions);
        self::assertCount(23, $permissions, 'ROLE-MAP-OWNER-01: Owner tam olarak yirmi üç permission taşımalı (bounded scope dışında ek yetki yok).');
    }

    // --- CORE03-ROLE-MAP-MEMBER-01 ----------------------------------------

    public function test_member_role_maps_to_view_and_analytics_only_and_not_manage_publish_qr_mutation_or_billing(): void
    {
        $permissions = RolePermissions::for(MembershipRole::Member);

        self::assertContains(Permission::WorkspaceView, $permissions, 'ROLE-MAP-MEMBER-01: Member workspace.view yetkisine sahip olmalı.');
        self::assertNotContains(Permission::WorkspaceManage, $permissions, 'ROLE-MAP-MEMBER-01: Member workspace.manage yetkisine sahip OLMAMALI.');
        self::assertContains(Permission::MenuView, $permissions, 'ROLE-MAP-MEMBER-01: Member menu.view yetkisine sahip olmalı.');
        self::assertNotContains(Permission::MenuManage, $permissions, 'ROLE-MAP-MEMBER-01: Member menu.manage yetkisine sahip OLMAMALI.');
        self::assertNotContains(Permission::MenuPublish, $permissions, 'ROLE-MAP-MEMBER-01: Member menu.publish yetkisine sahip OLMAMALI.');
        self::assertContains(Permission::QrView, $permissions, 'ROLE-MAP-MEMBER-01: Member qr.view yetkisine sahip olmalı (görüntüleme, mutasyon değil).');
        self::assertNotContains(Permission::QrCreate, $permissions, 'ROLE-MAP-MEMBER-01: Member qr.create yetkisine sahip OLMAMALI.');
        self::assertNotContains(Permission::QrDisable, $permissions, 'ROLE-MAP-MEMBER-01: Member qr.disable yetkisine sahip OLMAMALI.');
        self::assertNotContains(Permission::QrDesignManage, $permissions, 'ROLE-MAP-MEMBER-01: Member qr.design.manage yetkisine sahip OLMAMALI (S1-WP04b2 frozen MASTER contract).');
        self::assertContains(Permission::AnalyticsView, $permissions, 'ROLE-MAP-MEMBER-01: Member analytics.view yetkisine sahip olmalı (S1-WP05b1 location-scoped analytics).');
        self::assertNotContains(Permission::BillingView, $permissions, 'ROLE-MAP-MEMBER-01: Member billing.view yetkisine sahip OLMAMALI (S1-WP05 billing authorization, owner-only).');
        self::assertNotContains(Permission::BillingManage, $permissions, 'ROLE-MAP-MEMBER-01: Member billing.manage yetkisine sahip OLMAMALI (S1-WP01A Iyzico sandbox checkout backend, owner-only).');
        // Sahip kararı (FF-71): asıl indirme her role serbest; Member yine yükleyemez.
        self::assertContains(Permission::MediaDownloadOriginal, $permissions);
        self::assertNotContains(Permission::MediaManage, $permissions);
        /*
            Member salt okunurdur ve `rating.view` tam olarak bunun
            kapsamına girer: ölçümü OKUMAK. `rating.reply` girmez — o,
            misafirin gördüğü menüde restoran adına konuşmaktır ve salt
            okunur bir rolün işi değildir.
        */
        self::assertContains(Permission::RatingView, $permissions);
        self::assertNotContains(Permission::RatingReply, $permissions, 'ROLE-MAP-MEMBER-01: salt okunur bir rol restoran adına konuşamaz.');
        self::assertCount(6, $permissions, 'ROLE-MAP-MEMBER-01: Member workspace.view, menu.view, qr.view, analytics.view, media.download_original ve rating.view taşımalı; manage/publish/create/disable/design.manage/billing/security.evidence/media.manage/rating.reply taşımamalı.');
    }

    // --- CORE03-ROLE-MAP-EDITOR-01 -----------------------------------------

    public function test_editor_role_does_not_carry_billing_view(): void
    {
        $permissions = RolePermissions::for(MembershipRole::Editor);

        self::assertNotContains(Permission::BillingView, $permissions, 'ROLE-MAP-EDITOR-01: Editor billing.view yetkisine sahip OLMAMALI (S1-WP05 billing authorization, owner-only).');
        self::assertNotContains(Permission::BillingManage, $permissions, 'ROLE-MAP-EDITOR-01: Editor billing.manage yetkisine sahip OLMAMALI (S1-WP01A Iyzico sandbox checkout backend, owner-only).');
    }

    // --- CORE03-ROLE-MAP-DISTINCT-01 --------------------------------------

    public function test_owner_and_member_permission_sets_are_distinct(): void
    {
        $ownerPermissions = RolePermissions::for(MembershipRole::Owner);
        $memberPermissions = RolePermissions::for(MembershipRole::Member);

        self::assertNotEquals(
            $ownerPermissions,
            $memberPermissions,
            'ROLE-MAP-DISTINCT-01: Owner ve Member permission kümeleri özdeş olmamalı (Member manage taşımaz).'
        );
    }
}
