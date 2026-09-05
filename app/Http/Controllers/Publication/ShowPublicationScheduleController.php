<?php

declare(strict_types=1);

namespace App\Http\Controllers\Publication;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Publication\Dto\ScheduledPublicationRecord;
use App\Application\Publication\Port\PublicationSchedulePort;
use App\Application\Publication\UseCase\BuildScheduleOptions;
use App\Domain\Authorization\Permission;
use App\Domain\Publication\ScheduledPublicationOutcome;
use App\Domain\Publication\ScheduledPublicationState;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * "Planla" panelinin okunan yüzü: kurulu bir plan var mı, ve hangi saatler
 * önerilebilir.
 *
 * Saatler SUNUCUDA ve ŞUBENİN saat diliminde hesaplanır
 * (`BuildScheduleOptions`, `docs/62`). Ekran yalnız dönen ANI, dönen
 * dilimle okunabilir saate çevirir; hesap yapmaz.
 *
 * PLANIN SONUCU DA SUNUCUDA KARARA BAĞLANIR (`ScheduledPublicationOutcome`).
 * Ekran "vakti geçti mi" hesabını kendi yapsaydı, tarayıcının saati yanlış
 * olan bir bilgisayarda sahip çıkmış bir yayını "çıkmadı" sanır ya da
 * tersini görürdü. Dönen `status` bir GÖRÜŞ değil, sunucunun kararıdır.
 */
final class ShowPublicationScheduleController extends Controller
{
    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly PublicationSchedulePort $schedules,
    ) {}

    public function __invoke(Request $request, int $workspace, int $menu): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $plan = $this->schedules->unresolvedForMenu($workspace, $menu);

        /*
            Dilim ŞUBEDEN gelir ve ekrana da AYNI dilim gönderilir. Sunucu
            başka bir dilimde hesaplayıp ekran başka bir dilimde yazsaydı,
            sahibin okuduğu saat ile menünün gerçekten değişeceği an
            ayrışırdı — iki sayının tutmadığı yerde sahip hangisine
            güveneceğini bilemez.

            Okunamıyorsa `null` gider ve seçenek listesi boş kalır: bilinmeyen
            bir saat için "bu gece 03:00" demek tutulamayacak bir sözdür.
        */
        $timeZone = $this->schedules->timezoneForMenu($workspace, $menu);

        return response()->json([
            'timeZone' => $timeZone,
            'plan' => $plan === null ? null : $this->describe($plan),
            'options' => BuildScheduleOptions::forNow(now(), $timeZone),
        ], 200);
    }

    /**
     * @return array{id:int,scheduledFor:string,state:string,status:string,needsAttention:bool}
     */
    private function describe(ScheduledPublicationRecord $plan): array
    {
        $outcome = ScheduledPublicationOutcome::resolve(
            ScheduledPublicationState::from($plan->state),
            Carbon::parse($plan->scheduledFor),
            Carbon::parse($plan->touchedAt),
            now(),
        );

        return [
            'id' => $plan->id,
            'scheduledFor' => $plan->scheduledFor,
            'state' => $plan->state,
            'status' => $outcome->value,
            /*
                Ekranın uyarı mı yoksa bilgi mi çizeceğini SUNUCU söyler.
                Tarayıcı bu listeyi kendi tutsaydı, yeni bir hâl eklendiğinde
                sessizce "sorun yok" tarafına düşerdi — sessizlik tam da
                düzeltmeye çalıştığımız kusurun biçimidir.
            */
            'needsAttention' => $outcome->needsOwnerAttention(),
        ];
    }
}
