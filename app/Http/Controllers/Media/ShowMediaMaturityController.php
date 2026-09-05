<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Media\Port\MediaEvidencePort;
use App\Application\Media\UseCase\AssessMediaMaturity;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OLGUNLUK — kanonik kaynak `docs/reference/panel-v3/MedyaModulu.dc.html`,
 * `data-screen-label="Olgunluk"`; seviye sözlüğü `docs/108` §6.7.
 *
 * SALT OKUNURDUR ve hız sınırsızdır: yönlendirici koleksiyonunu ve test
 * paketini okur, hiçbir dosya işlemez, hiçbir satır yazmaz.
 *
 * ═══ BU UÇ KENDİNİ ÖVEMEZ ═══
 *
 * Yanıtın ilk alanı `selfAssessed: true`'dur ve orada durması bir üslup
 * tercihi değil: bu ekran ürünün KENDİSİ hakkındaki iddiasıdır. Bağımsız
 * bir denetim raporu gibi okunursa, sahibi olmayan bir güvenceye
 * dayandırır.
 *
 * PUAN da bir kalite notu değildir: geçilen basamakların toplamıdır ve
 * tavanı yetenek sayısı × 4'tür. Başka bir ölçek (yüzde, harf notu,
 * "hazır/hazır değil") uydurmak, aynı sayıya olmayan bir anlam yüklerdi.
 *
 * KİRACI: yalnız çalışma alanını görebilen okur. Olgunluk kiracıya göre
 * DEĞİŞMEZ — ürünün durumu her kiracıda aynıdır — ama yine de yetki
 * sorulur: adres bir kiracıya aittir ve yabancıya 404 dönmeyen bir uç, o
 * kiracının VAR olduğunu söyler.
 *
 * METİN BURADA DEĞİLDİR. Uç yalnız anahtar, seviye ve KANIT REFERANSI
 * gönderir; yetenek adı ve seviye açıklaması çeviri kataloğunda durur
 * (`docs/37`).
 */
final class ShowMediaMaturityController extends Controller
{
    public function __construct(private readonly AuthorizationPort $authorization) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::MediaManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        /*
            Kanıt çözücü BURADA çözülür, kurucu imzasında değil: testler
            `MediaEvidencePort`'u kap üzerinden değiştirir ve denetleyici
            zaten oluşturulmuşsa o değişiklik geç kalırdı.
        */
        $assess = new AssessMediaMaturity(app(MediaEvidencePort::class));
        $capabilities = $assess();

        $achieved = 0;

        foreach ($capabilities as $capability) {
            $achieved += (int) $capability['level'];
        }

        return response()->json([
            'selfAssessed' => true,
            'score' => [
                'achieved' => $achieved,
                'possible' => count($capabilities) * AssessMediaMaturity::MAX_LEVEL,
            ],
            'capabilities' => $capabilities,
        ]);
    }
}
