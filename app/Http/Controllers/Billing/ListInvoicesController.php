<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Billing\UseCase\ManageInvoices;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Kesilmiş belgelerin okuma yüzeyi (docs/107 Faz 1.4, docs/130).
 *
 * Faturayı görmek finansal bilgiye erişimdir; defterle aynı kapıdan geçer
 * ve yetkisiz istek varlığı bile sızdırmaz (403 değil, 404).
 */
final class ListInvoicesController extends Controller
{
    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly ManageInvoices $invoices,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::BillingView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json($this->invoices->listFor($workspace));
    }
}
