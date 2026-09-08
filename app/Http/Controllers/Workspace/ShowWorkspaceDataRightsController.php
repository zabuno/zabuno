<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspace;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\DataRights\Dto\DataRequestRow;
use App\Application\DataRights\Port\DataRequestRepositoryPort;
use App\Application\DataRights\Port\WorkspaceDataEraserPort;
use App\Domain\Authorization\Permission;
use App\Domain\DataRights\DataRequestKind;
use App\Domain\DataRights\DataRequestState;
use App\Domain\DataRights\ErasureWindow;
use App\Domain\DataRights\TenantDataScope;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Veri hakları — Ayarlar > Çalışma alanı sekmesinin tehlikeli bölgesi
 * (FF-226; `WorkspaceIdentityRegion`'ın "bu üçü doğduğunda buraya gelir"
 * notu).
 *
 * İKİ BASAMAKLI KAPI, medya ve denetim izindeki kuralla AYNI: çalışma
 * alanını hiç göremeyen için kayıt HİÇ YOKTUR (404); üye olan ama yetkisi
 * olmayan için 403 doğrudur.
 *
 * EKRAN LİSTEYİ DE SÖZÜ DE AYNI KAYNAKTAN OKUR. Silinmeyenlerin listesi
 * `TenantDataScope`'tan gelir, ekrana elle yazılmaz: bir gün bir tablo
 * saklananlar arasına girdiğinde ekran onu kendiliğinden söyler. Elle
 * yazılsaydı, o gün ekran "her şey silinir" demeye devam ederdi.
 */
final class ShowWorkspaceDataRightsController extends Controller
{
    public function __construct(
        private readonly DataRequestRepositoryPort $requests,
        private readonly WorkspaceDataEraserPort $eraser,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::WorkspaceDataExport, $workspace)) {
            return response()->json([
                'message' => 'Forbidden.',
                'requiredPermission' => Permission::WorkspaceDataExport->value,
            ], 403);
        }

        $minutes = (int) config('data-rights.export.link_minutes', 10);
        $minutes = $minutes < 1 ? 10 : $minutes;

        return response()->json([
            /*
                AD SUNUCUDAN GELİR. Onay metni çalışma alanının adıdır ve
                ekranın onu başka bir uçtan okuması, iki kaynağın bir gün
                ayrışması demekti — o gün kullanıcı doğru adı yazar ve
                sunucu reddederdi.
            */
            'workspaceName' => (string) DB::table('workspaces')->where('id', $workspace)->value('name'),
            'graceDays' => ErasureWindow::fromConfig(config('data-rights.erasure.grace_days'))->days,
            'exportedSections' => ['workspaces', ...array_map(
                static fn ($table): string => $table->name,
                TenantDataScope::exportedTables(),
            )],
            'retained' => TenantDataScope::retained(),
            'outOfScope' => TenantDataScope::outOfScope(),
            'assetsUnderLegalHold' => $this->eraser->assetsUnderLegalHold($workspace),
            'hosting' => [
                'provider' => (string) config('data-rights.hosting.provider'),
                'country' => (string) config('data-rights.hosting.country'),
            ],
            'requests' => array_map(
                fn (DataRequestRow $row): array => $this->present($row, $workspace, $minutes),
                $this->requests->forWorkspace($workspace),
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(DataRequestRow $row, int $workspace, int $minutes): array
    {
        return [
            'id' => $row->id,
            'kind' => $row->kind->value,
            'state' => $row->state->value,
            'requestedBy' => $row->requestedByEmail,
            'requestedAt' => $row->requestedAt,
            'sectionCount' => count($row->scopeSections),
            'bytes' => $row->artifactBytes,
            'availableUntil' => $row->availableUntil,
            'scheduledFor' => $row->scheduledFor,
            'completedAt' => $row->completedAt,
            'cancelledAt' => $row->cancelledAt,
            'failed' => $row->state === DataRequestState::Failed,
            'deletedRowTotal' => $row->deletedRowTotal(),
            /*
                BİLDİRİMİN HÂLİ EKRANDA GÖRÜNÜR (`docs/93`). "Haber verildi"
                ile "haber verilemedi" ile "hiç denenmedi" üç ayrı şeydir ve
                üçü de kullanıcının bilmesi gereken bir olgudur: e-postasını
                bekleyip beklemeyeceğini ancak böyle bilir.
            */
            'notifiedAt' => $row->notifiedAt,
            'notificationFailed' => $row->notificationFailure !== null,
            /*
                İNDİRME ADRESİ YALNIZ HAZIR ARŞİV İÇİN ÜRETİLİR ve her
                okumada yenilenir: kısa ömürlü bir imza, ekran açık kalırken
                eskiyebilir ve sayfa yenilendiğinde tazelenmelidir.
            */
            'downloadUrl' => $row->kind === DataRequestKind::Export && $row->state === DataRequestState::Ready
                ? URL::temporarySignedRoute(
                    'workspace.data-export.download',
                    now()->addMinutes($minutes),
                    ['workspace' => $workspace, 'request' => $row->id],
                )
                : null,
        ];
    }
}
