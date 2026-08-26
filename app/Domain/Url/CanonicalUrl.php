<?php

declare(strict_types=1);

namespace App\Domain\Url;

/**
 * Bir sayfanın kanonik adresini üretir.
 *
 * Kanonik adres arama motoruna "bu içeriğin asıl adresi budur" der. Aynı
 * içerik izleme parametreli, farklı sıralı sorguyla veya sondaki slash ile
 * birden çok adresten erişilebilir; kanonik olmadan arama motoru hangisini
 * indeksleyeceğine kendi karar verir ve genelde yanlış olanı seçer.
 *
 * Bu sınıf normalizer ile AYNI kuralları kullanır — ikinci bir yerde
 * yeniden yazılsaydı, kanonik etiket ile yönlendirmenin farklı adresler
 * üretmesi an meselesiydi.
 */
final class CanonicalUrl
{
    public function __construct(
        private readonly UrlPolicy $policy,
        private readonly UrlNormalizer $normalizer,
    ) {}

    /**
     * @param  array<string, string|list<string>>  $query
     */
    public function for(string $baseUrl, string $path, array $query = []): string
    {
        // İzleme parametreleri kanonikten çıkar: bir menü, Instagram'dan mı
        // yoksa doğrudan mı açıldığına göre farklı bir sayfa değildir.
        foreach ($this->policy->trackingParameters() as $tracking) {
            unset($query[$tracking]);
        }

        $normalized = $this->normalizer->normalize($path, $query);

        return rtrim($this->host($baseUrl), '/').$normalized->target();
    }

    private function host(string $baseUrl): string
    {
        $configuredHost = $this->policy->canonicalHost();

        if ($this->policy->enforcesHost() && $configuredHost !== null) {
            return $this->policy->canonicalScheme().'://'.$configuredHost;
        }

        // Host yapılandırılmamışsa isteğin kendi host'u kullanılır. Bu,
        // beş farklı barındırıcıda aynı kodun çalışabilmesi için gereklidir
        // (`docs/38` §8) — ama üretimde `enforce_host` açılmalıdır, aksi
        // hâlde sahte bir Host başlığı kanonik adresi kaydırabilir.
        return $baseUrl;
    }
}
