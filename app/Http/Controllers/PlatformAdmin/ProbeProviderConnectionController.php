<?php

declare(strict_types=1);

namespace App\Http\Controllers\PlatformAdmin;

use App\Application\Platform\Port\ConnectionProbePort;
use App\Application\Platform\Port\PlatformConnectionAdminPort;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * "Bu bağlantı gerçekten çalışıyor mu?" — `docs/95` Faz 3 uyumluluk katmanı.
 *
 * Superadmin bugüne kadar bir anahtarı kaydedip "kaydedildi" görüyordu ama
 * anahtarın yanlış olduğunu ancak ilk müşteri isteğinde — yani en kötü
 * anda — öğreniyordu. Bu uç, tek ve ücretsiz bir çağrıyla o soruyu şimdi
 * yanıtlar ve sonucu bağlantının sağlığına yazar.
 *
 * Cevap sırrı taşımaz: yalnız sonuç, HTTP durumu ve kısa bir açıklama.
 */
final class ProbeProviderConnectionController extends Controller
{
    public function __construct(
        private readonly ConnectionProbePort $probe,
        private readonly PlatformConnectionAdminPort $vault,
    ) {}

    public function __invoke(int $connection): JsonResponse
    {
        if ($this->vault->connection($connection) === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $result = $this->probe->probe($connection);

        return response()->json([
            'probe' => $result->toArray(),
            'connection' => $this->vault->connection($connection)?->toArray(),
        ]);
    }
}
