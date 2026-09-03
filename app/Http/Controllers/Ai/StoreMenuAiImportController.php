<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ai;

use App\Application\Ai\Exception\ProviderCallException;
use App\Application\Ai\UseCase\ExtractMenuFromImage;
use App\Application\Authorization\Port\AuthorizationPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Menü fotoğrafını okumaya başlatır — `docs/92` (P0-05 foto yolu).
 *
 * Fotoğraf ZATEN yüklenmiş bir medya varlığıdır: yükleme yolu, doğrulaması,
 * virüs taraması ve depolaması yeniden yazılmaz.
 */
final class StoreMenuAiImportController extends Controller
{
    public function __construct(
        private readonly ExtractMenuFromImage $extract,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace, int $menu): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::MenuManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $availability = $this->extract->availability($workspace);

        if (! $availability->isAvailable()) {
            /*
                503: istek DOĞRU, yetenek yok.

                500 vermek sahibi "ürün bozuldu" sanmaya iterdi; sessizce boş
                dönmek daha kötü olurdu. Sebep de verilir — kapalı bir anahtar
                ile tükenmiş bir bütçe farklı şeylerdir ve çıkış yolları
                farklıdır.
            */
            return response()->json([
                'message' => 'Fotoğraftan menü okuma şu anda kullanılamıyor.',
                'reason' => $availability->value,
            ], 503);
        }

        $validated = $request->validate(['mediaAssetId' => ['required', 'integer', 'min:1']]);

        $menuRow = DB::table('menus')->where('id', $menu)->where('workspace_id', $workspace)->first();
        $asset = DB::table('media_assets')
            ->where('id', (int) $validated['mediaAssetId'])
            ->where('workspace_id', $workspace)
            ->whereNull('deleted_at')
            ->first();

        if ($menuRow === null || $asset === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        try {
            $result = $this->extract->handle(
                $workspace,
                $menu,
                Storage::disk('local')->path((string) $asset->disk_path),
            );
        } catch (ProviderCallException $exception) {
            /*
                502: yetenek AÇIKTI, çağrı yapıldı ama sağlayıcı hata verdi.
                503'ten (yetenek yok) farklıdır ve çıkış yolu da farklı:
                anahtarı/kotayı kontrol et, ya da tekrar dene. Sağlayıcının
                ham hatası ya da anahtarı sızdırılmaz — yalnız sebep kodu.
            */
            return response()->json([
                'message' => 'Fotoğraf okunmaya çalışıldı ama sağlayıcı yanıt vermedi.',
                'reason' => $exception->reason,
            ], 502);
        }

        return response()->json([
            'id' => $result['id'],
            'uncertainFieldCount' => count($result['artifact']->uncertainFields()),
            'usedFallback' => $result['artifact']->usedFallback,
        ], 201);
    }
}
