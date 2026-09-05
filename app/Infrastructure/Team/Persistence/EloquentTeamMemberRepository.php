<?php

declare(strict_types=1);

namespace App\Infrastructure\Team\Persistence;

use App\Application\Team\Dto\TeamMemberSummary;
use App\Application\Team\Port\TeamMemberRepositoryPort;
use App\Domain\Tenancy\MembershipRole;
use Illuminate\Support\Facades\DB;

final class EloquentTeamMemberRepository implements TeamMemberRepositoryPort
{
    /**
     * @return list<TeamMemberSummary>
     */
    public function listByWorkspaceId(int $workspaceId): array
    {
        return DB::table('workspace_memberships')
            ->join('users', 'users.id', '=', 'workspace_memberships.user_id')
            ->where('workspace_memberships.workspace_id', $workspaceId)
            ->orderBy('workspace_memberships.id')
            ->select([
                'workspace_memberships.id as id',
                'users.name as name',
                'users.email as email',
                'workspace_memberships.role as role',
            ])
            ->get()
            ->map(fn (object $row): TeamMemberSummary => new TeamMemberSummary(
                (int) $row->id,
                (string) $row->name,
                (string) $row->email,
                (string) $row->role,
            ))
            ->all();
    }

    public function removeMember(int $workspaceId, int $membershipId): bool
    {
        return DB::table('workspace_memberships')
            ->where('id', $membershipId)
            ->where('workspace_id', $workspaceId)
            /*
                KALDIRILABİLİR KÜME KENDİ ADIYLA DURUR.

                Burada bir zamanlar sabit bir `role = 'editor'` vardı. O
                satır yazıldığında davet edilebilen tek rol Editör'dü;
                sonra Yönetici ve Mutfak doğdu, ama silme koşulu onlarla
                birlikte büyümedi. Sonuç sessiz bir yalandı: sahip "Çıkar"
                diyordu, sorgu sıfır satır siliyordu ve kimse duymuyordu.

                İlk onarım koşulu `MembershipRole::invitable()`'a bağladı ve
                o üç rolü kurtardı — ama yanlış soruyu ödünç aldı. Davet
                listesi "kimi yeni alabilirim"i anlatır; buradaki soru "kimi
                çıkarabilirim". İkisi eşitlenince eski `member` rolündeki
                kişiler kimsenin kastetmediği bir şekilde ekipte MAHSUR
                kaldı: davet edilemedikleri için çıkarılamaz da olmuşlardı.

                Bu yüzden koşul artık kendi kümesini okuyor:
                `MembershipRole::removable()`. Sınır orada tek cümleyle
                yazılı — `owner` silinmez, DEVREDİLİR
                (`transferOwnership`); silinseydi çalışma alanı sahipsiz
                kalır ve kimse onaramazdı.
            */
            ->whereIn('role', array_map(
                static fn (MembershipRole $role): string => $role->value,
                MembershipRole::removable(),
            ))
            ->delete() > 0;
    }

    public function transferOwnership(int $workspaceId, int $requesterUserId, int $targetMembershipId): bool
    {
        try {
            return $this->transferOwnershipInTransaction($workspaceId, $requesterUserId, $targetMembershipId);
        } catch (\RuntimeException) {
            return false;
        }
    }

    private function transferOwnershipInTransaction(int $workspaceId, int $requesterUserId, int $targetMembershipId): bool
    {
        return DB::transaction(function () use ($workspaceId, $requesterUserId, $targetMembershipId): bool {
            $memberships = DB::table('workspace_memberships')
                ->where('workspace_id', $workspaceId)
                ->lockForUpdate()
                ->get(['id', 'user_id', 'role']);

            $owners = $memberships->where('role', 'owner');

            if ($owners->count() !== 1) {
                return false;
            }

            $ownerMembership = $owners->first();

            if ((int) $ownerMembership->user_id !== $requesterUserId) {
                return false;
            }

            $targetMembership = $memberships->firstWhere('id', $targetMembershipId);

            /*
                HEDEF, DEVRALABİLEN BİR ROLDE OLMALI.

                Koşul bir zamanlar "tam olarak `editor`" diyordu ve bu bir
                seçim değildi: yazıldığı gün enum yalnız `owner`, salt okunur
                `member` ve `editor` taşıyordu — editör, devredilebilecek tek
                adaydı. `Manager` sonradan doğdu ve koşula kimse geri dönmedi,
                böylece sahip dükkânı devrederken günlük operasyonu yürüten
                kişiyi seçemez oldu (`MembershipRole::ownershipTransferable()`
                bu kümenin NEDEN'ini taşır).

                Küme `removable()` DEĞİLDİR ve ondan türetilmez: mutfak ve
                miras `member` çıkarılabilir ama devralamaz. Aynı bayrağı
                paylaşsalardı, devir bu iki rolü de kapsardı.
            */
            $transferableRoles = array_map(
                static fn (MembershipRole $role): string => $role->value,
                MembershipRole::ownershipTransferable(),
            );

            if ($targetMembership === null || ! in_array($targetMembership->role, $transferableRoles, true)) {
                return false;
            }

            if ((int) $targetMembership->id === (int) $ownerMembership->id) {
                return false;
            }

            $promoted = DB::table('workspace_memberships')
                ->where('id', $targetMembership->id)
                ->where('workspace_id', $workspaceId)
                /*
                    Koşullu güncelleme AYNEN durur, yalnız sabit `editor`
                    yerine az önce DOĞRULANAN rolü bekler: satır kilit
                    altında okunduktan sonra da olsa değişmişse güncelleme
                    sıfır satır etkiler ve işlem geri sarılır. Sabit değer
                    bırakılsaydı, yönetici hedefte bu kapı her zaman
                    tökezlerdi.
                */
                ->where('role', $targetMembership->role)
                ->update(['role' => 'owner', 'updated_at' => now()]);

            if ($promoted !== 1) {
                throw new \RuntimeException('Failed to promote target membership to owner.');
            }

            $demoted = DB::table('workspace_memberships')
                ->where('id', $ownerMembership->id)
                ->where('workspace_id', $workspaceId)
                ->where('role', 'owner')
                ->update(['role' => 'editor', 'updated_at' => now()]);

            if ($demoted !== 1) {
                throw new \RuntimeException('Failed to demote prior owner to editor.');
            }

            return true;
        });
    }
}
