<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * GÖRÜNTÜLE — "bu dosyayı panelde nasıl açacağız?" (kanonik kaynak:
 * `docs/reference/media-manager/Medya Yonetimi v2.dc.html`, ekran etiketi
 * "Görüntüle"; sıra `docs/108` §3 madde 8).
 *
 * Ekran bu soruyu KENDİ BAŞINA cevaplayamaz: elindeki listede dosyanın
 * MIME türü yok, yalnız kullanıcının yazdığı ad var. Uzantıya bakmak da
 * cevap değildir — uzantı yükleyenin denetimindedir ve alım kapısının
 * baytlara bakarak verdiği kararı ekranın tahminine çevirmek, o kapıyı
 * anlamsız kılardı.
 *
 * Yanıt üç şeyi söyler ve üçü de bir CÜMLEYE dönüşür:
 *
 *   - `kind`  : hangi okuyucu (`pdf` sayfa sayfa, `image` tek kare,
 *               `other` "bu tür panelde açılmıyor").
 *   - `blockedReason`: açılmıyorsa NEDEN — `scan` (henüz taranmadı /
 *               temiz dönmedi) ya da `type` (tür desteklenmiyor). Sebepsiz
 *               bir "açılmıyor", kullanıcıyı ne yapacağını bilmeden bırakır.
 *   - `pageCount`: yalnız baytlar gerçekten söylüyorsa; aksi halde `null`.
 *
 * İZİN, aslın indirme izniyle AYNIDIR (`media.download_original`): panel
 * önizlemesi aslın baytlarını gösterir, dolayısıyla ondan daha gevşek bir
 * kapı arkadan dolaşmanın yolu olurdu.
 */
final class ShowMediaViewerController extends Controller
{
    public function __construct(private readonly AuthorizationPort $authorization) {}

    public function __invoke(Request $request, int $workspace, int $media): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::MediaDownloadOriginal, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        /*
            Çöptekiler DIŞARIDA (varsayılan soft-delete kapsamı): çöpe
            atılmış bir dosyayı görüntülemek "geri al"dan önce gelen bir
            adım değildir ve çöp sekmesinin kendi anlatısı vardır.
        */
        $asset = MediaAsset::query()
            ->where('id', $media)
            ->where('workspace_id', $workspace)
            ->first();

        if ($asset === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $mimeType = (string) $asset->mime_type;
        $kind = MediaPreviewPolicy::kind($mimeType);
        $scanCleared = MediaPreviewPolicy::isScanCleared((string) $asset->status);

        $blockedReason = match (true) {
            $kind === 'other' => 'type',
            ! $scanCleared => 'scan',
            default => null,
        };

        $embeddable = $blockedReason === null;

        return response()->json([
            'id' => (int) $asset->id,
            'kind' => $kind,
            'mimeType' => $mimeType,
            'originalName' => (string) $asset->original_name,
            'sizeBytes' => (int) $asset->size_bytes,
            'status' => (string) $asset->status,
            'embeddable' => $embeddable,
            'blockedReason' => $blockedReason,
            'previewUrl' => $embeddable
                ? "/api/workspaces/{$workspace}/media/{$media}/preview"
                : null,
            'pageCount' => $embeddable && $kind === 'pdf' ? $this->pageCount($asset) : null,
        ]);
    }

    /**
     * Sayfa sayısı dosyanın KENDİ baytlarından okunur; okunamıyorsa
     * `null` döner ve ekran sayfa gezintisini hiç çizmez.
     */
    private function pageCount(MediaAsset $asset): ?int
    {
        $disk = Storage::disk('local');
        $path = (string) $asset->disk_path;

        if (! $disk->exists($path)) {
            return null;
        }

        return MediaPreviewPolicy::pdfPageCount((string) $disk->get($path));
    }
}
