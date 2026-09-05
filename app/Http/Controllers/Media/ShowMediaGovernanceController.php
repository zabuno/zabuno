<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Media\Port\MediaAuditPort;
use App\Application\Media\Port\MediaBulkPort;
use App\Application\Media\Port\MediaLegalHoldPort;
use App\Application\Media\Port\MediaQuotaPort;
use App\Domain\Authorization\Permission;
use App\Domain\Authorization\RolePermissions;
use App\Domain\Media\MediaBulkAction;
use App\Domain\Tenancy\MembershipRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * YÖNETİŞİM — kanonik kaynak `docs/reference/panel-v3/MedyaModulu.dc.html`,
 * `data-screen-label="Yönetişim"`. Kaynağın kendi cümlesi: "Kim ne
 * yapabilir, dosyalar ne kadar saklanır, kim ne yaptı."
 *
 * SALT OKUNURDUR ve hız sınırsızdır: sayar, okur, hiçbir dosyaya
 * dokunmaz.
 *
 * ═══ KAYNAĞIN ROL MODELİ UYDURULMADI ═══
 *
 * Kaynak dört kademeli bir matris çiziyor (İzleyici · Editör · Yönetici ·
 * Sahip) ve her satırı bir kademe numarasıyla açıyor. Bu deponun rol
 * modeli farklıdır ve GERÇEK olan odur (`RolePermissions`): dört rol var
 * (owner/manager/editor/member) ama medya izinleri kademeli değil,
 * kümesel dağılmış — `media.manage` editörde de var, `workspace.manage`
 * yalnız sahip ve yöneticide.
 *
 * O yüzden matris kaynağın kademe numaralarını değil, bu deponun gerçek
 * izinlerini gösterir. Kaynağa BENZETMEK, editöre "yönetici olsan
 * yapabilirdin" diyen ama gerçekte yöneticinin de yapamadığı bir tablo
 * çizmek olurdu — ve o tablo, bakan herkesi yanıltırdı.
 */
final class ShowMediaGovernanceController extends Controller
{
    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly MediaBulkPort $bulk,
        private readonly MediaAuditPort $audit,
        private readonly MediaQuotaPort $quota,
        private readonly MediaLegalHoldPort $legalHold,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $permissions = $this->authorization->permissionsFor($userId, $workspace);
        $quota = $this->quota->statusFor($workspace);
        $legalHolds = $this->legalHold->all($workspace);

        return response()->json([
            'role' => $this->roleFor($permissions),
            'permissions' => $this->matrixFor($permissions),
            'retention' => [
                // Çöp penceresi EKRANDA yazılı bir sabit değil, kotanın
                // kendi sayısıdır: plan değişince ekran da değişir.
                'trashRetentionDays' => $quota->trashRetentionDays,
                'legalHoldCount' => count($legalHolds),
                /*
                    Kaynak burada ayrıca "Arşiv · 1 yıl" ve "Sürüm geçmişi ·
                    10 sürüm" satırları gösteriyor. İkisi de YAZILMADI:
                    arşiv yaşam döngüsü bu depoda hiçbir listelemeyi
                    süzmüyor (bkz. `MediaBulkAction`), sürüm geçmişi ise
                    BUDANMIYOR — her sürüm duruyor. "10 sürüm" yazmak,
                    olmayan bir budama sözü vermek olurdu.
                */
            ],
            'legalHolds' => $legalHolds,
            'trail' => $this->trail($workspace),
        ]);
    }

    /**
     * Rol, izin kümesinden geri okunur — `BuildWorkspaceContextPayload`
     * ile birebir aynı kural: ekran rol adını yalnız GÖSTERİR, karar her
     * zaman izin listesinden verilir.
     *
     * @param  list<Permission>  $permissions
     */
    private function roleFor(array $permissions): ?string
    {
        $given = array_map(static fn (Permission $p): string => $p->value, $permissions);
        sort($given);

        foreach (MembershipRole::cases() as $role) {
            $expected = array_map(static fn (Permission $p): string => $p->value, RolePermissions::for($role));
            sort($expected);

            if ($expected === $given) {
                return $role->value;
            }
        }

        return null;
    }

    /**
     * Yetki matrisi: her toplu eylem + yasal saklama, bu kullanıcı için
     * açık mı, değilse hangi izni ister.
     *
     * Kilitli satır GİZLENMEZ. Gizleseydik editör "ürün kalıcı silemiyor"
     * sanır ve yöneticisinden hiç istemezdi — kaynağın "Herkes sadece
     * işine yeteni görür" kuralı, yapamadığını da görmemesi demek değil.
     *
     * @param  list<Permission>  $permissions
     * @return list<array{action:string, allowed:bool, requiredPermission:string, reversible:bool}>
     */
    private function matrixFor(array $permissions): array
    {
        $rows = [];

        foreach (MediaBulkAction::cases() as $action) {
            $rows[] = [
                'action' => $action->value,
                'allowed' => in_array($action->requiredPermission(), $permissions, true),
                'requiredPermission' => $action->requiredPermission()->value,
                'reversible' => $action->isReversible(),
            ];
        }

        /*
            Yasal saklama bir TOPLU EYLEM değildir (tek dosyaya konur), ama
            matriste durur: kaynağın "Yasal saklamayı kaldır" satırı ve
            sahibin sorduğu soru aynı — "bu kilidi kim açabilir?".
        */
        $rows[] = [
            'action' => 'legal-hold',
            'allowed' => in_array(Permission::WorkspaceManage, $permissions, true),
            'requiredPermission' => Permission::WorkspaceManage->value,
            'reversible' => true,
        ];

        return $rows;
    }

    /**
     * Denetim izi — iki kaynağın BİRLEŞİMİ, en yeni önce.
     *
     * Sahibin sorusu "kim ne yaptı", "hangi tabloya bakayım" değil. Tek
     * dosya kayıtları `media_audits`te, toplu iş kayıtları
     * `media_bulk_operations`ta durur; ekran ikisini tek bir listede
     * okur ve satır `kind` ile hangisinden geldiğini söyler.
     *
     * @return list<array<string, mixed>>
     */
    private function trail(int $workspace): array
    {
        $rows = [];

        foreach ($this->audit->recent($workspace, 25) as $entry) {
            $rows[] = [
                'kind' => 'asset',
                'action' => $entry['action'],
                'actor' => $entry['actor'],
                'at' => $entry['at'],
                'mediaAssetId' => $entry['mediaAssetId'],
            ];
        }

        foreach ($this->bulk->recentOperations($workspace, 25) as $entry) {
            $rows[] = [
                'kind' => 'bulk',
                'action' => $entry['action'],
                'actor' => $entry['actor'],
                'at' => $entry['at'],
                'scope' => $entry['scope'],
                'applied' => $entry['applied'],
                'skipped' => $entry['skipped'],
                'failed' => $entry['failed'],
                'operationKey' => $entry['operationKey'],
            ];
        }

        usort($rows, static fn (array $a, array $b): int => strcmp((string) $b['at'], (string) $a['at']));

        return array_slice($rows, 0, 40);
    }
}
