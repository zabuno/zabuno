<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal\Documents;

use App\Domain\Legal\DependencyInventory;
use App\Domain\Legal\LegalDocument;
use App\Domain\Legal\LegalSection;

/**
 * Üçüncü Taraf Lisansları — İNGİLİZCE KAYNAK METİN (FF-228, `docs/140` §5).
 *
 * ═══ NEDEN BİR ZİNCİRİN HUKUKÇUSU BUNU İSTER ═══
 *
 * Satın alma incelemesinin standart sorusu: *bu yazılım kimin kodunu
 * taşıyor ve hangi lisansla?* Cevap verilemediğinde alım durur; yanlış
 * verildiğinde bir lisans ihlali sözleşmeye girer.
 *
 * ═══ LİSTE ELLE YAZILMADI ═══
 *
 * Bölümler `DependencyInventory`den gelir ve o da `composer.json`/
 * `composer.lock` ile `package.json`/`package-lock.json`den TÜRETİLİR
 * (`ManifestThirdPartyLicenses`). Elle yazılmış bir liste ilk
 * `composer require` ile sessizce eskirdi; bu liste eskiyemez, çünkü
 * kaynağı deponun kendi gerçeğidir.
 *
 * ═══ LİSANS METNİ KOPYALANMADI ═══
 *
 * Sayfa lisansın ADINI ve paketin sürümünü söyler, metnini değil. Bir MIT
 * ya da Apache-2.0 metnini buraya çoğaltmak, kopyanın bir gün asıl metinden
 * ayrışması demektir; asıl metin her paketin kendi dağıtımındadır ve orada
 * güncel kalır.
 */
final class ThirdPartyLicenses
{
    /** @param  list<DependencyInventory>  $inventories */
    public static function document(array $inventories): LegalDocument
    {
        return new LegalDocument(
            key: 'third-party-licenses',
            version: '0.1',
            effectiveDate: '2026-09-08',
            title: 'Third-Party Licences',
            summary: 'The open source components Zabuno is built on, with the licence each one is published under, generated from this deployment\'s own dependency manifests.',
            sections: array_merge(
                [
                    new LegalSection('What this page is', [
                        'Zabuno is built on open source components published by other people. This page names the ones the service depends on directly and the licence each is published under, so that a procurement or legal review can answer the question without asking for a spreadsheet.',
                        'The lists below are not typed by hand. They are read from this deployment\'s own dependency manifests and lock files when the page is opened, so a component added or removed by a release is reflected here without anyone having to remember to update a document.',
                        'The licence names come from those lock files as the package authors declared them. Where a package declares no licence, the entry says so instead of guessing one.',
                        'The full text of each licence is published by that package with the package itself; it is not copied here, because a copy would in time stop matching the original.',
                    ]),
                ],
                array_map(static fn (DependencyInventory $inventory): LegalSection => self::section($inventory), $inventories),
                [
                    new LegalSection('What is not listed here', [
                        'Indirect dependencies are counted above but not named one by one. Naming several hundred packages on a page nobody could read would hide the ones that matter; the complete resolved list, with versions, is in the lock files in the public source repository of this software.',
                        'Build and development tooling that never reaches a visitor\'s browser or a running server — test runners, linters, the component workshop, the type checker — is not listed. It is not distributed as part of the service.',
                        'The runtime the service needs — the PHP interpreter and its extensions, the database engine, the web server and the operating system — is not listed either. Those are not components shipped inside this software; they are the environment it runs in.',
                    ]),
                    new LegalSection('Zabuno\'s own code', [
                        'The source code of Zabuno itself is published in a public repository, under the licence stated there. This page is about the components Zabuno uses, not about Zabuno\'s own licence terms; what you may do with the service is set out in the Terms of Service.',
                    ]),
                    new LegalSection('Questions and corrections', [
                        'If you believe a component is listed with the wrong licence, or that a component is missing, write through the contact form on this site or to {company.email}. Say which package you mean.',
                    ]),
                ],
            ),
        );
    }

    private static function section(DependencyInventory $inventory): LegalSection
    {
        if (! $inventory->readable) {
            /*
                Kilit dosyası bu dağıtımda okunamadı. BOŞ BİR LİSTE "hiçbir
                üçüncü taraf kod yok" diye okunur ve bu, bu ürün için apaçık
                yanlıştır — o yüzden bölüm okunamadığını SÖYLER.
            */
            return new LegalSection($inventory->ecosystem, [
                'The dependency manifest for this ecosystem ('.$inventory->manifest.') could not be read on this deployment, so no list can be generated here. This is stated rather than shown as an empty list, which would read as "no third-party code", and that would be wrong.',
                'The authoritative list is in those files in the public source repository of this software. Ask through the contact form if you need it in another form.',
            ]);
        }

        $paragraphs = [
            'Direct dependencies declared in '.$inventory->manifest.', with the licence each declares ('.count($inventory->packages).' packages; '.$inventory->transitiveCount.' further packages are pulled in indirectly and are not named here):',
        ];

        foreach ($inventory->lines() as $line) {
            $paragraphs[] = $line;
        }

        if ($inventory->packages === []) {
            $paragraphs[] = 'No direct dependency is declared for this ecosystem in this deployment.';
        }

        return new LegalSection($inventory->ecosystem, $paragraphs);
    }
}
