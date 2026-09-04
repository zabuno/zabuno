<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Media\Port\MediaQuotaPort;
use App\Application\Media\Port\MediaStorageBreakdownPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "YERİ NE DOLDURUYOR?" (kanonik kaynak: `docs/reference/media-manager/
 * Medya Yonetimi v2.dc.html`, ekran etiketi "Kota ve çöp"; somut liste
 * `docs/108` §6.4).
 *
 * SALT OKUNUR. Tek bir dosyayı bile değiştirmez.
 *
 * Kota şeridi bugün "185 MB / 200 MB" diyor ve sahip bunu okuduğunda ne
 * yapacağını bilmiyor. Bu uç aynı depoyu iki eksende anlatır:
 *
 *   1. `totals` — kaynağın kota KARTLARI. Kaynak dört kart sayıyor
 *      (depolama, dosya sayısı, dönüştürme, CDN trafiği); bu depoda yalnız
 *      İKİSİNİN sayacı ve sınırı var. "Dönüştürme" ve "CDN trafiği" için ne
 *      sayaç ne sınır olduğu için HİÇ gönderilmezler — uydurulmuş bir kart,
 *      sahibi olmayan bir yeteneğe güvendirir.
 *   2. `categories` + `trash` — kırılım. Eşlemenin gerekçesi
 *      `App\Domain\Media\StorageCategory` içindedir.
 *
 * İzin: `ListDerivativeRulesController` ile aynı gerekçe — buradaki sayılar
 * kiracının depolama davranışını anlatır ve ekipteki herkesin işi değildir.
 * Üye olmayan 404 görür; 403 "böyle bir kiracı var ama sana kapalı" der ve
 * bu da bir bilgidir.
 */
final class ShowMediaStorageBreakdownController extends Controller
{
    public function __construct(
        private readonly MediaStorageBreakdownPort $breakdown,
        private readonly MediaQuotaPort $quota,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::MediaManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $status = $this->quota->statusFor($workspace);

        return response()->json([
            'totals' => [
                'planLabel' => $status->planLabel,
                'bytesUsed' => $status->originalBytesUsed,
                'bytesLimit' => $status->originalBytesLimit,
                'assetsUsed' => $status->assetsUsed,
                'assetsLimit' => $status->assetsLimit,
            ],
        ] + $this->breakdown->breakdownFor($workspace)->toArray());
    }
}
