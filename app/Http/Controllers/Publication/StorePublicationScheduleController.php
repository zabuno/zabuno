<?php

declare(strict_types=1);

namespace App\Http\Controllers\Publication;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Publication\Exception\UnreadyDraftException;
use App\Application\Publication\Port\PublicationSchedulePort;
use App\Application\Publication\UseCase\AssembleDraftSnapshot;
use App\Application\Publication\UseCase\BuildScheduleOptions;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Yayını ileri bir zamana kurar — kanonik kaynaktaki "Planla".
 *
 * İKİ KARAR BURADA VERİLİR VE İKİSİ DE ÜRÜNSELDİR:
 *
 * 1. TASLAK ŞİMDİ DOĞRULANIR. Hazır olmayan bir taslak gece 03:00'te değil,
 *    düğmeye basıldığı an reddedilir. Aksi hâlde sahip "kuruldu" yazısını
 *    görür, uyur ve sabah menüsünün değişmediğini müşteriden öğrenirdi.
 *
 * 2. SNAPSHOT ŞİMDİ DONAR. Yayın anında taslaktan yeniden üretilseydi,
 *    akşam yarım bırakılmış bir düzenleme gece kimse bakmıyorken misafirin
 *    önüne çıkardı. Sahip neyi onayladıysa o yayınlanır.
 */
final class StorePublicationScheduleController extends Controller
{
    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly PublicationSchedulePort $schedules,
        private readonly AssembleDraftSnapshot $assembler,
    ) {}

    public function __invoke(Request $request, int $workspace, int $menu): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::MenuPublish, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $raw = $request->input('scheduledFor');

        if (! is_string($raw) || $raw === '') {
            return response()->json(['message' => 'A moment is required.'], 422);
        }

        try {
            $scheduledFor = Carbon::parse($raw)->utc();
        } catch (InvalidFormatException) {
            return response()->json(['message' => 'That moment could not be read.'], 422);
        }

        if (! BuildScheduleOptions::isWithinHorizon(now(), $scheduledFor)) {
            /*
                Geçmiş bir an HİÇ ÇALIŞMAYACAK bir yayındır ve sahip onu
                kurulmuş sanır. Çok uzak bir an ise donmuş içeriği sahibin
                unuttuğu bir noktaya taşır.
            */
            return response()->json(['message' => 'That moment is not schedulable.'], 422);
        }

        try {
            $assembled = $this->assembler->forMenu($workspace, $menu);
        } catch (UnreadyDraftException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($assembled === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $record = $this->schedules->schedule(
            $workspace,
            $menu,
            $assembled['locationId'],
            $scheduledFor,
            $assembled['snapshot'],
            $assembled['visibleItemIds'],
            $assembled['brandId'],
            $userId,
        );

        return response()->json([
            'id' => $record->id,
            'scheduledFor' => $record->scheduledFor,
            'state' => $record->state,
            'timeZone' => BuildScheduleOptions::TIME_ZONE,
        ], 201);
    }
}
