<?php

declare(strict_types=1);

namespace App\Domain\Legal;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Bir yasal belge — anahtar, sürüm, yürürlük tarihi, başlık ve numaralı
 * bölümler (FF-198, `docs/107` Faz 1.2).
 *
 * KAYNAK DİL İNGİLİZCE (`docs/118` E4). Belge metni burada, sunucuda
 * üretilen bir ŞABLONDA değil: şablon tek bir sabit dize taşımaz
 * (I18N-SSR-RATCHET-16) ve bir hukuk metni cümle başına katalog anahtarı
 * için yanlış şekildir — çevirmen bağlamı göremez, gözden geçiren bütünü
 * okuyamaz. Yardım makaleleriyle aynı karar (`HelpLibrary`).
 *
 * SÜRÜM VE TARİH BELGENİN KİMLİĞİDİR. Kayıt anında onaylanan sürüm
 * `consent_records`a yazılır; metin değiştiğinde sürüm değişir ve kimin
 * hangi metni kabul ettiği bir daha karışmaz.
 *
 * ŞİRKET BİLGİSİ METNE GÖMÜLMEZ. Belge `{company.legal_name}` gibi yer
 * tutucular taşır; sayfa çizilirken `CompanyProfile` onları doldurur ya da
 * girilmemiş olduğunu yazar. Bir tüzel kişi adı ya da adres uydurulmaz.
 */
final class LegalDocument
{
    /** @param  list<LegalSection>  $sections */
    public function __construct(
        public readonly string $key,
        public readonly string $version,
        public readonly string $effectiveDate,
        public readonly string $title,
        public readonly string $summary,
        public readonly array $sections,
    ) {
        if (preg_match('/^[a-z][a-z-]*$/', $key) !== 1) {
            throw new InvalidArgumentException("Legal document key \"{$key}\" must be a lowercase slug.");
        }

        if (trim($version) === '') {
            throw new InvalidArgumentException("Legal document \"{$key}\" needs a version.");
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $effectiveDate);

        if ($parsed === false || $parsed->format('Y-m-d') !== $effectiveDate) {
            throw new InvalidArgumentException("Legal document \"{$key}\" effective date must be Y-m-d, got \"{$effectiveDate}\".");
        }

        if ($sections === []) {
            throw new InvalidArgumentException("Legal document \"{$key}\" has no sections.");
        }
    }

    /** Yer tutucular şirket olgularıyla doldurulmuş kopya. */
    public function withCompany(CompanyProfile $company): self
    {
        return new self(
            $this->key,
            $this->version,
            $this->effectiveDate,
            $company->fill($this->title),
            $company->fill($this->summary),
            array_map(
                static fn (LegalSection $section): LegalSection => new LegalSection(
                    $company->fill($section->heading),
                    array_map(static fn (string $paragraph): string => $company->fill($paragraph), $section->paragraphs),
                ),
                $this->sections,
            ),
        );
    }
}
