<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal;

use App\Application\Legal\Port\LegalLibraryPort;
use App\Application\Legal\Port\SubprocessorRegistryPort;
use App\Application\Legal\Port\ThirdPartyLicensePort;
use App\Domain\Legal\LegalDocument;
use App\Domain\Legal\ServiceLevelCommitment;
use App\Infrastructure\Legal\Documents\AcceptableUsePolicy;
use App\Infrastructure\Legal\Documents\CookiePolicy;
use App\Infrastructure\Legal\Documents\DataProcessingAgreement;
use App\Infrastructure\Legal\Documents\DeliveryAndPerformancePolicy;
use App\Infrastructure\Legal\Documents\DistanceSalesAgreement;
use App\Infrastructure\Legal\Documents\KvkkDisclosure;
use App\Infrastructure\Legal\Documents\MarketingConsentText;
use App\Infrastructure\Legal\Documents\PreliminaryInformationForm;
use App\Infrastructure\Legal\Documents\PrivacyPolicy;
use App\Infrastructure\Legal\Documents\RefundPolicy;
use App\Infrastructure\Legal\Documents\ServiceLevelTerms;
use App\Infrastructure\Legal\Documents\TermsOfService;
use App\Infrastructure\Legal\Documents\ThirdPartyLicenses;

/**
 * Yasal belgelerin kütüphanesi — FF-198 (`docs/107` Faz 1.2), FF-216'da
 * dokuza, FF-228'de ON ÜÇE çıktı: kurumsal sözleşmeler (`docs/107` Faz 3.2,
 * `docs/140`) — veri işleme (`data-processing`), hizmet seviyesi (`sla`),
 * kabul edilebilir kullanım (`acceptable-use`) ve üçüncü taraf lisansları
 * (`third-party-licenses`).
 *
 * Metin BUGÜN kodda yaşıyor ve bu bilinçli: kod incelemesinden ve testten
 * geçer, sürümü Git'te izlenir ve hukukçu değişikliği bir diff olarak
 * görür. `ProductPageLibrary` ile aynı karar.
 *
 * İngilizce varsayılan kaynaktır; sahibin onayıyla Türkçe belge kaynakları
 * ayrı sınıflarda tutulur. Önbellek belge anahtarı ve dile göre ayrılır.
 *
 * ═══ BELGELER ARTIK TEMBEL KURULUYOR (FF-228) ═══
 *
 * Önceden `indexed()` her çağrıda dokuz belgeyi birden kuruyordu ve bu
 * bedava bir işti: dokuzu da sabit metindi. Artık iki belge ÖLÇÜM yapıyor —
 * DPA kimlik kasasını okuyor, lisans sayfası iki kilit dosyasını
 * ayrıştırıyor (400 KB + 300 KB JSON). `/terms` isteyen bir ziyaretçinin o
 * bedeli ödemesi için hiçbir sebep yok, o yüzden bir belge yalnız
 * İSTENDİĞİNDE kurulur ve istek boyunca önbelleklenir.
 */
final class LegalLibrary implements LegalLibraryPort
{
    /** @var array<string, LegalDocument> */
    private array $cache = [];

    public function __construct(
        private readonly SubprocessorRegistryPort $subprocessors,
        private readonly ThirdPartyLicensePort $licenses,
    ) {}

    public function find(string $key, string $locale = 'en'): ?LegalDocument
    {
        $locale = $locale === 'tr' ? 'tr' : 'en';
        $cacheKey = $locale.':'.$key;

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        if (! in_array($key, LegalLibraryPort::KEYS, true)) {
            return null;
        }

        return $this->cache[$cacheKey] = $this->build($key, $locale);
    }

    /** @return list<LegalDocument> */
    public function all(string $locale = 'en'): array
    {
        $documents = [];

        foreach (LegalLibraryPort::KEYS as $key) {
            $document = $this->find($key, $locale);

            if ($document !== null) {
                $documents[] = $document;
            }
        }

        return $documents;
    }

    /**
     * `match` VARSAYILANSIZDIR: `LegalLibraryPort::KEYS`'e bir anahtar eklenip burada
     * karşılığı yazılmazsa çağrı patlar. Sessizce `null` dönen bir kütüphane,
     * altbilgide bağlantısı olan bir 404 üretirdi.
     */
    private function build(string $key, string $locale): LegalDocument
    {
        if ($locale === 'tr') {
            return match ($key) {
                'terms' => Documents\Turkish\TermsOfService::document(),
                'privacy' => Documents\Turkish\PrivacyPolicy::document(),
                'kvkk' => Documents\Turkish\KvkkDisclosure::document(),
                'distance-sales' => Documents\Turkish\DistanceSalesAgreement::document(),
                'pre-information' => Documents\Turkish\PreliminaryInformationForm::document(),
                'delivery' => Documents\Turkish\DeliveryAndPerformancePolicy::document(),
                'refund-policy' => Documents\Turkish\RefundPolicy::document(),
                'cookies' => Documents\Turkish\CookiePolicy::document(),
                'marketing-consent' => Documents\Turkish\MarketingConsentText::document(),
                // Alt işleyen listesi ÇİZİM ANINDA ölçülür (`docs/140` §3).
                'data-processing' => Documents\Turkish\DataProcessingAgreement::document($this->subprocessors->inventory($locale)),
                // Rakamlar `config/sla.php`'den; girilmemişken taahhüt yazılmaz.
                'sla' => Documents\Turkish\ServiceLevelTerms::document(ServiceLevelCommitment::fromConfig()),
                'acceptable-use' => Documents\Turkish\AcceptableUsePolicy::document(),
                // Lisanslar manifestlerden TÜRETİLİR (`docs/140` §5).
                'third-party-licenses' => Documents\Turkish\ThirdPartyLicenses::document($this->licenses->inventories()),
            };
        }

        return match ($key) {
            'terms' => TermsOfService::document(),
            'privacy' => PrivacyPolicy::document(),
            'kvkk' => KvkkDisclosure::document(),
            'distance-sales' => DistanceSalesAgreement::document(),
            'pre-information' => PreliminaryInformationForm::document(),
            'delivery' => DeliveryAndPerformancePolicy::document(),
            'refund-policy' => RefundPolicy::document(),
            'cookies' => CookiePolicy::document(),
            'marketing-consent' => MarketingConsentText::document(),
            // Alt işleyen listesi ÇİZİM ANINDA ölçülür (`docs/140` §3).
            'data-processing' => DataProcessingAgreement::document($this->subprocessors->inventory()),
            // Rakamlar `config/sla.php`'den; girilmemişken taahhüt yazılmaz.
            'sla' => ServiceLevelTerms::document(ServiceLevelCommitment::fromConfig()),
            'acceptable-use' => AcceptableUsePolicy::document(),
            // Lisanslar manifestlerden TÜRETİLİR (`docs/140` §5).
            'third-party-licenses' => ThirdPartyLicenses::document($this->licenses->inventories()),
        };
    }
}
