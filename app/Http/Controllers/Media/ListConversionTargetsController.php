<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Media\Port\MediaConversionPort;
use App\Application\Media\Port\MediaFormatSupportPort;
use App\Domain\Authorization\Permission;
use App\Domain\Media\ConversionTarget;
use App\Domain\Media\ConversionTargetCatalogue;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * DÖNÜŞTÜR — "eski biçimleri modern biçime çevir" (kanonik kaynak:
 * `docs/reference/media-manager/Medya Yonetimi v2.dc.html`, ekran etiketi
 * "Dönüştür"; hedef listesi `docs/108` §6.3).
 *
 * SALT OKUNUR. Bu uç tek bir dosyayı bile değiştirmez; okumak bir maliyet
 * doğurmadığı için hız sınırı da yoktur.
 *
 * Cevap DÖRT şey taşır ve dördü de ayrı ayrı durur:
 *
 *   1. `targets` — kaynağın dört hedefi, kaynağın sırasıyla, KAYNAĞIN
 *      yüzdeleriyle. `claimedSavingPercent` adı bunun bir İDDİA olduğunu
 *      söyler; ölçüm değildir.
 *   2. Her hedefin `supported` ve `limitation` alanı — BU KURULUMDA
 *      yapılabiliyor mu, yapılamıyorsa neden. Kaynağın listesi TAM,
 *      ürünün yeteneği değil; farkı gizlemek sahibi olmayan bir yeteneğe
 *      güvendirirdi. Sebep bir KOD döner, cümle değil: cümle çeviri
 *      kataloğunda durur (`docs/37`).
 *   3. `sources` — seçilebilir HAZIR görseller, adı ve gerçek boyutuyla.
 *   4. `measured` — GERÇEKTEN tartılmış bayt, biçim başına. Hiç ölçüm
 *      yoksa alan BOŞTUR ve ekran o bölümü hiç çizmez. Sıfır yazmak "hiç
 *      kazanmadın" demek olurdu; oysa doğru cevap "henüz ölçülmedi".
 *
 * İzin: okumak YÖNETME izni ister (`ListDerivativeRulesController` ile
 * aynı gerekçe). Üye olmayan 404 görür.
 */
final class ListConversionTargetsController extends Controller
{
    public function __construct(
        private readonly MediaConversionPort $conversion,
        private readonly MediaFormatSupportPort $support,
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

        $batchLimit = (int) config('media-slots.regeneration.batch_limit', 25);

        $targets = array_map(
            fn (ConversionTarget $target): array => [
                'format' => $target->format,
                'family' => $target->family,
                'claimedSavingPercent' => $target->claimedSavingPercent,
                'supported' => $this->support->supports($target->format),
                'limitation' => $this->support->limitation($target->format),
            ],
            ConversionTargetCatalogue::canonical()->all(),
        );

        return response()->json([
            'targets' => $targets,
            /*
                Kaynak listesi tek çağrıda işlenebilecekten UZUN olabilir;
                o yüzden liste sınırın kendisiyle değil, ondan geniş bir
                pencereyle çekilir: sahip seçebildiğinden fazlasını görür ve
                "kalan" sayısını cevaptan okur. Listeyi sınıra kısmak,
                kütüphanesinin bir kısmını yok gibi göstermek olurdu.
            */
            'sources' => $this->conversion->convertibleAssets($workspace, $batchLimit * 8),
            'measured' => $this->conversion->measuredByFormat($workspace),
            // Tek çağrının işleyebileceği azami dosya: ekran "hepsi bir
            // seferde bitmeyecek" diyebilsin diye açıkça söylenir.
            'batchLimit' => $batchLimit,
        ]);
    }
}
