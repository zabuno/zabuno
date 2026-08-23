<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

enum MembershipRole: string
{
    case Owner = 'owner';
    case Member = 'member';

    /**
     * Canonical UI name for the Member permission set (docs/02). Mapped
     * conservatively to the exact current Member permission list in
     * RolePermissions — Editor stays denied WorkspaceManage and carries no
     * privilege beyond Member.
     */
    case Editor = 'editor';
}
