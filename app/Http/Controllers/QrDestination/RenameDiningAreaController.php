<?php

declare(strict_types=1);

namespace App\Http\Controllers\QrDestination;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\MenuCatalog\Api\Port\MenuCatalogApiContextPort;
use App\Application\QrDestination\Port\DiningAreaRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Salon bölümünü yeniden adlandırır — FF-123, sahibin cümlesi: "salon üst kat,
 * salon içerisi, salon bahçe".
 *
 * Toplu üretim bölümleri "Area 1", "Area 2" diye açıyor ve bu bir YER
 * TUTUCUDUR. Kart basarken alanı seçen kişi kendi kullandığı adı görmeli;
 * yoksa hangi "Area"nın bahçe olduğunu hatırlamak zorunda kalır ve yanlış
 * kartları bastırır.
 *
 * Ad DEĞİŞİR ama kimlik değişmez: basılı kartlar alanın kimliğine değil kendi
 * token'ına bağlı, dolayısıyla yeniden adlandırmak hiçbir kartı bozmaz.
 */
final class RenameDiningAreaController extends Controller
{
    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly MenuCatalogApiContextPort $context,
        private readonly DiningAreaRepositoryPort $areas,
    ) {}

    public function __invoke(Request $request, int $workspace, int $location, int $area): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::QrView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if ($this->context->locationWorkspaceId($location) !== $workspace) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::QrCreate, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // Alan BU şubeye ait olmalı: kimliği bilen biri başka şubenin bölümünü
        // yeniden adlandıramaz.
        if (! $this->areas->belongsToLocation($area, $workspace, $location)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $validated = $request->validate([
            // Boş ad kabul edilmez: adsız bir bölüm, seçicide boş bir düğme
            // olurdu.
            'label' => ['required', 'string', 'min:1', 'max:60'],
        ]);

        $label = trim($validated['label']);

        if ($label === '') {
            return response()->json(['message' => 'Invalid area name.'], 422);
        }

        $this->areas->rename($area, $label);

        return response()->json(['id' => $area, 'label' => $label], 200);
    }
}
