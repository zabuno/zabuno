<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal;

use App\Application\Legal\Port\LegalLibraryPort;
use App\Domain\Legal\LegalDocument;
use App\Infrastructure\Legal\Documents\CookiePolicy;
use App\Infrastructure\Legal\Documents\DistanceSalesAgreement;
use App\Infrastructure\Legal\Documents\KvkkDisclosure;
use App\Infrastructure\Legal\Documents\MarketingConsentText;
use App\Infrastructure\Legal\Documents\PreliminaryInformationForm;
use App\Infrastructure\Legal\Documents\PrivacyPolicy;
use App\Infrastructure\Legal\Documents\RefundPolicy;
use App\Infrastructure\Legal\Documents\TermsOfService;

/**
 * Sekiz yasal belgenin kütüphanesi — FF-198 (`docs/107` Faz 1.2).
 *
 * Metin BUGÜN kodda yaşıyor ve bu bilinçli: kod incelemesinden ve testten
 * geçer, sürümü Git'te izlenir ve hukukçu değişikliği bir diff olarak
 * görür. `ProductPageLibrary` ile aynı karar.
 *
 * YALNIZ İNGİLİZCE (`docs/118` E4): Türkçe sürüm çeviri kilidine tabidir
 * ve sahibin `ÇEVİRİLERE BAŞLA` kararını bekler. Bu pakette tek bir çeviri
 * üretilmedi.
 */
final class LegalLibrary implements LegalLibraryPort
{
    /** @var array<string, LegalDocument>|null */
    private ?array $cache = null;

    public function find(string $key): ?LegalDocument
    {
        return $this->indexed()[$key] ?? null;
    }

    /** @return list<LegalDocument> */
    public function all(): array
    {
        return array_values($this->indexed());
    }

    /** @return array<string, LegalDocument> */
    private function indexed(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $index = [];

        foreach ([
            TermsOfService::document(),
            PrivacyPolicy::document(),
            KvkkDisclosure::document(),
            DistanceSalesAgreement::document(),
            PreliminaryInformationForm::document(),
            RefundPolicy::document(),
            CookiePolicy::document(),
            MarketingConsentText::document(),
        ] as $document) {
            $index[$document->key] = $document;
        }

        return $this->cache = $index;
    }
}
