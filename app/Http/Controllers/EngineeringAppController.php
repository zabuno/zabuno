<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Localization\SiteText;
use Illuminate\Contracts\View\View;

/**
 * Mühendislik kabuğu — `docs/50` §3 shell ailesinin son üyesi, `docs/98` FF-66.
 *
 * Release readiness, güvenlik kanıtı ve AI denetim izi bugüne kadar platform
 * (finans/plan) kabuğunun içinde yaşıyordu. Aynı kişi olabilir, aynı İŞ
 * değil: plan fiyatı belirleyen ekranla "bu sürüm çıkabilir mi" ekranı aynı
 * kenar çubuğunda durunca ikisi de birbirinin gürültüsü olur (`docs/69`
 * madde 3'ün 🔶 sebebi). Yetki aynı: superadmin.
 */
final class EngineeringAppController extends Controller
{
    public function __invoke(SiteText $text): View
    {
        // Sekme başlığı katalogdan: Blade'e sabit dize yazmak çevrilemez
        // borcu büyütürdü (I18N-SSR-RATCHET-16).
        return view('engineering-app', ['st' => $text->all()]);
    }
}
