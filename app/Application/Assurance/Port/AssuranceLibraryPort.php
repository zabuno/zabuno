<?php

declare(strict_types=1);

namespace App\Application\Assurance\Port;

use App\Domain\Assurance\AssuranceStatement;

/**
 * Güvence beyanlarının kaynağı — FF-252.
 *
 * `LegalLibraryPort` ile aynı gerekçe: metin bugün kodda yaşıyor ama orada
 * kalmak zorunda değil. Aradaki fark, bu portun arkasındaki metnin BÜYÜK
 * KISMININ elle yazılmamış olması — alt işleyen listesi kasadan, hizmet
 * seviyesi yapılandırmadan, kanıtlar depodaki kapıların adlarından geliyor.
 * Port bu yüzden bir "kütüphane" değil bir DERLEYİCİ arkasında durur.
 */
interface AssuranceLibraryPort
{
    /**
     * Güven merkezi — kurumsal alıcının satın almadan önce sorduğu sorular.
     */
    public function trustCentre(): AssuranceStatement;

    /**
     * Erişilebilirlik beyanı — neyi ölçüyoruz, hangi kapı koruyor, neyi
     * ölçmedik.
     */
    public function accessibilityStatement(): AssuranceStatement;
}
