<?php

declare(strict_types=1);

namespace App\Domain\Assurance;

/**
 * Bir güvence iddiasının DÖRT hâli — FF-252 (`docs/107` Faz 3).
 *
 * ═══ NEDEN BİR ENUM, NEDEN BİR CÜMLE DEĞİL ═══
 *
 * Güven merkezi ve erişilebilirlik beyanı, bir ürünün en kolay yalan
 * söylediği iki sayfadır. Yalanın biçimi hep aynıdır: ölçülmemiş bir şey,
 * ölçülmüş bir şeyle AYNI cümle yapısında yazılır. *"Kontrast oranları
 * WCAG'a uygundur"* ile *"ekran okuyucuyla test edilmiştir"* aynı sayfada
 * yan yana durduğunda okuyucu ikisini de aynı ağırlıkta okur — oysa
 * birincisinin bir kapısı vardır, ikincisinin hiç ölçümü yoktur.
 *
 * Bu yüzden hâl bir SIFAT değil bir TÜRDÜR: metnin içine gömülmüş bir
 * "muhtemelen" sözcüğü bir kapının okuyamayacağı bir şeydir, ama bir enum
 * değeri okunabilir. `AssuranceHonestyGateTest` tam olarak bunu okur.
 *
 * ═══ "BİLİNMİYOR" YEŞİL GÖSTERİLMEZ ═══
 *
 * `NotMeasured` bir eksiklik itirafıdır ve `Measured`in zayıf hâli DEĞİLDİR:
 * ikisi arasında bir orta yol yoktur. Global yönergenin cümlesi birebir bu:
 * *"Ölçüm yapılamadıysa sonuç 'geçti' değil 'bilinmiyor'dur ve bilinmeyen
 * bir sonuç yeşil gösterilmez."*
 */
enum ClaimState: string
{
    /**
     * ÖLÇÜLÜYOR — ve ölçen şey adıyla gösterilebilir.
     *
     * Bu hâli taşıyan her iddia bir kanıt (`AssuranceClaim::$evidence`)
     * taşımak ZORUNDA ve o kanıt depoda GERÇEKTEN var olmak zorunda:
     * `AssuranceHonestyGateTest` her birini dosya sisteminde ya da kayıtlı
     * artisan komutları arasında arar. Yani "ölçülüyor" demek, bir gün o
     * kapı silindiğinde sessizce yalana dönüşemez.
     */
    case Measured = 'measured';

    /**
     * ÖLÇÜLMEDİ — ve bu, "sorun yok" demek değildir.
     *
     * Bu hâl bir borç kaydıdır. Sayfa onu saklamaz, çünkü saklanan bir
     * eksik, okuyucunun ölçülmüş bir şey sandığı bir eksiktir.
     */
    case NotMeasured = 'not-measured';

    /**
     * ÖLÇÜLDÜ VE KUSURLU — bilinen bir eksik.
     *
     * `NotMeasured`ten ayrı, çünkü ikisi okuyucuya farklı şey söyler:
     * biri "bakmadık", öteki "baktık, bulduk, hâlâ duruyor". Bir alıcının
     * risk hesabında bu ikisi aynı satır değildir.
     *
     * Kanıt ZORUNLU: bir kusuru bulan ölçüm adıyla anılmalı, yoksa "bilinen
     * eksik" bir kanaat olur.
     */
    case KnownGap = 'known-gap';

    /**
     * SAHİP DEĞİLİZ — ve bunu SÖYLÜYORUZ.
     *
     * Sertifika, denetim raporu, rozet, hizmet kredisi… Bu hâl, sayfada bir
     * standardın ADININ geçebildiği TEK yerdir: dürüstlük kapısı yasaklı
     * terimleri ararken yalnız bu hâli (ve `NotMeasured`i) taşıyan
     * düğümlerin dışını tarar. Yani "ISO 27001 belgemiz yok" yazılabilir,
     * "ISO 27001 belgeliyiz" yazılamaz — ve aradaki farkı bir insanın
     * dikkati değil, bir kapı ayırır.
     */
    case NotHeld = 'not-held';

    /**
     * Kanıt ZORUNLU mu?
     *
     * Bir şeyi ölçtüğünü ya da bir kusuru bulduğunu söylemek, ölçen şeyi
     * göstermeyi gerektirir. Sahip olmadığını ya da bakmadığını söylemek
     * gerektirmez — orada gösterilecek bir kanıt zaten yoktur.
     */
    public function requiresEvidence(): bool
    {
        return $this === self::Measured || $this === self::KnownGap;
    }

    /**
     * Yasaklı terimlerin geçebildiği hâller.
     *
     * `AssuranceHonestyGateTest` çizilen HTML'den bu hâlleri taşıyan
     * düğümleri ÇIKARIR ve kalan metni tarar.
     */
    public function mayNameAStandard(): bool
    {
        return $this === self::NotHeld || $this === self::NotMeasured;
    }
}
