<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Application\Assurance\Port\AssuranceLibraryPort;
use App\Http\Controllers\Controller;
use App\Support\Localization\PageLanguage;
use App\Support\Site\SiteShell;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ERİŞİLEBİLİRLİK BEYANI — FF-252 (`docs/107` Faz 3).
 *
 * ═══ BU SAYFA EN KOLAY YALAN SÖYLENEN SAYFADIR ═══
 *
 * *"WCAG 2.1 AA uyumludur"* cümlesi bir denetim sonucudur; ölçülmeden
 * yazıldığında uydurmadır ve tam olarak bu sayfada, tam olarak bu biçimde
 * uydurulur. Beyan bu yüzden üç kümeye ayrılmıştır — ölçülen, bilinen
 * kusur, ölçülmeyen — ve üçünü ayıran şey bir sıfat değil, bir tür
 * (`ClaimState`). Bir dürüstlük kapısı (`AssuranceHonestyGateTest`) o türü
 * okuyor: uyum iddiası taşıyan bir cümle yalnız "sahip değiliz" hâlindeki
 * bir düğümün içinde geçebilir.
 *
 * ═══ OTURUM İSTEMEZ ═══
 *
 * Arayüzü kullanamayan biri oturum da açamıyor olabilir. Aynı gerekçe
 * `/help` için de yazıldı (`docs/89`): yardımın kapı tutması, en çok
 * ihtiyaç duyulduğu anda kapıyı kapatır.
 */
final class ShowAccessibilityStatementController extends Controller
{
    public function __construct(
        private readonly SiteShell $shell,
        private readonly AssuranceLibraryPort $assurance,
    ) {}

    public function __invoke(Request $request): View
    {
        $context = $this->shell->context($request, 'accessibility', '/accessibility');
        $statement = $this->assurance->accessibilityStatement($context['lang']->ui);
        $context['lang'] = PageLanguage::for($statement->language);

        return view('public.assurance', $context + [
            'statement' => $statement,
            /*
                ÖNSÖZ YÜZÜ — `conduit`. Erişilebilirlik bir durum değil bir
                AKIŞTIR: bir kişi bir yoldan geçmeye çalışıyor. Veri hattı
                tam olarak onu çizer — çizgi durur, ışık akar.
            */
            'prologueVariant' => 'conduit',
        ]);
    }
}
