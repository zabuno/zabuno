<?php

declare(strict_types=1);

namespace App\Domain\Assurance;

use InvalidArgumentException;

/**
 * Güvence beyanının bir bölümü — FF-252.
 *
 * `LegalSection` ile aynı şekle sahip görünür ve YİNE DE ondan ayrıdır:
 * bir bölüm burada düz paragrafların yanında İDDİA taşıyabilir ve iddianın
 * hâli (`ClaimState`) çizimde bir öznitelik olur. Yasal bölüm bunu bilmez ve
 * bilmemeli — bir sözleşme metninin "ölçüldü/ölçülmedi" diye bir kipi yok,
 * her cümlesi bağlayıcıdır.
 *
 * ═══ PARAGRAF DA İDDİA DA BOŞ OLABİLİR, İKİSİ BİRDEN OLAMAZ ═══
 *
 * Başlığı olup gövdesi olmayan bir bölüm, olmayan bir bilginin sözünü
 * vermektir — altbilgideki boş grup kuralının aynısı (`SiteNavigation`).
 */
final readonly class AssuranceSection
{
    /**
     * @param  list<string>  $paragraphs
     * @param  list<AssuranceClaim>  $claims
     */
    public function __construct(
        public string $heading,
        public array $paragraphs = [],
        public array $claims = [],
    ) {
        if (trim($heading) === '') {
            throw new InvalidArgumentException('An assurance section needs a heading.');
        }

        if ($paragraphs === [] && $claims === []) {
            throw new InvalidArgumentException("Assurance section \"{$heading}\" has neither text nor claims.");
        }
    }
}
