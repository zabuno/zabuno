<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ai;

use App\Application\Ai\Port\AiAvailabilityPort;
use App\Application\Authorization\Port\AuthorizationPort;
use App\Domain\Ai\Capability;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "AI şu an ne yapabilir?" — TIKLAMADAN ÖNCE (`docs/97` R9, AIV-07).
 *
 * Bu uç nokta olmadan ekranın tek öğrenme yolu düğmeye basıp 503 almaktı:
 * kullanıcı, var olmayan bir işi denemek zorunda kalıyordu. Ekran artık
 * eylemi hiç göstermeden önce sorar ve gerekirse yerine tek satırlık bir
 * sebep koyar.
 *
 * Cevap LİSTEDİR, nesne değil: yetenek adları nokta içerir (`menu.extract`)
 * ve noktalı bir anahtarı JSON nesnesine gömmek, `ConfiguredAvailability`'nin
 * docblock'unda kayıtlı dotted-key arızasının aynısını istemci tarafında
 * doğururdu.
 *
 * Hiçbir sağlayıcı çağrısı YAPMAZ — yalnız yapılandırma/bütçe okur, bu
 * yüzden ücretsizdir ve hız sınırı gerektirmez.
 */
final class ShowAiAvailabilityController extends Controller
{
    /**
     * Ekranların gerçekten sorduğu yetenekler. Kasıtlı olarak `Capability`
     * enum'unun TAMAMI değil: henüz ekranı olmayan bir yeteneğin durumunu
     * yayınlamak, olmayan bir düğme hakkında söz vermek olurdu.
     */
    private const SCREEN_CAPABILITIES = [
        Capability::MenuExtract,
        Capability::ProductDescription,
        Capability::TextEmbedding,
    ];

    public function __construct(
        private readonly AiAvailabilityPort $availability,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $capabilities = [];

        foreach (self::SCREEN_CAPABILITIES as $capability) {
            $state = $this->availability->isAvailable($workspace, $capability);

            $capabilities[] = [
                'capability' => $capability->value,
                'available' => $state->isAvailable(),
                'reason' => $state->value,
            ];
        }

        return response()->json(['capabilities' => $capabilities]);
    }
}
