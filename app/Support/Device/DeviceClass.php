<?php

declare(strict_types=1);

namespace App\Support\Device;

use Illuminate\Http\Request;

/**
 * Hangi cihaz sınıfına hizmet veriyoruz — SUNUCUDA, ilk bayt gönderilmeden önce.
 *
 * Sahibin kuralı: "responsive kod yazarak tüm kodları her cihazda fazlaca
 * yükleyen değil; cihazı sorgulayıp, cihaza uygun frontend kodunu, cihaza
 * yükleyen." Yani seçim tarayıcıda medya sorgusuyla DEĞİL, burada yapılır ve
 * sonucu farklı bir JavaScript paketidir.
 *
 * Fark önemsiz değil: medya sorgusuyla yapılan uyarlamada telefon, masaüstü
 * düzeninin kodunu da indirir ve ayrıştırır — sonra onu gizler. 320 pikselde
 * indirilen her fazladan kilobayt, kullanıcının beklediği süredir.
 *
 * ## Sinyal sırası
 *
 * 1. `Sec-CH-UA-Mobile` — İstemci İpucu. Yapılandırılmış, tek amaçlı ve
 *    tarayıcının kendi beyanı; tahmin değil.
 * 2. User-Agent metni — ipucu göndermeyen tarayıcılar için.
 * 3. Varsayılan: **mobil**.
 *
 * Varsayılanın mobil olması bilinçli. Yanlış tahmin iki yönde de olabilir ama
 * bedeli simetrik değildir: telefona masaüstü paketi göndermek, dar ekranda
 * kullanılamayan bir arayüz ve boşa harcanmış indirme demektir; masaüstüne
 * mobil paket göndermek ise yalnız daha sade bir düzen demektir — çalışır.
 * Belirsizlikte, çalışan tarafa düşülür.
 */
enum DeviceClass: string
{
    case Mobile = 'mobile';
    case Desktop = 'desktop';

    public static function detect(Request $request): self
    {
        $hint = $request->header('Sec-CH-UA-Mobile');

        if (is_string($hint) && $hint !== '') {
            // Yapılandırılmış başlık sözdizimi: `?1` doğru, `?0` yanlış.
            return trim($hint) === '?1' ? self::Mobile : self::Desktop;
        }

        $agent = (string) $request->header('User-Agent', '');

        if ($agent === '') {
            return self::Mobile;
        }

        // iPad'i masaüstü sayıyoruz: bağlam menüsü ve yan panel için yeterli
        // genişliği var, ve iPadOS zaten kendini masaüstü olarak tanıtıyor.
        if (preg_match('/\b(iPhone|iPod|Android.+Mobile|Windows Phone|BlackBerry|IEMobile|Opera Mini)\b/i', $agent) === 1) {
            return self::Mobile;
        }

        if (preg_match('/\b(Macintosh|Windows NT|X11|CrOS|iPad|Android(?!.+Mobile))\b/i', $agent) === 1) {
            return self::Desktop;
        }

        return self::Mobile;
    }

    /** Vite giriş noktası adı: `resources/js/{surface}.{device}.tsx`. */
    public function entryFor(string $surface): string
    {
        return sprintf('resources/js/%s.%s.tsx', $surface, $this->value);
    }
}
