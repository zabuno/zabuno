<?php

declare(strict_types=1);

namespace App\Http\Controllers\Content;

use App\Domain\Content\PageEnvironment;
use App\Domain\Content\PageGate;
use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use App\Support\Localization\SiteText;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Kurumsal sitenin TEK giriş kapısı — FF-117, yönerge §3 ve §7.
 *
 * Site haritasındaki 414 yol için 414 denetleyici ya da 414 Blade dosyası
 * üretilmez. Her yol kütükte bir kayıttır; bu denetleyici o kaydı bulur,
 * `PageGate`'e sorar ve kararı uygular. Bir sayfayı açmak için koddan bir
 * bileşen silinmez — yalnız kontrollü yayın durumu değişir.
 */
final class ShowCorporatePageController extends Controller
{
    /**
     * Bakım yanıtının `Retry-After` değeri.
     *
     * Gerçekçi olmalı ve uydurulmamalı: yarım saat, kısa bir bakım için dürüst
     * bir tahmindir. Uzun sürecek bir iş 503 değil, planlı bir yayın durumu
     * meselesidir.
     */
    private const int RETRY_AFTER_SECONDS = 1800;

    public function __construct(private readonly SiteText $siteText) {}

    public function __invoke(Request $request): SymfonyResponse
    {
        $path = rtrim($request->getPathInfo(), '/').'/';

        $page = ContentPage::query()->where('canonical_path', $path)->first();

        // Kütükte olmayan bir yol için hazırlanıyor ekranı göstermek, olmayan
        // bir sayfayı yapıyormuş gibi göstermek olurdu.
        if ($page === null) {
            abort(404);
        }

        // Şablon bir DESENDİR (`/tr/blog/{slug}/`), bir sayfa değil. Dış
        // bağlantı da bu sitede bir sayfa değildir.
        if ($page->is_template || $page->is_external) {
            abort(404);
        }

        $decision = PageGate::decide(
            $page->status(),
            /*
                Ortam YAPILANDIRMADAN okunur, `APP_ENV`'den türetilmez
                (`config/content.php`). Türetseydik yerelde ve testte staging
                davranışı çıkardı; asıl tehlike ise tersidir — yapılandırması
                unutulmuş bir sunucunun taslakları 200 ile sunması. Varsayılan
                bu yüzden production.
            */
            PageEnvironment::tryFrom((string) config('content.page_environment')) ?? PageEnvironment::Production,
            /*
                Önizleme yetkisi HENÜZ YOK: imzalı önizleme token'ı bu paketin
                dışında. Varsayılanı `false` bırakmak, yanlış tarafta hata
                yapmamak demek — `true` bırakmak taslakları herkese açardı.
            */
            false,
            $page->was_ever_published,
        );

        if ($decision->mode === 'not-found') {
            abort(404);
        }

        $locale = SiteText::pick($page->locale);

        if ($decision->mode === 'content') {
            /*
                Gerçek içerik şablonu henüz yok; sayfa yayına alındığında bu dal
                içerik bloklarını çizecek. Bugün yayınlanmış tek bir kurumsal
                sayfa yok, dolayısıyla bu dal yalnız testte çalışıyor ve
                tutulmayacak bir söz vermiyor.
            */
            return $this->withRobots(
                response()->view('content.page', [
                    'page' => $page,
                    'st' => $this->siteText->all($locale),
                ], 200),
                $decision->robots,
            );
        }

        $response = $this->withRobots(
            response()->view('content.under-construction', [
                'page' => $page,
                'stage' => $this->siteText->get($page->status()->translationKey(), $locale),
                'isMaintenance' => $decision->mode === 'maintenance',
                'st' => $this->siteText->all($locale),
            ], $decision->statusCode),
            $decision->robots,
        );

        if ($decision->statusCode === 503) {
            $response->headers->set('Retry-After', (string) self::RETRY_AFTER_SECONDS);
        }

        return $response;
    }

    private function withRobots(SymfonyResponse $response, string $robots): SymfonyResponse
    {
        // Robots kararı KAPIDAN gelir; şablonda ikinci kez yazılmaz.
        $response->headers->set('X-Robots-Tag', str_replace(',', ', ', $robots));

        return $response;
    }
}
