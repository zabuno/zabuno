<?php

declare(strict_types=1);

namespace App\Support\Legal;

use App\Application\Legal\Port\LegalLibraryPort;
use App\Domain\Legal\CompanyProfile;
use App\Domain\Legal\LegalDocument;
use App\Domain\Legal\LegalReview;
use App\Domain\Legal\LegalSection;

/**
 * Kayıt ekranının okuyacağı yasal metinlerin PROJEKSİYONU (REG-LEGAL-01).
 *
 * ═══ NEDEN YENİ BİR UÇ NOKTA DEĞİL ═══
 *
 * Metin, sayfa çizilirken zaten sunucuda: onu ikinci bir istekle geri
 * çağırmak, kaydolmaya çalışan kişiye "önce şu üç belgeyi indir" demek
 * olurdu — ve o istek düşerse elimizde kabul kutusu olan ama metni
 * gösteremeyen bir form kalırdı. Kabul edilen metin, kabul edildiği anda
 * ekranda olmalıdır; sonradan getirilebilir bir şey değildir.
 *
 * Ayrıca bir uç nokta yeni bir yüzeydir: hız sınırı, önbellek başlığı,
 * yetkilendirme kararı ister. Buradaki veri zaten herkese açık üç sayfanın
 * ta kendisi (`/terms`, `/privacy`, `/marketing-consent`).
 *
 * ŞİRKET YER TUTUCULARI BURADA DOLAR (`withCompany`): kayıt ekranında
 * `{company.legal_name}` gören biri, kimle sözleşme yaptığını göremez.
 * Girilmemiş alanlar uydurulmaz; `CompanyProfile` "not yet provided" yazar.
 *
 * İNCELEME NOTU DA TAŞINIR: metin bir hukukçu tarafından okunmadıysa bunu
 * kayıt ekranı da söyler, yalnız kalıcı sayfa değil (`LegalReview`).
 */
final class RegistrationLegalPayload
{
    /**
     * Kayıt anında OKUNAN belgeler ve kanonik adresleri.
     *
     * Adres burada yazılı çünkü kanonik sayfa her hâlükârda kalır: metin
     * artık formun içinde okunuyor olsa da, paylaşılabilir ve yer imine
     * eklenebilir bir adresi olmayan bir sözleşme eksiktir.
     *
     * @var array<string, string>
     */
    private const DOCUMENTS = [
        'terms' => '/terms',
        'privacy' => '/privacy',
        'marketing-consent' => '/marketing-consent',
    ];

    public function __construct(private readonly LegalLibraryPort $library) {}

    /** @return array<string, mixed> */
    public function forLocale(string $locale): array
    {
        $company = CompanyProfile::fromConfig();
        $documents = [];

        foreach (self::DOCUMENTS as $key => $url) {
            $document = $this->library->find($key, $locale);

            if ($document === null) {
                continue;
            }

            $documents[$key] = $this->project($document->withCompany($company), $url);
        }

        return [
            'reviewPending' => LegalReview::fromConfig()->isPending(),
            'documents' => $documents,
        ];
    }

    /** @return array<string, mixed> */
    private function project(LegalDocument $document, string $url): array
    {
        return [
            'key' => $document->key,
            'url' => $url,
            'title' => $document->title,
            'summary' => $document->summary,
            // Sürüm ve yürürlük tarihi belgenin KİMLİĞİDİR: onay defterine
            // yazılan sürüm ile ekranda okunan sürüm aynı olmalı.
            'version' => $document->version,
            'effectiveDate' => $document->effectiveDate,
            'language' => $document->language,
            'sections' => array_map(
                static fn (LegalSection $section): array => [
                    'heading' => $section->heading,
                    'paragraphs' => $section->paragraphs,
                ],
                $document->sections,
            ),
        ];
    }
}
