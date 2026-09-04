<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Media\Port\MediaProcessingJobPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * KUYRUK — "takıldı mı, yoksa hâlâ çalışıyor mu?" (kanonik kaynak:
 * `docs/reference/media-manager/Medya Yonetimi v2.dc.html`, ekran etiketi
 * "Kuyruk"; gerekçe `docs/108` §3 madde 5).
 *
 * Yükleme ve yeniden üretim iş üretir; o iş bugüne kadar
 * `media_processing_jobs` tablosuna yazılıp hiçbir ekranda görünmüyordu.
 * Sahip on fotoğraf yükleyip kütüphanede önizleme çıkmadığını gördüğünde,
 * cevabı olmayan bu soruyla baş başa kalıyor ve aynı fotoğrafı tekrar
 * tekrar yükleyerek kotasını kendi eliyle dolduruyordu.
 *
 * SALT OKUNUR. Burada iş başlatılmaz: "yeniden dene", var olan tek-varlık
 * yeniden üretim ucuna gider (`ReprocessMediaController`). Kuyruğun kendi
 * işleme hattı yoktur ve olmamalıdır.
 */
final class ListMediaProcessingJobsController extends Controller
{
    public function __construct(
        private readonly MediaProcessingJobPort $jobs,
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

        return response()->json([
            'data' => $this->jobs->recent($workspace),
            'counts' => $this->jobs->counts($workspace),
        ]);
    }
}
