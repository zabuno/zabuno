<?php

declare(strict_types=1);

namespace App\Http\Controllers\Security;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Security\UseCase\RecordReleaseAttestation;
use App\Domain\Authorization\Permission;
use App\Domain\Security\ReleaseAttestationKey;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Bir maddenin EN SON tanıklığı — `docs/98` FF-63.
 *
 * Diğer kanıt uçlarıyla aynı sözleşme: yetkisiz → 404 (varlık bile bilgi),
 * kayıt yok → 404, bütünlük bozuk → 500. Cevap "insan tanıklığı" olduğunu
 * `kind` ile açıkça söyler; ekran onu makine kanıtından ayrı etiketler.
 */
final class ShowReleaseAttestationController extends Controller
{
    public function __construct(private readonly AuthorizationPort $authorization) {}

    public function __invoke(Request $request, int $workspace, string $key): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::SecurityEvidenceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $attestationKey = ReleaseAttestationKey::tryFrom($key);

        if ($attestationKey === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $row = DB::table('release_attestations')
            ->where('key', $attestationKey->value)
            ->orderByDesc('attested_at')
            ->orderByDesc('id')
            ->first();

        if ($row === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $expected = RecordReleaseAttestation::digest(
            (string) $row->key,
            (string) $row->status,
            (string) $row->summary,
            $row->reference === null ? null : (string) $row->reference,
            $row->payload === null ? null : (string) $row->payload,
            CarbonImmutable::parse((string) $row->attested_at)->toIso8601String(),
        );

        if (! hash_equals($expected, (string) $row->integrity_sha256)) {
            return response()->json(['message' => 'Evidence integrity check failed.'], 500);
        }

        $attestedBy = $row->attested_by_user_id === null
            ? null
            : DB::table('users')->where('id', $row->attested_by_user_id)->value('name');

        return response()->json([
            'data' => [
                'id' => (int) $row->id,
                'key' => (string) $row->key,
                'kind' => 'attestation',
                'status' => (string) $row->status,
                'summary' => (string) $row->summary,
                'reference' => $row->reference,
                'payload' => $row->payload === null ? [] : json_decode((string) $row->payload, true),
                'attested_by' => $attestedBy,
                'attested_at' => (string) $row->attested_at,
                'integrity_sha256' => (string) $row->integrity_sha256,
            ],
        ]);
    }
}
