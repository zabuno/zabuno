<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ledger;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Ledger\UseCase\ShowWorkspaceLedger;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Defterin okuma yüzeyi — CORE-12.
 *
 * Defteri görmek finansal bilgiye erişimdir; bu yüzden fatura görüntüleme
 * yetkisiyle aynı kapıdan geçer ve yetkisiz istek varlığı bile sızdırmaz
 * (403 değil, 404).
 */
final class ShowWorkspaceLedgerController extends Controller
{
    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly ShowWorkspaceLedger $ledger,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::BillingView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json($this->ledger->forWorkspace($workspace));
    }
}
