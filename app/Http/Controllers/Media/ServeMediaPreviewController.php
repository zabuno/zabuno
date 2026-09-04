<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Panel önizlemesinin BAYTLARI (`docs/108` §3 madde 8, ekran "Görüntüle").
 *
 * Neden `ServeOriginalController` yetmiyor: o uç dosyayı `attachment`
 * olarak verir ve vermek zorundadır — imzalı adres paylaşılabilir, süresi
 * dolan, oturumsuz bir İNDİRME adresidir. Onu bir çerçeveye koymak her
 * seçimde bir indirme başlatırdı.
 *
 * Bu uç ise tam tersidir ve farkları bilinçlidir:
 *
 *   - `inline`, çünkü dosya panelin İÇİNDE açılacak.
 *   - İMZASIZ ve OTURUMLU: adres paylaşılamaz, çünkü tek başına bir şey
 *     ifade etmez; oturum çerezi olmayan biri 401 alır. Bir çerçeve/`<img>`
 *     isteği aynı kökene çerezi kendiliğinden taşır, o yüzden imzaya gerek
 *     de yoktur.
 *   - `no-store`: asıl özeldir, ara önbellek saklamaz.
 *
 * GÜVENLİK ÜÇ KAPI (`MediaPreviewPolicy`):
 *   1. İzin — aslın indirme izniyle aynı.
 *   2. Durum — taraması temiz dönmemiş dosya SERVİS EDİLMEZ. Ekranın
 *      kararına güvenilmez: adresi elle çağıran da alamaz (409).
 *   3. Tür — beyaz listede olmayan tür servis edilmez (415), çünkü
 *      tanımadığımız bir belgeyi tarayıcıya açtırmak, ne yapacağını
 *      tarayıcının kararına bırakmaktır.
 *
 * DENETİM İZİ burada BİLEREK yazılmaz. Bir PDF'te her sayfa değişimi yeni
 * bir istektir; on iki sayfalık bir belgeyi okumak ize on iki satır
 * yazardı ve "kim asıl dosyayı dışarı çıkardı" sorusunun cevabı o
 * gürültünün altında kaybolurdu. İze yazılan olay paylaşılabilir imzalı
 * bağlantının ÜRETİLMESİDİR (`CreateOriginalDownloadLinkController`) —
 * panelde bakmak ile dosyayı dışarı çıkarmak aynı olay değildir.
 */
final class ServeMediaPreviewController extends Controller
{
    public function __construct(private readonly AuthorizationPort $authorization) {}

    public function __invoke(Request $request, int $workspace, int $media): SymfonyResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::MediaDownloadOriginal, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $asset = MediaAsset::query()
            ->where('id', $media)
            ->where('workspace_id', $workspace)
            ->first();

        if ($asset === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! MediaPreviewPolicy::isEmbeddableType((string) $asset->mime_type)) {
            return response()->json([
                'message' => 'This file type is not opened in the panel.',
            ], 415);
        }

        if (! MediaPreviewPolicy::isScanCleared((string) $asset->status)) {
            return response()->json([
                'message' => 'This file has not cleared the security scan yet.',
            ], 409);
        }

        $disk = Storage::disk('local');
        $path = (string) $asset->disk_path;

        if (! $disk->exists($path)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response(
            $disk->get($path),
            200,
            MediaPreviewPolicy::headers(
                (string) $asset->mime_type,
                (string) $asset->original_name,
                (int) $asset->id,
            ),
        );
    }
}
