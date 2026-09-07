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
 *
 * BELGE KENDİ DİLİNİ TAŞIR (`language`, FF-216 · `docs/121` Ö11). Bugün
 * kütüphanedeki sekiz+iki belgenin hepsi İngilizce kaynak metindir ve sayfa
 * `<main lang="en">` çizer. Bu tek satır, çeviri kilidi açıldığı gün
 * yazılacak Türkçe metnin ŞABLONU değiştirmeden yerleşebilmesi için var:
 * bir belge dilini söylemiyorsa, Türkçe bir sayfanın içine düşen İngilizce
 * bir bölüm ekran okuyucuya yanlış dilde okunur ve arama motoruna yanlış
 * dilde ilan edilir. Dil ÖLÇÜLEN bir olgudur, varsayılan değil.
 *
 * SÖZLEŞMENİN TARAFI OLMAYAN BELGE OLMAZ (`requiresSellerIdentity`).
 * Mesafeli satış sözleşmesi, ön bilgilendirme formu ve teslimat/ifa
 * koşulları satıcının kim olduğunu SÖYLEMEK zorundadır; şirket bilgisi
 * girilmemişken bu üç belge "tamam" görünemez (`ShowLegalDocumentController`).
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
        public readonly string $language = 'en',
        public readonly bool $requiresSellerIdentity = false,
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

        // Dil BCP-47 etiketidir ve boş olamaz: `lang=""` bir dil bildirimi
        // değil, bildirimin unutulduğunun kanıtıdır.
        if (preg_match('/^[a-z]{2}(-[A-Za-z0-9]{2,8})*$/', $language) !== 1) {
            throw new InvalidArgumentException("Legal document \"{$key}\" language must be a BCP-47 tag, got \"{$language}\".");
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
            $this->language,
            $this->requiresSellerIdentity,
        );
    }
}
