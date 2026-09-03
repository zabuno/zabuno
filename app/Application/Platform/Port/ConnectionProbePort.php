<?php

declare(strict_types=1);

namespace App\Application\Platform\Port;

use App\Domain\Platform\Credential\ProbeResult;

/**
 * UYUMLULUK KATMANI — `docs/95` Faz 3, `docs/51` §4.5.
 *
 * Doktrin açık: "özel bir uç noktanın tam uyumluluğu VARSAYILMAZ" ve
 * "hangi portları desteklediği test edilmeden aday olmaz". Bu port o testi
 * yapar: bağlantının anahtarıyla, sağlayıcının en ucuz uç noktasına tek bir
 * çağrı atar ve sonucu sağlığa yazar.
 *
 * Neden gerçek bir çağrı: bir anahtarın doğru olup olmadığını yazılış
 * biçiminden anlamanın yolu yok. Superadmin panelde "kaydedildi" görüp
 * anahtarın yanlış olduğunu ancak ilk müşteri isteğinde — yani en kötü
 * anda — öğreniyordu.
 *
 * ÜCRET ÜRETMEZ: model listesi/uç nokta yoklaması token harcamaz. Bir
 * "merhaba" tamamlaması istemek, her denemede küçük de olsa fatura
 * üretirdi.
 */
interface ConnectionProbePort
{
    public function probe(int $connectionId): ProbeResult;
}
