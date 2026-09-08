<?php

declare(strict_types=1);

namespace App\Domain\Assurance;

use InvalidArgumentException;

/**
 * Bir güvence beyanı — güven merkezi ya da erişilebilirlik beyanı (FF-252).
 *
 * ═══ NEDEN `LegalDocument` DEĞİL ═══
 *
 * Şekilleri benzer ama TÜRLERİ farklı ve fark okuyucu için önemli:
 *
 *   · Bir `LegalDocument` bir SÖZLEŞMEDİR. Sürümü ve yürürlük tarihi vardır,
 *     çünkü onay kaydı (`consent_records`) o sürüme yazılır ve hangi ayda
 *     neyin geçerli olduğu ispatlanabilir olmak zorundadır.
 *   · Bir `AssuranceStatement` bir DURUM TESPİTİDİR. Sürümü yoktur ve
 *     olmamalı: içeriği her çizimde o anki yapılandırmadan ve o anki
 *     kapılardan doğar. Bir sürüm numarası, dondurulmuş bir olgu iddiası
 *     olurdu — oysa buradaki her satırın anlamı "BUGÜN böyle"dir.
 *
 * Bu yüzden `LegalLibraryPort::KEYS` listesine de girmezler: altbilginin
 * yasal satırı sözleşmeleri sayar, bu iki sayfa oraya değil şirket grubuna
 * bağlanır (`SiteNavigation`).
 */
final readonly class AssuranceStatement
{
    /** @param  list<AssuranceSection>  $sections */
    public function __construct(
        public string $key,
        public string $title,
        public string $summary,
        public array $sections,
    ) {
        foreach (['key' => $key, 'title' => $title, 'summary' => $summary] as $field => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException("Assurance statement field \"{$field}\" cannot be empty.");
            }
        }

        if ($sections === []) {
            throw new InvalidArgumentException("Assurance statement \"{$key}\" has no sections.");
        }
    }

    /**
     * Beyandaki bütün iddialar, bölüm sırasıyla.
     *
     * Kapılar bu listeyi okur: kanıtın var olduğunu ölçen test, bölümleri
     * tek tek dolaşmak zorunda kalsaydı yeni bir bölüm eklendiği gün sessizce
     * taramanın dışında kalırdı.
     *
     * @return list<AssuranceClaim>
     */
    public function claims(): array
    {
        $claims = [];

        foreach ($this->sections as $section) {
            foreach ($section->claims as $claim) {
                $claims[] = $claim;
            }
        }

        return $claims;
    }
}
