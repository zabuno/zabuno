<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Billing\Dto\BillingProfile;
use App\Application\Billing\UseCase\ManageBillingProfile;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Fatura profili yazma ucu. Yetki ÖNCE, doğrulama SONRA: yetkisiz bir üye
 * alan hatası alarak bile bu yüzeyin şeklini öğrenemez (404).
 */
final class StoreBillingProfileController extends Controller
{
    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly ManageBillingProfile $profiles,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::BillingManage, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $validated = Validator::make($request->all(), [
            'legal_name' => ['required', 'string', 'max:200'],
            'tax_number' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9\-]+$/'],
            'tax_office' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:120'],
            'country' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'email' => ['required', 'string', 'email', 'max:200'],
            'phone' => ['required', 'string', 'max:32', 'regex:/^\+?[0-9 ()\-]{5,}$/'],
        ])->validate();

        $profile = BillingProfile::fromArray(array_map(static fn (mixed $value): string => trim((string) $value), $validated));

        return response()->json($this->profiles->store($workspace, $profile));
    }
}
