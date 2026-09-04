<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Media\Port\MediaRegenerationPort;
use App\Domain\Authorization\Permission;
use App\Domain\Media\DerivativeCatalogue;
use App\Domain\Media\DerivativeRule;
use App\Domain\Media\SlotCatalogue;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BOYUT MOTORU — "her yüklenen görselden hangi boyutlar üretilecek?"
 * (kanonik kaynak: `docs/reference/media-manager/Medya Yonetimi v2.dc.html`,
 * ekran etiketi "Boyut motoru"; somut tablo `docs/108` §6.1).
 *
 * SALT OKUNUR. Bu uç tek bir dosyayı bile değiştirmez — değiştirmemesi
 * kuralın kendisidir: yeni kural yalnız yeni yüklemelere uygulanır, eskiler
 * ancak AÇIK bir yeniden üretim işiyle değişir (`docs/108` §4).
 *
 * Cevap üç şey taşır:
 *
 *   1. `rules` — kaynağın adlandırdığı altı türev, işleriyle birlikte.
 *   2. `regeneration` — "yeniden üretimi başlatırsam kaç dosya etkilenir".
 *      GERÇEK sayıdır; uydurulmuş bir sayı kararı bilgisiz bırakırdı.
 *   3. `measured` — gerçekten tartılmış bayt. Kaynak "AVIF ~%74 küçük" gibi
 *      rakamlar gösteriyor; onlar biçimlerin genel iddiasıdır, BU kiracının
 *      dosyalarının ölçümü değil. Hiç ölçüm yoksa üçü de sıfır döner ve
 *      ekran "ölçülen kazanç" bölümünü hiç çizmez.
 *
 * İzin: okumak YÖNETME izni ister (`ListMediaAuditsController` ile aynı
 * gerekçe). Buradaki sayılar kiracının depolama davranışını anlatır ve
 * ekipteki herkesin işi değildir. Üye olmayan 404 görür — 403 "böyle bir
 * kiracı var ama sana kapalı" der ve bu da bir bilgidir.
 */
final class ListDerivativeRulesController extends Controller
{
    public function __construct(
        private readonly MediaRegenerationPort $regeneration,
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

        $catalogue = DerivativeCatalogue::fromArray((array) config('media-slots.derivatives', []));
        $slots = SlotCatalogue::fromArray((array) config('media-slots.slots', []));

        /*
            KURAL ADLANDIRILDI DİYE ÜRETİLİYOR OLMAZ. Boru hattı bugün slot
            başına genişlik listesinden üretiyor; `producedBySlots` boş
            dönen bir kural, ekranda "bu ölçü henüz üretilmiyor" olarak
            yazılır. Bunu gizlemek, sahibi olmayan bir yeteneğe
            güvendirirdi.
        */
        $producers = $catalogue->producedBySlots($slots);

        $rules = array_values(array_map(
            static fn (DerivativeRule $rule): array => [
                'name' => $rule->name,
                'width' => $rule->width,
                'height' => $rule->height,
                'fit' => $rule->fit,
                'formats' => $rule->formats,
                'producedBySlots' => $producers[$rule->name] ?? [],
            ],
            $catalogue->all(),
        ));

        $stats = $this->regeneration->stats($workspace);

        return response()->json([
            'rules' => $rules,
            'regeneration' => [
                'affectedAssets' => $stats['affectedAssets'],
                'existingRenditions' => $stats['existingRenditions'],
                // Tek çağrının işleyebileceği azami dosya: ekran "hepsi bir
                // seferde bitmeyecek" diyebilsin diye açıkça söylenir.
                'batchLimit' => (int) config('media-slots.regeneration.batch_limit', 25),
            ],
            'measured' => $this->regeneration->measuredBytes($workspace),
        ]);
    }
}
