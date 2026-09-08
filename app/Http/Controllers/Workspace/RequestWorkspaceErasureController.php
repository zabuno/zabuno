<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspace;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\DataRights\Exception\ErasureBlockedException;
use App\Application\DataRights\Port\DataRequestRepositoryPort;
use App\Application\DataRights\Port\DataRightsNotifierPort;
use App\Application\DataRights\UseCase\RequestWorkspaceErasure;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "Verimizi silin" — FF-226 (`docs/138` §4).
 *
 * ONAY BİR KUTUYA DEĞİL, ÇALIŞMA ALANININ ADINA bağlıdır. "Eminim" kutusu
 * refleksle işaretlenir; adı yazmak, kullanıcıyı hangi çalışma alanında
 * olduğuna BAKMAYA zorlar. İki restoranı olan bir sahibin yanlış paneli
 * silmesini önleyen tek şey budur.
 *
 * 409 (`legal_hold`) bir doğrulama hatası değil bir DURUM çatışmasıdır:
 * kullanıcının yazdığı hiçbir şey yanlış değil, ortamda silmeyi imkânsız
 * kılan bir kilit var.
 */
final class RequestWorkspaceErasureController extends Controller
{
    public function __construct(
        private readonly RequestWorkspaceErasure $erasure,
        private readonly DataRequestRepositoryPort $requests,
        private readonly DataRightsNotifierPort $notifier,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::WorkspaceDataErase, $workspace)) {
            return response()->json([
                'message' => 'Forbidden.',
                'requiredPermission' => Permission::WorkspaceDataErase->value,
            ], 403);
        }

        $expected = (string) DB::table('workspaces')->where('id', $workspace)->value('name');

        $request->validate([
            'confirmation' => ['required', 'string'],
        ]);

        if (trim((string) $request->input('confirmation')) !== trim($expected)) {
            return response()->json([
                'message' => 'The confirmation does not match.',
                'errors' => ['confirmation' => ['Type the workspace name exactly as it is shown.']],
            ], 422);
        }

        try {
            $created = $this->erasure->handle($workspace, $userId);
        } catch (ErasureBlockedException $exception) {
            return response()->json([
                'message' => 'Erasure is blocked.',
                'reason' => $exception->reason,
                'count' => $exception->count,
            ], 409);
        }

        $email = $request->user()->getAttribute('email');

        if (is_string($email) && $email !== '') {
            // Haber gitmese de talep durur (`docs/93`).
            $this->requests->recordNotification($created->id, $this->notifier->notify($created, $email, null));
        }

        return response()->json([
            'id' => $created->id,
            'state' => $created->state->value,
            'scheduledFor' => $created->scheduledFor,
        ], 202);
    }
}
