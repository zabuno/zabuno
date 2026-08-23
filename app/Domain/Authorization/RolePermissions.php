<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

use App\Domain\Tenancy\MembershipRole;

final class RolePermissions
{
    /**
     * @return list<Permission>
     */
    public static function for(MembershipRole $role): array
    {
        return match ($role) {
            MembershipRole::Owner => [Permission::WorkspaceView, Permission::WorkspaceManage, Permission::MenuView, Permission::MenuManage, Permission::MenuPublish, Permission::QrView, Permission::QrCreate, Permission::QrDisable, Permission::QrDesignManage, Permission::AnalyticsView, Permission::BillingView, Permission::BillingManage, Permission::SecurityEvidenceView],
            MembershipRole::Member, MembershipRole::Editor => [Permission::WorkspaceView, Permission::MenuView, Permission::QrView, Permission::AnalyticsView],
        };
    }
}
