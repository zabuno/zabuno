<?php

declare(strict_types=1);

namespace App\Support\Localization\Negotiation;

use Illuminate\Http\Request;

/**
 * KAYNAK DİL — zincirin son halkası (`docs/120` §4.2).
 *
 * Yapılandırma dosyasını değil, O ANDA ÇALIŞAN dili döndürür. Fark önemli:
 * yapılandırmayı döndürseydi, dili bilerek ayarlamış olan bir taraf (bir
 * konsol komutu, bir testin kurduğu bağlam, ileride bir kiracı ayarı)
 * sessizce ezilirdi. Sinyalsiz bir istekte doğru davranış "hiçbir şeyi
 * değiştirme"dir ve çalışan dili geri vermek tam olarak bunu yapar.
 *
 * Bu çözücü her zaman bir cevap üretir — zincirin sonunda `null` dönmesi,
 * uygulamanın dilsiz kalması demek olurdu.
 */
final class SourceLanguageResolver implements LanguageResolver
{
    public function resolve(Request $request, array $options): ?string
    {
        return app()->getLocale();
    }
}
