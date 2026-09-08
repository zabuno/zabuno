<?php

declare(strict_types=1);

namespace App\Infrastructure\Support\Authorization;

use App\Application\Tenancy\Dto\WorkspaceSummary;
use App\Application\Tenancy\Port\WorkspaceRepositoryPort;
use App\Domain\Tenancy\WorkspaceState;
use App\Models\Workspace;

/**
 * Destek oturumu süresince kiracıyı GÖRÜNÜR kılan sarmalayıcı — `docs/133`.
 *
 * Panel kabuğu çalışma alanını üyelikten okur; üyeliği olmayan bir destek
 * görevlisi için o kapı kapalıdır ve kapalı kalmalıdır. Açılan tek şey,
 * AÇIK bir destek oturumunun işaret ettiği TEK çalışma alanıdır — ve o da
 * yalnız oturum yaşadığı sürece.
 *
 * `createOwnedWorkspace` SARMALANMAZ, aynen geçer: yazma zaten istek
 * düzeyinde kapalıdır ve burada ikinci bir kural yazmak, aynı yasağın iki
 * yerde yaşamasına yol açardı.
 *
 * BAĞLAM DEĞİŞTİRME UCU BU SINIFA RAĞMEN KAPALIDIR (`PUT
 * /api/workspace-context` bir yazma yöntemidir): oturum açılırken bağlam
 * sunucuda kurulur ve destek görevlisi o oturum boyunca başka bir kiracıya
 * geçemez. Bakış tek restorana çivilenir.
 */
final class SupportAccessWorkspaceRepository implements WorkspaceRepositoryPort
{
    public function __construct(
        private readonly WorkspaceRepositoryPort $inner,
        private readonly SupportAccessScope $scope,
    ) {}

    public function createOwnedWorkspace(string $name, int $ownerUserId): WorkspaceSummary
    {
        return $this->inner->createOwnedWorkspace($name, $ownerUserId);
    }

    public function listForUser(int $userId): array
    {
        $own = $this->inner->listForUser($userId);
        $workspaceId = $this->scope->workspaceIdFor($userId);

        if ($workspaceId === null) {
            return $own;
        }

        foreach ($own as $summary) {
            if ($summary->id === $workspaceId) {
                return $own;
            }
        }

        $extra = $this->summaryFor($workspaceId);

        return $extra === null ? $own : [...$own, $extra];
    }

    public function findEligibleForUser(int $workspaceId, int $userId): ?WorkspaceSummary
    {
        $own = $this->inner->findEligibleForUser($workspaceId, $userId);

        if ($own !== null) {
            return $own;
        }

        return $this->scope->workspaceIdFor($userId) === $workspaceId
            ? $this->summaryFor($workspaceId)
            : null;
    }

    private function summaryFor(int $workspaceId): ?WorkspaceSummary
    {
        /*
            DURUM KAPISI ÜYELİKTEKİYLE AYNI: askıya alınmış ya da silinmeye
            ayrılmış bir çalışma alanı sahibi için bağlam olamıyorsa, destek
            görevlisi için de olamaz. Destek oturumu bir kapı açar, kuralları
            değiştirmez.
        */
        $workspace = Workspace::query()
            ->whereKey($workspaceId)
            ->whereIn('state', WorkspaceState::eligibleValues())
            ->first();

        return $workspace === null ? null : new WorkspaceSummary(
            (int) $workspace->getKey(),
            (string) $workspace->name,
            (string) $workspace->slug,
            $workspace->state,
        );
    }
}
