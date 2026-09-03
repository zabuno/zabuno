<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ai;

use App\Application\Ai\Exception\ProviderCallException;
use App\Application\Ai\UseCase\DetectDuplicateProductNames;
use App\Application\Authorization\Port\AuthorizationPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Yinelenen ürün adı adayları — `docs/95`/`docs/96` Faz 2 (core-taxonomy).
 *
 * SALT OKUNUR — hiçbir kaydı değiştirmez. Tek gate: görüntüleme yetkisi.
 */
final class ShowDuplicateProductCandidatesController extends Controller
{
    public function __construct(
        private readonly DetectDuplicateProductNames $detect,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $availability = $this->detect->availability($workspace);

        if (! $availability->isAvailable()) {
            return response()->json([
                'message' => 'Yinelenen ürün tespiti şu anda kullanılamıyor.',
                'reason' => $availability->value,
            ], 503);
        }

        try {
            $candidates = $this->detect->handle($workspace);
        } catch (ProviderCallException $exception) {
            return response()->json([
                'message' => 'Tespit denendi ama sağlayıcı yanıt vermedi.',
                'reason' => $exception->reason,
            ], 502);
        }

        return response()->json(['candidates' => $candidates]);
    }
}
