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

/**
 * SAHİP KENDİ SÖZÜNÜ GERİ ALIR — `docs/116` §4 (P6).
 *
 * ═══ BU, "SİLME YOK" KURALININ İSTİSNASI DEĞİL ═══
 *
 * Silinen şey misafirin ölçümü değil, restoranın kendi cümlesidir. Ayrım
 * keskin olmak zorunda, yoksa iki yanlıştan biri kaçınılmaz:
 *
 * - Yanıt da silinemez olsaydı, sahip yanlış yazdığı bir cümleye sonsuza
 *   kadar mahkûm olurdu ve o cümle misafirin gördüğü menüde dururdu.
 * - Puan da silinebilir olsaydı, ortalama bir ölçüm olmaktan çıkıp bir
 *   pazarlama sayısına dönerdi.
 *
 * Bu denetleyici `RatingReplyRepositoryPort` DIŞINDA hiçbir depoya
 * bağlanmaz; sinyale ya da türetilmiş puana uzanacak bir eli yoktur.
 *
 * Requirement ID'leri: RATING-REPLY-NO-DELETE-02, RATING-REPLY-OWN-WORDS-05.
 */
final class DeleteRatingReplyController extends Controller
{
    public function __construct(
        private readonly RatingReplyRepositoryPort $replies,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace, int $product): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::RatingReply, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $this->replies->remove($workspace, RatingSubject::Product->value, $product);

        /*
            YOK OLAN BİR YANITI SİLMEK DE BAŞARIDIR.

            404 dönseydi, sahip "yanıtım var mıydı?" sorusunu denemeyle
            cevaplardı; sonuç her iki hâlde de aynı: o tabakta restoranın
            söylediği bir cümle yok.
        */
        return response()->json(['status' => 'withdrawn']);
    }
}
