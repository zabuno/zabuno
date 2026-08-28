<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

use App\Domain\Tenancy\MembershipRole;

final class RolePermissions
{
    /**
     * Rol → izin listesi.
     *
     * Sınırlar planın tarif ettiği gibi (`docs/70`):
     *
     * - **Owner** her şeyi yapar.
     * - **Manager** menü, şube ve karekod yönetir; faturayı GÖRÜR ama
     *   yönetmez.
     * - **Editor** içerik düzenler; YAYINLAMAZ, şube/marka ayarlarına
     *   dokunmaz, faturayı hiç görmez.
     * - **Member** salt okunurdur ve yalnız eski kayıtlar için vardır.
     *
     * Yayınlama iznini `Editor`'dan ayırmak kasıtlıdır: içerik düzenlemek
     * geri alınabilir bir iştir, yayınlamak ise misafirin gördüğü menüyü
     * değiştirir. İkisini aynı role vermek, en kolay yetkiyi en geniş
     * sonuçla eşleştirmek olurdu.
     *
     * @return list<Permission>
     */
    public static function for(MembershipRole $role): array
    {
        return match ($role) {
            MembershipRole::Owner => [
                Permission::WorkspaceView,
                Permission::WorkspaceManage,
                Permission::MenuView,
                Permission::MenuManage,
                Permission::MenuPublish,
                Permission::QrView,
                Permission::QrCreate,
                Permission::QrDisable,
                Permission::QrDesignManage,
                Permission::AnalyticsView,
                Permission::BillingView,
                Permission::BillingManage,
                Permission::SecurityEvidenceView,
            ],
            MembershipRole::Manager => [
                Permission::WorkspaceView,
                Permission::WorkspaceManage,
                Permission::MenuView,
                Permission::MenuManage,
                Permission::MenuPublish,
                Permission::QrView,
                Permission::QrCreate,
                Permission::QrDisable,
                Permission::QrDesignManage,
                Permission::AnalyticsView,
                // Faturayı görebilir — planın yasağı YÖNETMEKle ilgili.
                // Görmeyi de engellemek, planın söylemediği bir kısıt eklemek
                // olurdu.
                Permission::BillingView,
            ],
            MembershipRole::Editor => [
                Permission::WorkspaceView,
                Permission::MenuView,
                // Adının söylediği şey: içerik düzenler.
                Permission::MenuManage,
                Permission::QrView,
                Permission::AnalyticsView,
            ],
            MembershipRole::Member => [
                Permission::WorkspaceView,
                Permission::MenuView,
                Permission::QrView,
                Permission::AnalyticsView,
            ],
        };
    }
}
