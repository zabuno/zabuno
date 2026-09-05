<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rating;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Rating\Port\RatingReplyRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Domain\Rating\RatingSubject;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * SAHİBİN YANITI — `docs/116` §4 (P6).
 *
 * ═══ YANIT VERİR, KALDIRMAZ ═══
 *
 * Bu denetleyici puana DOKUNMAZ. `rating_signals` ve `rating_scores`
 * tablolarına tek bir yazma yapmaz ve yapamaz: bağımlılık listesinde o
 * depolar yoktur. "Sahip puanı silemez" kuralı bir kontrolle değil, ELDE
 * OLMAYAN BİR BAĞIMLILIKLA korunuyor — çünkü kontrol kaldırılabilir,
 * olmayan bir bağımlılık ise fark edilmeden geri gelmez.
 *
 * Silinebilen bir ortalama, misafire "bu restoranın seçtiği oyların
 * ortalaması" olarak gösterilir; yani bir ölçüm değil, bir reklam.
 *
 * ═══ AMA SAHİP KENDİ SÖZÜNÜ DÜZELTEBİLİR ═══
 *
 * Yanıt sahibin KENDİ metnidir. Düzeltilemez olsaydı, yanlış yazdığı bir
 * cümleye sonsuza kadar mahkûm olurdu — ve o cümle misafirin gördüğü
 * menüde dururdu.
 *
 * Requirement ID'leri: RATING-REPLY-WRITE-01, RATING-REPLY-PERMISSION-04,
 * RATING-REPLY-OWN-WORDS-05.
 */
final class UpdateRatingReplyController extends Controller
{
    /**
     * Yanıtın en uzun hâli — karakter.
     *
     * Sınır SUNUCUDA yaşar, sütunda değil (`text` sütunu bilerek sınırsız).
     * Sebebi bu deponun kendi geçmişi: uzunluğu sütuna gömen bir alan,
     * PostgreSQL'de isteği reddederken SQLite'ta sessizce kabul eder — yani
     * aynı cümle yerelde kaydedilir, dağıtımda 500 verir.
     *
     * 600 karakter: masadaki misafirin telefonunda okunabilecek bir
     * paragraf. Daha uzunu menü satırının altında bir duvar olurdu ve
     * misafir onu okumaz — yani sahibin cümlesi de kaybolurdu.
     */
    public const MAX_BODY_LENGTH = 600;

    public function __construct(
        private readonly RatingReplyRepositoryPort $replies,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace, int $product): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        /*
            YANIT MARKANIN SESİDİR.

            Menüyü yayınlayamayan bir rol, misafirin gördüğü menüde restoran
            adına cümle de kuramaz. Ret 404'tür: "bu ürün var ama sana
            kapalı" bilgisi bile kiracı sınırının dışına çıkmaz.
        */
        if (! $this->authorization->can($userId, Permission::RatingReply, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->productBelongsToWorkspace($workspace, $product)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $body = trim((string) $request->input('body', ''));

        if ($body === '') {
            // Boş bir yanıt yayınlamak, misafire boş bir kap göstermektir.
            // Yanıtı KALDIRMAK ayrı bir istektir (DELETE) ve öyle kalmalı:
            // "boş gönder = sil" bir gün kazayla silinen bir cümledir.
            return response()->json(['reason' => 'reply_empty'], 422);
        }

        if (mb_strlen($body) > self::MAX_BODY_LENGTH) {
            /*
                SESSİZ KIRPMA YASAK. Sahibin cümlesini kırpıp yayınlamak,
                ona yazmadığı bir cümleyi söyletmektir — ve o cümle
                misafirin gördüğü menüde durur.
            */
            return response()->json([
                'reason' => 'reply_too_long',
                'maxLength' => self::MAX_BODY_LENGTH,
            ], 422);
        }

        $this->replies->put($workspace, RatingSubject::Product->value, $product, $body, $userId);

        return response()->json(['status' => 'published']);
    }

    private function productBelongsToWorkspace(int $workspace, int $product): bool
    {
        return DB::table('products')
            ->where('id', $product)
            ->where('workspace_id', $workspace)
            ->exists();
    }
}
