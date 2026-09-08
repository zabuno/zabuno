<?php

declare(strict_types=1);

namespace App\Domain\Legal;

/**
 * Üçüncü taraf bağımlılık envanteri — bir ekosistem için (FF-228).
 *
 * `packages` yalnız DOĞRUDAN bağımlılıkları taşır: manifestte adıyla
 * istenenler. Dolaylı olanlar sayılır (`transitiveCount`) ama listelenmez —
 * bu üründe dolaylı paket sayısı yüzlerledir ve hepsini bir hukuk sayfasına
 * basmak, okunmayacak bir duvar üretirdi. Tam liste kilit dosyasındadır ve
 * belge oraya yollar.
 *
 * `readable` yanlışsa liste boş demek DEĞİLDİR: kilit dosyası bu dağıtımda
 * okunamamıştır ve belge bunu söyler. Boş bir liste "hiç bağımlılık yok"
 * diye okunurdu.
 */
final readonly class DependencyInventory
{
    /** @param  list<DependencyLicense>  $packages */
    public function __construct(
        public string $ecosystem,
        public string $manifest,
        public array $packages,
        public int $transitiveCount,
        public bool $readable = true,
    ) {}

    /** @return list<string> Alfabetik sırayla, ad + sürüm + lisans. */
    public function lines(): array
    {
        return array_map(
            static fn (DependencyLicense $package): string => $package->sentence(),
            $this->packages,
        );
    }
}
