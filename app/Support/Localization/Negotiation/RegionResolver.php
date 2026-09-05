<?php

declare(strict_types=1);

namespace App\Support\Localization\Negotiation;

use Illuminate\Http\Request;

/**
 * BÖLGE — saat dilimi (`docs/120` §4.2).
 *
 * Bölge dili SEÇMEZ, BELİRSİZLİĞİ ÇÖZER. İstanbul'daki bir tarayıcı `en`
 * diyorsa dil İngilizcedir; saat dilimine bakıp Türkçeye çevirmek,
 * kullanıcının açık ayarını görmezden gelmektir. Bu yüzden yöntemin
 * ağırlığı tarayıcıdan SONRA gelir ve yalnız kendinden ağır hiçbir yöntem
 * çözemediğinde konuşur — bunu sağlayan şey kodda bir `if` değil,
 * zincirdeki sırasıdır.
 *
 * Saat dilimi sunucuda BİLİNMEZ; tarayıcı `Intl…timeZone` ile onu bir çereze
 * yazar. Sunucunun IP'den tahmin etmesi istenmedi: VPN, kurumsal ağ ve
 * mobil operatör bu tahmini düzenli olarak yanlışlar, ve yanlış bir tahmin
 * sessizce yanlış bir dil demektir.
 *
 * Eşleme tablosu AYARDIR ve KISADIR: yalnız gerçekten tek bir baskın dile
 * işaret eden saat dilimleri yazılır. Belirsiz bir bölgeye dil atamak,
 * belirsizliği çözmek değil onu gizlemektir.
 */
final class RegionResolver implements LanguageResolver
{
    public function resolve(Request $request, array $options): ?string
    {
        $cookie = is_string($options['cookie'] ?? null) ? $options['cookie'] : null;

        if ($cookie === null) {
            return null;
        }

        $timezone = $request->cookies->get($cookie);

        if (! is_string($timezone) || $timezone === '') {
            return null;
        }

        /** @var array<string, string> $hints */
        $hints = is_array($options['hints'] ?? null) ? $options['hints'] : [];

        return $hints[$timezone] ?? null;
    }
}
