<?php

declare(strict_types=1);

namespace App\Http\Controllers\Security;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Host yetenek kanıdının en son kaydı — `docs/98` FF-63.
 *
 * `platform:evidence:host-capability` komutu bu satırı 2026-08-26'dan beri
 * yazıyordu; okuyan bir uç nokta hiç olmadı, ekran bu yüzden "Unavailable"
 * kaldı. Kanıt paylaşımlı host'ta ürünün nasıl DÜŞTÜĞÜNÜ de söyler
 * (`degradations`) — ekran onu gizlemez.
 */
final class ShowHostCapabilityEvidenceController extends Controller
{
    public function __construct(private readonly AuthorizationPort $authorization) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::SecurityEvidenceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $row = DB::table('host_capability_evidence')->orderByDesc('ran_at')->orderByDesc('id')->first();

        if ($row === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json([
            'data' => [
                'id' => (int) $row->id,
                'key' => 'host-capability',
                'kind' => 'automated',
                'php_version' => (string) $row->php_version,
                'capabilities' => json_decode((string) $row->capabilities, true),
                'degradations' => json_decode((string) $row->degradations, true),
                'claim' => (string) $row->claim,
                'ran_at' => (string) $row->ran_at,
            ],
        ]);
    }
}
