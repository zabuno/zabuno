<?php

declare(strict_types=1);

namespace App\Application\Tenancy\UseCase;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Tenancy\Dto\WorkspaceSummary;
use App\Application\Tenancy\Port\FeatureFlagPort;
use App\Domain\Authorization\Permission;
use App\Domain\Authorization\RolePermissions;
use App\Domain\Tenancy\MembershipRole;

/**
 * `GET/PUT /workspace-context` gövdesi: çalışma alanı + kullanıcının o
 * alandaki izinleri + bayraklar (`docs/98` FF-74).
 *
 * Kullanıcı yolculuğu: Editor Ayşe kabuğu açar → kenar çubuğunda "Team"
 * yok, "Create → Location" yok; çünkü sunucu "yapamaz" dedi, ekran onu
 * hiç çizmedi. Eskiden çiziyor, tıklayınca 403 veriyordu.
 */
final class BuildWorkspaceContextPayload
{
    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly FeatureFlagPort $features,
    ) {}

    /** @return array<string, mixed> */
    public function for(int $userId, WorkspaceSummary $summary): array
    {
        $permissions = array_map(
            static fn (Permission $permission): string => $permission->value,
            $this->authorization->permissionsFor($userId, $summary->id),
        );

        return $summary->toArray() + [
            'role' => $this->roleFor($permissions),
            'permissions' => array_values($permissions),
            'features' => $this->features->flagsFor($summary->id),
        ];
    }

    /**
     * Rol, izin kümesinden geri okunur: en geniş eşleşen rol. Ekran rol
     * adını yalnız GÖSTERİR; karar her zaman izin listesinden verilir.
     *
     * @param  list<string>  $permissions
     */
    private function roleFor(array $permissions): ?string
    {
        foreach ([MembershipRole::Owner, MembershipRole::Manager, MembershipRole::Editor, MembershipRole::Member] as $role) {
            $expected = array_map(static fn (Permission $p): string => $p->value, RolePermissions::for($role));
            sort($expected);
            $given = $permissions;
            sort($given);

            if ($expected === $given) {
                return $role->value;
            }
        }

        return null;
    }
}
