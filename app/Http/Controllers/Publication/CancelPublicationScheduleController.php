<?php

declare(strict_types=1);

namespace App\Http\Controllers\Publication;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Publication\Port\PublicationSchedulePort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Kurulmuş bir planı iptal eder — ya da çıkmamış bir yayının uyarısını
 * kapatır.
 *
 * İPTAL, PLANIN KENDİSİ KADAR ÖNEMLİDİR: zam kararından vazgeçen bir sahip,
 * gece 03:00'e kadar tırnak yiyerek beklemek zorunda kalmamalı. İptal
 * kaydı SİLMEZ — plan `cancelled` olarak durur, çünkü "o gece ne oldu"
 * sorusunun cevabı bir gün sorulur.
 *
 * AYNI DÜĞME İKİ İŞ YAPAR ÇÜNKÜ SAHİP İÇİN TEK BİR İŞTİR: "bu planı
 * ekranımdan kaldır". Bekleyen bir plan için bu gerçek bir iptaldir
 * (`cancel`); çıkmamış bir yayın için yalnız uyarıyı kapatmaktır
 * (`acknowledge`) — kaydın `failed` hâli yerinde kalır. İkisini ayrı rotalara
 * bölmek, sahibin hangisine bastığını düşünmesini isterdi; oysa ürün
 * gerçeğini zaten sunucu biliyor.
 */
final class CancelPublicationScheduleController extends Controller
{
    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly PublicationSchedulePort $schedules,
    ) {}

    public function __invoke(Request $request, int $workspace, int $menu, int $schedule): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::MenuPublish, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($this->schedules->cancel($workspace, $menu, $schedule)) {
            return response()->json(['id' => $schedule, 'state' => 'cancelled'], 200);
        }

        if ($this->schedules->acknowledge($workspace, $menu, $schedule)) {
            /*
                Dönen `state` UYDURULMAZ: kayıt `failed` olarak duruyorsa
                cevap da `acknowledged` der, `cancelled` demez. Sahip
                geçmişte "iptal ettim" satırı görüp yayının patladığını
                unutmamalı.
            */
            return response()->json(['id' => $schedule, 'state' => 'acknowledged'], 200);
        }

        return response()->json(['message' => 'Not Found.'], 404);
    }
}
