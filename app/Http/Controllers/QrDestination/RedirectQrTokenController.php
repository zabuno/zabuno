<?php

declare(strict_types=1);

namespace App\Http\Controllers\QrDestination;

use App\Application\Analytics\UseCase\RecordAnalyticsEvent;
use App\Application\Publication\Port\PublicMenuAddressPort;
use App\Application\QrDestination\Port\QrCodeRepositoryPort;
use App\Domain\Analytics\AnalyticsEventType;
use App\Domain\QrDestination\QrToken;
use App\Http\Controllers\Controller;
use App\Http\Responses\GuestDeadEnd;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class RedirectQrTokenController extends Controller
{
    public function __construct(
        private readonly QrCodeRepositoryPort $qrCodes,
        private readonly RecordAnalyticsEvent $recordAnalyticsEvent,
        private readonly PublicMenuAddressPort $addresses,
    ) {}

    public function __invoke(string $token): SymfonyResponse
    {
        try {
            $qrToken = QrToken::fromString($token);
        } catch (InvalidArgumentException) {
            return $this->notFound();
        }

        $qrCode = $this->qrCodes->findActiveByToken($qrToken->value());

        if ($qrCode === null) {
            return $this->notFound();
        }

        $this->recordAnalyticsEvent->handle(
            $qrCode->workspaceId,
            $qrCode->locationId,
            $qrCode->id,
            $qrCode->menuId,
            AnalyticsEventType::QrResolve,
        );

        // Hedef adı verilmiş route'tan üretilir, elle birleştirilmez: yol
        // bir gün değişirse basılı kod yine doğru yere gitmelidir.
        //
        // 302, 301 DEĞİL: bu bir "kalıcı taşıma" değil, işletmenin
        // değiştirebileceği bir eşlemedir. 301 tarayıcıda ve ara
        // katmanlarda kalıcı olarak önbelleklenir; menü hedefi değiştiğinde
        // masadaki kod eski adrese gitmeye devam eder ve bunu geri almanın
        // yolu yoktur.
        //
        // `no-store` aynı sebeple: önbelleklenen bir yönlendirme, basılı bir
        // QR'ı sessizce eski hedefe kilitler.
        // Hedef token yoludur ve bu bilinçlidir. QR akışı ile SEO akışı
        // meşru biçimde AYRIDIR:
        //
        //   /q/{token}      → 302 → /menu/{token}   (misafir; huni ölçülür)
        //   /menu/{key}/{slug}                       (arama; kanonik, indeksli)
        //
        // Taramayı kanonik adrese göndermek, "QR çözümlemesi → menü açılışı"
        // hunisinin ikinci yarısını ölçülemez hâle getirirdi: kanonik sayfa
        // hangi karekodun getirdiğini bilmez. Token sayfası ise `noindex`
        // ve kanonik adrese işaret eder, yani arama motoru ikisini
        // birleştirir.
        return redirect()->route('qr.publicMenu', ['token' => $qrToken->value()], 302)
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function notFound(): SymfonyResponse
    {
        // Tarayıcıda ham JSON gören bir misafir, ürünü bozuk sanır.
        // Yanıt her durumda aynıdır (QR-PUBLIC-404-UNIFORM-01).
        return GuestDeadEnd::respond(request());
    }
}
